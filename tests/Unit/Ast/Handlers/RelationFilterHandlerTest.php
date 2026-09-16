<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassTypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CollectionMemberModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OwnResourceRelationModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ResourceRelationModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedFilterOverrideModel;
use Illuminate\Support\Facades\Schema;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;

/**
 * An engine that answers every expression with one canned result, standing in for a relation read.
 *
 * @param  array{type: string, optional: bool}  $result
 */
function relationFilterStubEngine(array $result): ExpressionEngine
{
    return new class($result) implements ExpressionEngine
    {
        /** @param  array{type: string, optional: bool}  $result */
        public function __construct(private array $result) {}

        public function resolve(Expr $expr): array
        {
            return $this->result;
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

/** The single expression a PHP snippet spells. */
function relationFilterExpr(string $php): Expr
{
    /** @var Expression $statement */
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];

    return $statement->expr;
}

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function relationFilterHandlerThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

it('resolves $this->relation->only([...]) to a Pick<Model, ...> reference when every key is a column', function () {
    // Mirrors CommentResource::$post_limited — Comment::post() is a BelongsTo to Post, and 'id'/
    // 'title' are both plain published columns, so the relation-filter guard emits a Pick<> arm.
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('id')),
            new ArrayItem(new String_('title')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => "Pick<Post, 'id' | 'title'>",
        'optional' => false,
        'modelFqcn' => Post::class,
    ]);
});

it('relation except() falls back to database columns only, matching Model::except() at runtime — RelationFilterHandler::resolveFilteredRelationType()', function () {
    // 'excerpt' is a pure Post accessor, not a published column, so relationFilterModelReference()
    // declines and this reaches resolveFilteredRelationType()'s except branch: HasAttributes::except()
    // iterates getAttributes() only, so 'excerpt' must never appear even though named; 'created_at' does.
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'except',
        [new Arg(new Array_([
            new ArrayItem(new String_('excerpt')),
            new ArrayItem(new String_('created_at')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => '{ id: number; title: string; content: string; user_id: number; status: StatusType; '
            .'published_at: string | null; metadata: unknown[] | null; rating: number | null; category: string; '
            .'options: Record<string, string> | null; deleted_at: string | null; updated_at: string | null; '
            .'category_id: number | null; visibility: VisibilityType | null; priority: PriorityType | null; '
            .'word_count: number | null; reading_time_minutes: number | null; featured_image_url: string | null; '
            .'is_pinned: boolean }',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class, Visibility::class, Priority::class],
        'embeddedModelFqcns' => [],
        'customImports' => [],
    ]);
});

it('relation only() still resolves a named accessor and a named relation, unlike except() — RelationFilterHandler::resolveFilteredRelationType()', function () {
    // 'title_display' (accessor) and 'comments' (relation) are both non-columns, so
    // relationFilterModelReference() declines and this reaches resolveFilteredRelationType()'s include
    // branch — only() calls getAttribute() per key, so both the accessor and the relation resolve.
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('title')),
            new ArrayItem(new String_('title_display')),
            new ArrayItem(new String_('comments')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => '{ title: string; title_display: string | null; comments: Comment[] }',
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [Comment::class],
        'customImports' => [],
    ]);
});

it('resolves $this->resource->relation->only([...]) exactly as $this->relation->only([...])', function () {
    $expr = new MethodCall(
        new PropertyFetch(new PropertyFetch(new Variable('this'), 'resource'), 'post'),
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('id')),
            new ArrayItem(new String_('title')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]);
});

// Each of these used to be claimed as `unknown`, which kept the receiver rules and every later handler from answering.
it('declines a filter it cannot type so a later handler gets its turn', function (Expr $receiver, array $keys) {
    $expr = new MethodCall(
        $receiver,
        'only',
        [new Arg(new Array_(array_map(fn (string $key): ArrayItem => new ArrayItem(new String_($key)), $keys)))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBeNull();
})->with([
    // The proxy is the resource's own model, not a member named `resource`.
    '$this->resource' => [fn (): Expr => new PropertyFetch(new Variable('this'), 'resource'), ['id', 'content']],
    'neither a relation nor an accessor' => [fn (): Expr => new PropertyFetch(new Variable('this'), 'not_a_member'), ['id']],
    'a relation whose keys name nothing' => [fn (): Expr => new PropertyFetch(new Variable('this'), 'post'), ['not_a_key']],
]);

it('declines a method call whose name is not only/except', function () {
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'count',
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('emits Pick<Model, never> when except() names every published column', function () {
    $columns = Schema::getColumnListing((new Tag)->getTable());

    $type = (fn () => $this->relationFilterModelReference(Tag::class, $columns, false))
        ->call(new RelationFilterHandler);

    expect($type)->toBe('Pick<Tag, never>');
});

it('reads only(attributes: [...]) by name, matching Model::only()\'s own parameter', function () {
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([new ArrayItem(new String_('id')), new ArrayItem(new String_('title'))]), name: new Identifier('attributes'))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]);
});

// func_get_args() on a lone named argument is [that value], so a single-key named call is a one-key list.
it('reads a lone only(attributes: \'id\') as a single-key list', function () {
    $expr = new MethodCall(new PropertyFetch(new Variable('this'), 'post'), 'only', [
        new Arg(new String_('id'), name: new Identifier('attributes')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => "Pick<Post, 'id'>", 'optional' => false, 'modelFqcn' => Post::class]);
});

// Eloquent\Collection::only()/except() keep the models whose primary key is listed and return them whole, so a
// many-relation filter publishes the relation read itself, channels included, whatever the key list holds.
it('publishes a many-relation filter as the relation read, under both spellings and any key list', function (string $resource, string $model, string $php, string $read, string $readType, string $type) {
    $engine = new ResourceAstAnalyzer(new ReflectionClass($resource), $model);
    $scope = new AnalysisScope(new ReflectionClass($resource), $model);
    $expected = $engine->resolve(relationFilterExpr($read));

    expect($expected['type'])->toBe($readType);

    $expected['type'] = $type;

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, $engine))->toBe($expected);
})->with([
    'hasMany, int list' => [PostResource::class, Post::class, '$this->comments->only([1, 2])', '$this->comments', 'Comment[]', 'Comment[]'],
    'hasMany, named keys' => [PostResource::class, Post::class, '$this->comments->only(keys: [\'id\', \'content\'])', '$this->comments', 'Comment[]', 'Comment[]'],
    'hasMany, runtime except' => [PostResource::class, Post::class, '$this->comments->except($ids)', '$this->comments', 'Comment[]', 'Comment[]'],
    'hasMany through $this->resource' => [PostResource::class, Post::class, '$this->resource->comments->only($ids)', '$this->resource->comments', 'Comment[]', 'Comment[]'],
    'hasMany ?->' => [PostResource::class, Post::class, '$this->comments?->only($ids)', '$this->comments', 'Comment[]', 'Comment[] | null'],
    'hasMany ?-> through $this->resource' => [PostResource::class, Post::class, '$this->resource->comments?->except([1])', '$this->resource->comments', 'Comment[]', 'Comment[] | null'],
    'morphMany' => [PostResource::class, Post::class, '$this->images->only([1])', '$this->images', 'Image[]', 'Image[]'],
    'morphMany through $this->resource' => [PostResource::class, Post::class, '$this->resource->images->except($ids)', '$this->resource->images', 'Image[]', 'Image[]'],
    'belongsToMany with a pivot' => [TeamResource::class, Team::class, '$this->members->only($ids)', '$this->members', 'User[]', 'User[]'],
    'belongsToMany ?-> through $this->resource' => [TeamResource::class, Team::class, '$this->resource->members?->only([1])', '$this->resource->members', 'User[]', 'User[] | null'],
]);

it('declines a many-relation filter whose relation read is unknown', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $engine = relationFilterStubEngine(['type' => 'unknown', 'optional' => false]);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr('$this->comments->only([1])'), $scope, $engine))->toBeNull();
});

it('adds no second null when the relation read already carries one', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $engine = relationFilterStubEngine(['type' => 'Comment[] | null', 'optional' => false]);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr('$this->comments?->only([1])'), $scope, $engine))
        ->toBe(['type' => 'Comment[] | null', 'optional' => false]);
});

// Model::only()/except() return an attribute-keyed array whatever keys arrive at runtime; the receiver rule builds the
// same answer for the resource's own model from the same ResolvesFilteredRelationTypes helper.
it('publishes a single-relation filter with no literal key list as Record<string, unknown>, under both spellings', function (string $php, string $type) {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => $type, 'optional' => false]);
})->with([
    ['$this->post->only($fields)', 'Record<string, unknown>'],
    ['$this->resource->post->except($fields)', 'Record<string, unknown>'],
    ['$this->post->except(self::FIELDS)', 'Record<string, unknown>'],
    ['$this->post->only([1, 2])', 'Record<string, unknown>'],
    ['$this->resource->post?->only($fields)', 'Record<string, unknown> | null'],
]);

// An accessor typed as a union of models (`Attribute<CrmUser|User|null, never>`) reaches the multi-model branch, which
// answers a runtime key list with the same Record<string, unknown> the single-model branch does.
it('publishes a multi-model accessor filter with no literal key list as Record<string, unknown>, under both spellings', function (string $php, string $type) {
    $scope = new AnalysisScope(new ReflectionClass(WarehouseResource::class), Warehouse::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => $type, 'optional' => false]);
})->with([
    ['$this->last_user_activity_by?->only($keys)', 'Record<string, unknown> | null'],
    ['$this->resource->last_user_activity_by->except($keys)', 'Record<string, unknown>'],
]);

// PHP reads the declared JsonResource::$resource before any __get(), so a model's own `resource` relation is
// unreachable through it. The filter is on the resource's model, which the receiver rules own, not on that relation.
it('never reads $this->resource as a relation, even on a model that declares one', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), ResourceRelationModel::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr('$this->resource->only([\'id\'])'), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// Only a resource forwards to its model. In a model's own body `$this->resource` is a member like any other: the
// model's own `resource` relation, or nothing, so a filter read through it is not a relation filter on the model.
it('matches the $this->resource->member spelling only on a subject that forwards to its model', function (string $subject, string $model, string $php, ?array $expected) {
    $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), $model, 'toArray', ResourceExpressionHandlers::forModelClosures());
    $scope = new AnalysisScope(new ReflectionClass($subject), $model);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, $engine))->toBe($expected);
})->with([
    'resource, a model with a resource relation' => [CommentResource::class, OwnResourceRelationModel::class, '$this->resource->author->only([\'id\', \'name\'])', [
        'type' => "Pick<Category, 'id' | 'name'>", 'optional' => false, 'modelFqcn' => Category::class,
    ]],
    'model body, a model with a resource relation' => [OwnResourceRelationModel::class, OwnResourceRelationModel::class, '$this->resource->author->only([\'id\', \'name\'])', null],
    'model body, a relation' => [Post::class, Post::class, '$this->resource->author->only([\'id\'])', null],
    'model body, a to-many relation' => [Post::class, Post::class, '$this->resource->comments->only([1])', null],
    'model body, a map proxy' => [Post::class, Post::class, '$this->resource->comments->map->only([\'id\'])', ['type' => 'unknown', 'optional' => false]],
]);

// The model's `resource` relation leads to a Post authored by a User; its own `author` is a Category.
it('reads a model\'s own resource relation in its getter and method bodies', function () {
    $getter = resolve(ModelAttributeResolver::class)->resolveAttribute(OwnResourceRelationModel::class, 'resource_author');

    expect($getter['type'])->toBe("Pick<User, 'id' | 'name'>")
        ->and($getter['classFqcns'])->toBe([User::class])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(OwnResourceRelationModel::class, 'resourceAuthorFields'))
        ->toBe(['type' => '{ author: { id: number; email: string } }', 'optional' => false]);
});

// On a model that declares a real `map` relation, `$this->resource->map` is that relation, exactly as `$this->map` is.
it('reads a real map relation as a relation under both spellings, not as the map proxy', function (string $php) {
    $scope = new AnalysisScope(new ReflectionClass(TeamResource::class), Team::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => "Pick<User, 'id'>", 'optional' => false, 'modelFqcn' => User::class]);
})->with([
    '$this->map->only([\'id\'])',
    '$this->resource->map->only([\'id\'])',
]);

// The map proxy binds its element model from the relation, read directly or through `$this->resource`.
it('types a map proxy filter on a relation the same under both spellings', function (string $php) {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => '{ id: number }[]',
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [],
        'customImports' => [],
    ]);
})->with([
    '$this->comments->map->only([\'id\'])',
    '$this->resource->comments->map->only([\'id\'])',
]);

// `map` is no member of the model, so the member arm declines and the map-proxy arm claims the call, as it did before
// the proxy spelling was matched. That arm is the only path here that returns `unknown` rather than null.
it('lets $this->resource->map->only([...]) reach the map-proxy arm', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr('$this->resource->map->only([\'id\'])'), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
});

// A single relation is no collection, so the map proxy binds no element model from it; without that check
// `$this->author->map->only(['id'])` would publish a list of filtered authors that the call never returns.
it('binds no map proxy element model from a single relation, under both spellings', function (string $php) {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
})->with([
    '$this->author->map->only([\'id\'])',
    '$this->resource->author->map->only([\'id\'])',
]);

// MethodReturnTypeResolver's body fallback flattens a method body into a type with no FQCN channel and drops the whole
// shape once a value names a token, so there a filter publishes the most specific answer that names none.
it('publishes a relation filter that names no token where the scope carries no import', function (string $model, string $php, array $expected) {
    $scope = new AnalysisScope(new ReflectionClass($model), $model);
    $scope->carriesImports = false;
    $engine = new ResourceAstAnalyzer(new ReflectionClass($model), $model, carriesImports: false);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, $engine))->toBe($expected);
})->with([
    'single relation, columns' => [Comment::class, '$this->user->only([\'id\', \'name\'])', [
        'type' => '{ id: number; name: string }', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
    ]],
    'single relation, an enum column' => [Comment::class, '$this->user->only([\'id\', \'role\'])', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'single relation, ?-> complement' => [Comment::class, '$this->post?->except([\'content\'])', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
    'single relation, runtime keys' => [Comment::class, '$this->user->only($keys)', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'multi-model accessor, an enum column' => [Warehouse::class, '$this->last_user_activity_by?->only([\'id\', \'role\'])', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
    'multi-model accessor' => [Warehouse::class, '$this->last_user_activity_by->only([\'id\'])', [
        'type' => '{ id: number } | { id: number }', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
    ]],
    'multi-model accessor, a typed override arm beside an enum column' => [FilterOverrideModel::class, '$this->counterpart->only([\'id\', \'role\'])', [
        'type' => 'string | Record<string, unknown>', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
    ]],
    'to-many' => [Comment::class, '$this->replies->only([1, 2])', ['type' => 'unknown[]', 'optional' => false]],
    'to-many ?->' => [Comment::class, '$this->replies?->except($keys)', ['type' => 'unknown[] | null', 'optional' => false]],
    'map proxy, columns' => [Comment::class, '$this->replies->map->only([\'id\'])', [
        'type' => '{ id: number }[]', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
    ]],
    'map proxy, an enum column' => [User::class, '$this->posts->map->only([\'id\', \'status\'])', ['type' => 'Record<string, unknown>[]', 'optional' => false]],
    'map proxy ?->, an enum column' => [User::class, '$this->posts->map?->only([\'id\', \'status\'])', ['type' => 'Record<string, unknown>[] | null', 'optional' => false]],
]);

// A model that overrides only()/except() declares its own return. ReceiverMethodCallHandler reflects it on a single
// relation, and this handler reads the same reflection for a map proxy's elements and a multi-model accessor's arm.
it('publishes a typed filter override\'s own return for a map proxy or multi-model accessor, and declines a relation', function (string $model, string $php, ?array $expected) {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), $model);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine()))->toBe($expected);
})->with([
    'single relation' => [FilterOverrideModel::class, '$this->twin->only([\'id\'])', null],
    'single relation, runtime keys' => [FilterOverrideModel::class, '$this->resource->twin?->except($keys)', null],
    'multi-model accessor' => [FilterOverrideModel::class, '$this->counterpart->only([\'id\'])', [
        'type' => "string | Pick<User, 'id'>",
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [User::class],
        'customImports' => [],
    ]],
    'multi-model accessor, runtime keys' => [FilterOverrideModel::class, '$this->counterpart?->except($keys)', [
        'type' => 'number | Record<string, unknown> | null',
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [],
        'customImports' => [],
    ]],
    'multi-model accessor, a nullable model return' => [ClassTypedFilterOverrideModel::class, '$this->counterpart->only([\'id\'])', [
        'type' => "ClassTypedFilterOverrideModel | Pick<User, 'id'> | null",
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [ClassTypedFilterOverrideModel::class, User::class],
        'customImports' => [],
    ]],
    'map proxy' => [FilterOverrideModel::class, '$this->twins->map->only([\'id\'])', ['type' => 'string[]', 'optional' => false]],
    'map proxy ?->, a nullable model return' => [ClassTypedFilterOverrideModel::class, '$this->resource->twins->map?->only([\'id\'])', [
        'type' => '(ClassTypedFilterOverrideModel | null)[] | null', 'optional' => false, 'modelFqcn' => ClassTypedFilterOverrideModel::class,
    ]],
    'map proxy, an enum return' => [ClassTypedFilterOverrideModel::class, '$this->twins->map->except([\'id\'])', [
        'type' => 'PriorityType[]', 'optional' => false, 'directEnumFqcn' => Priority::class,
    ]],
]);

// Laravel's collection casts build their collection with no return type reflection can read, so the receiver rules
// never see one; this arm types every member holding Support\Collection's own filter, which keeps keys.
it('publishes a filter on a member holding a Support\Collection as Record<string, unknown>, under both spellings', function (string $php, ?array $expected) {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), CollectionMemberModel::class);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine()))->toBe($expected);
})->with([
    'collection-cast column' => ['$this->options->only([\'a\'])', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'collection-cast column ?->, through $this->resource' => ['$this->resource->options?->except($keys)', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
    'AsEncryptedCollection column' => ['$this->visibility->except([\'a\'])', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'collection column' => ['$this->content->only($keys)', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'encrypted:collection column ?->' => ['$this->featured_image_url?->only([\'a\'])', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
    'accessor' => ['$this->stats->except([\'a\'])', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'accessor of models, read before its element model' => ['$this->people->only([\'id\'])', ['type' => 'Record<string, unknown>', 'optional' => false]],
    'accessor holding a model, not a collection' => ['$this->lead->only([\'id\'])', ['type' => "Pick<Comment, 'id'>", 'optional' => false, 'modelFqcn' => Comment::class]],
    'a cast building an Eloquent collection' => ['$this->metadata->only([\'a\'])', null],
]);

// An override that declares no return, such as one returning parent::only(), leaves reflection nothing to publish,
// so every arm answers it as it answers Model's own filter, and the receiver rules fall back to the same answer.
it('answers a relation filter on a model whose override declares no return', function (string $php, array $expected) {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), UntypedFilterOverrideModel::class);

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, relationFilterHandlerThrowingEngine()))->toBe($expected);
})->with([
    'single relation' => ['$this->twin->only([\'id\', \'title\'])', [
        'type' => "Pick<UntypedFilterOverrideModel, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => UntypedFilterOverrideModel::class,
    ]],
    'single relation, runtime keys' => ['$this->resource->twin?->except($keys)', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
    'multi-model accessor' => ['$this->counterpart->only([\'id\'])', [
        'type' => "Pick<UntypedFilterOverrideModel, 'id'> | Pick<User, 'id'>",
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [UntypedFilterOverrideModel::class, User::class],
        'customImports' => [],
    ]],
    'map proxy' => ['$this->twins->map->only([\'id\', \'title\'])', [
        'type' => '{ id: number; title: string }[]', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
    ]],
]);

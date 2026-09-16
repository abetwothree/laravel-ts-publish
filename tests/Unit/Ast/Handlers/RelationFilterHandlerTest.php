<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ResourceRelationModel;
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
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;

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
it('publishes a many-relation filter as the relation read, under both spellings and any key list', function (string $php, string $read, string $type) {
    $engine = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $expected = $engine->resolve(relationFilterExpr($read));

    expect($expected['type'])->toBe('Comment[]');

    $expected['type'] = $type;

    expect((new RelationFilterHandler)->resolve(relationFilterExpr($php), $scope, $engine))->toBe($expected);
})->with([
    ['$this->comments->only([1, 2])', '$this->comments', 'Comment[]'],
    ['$this->comments->only(keys: [\'id\', \'content\'])', '$this->comments', 'Comment[]'],
    ['$this->comments->except($ids)', '$this->comments', 'Comment[]'],
    ['$this->resource->comments->only($ids)', '$this->resource->comments', 'Comment[]'],
    ['$this->comments?->only($ids)', '$this->comments', 'Comment[] | null'],
    ['$this->resource->comments?->except([1])', '$this->resource->comments', 'Comment[] | null'],
]);

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

// PHP reads the declared JsonResource::$resource before any __get(), so a model's own `resource` relation is
// unreachable through it. The filter is on the resource's model, which the receiver rules own, not on that relation.
it('never reads $this->resource as a relation, even on a model that declares one', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), ResourceRelationModel::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr('$this->resource->only([\'id\'])'), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// `map` is no member of the model, so the member arm declines and the map-proxy arm claims the call, as it did before
// the proxy spelling was matched. That arm is the only path here that returns `unknown` rather than null.
it('lets $this->resource->map->only([...]) reach the map-proxy arm', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve(relationFilterExpr('$this->resource->map->only([\'id\'])'), $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
});

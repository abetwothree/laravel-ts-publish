<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsInstanceofGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MethodChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CountingCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\AppendingModelFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CastablePostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassTypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CollectionMemberModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DocblockFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\GuardOrderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\HiddenFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ListTypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MagicPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NarrowingGuardBodyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverIntegerKeyModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverMethodProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverShapedToArrayModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverStringDateProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverVarProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UserFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UserListFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\VagueFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\VisibleFilterOverrideModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Fluent;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\ImageResource;
use Workbench\App\Http\Resources\ModelWrappedPropResource;
use Workbench\App\Http\Resources\NarrowedImageableResource;
use Workbench\App\Http\Resources\NarrowedParentResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PostStatsResource;
use Workbench\App\Http\Resources\ReceiverMethodResource;
use Workbench\App\Http\Resources\ReceiverPropertyResource;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Release;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** Parse one expression statement written with fully-qualified names. */
function receiverHandlerExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/** A scope whose subject is the method probe, backed by Post. */
function receiverProbeScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ReceiverMethodProbe::class), Post::class);
}

/**
 * ReceiverPropertyResource's published property types, keyed by property name.
 *
 * @return array<string, string>
 */
function receiverPropertyTypes(): array
{
    return collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverPropertyResource::class), Comment::class)->analyze()->properties)
        ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']])
        ->all();
}

/** A scope whose subject is the property resource, with `$post` bound the way its body binds it. */
function receiverPropertyScope(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverPropertyResource::class), Comment::class);
    $scope->localVarBindings['post'] = receiverHandlerExpr('$this->post');

    return $scope;
}

describe('ReceiverMethodCallHandler through the resource analyzer', function () {
    test('follows method return types through every receiver kind', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toMatchArray([
            'priority_label' => 'string',
            'priority_label_nullsafe' => 'string | null',
            'resource_priority_label' => 'string',
            'resource_priority_label_nullsafe' => 'string | null',
            'resource_published_date' => 'string',
            'published_date' => 'string',
            'author_morph' => 'string | null',
            'record_class' => 'string',
            'from_label' => 'string',
            'author_fresh' => 'User | null',
            'author_fresh_nullsafe' => 'User | null',
            'resource_author_fresh' => 'User | null',
            'resource_author_fresh_nullsafe' => 'User | null',
        ]);
    });

    test('a method read through a Castable-cast column types from its caster without running castUsing()', function () {
        CountingCastable::$calls = 0;

        $props = collect(resolve(AstEngine::class)->analyze(CastablePostResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe(['title_label' => 'string', 'options_label' => 'unknown'])
            ->and(CountingCastable::$calls)->toBe(0);
    });

    test('getKey and modelKeys type from the receiver model key type', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['author_key'])->toBe('number | null')
            ->and($props['comment_ids'])->toBe('number[]')
            ->and($props['resource_comment_ids'])->toBe('number[]')
            ->and($props['resource_author_key'])->toBe('number | null');
    });

    test('getKey and modelKeys type the same through the resource proxy and the model', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->author->getKey()', 'number'],
        ['$this->resource->author->getKey()', 'number'],
        ['$this->author?->getKey()', 'number | null'],
        ['$this->resource?->author?->getKey()', 'number | null'],
        ['$this->resource?->getKey()', 'number | null'],
        ['$this->comments?->modelKeys()', 'number[] | null'],
        ['$this->resource?->comments?->modelKeys()', 'number[] | null'],
    ]);

    test('a bare $this->method() on a resource publishes the type of its $this->resource twin', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['bare_key'])->toBe('number')
            ->and($props['resource_key'])->toBe($props['bare_key'])
            ->and($props['bare_comments_count'])->toBe('number')
            ->and($props['resource_comments_count'])->toBe($props['bare_comments_count']);
    });

    test('a method only the model declares types the same bare, nullsafe on $this, and through $this->resource', function (string $call, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->'.$call))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this?->'.$call))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource->'.$call))['type'])->toBe($type);
    })->with([
        ['getKey()', 'number'],
        ['commentsCount()', 'number'],
        ['fresh()', 'Post | null'],
        ['replicate()', 'Post'],
    ]);

    test('an integer key type publishes number in both $this->resource getKey() spellings', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), ReceiverIntegerKeyModel::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource->getKey()'))['type'])->toBe('number')
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource?->getKey()'))['type'])->toBe('number | null');
    });

    test('a DateTime return stays unknown in every spelling, since json_encode() writes it as an object', function (string $php) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('unknown');
    })->with([
        '$this->resource->published_at->toDateTime()',
        '$this->published_at?->toDateTime()',
        '$this->resource?->published_at?->toDateTime()',
    ]);
});

describe('ReceiverMethodReturnResolver', function () {
    test('a public method types on any receiver, a protected one only from inside', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $probe = ReceiverType::of(ReceiverMethodProbe::class);

        expect($resolver->resolve($probe, 'label', receiverProbeScope())['type'] ?? null)->toBe('string')
            ->and($resolver->resolve($probe, 'secret', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'secret', receiverProbeScope(), fromInside: true)['type'] ?? null)->toBe('number');
    });

    test('a receiver-returning method keeps the receiver type, with null when the return admits it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'fresh', receiverProbeScope()))
            ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class])
            ->and($resolver->resolve(ReceiverType::of(ReceiverProbeEnum::class), 'next', receiverProbeScope()))
            ->toBe(['type' => 'ReceiverProbeEnumType | null', 'optional' => false, 'directEnumFqcn' => ReceiverProbeEnum::class])
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'setTimezone', receiverProbeScope())['type'] ?? null)
            ->toBe('string');
    });

    test('a class published as string through __toString declines unless it serializes as one', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $probe = ReceiverType::of(ReceiverMethodProbe::class);

        expect($resolver->resolve($probe, 'interval', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'docblockInterval', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'diff', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'docblockIntervals', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'text', receiverProbeScope())['type'] ?? null)->toBe('string');
    });

    test('a DateTime that is not JsonSerializable declines, while Carbon keeps its string', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(Carbon::class), 'toDateTime', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(ReceiverMethodProbe::class), 'plainDate', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'toMutable', receiverProbeScope())['type'] ?? null)->toBe('string');
    });

    test('a framework or abstract model token declines, because no file is published for it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'resolveRouteBinding', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(User::class), 'newPivot', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Model::class), 'fresh', receiverProbeScope()))->toBeNull();
    });

    test('a union types only when every class types the method', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(new ReceiverType([Priority::class, Post::class]), 'label', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(new ReceiverType([User::class, Post::class]), 'getMorphClass', receiverProbeScope()))
            ->toBe(['type' => 'string', 'optional' => false]);
    });

    test('a model toArray() declines even when it declares a precise shape', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $model = ReceiverType::of(ReceiverShapedToArrayModel::class);

        expect($resolver->resolve($model, 'toArray', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($model, 'shape', receiverProbeScope())['type'] ?? null)->toBe('{ id: number }');
    });

    test('a vague array return declines', function () {
        expect(resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of(Post::class), 'attributesToArray', receiverProbeScope()))
            ->toBeNull();
    });
});

describe('ReceiverMethodCallHandler', function () {
    test('leaves a request receiver to the request rule', function () {
        $scope = receiverProbeScope();
        $scope->requestVarNames['request'] = Request::class;

        expect(new ReceiverMethodCallHandler()->resolve(receiverHandlerExpr('$request->ip()'), $scope, chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('forwards a bare $this->method() only on a resource, and only for a method the resource does not declare', function () {
        $resource = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $plain = new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class), Post::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('$this->getKey()'), $resource, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('number')
            ->and($handler->resolve(receiverHandlerExpr('$this->urls()'), $resource, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$this->getKey()'), $plain, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('reaches a protected static method only through static::', function () {
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverMethodProbe::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('static::secret()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('number')
            ->and($handler->resolve(receiverHandlerExpr('$probe::secret()'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    // Laravel's only()/except() return an attribute-keyed array whatever keys arrive at runtime. In a resource this
    // rule answers the own-model spellings; RelationFilterHandler answers a relation first, from the same helper.
    test('types a filter with no literal key list on a lone model as Record<string, unknown>', function (string $php, string $type) {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect(new ReceiverMethodCallHandler()->resolve(receiverHandlerExpr($php), $scope, chainHandlersThrowingEngine()))
            ->toBe(['type' => $type, 'optional' => false]);
    })->with([
        ['$this->resource->only($fields)', 'Record<string, unknown>'],
        ['$this->resource->author->only($fields)', 'Record<string, unknown>'],
        ['$this->author->except($fields)', 'Record<string, unknown>'],
        ['$this->resource->author?->only($fields)', 'Record<string, unknown> | null'],
    ]);

    // In a model's own method or accessor body `$this` is the model, and the chain handler declines its filters.
    // A method body reached by the body fallback carries no import, so a literal list there names no token: a key whose
    // type names one, such as an enum column, is `unknown`, and the rest of the shape keeps its types.
    test('types a filter on a model subject\'s own $this, naming no token where the scope carries no import', function () {
        $getter = new AnalysisScope(new ReflectionClass(Release::class), Release::class);
        $method = new AnalysisScope(new ReflectionClass(Post::class), Post::class);
        $method->carriesImports = false;
        $plain = new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class), Post::class);
        $handler = new ReceiverMethodCallHandler;
        $resolve = fn (string $php, AnalysisScope $scope): ?array => $handler->resolve(receiverHandlerExpr($php), $scope, chainHandlersThrowingEngine());
        $record = ['type' => 'Record<string, unknown>', 'optional' => false];

        expect($resolve('$this->only($keys)', $getter))->toBe($record)
            ->and($resolve('$this->except($this->keys)', $getter))->toBe($record)
            ->and($resolve('$this?->except($keys)', $getter))->toBe($record)
            ->and($resolve("\$this->only(['major'])", $getter))->toBe(['type' => "Pick<Release, 'major'>", 'optional' => false, 'modelFqcn' => Release::class])
            ->and($resolve("\$this->only(['id', 'title'])", $method))->toBe([
                'type' => '{ id: number; title: string }', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
            ])
            ->and($resolve("\$this?->only(['id', 'status'])", $method))->toBe([
                'type' => '{ id: number; status: unknown }', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
            ])
            ->and($resolve('$this->except($keys)', $method))->toBe($record)
            ->and($resolve('$this->getKey()', $getter))->toBeNull()
            ->and($resolve('$this->only($keys)', $plain))->toBeNull();
    });

    // Model::only()/except() are what the filter rules describe; an override declares its own return, which PHP holds
    // every subclass to, so reflection answers it, as it answers a getKey() override.
    test('keeps a model\'s own only()/except() override return, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), FilterOverrideModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, own model' => [CommentResource::class, null, true, "\$this->only(['id'])", 'string'],
        'resource, $this->resource' => [CommentResource::class, null, true, '$this->resource->except($keys)', 'number'],
        'resource, relation' => [CommentResource::class, null, true, "\$this->twin->only(['id'])", 'string'],
        'resource, relation ?->' => [CommentResource::class, null, true, '$this->resource->twin?->except($keys)', 'number | null'],
        'method body' => [FilterOverrideModel::class, null, false, "\$this->only(['id', 'title'])", 'string'],
        'method body, relation' => [FilterOverrideModel::class, null, false, '$this->twin->except($keys)', 'number'],
        'getter body' => [FilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this?->except([\'id\'])', 'number'],
        'getter body, relation' => [FilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->twin->only($keys)', 'string'],
    ]);

    // An override that declares no return leaves reflection nothing to publish, so it keeps Model's filter answer.
    test('keeps the filter answer for an only()/except() override that declares no return, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), UntypedFilterOverrideModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, own model' => [CommentResource::class, null, true, "\$this->only(['id', 'title'])", "Pick<UntypedFilterOverrideModel, 'id' | 'title'>"],
        'resource, $this->resource' => [CommentResource::class, null, true, '$this->resource->except($keys)', 'Record<string, unknown>'],
        'resource, relation' => [CommentResource::class, null, true, "\$this->twin->only(['id', 'title'])", "Pick<UntypedFilterOverrideModel, 'id' | 'title'>"],
        'resource, relation ?->' => [CommentResource::class, null, true, '$this->resource->twin?->except($keys)', 'Record<string, unknown> | null'],
        'resource, map proxy' => [CommentResource::class, null, true, "\$this->twins->map->only(['id', 'title'])", '{ id: number; title: string }[]'],
        'resource, multi-model accessor' => [CommentResource::class, null, true, "\$this->counterpart->only(['id'])", "Pick<UntypedFilterOverrideModel, 'id'> | Pick<User, 'id'>"],
        'method body' => [UntypedFilterOverrideModel::class, null, false, "\$this->only(['id', 'title'])", '{ id: number; title: string }'],
        'method body, relation' => [UntypedFilterOverrideModel::class, null, false, "\$this->twin?->only(['id', 'title'])", '{ id: number; title: string } | null'],
        'getter body' => [UntypedFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this?->only(['id', 'title'])", "Pick<UntypedFilterOverrideModel, 'id' | 'title'>"],
        'getter body, relation' => [UntypedFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->twin->only($keys)', 'Record<string, unknown>'],
    ]);

    // A declared return that publishes nothing more than `unknown[]`, `unknown` or `Record<string, unknown>` is one
    // reflection cannot type, so the override keeps Model's filter answer exactly as an undeclared one does.
    test('keeps the filter answer for an only()/except() override whose declared return is too vague to publish', function (string $model, string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), $model, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ': array, resource' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, "\$this->only(['id', 'title'])", "Pick<VagueFilterOverrideModel, 'id' | 'title'>"],
        ': array, resource relation' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, "\$this->resource->twin->only(['id'])", "Pick<VagueFilterOverrideModel, 'id'>"],
        ': array, method body' => [VagueFilterOverrideModel::class, VagueFilterOverrideModel::class, null, false, "\$this->only(['id', 'title'])", '{ id: number; title: string }'],
        ': array, getter body' => [VagueFilterOverrideModel::class, VagueFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->twin?->only(['id'])", "Pick<VagueFilterOverrideModel, 'id'> | null"],
        ': mixed, resource' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, '$this->except($keys)', 'Record<string, unknown>'],
        ': mixed, resource relation' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, '$this->twin->except($keys)', 'Record<string, unknown>'],
        'docblock array<string, mixed>, resource' => [DocblockFilterOverrideModel::class, CommentResource::class, null, true, "\$this->only(['id', 'title'])", "Pick<DocblockFilterOverrideModel, 'id' | 'title'>"],
        'docblock array<string, mixed>, getter body' => [DocblockFilterOverrideModel::class, DocblockFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->only(['id'])", "Pick<DocblockFilterOverrideModel, 'id'>"],
    ]);

    // The body fallback carries no FQCN channel and drops a shape naming a token. Where the scope imports nothing, a
    // model the override returns is spelled as the object it serializes to, narrowed to the call's literal keys, and an
    // override returning any other token, such as an enum, publishes unknown; the enclosing shape survives either way.
    test('publishes a class-typed only()/except() override with no token where the scope carries no import', function (?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass(ClassTypedFilterOverrideModel::class), ClassTypedFilterOverrideModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'method body, a model return' => [null, false, "\$this->only(['id'])", '{ id: number } | null'],
        'method body, relation ?->' => [null, false, '$this->twin?->only($keys)', 'Record<string, unknown> | null'],
        'method body, an enum return' => [null, false, "\$this->except(['id'])", 'unknown'],
        'method body, relation ?->, an enum return' => [null, false, "\$this->twin?->except(['id'])", 'unknown'],
        'method body, map proxy' => [null, false, "\$this->twins->map->only(['id', 'name'])", '({ id: number; name: string } | null)[]'],
        'method body, map proxy, an enum return' => [null, false, "\$this->twins->map->except(['id'])", 'unknown[]'],
        'method body, multi-model accessor' => [null, false, "\$this->counterpart->only(['id'])", '{ id: number } | { id: number } | null'],
        'method body, multi-model accessor, runtime keys' => [null, false, '$this->counterpart?->only($keys)', 'Record<string, unknown> | null'],
        'method body, multi-model accessor, an enum return' => [null, false, "\$this->counterpart->except(['id'])", 'unknown'],
        'getter body, a model return' => [ResourceExpressionHandlers::forModelClosures(), true, "\$this->only(['id'])", 'ClassTypedFilterOverrideModel | null'],
        'getter body, an enum return' => [ResourceExpressionHandlers::forModelClosures(), true, '$this->twin->except($keys)', 'PriorityType'],
        'getter body, map proxy' => [ResourceExpressionHandlers::forModelClosures(), true, "\$this->twins->map->except(['id'])", 'PriorityType[]'],
        'getter body, multi-model accessor' => [ResourceExpressionHandlers::forModelClosures(), true, "\$this->counterpart->only(['id'])", "ClassTypedFilterOverrideModel | Pick<User, 'id'> | null"],
    ]);

    test('keeps a method body\'s shape around a class-typed only()/except() override', function () {
        expect(resolve(MethodReturnTypeResolver::class)->resolve(ClassTypedFilterOverrideModel::class, 'filterFields'))->toBe([
            'type' => '{ own: { id: number; name: string } | null; twin: { id: number } | null; rest: unknown; id: number }',
            'optional' => false,
        ]);
    });

    // A returned model reaches JSON through toArray(): its columns and appended accessors, kept to `$visible` when that
    // lists any, less `$hidden`, whatever `exclude_hidden` says. The literal keys select from those names, a member
    // naming a token is `unknown`, and a runtime key list, or keys selecting nothing, leave a record.
    test('spells a model an override returns as the attributes it serializes, narrowed to the literal keys, where the scope carries no import', function (string $model, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($model), $model, 'toArray', carriesImports: false);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'hidden columns, an enum cast and a class cast' => [HiddenFilterOverrideModel::class, "\$this->only(['id', 'name', 'password', 'remember_token', 'role', 'options'])", '{ id: number; name: string; role: unknown; options: unknown }'],
        'a map proxy, a key naming no column and a repeated one' => [HiddenFilterOverrideModel::class, "\$this->twins->map->only(['id', 'role', 'nope', 'id'])", '{ id: number; role: unknown }[]'],
        'a runtime key list' => [HiddenFilterOverrideModel::class, '$this->twins->map->except($keys)', 'Record<string, unknown>[]'],
        'keys selecting only hidden columns' => [HiddenFilterOverrideModel::class, "\$this->only(['password'])", 'Record<string, unknown>'],
        'visible columns less a hidden one' => [VisibleFilterOverrideModel::class, "\$this->only(['id', 'slug', 'color'])", '{ id: number } | null'],
        'another model, its own columns' => [UserFilterOverrideModel::class, "\$this->twins->map->only(['id', 'title', 'password', 'role'])", '{ id: number; role: unknown }[]'],
        'appended accessors, one of them an enum' => [HiddenFilterOverrideModel::class, "\$this->only(['id', 'badge', 'rank'])", '{ id: number; badge: string; rank: unknown }'],
        'an appended accessor alone, through a map proxy' => [HiddenFilterOverrideModel::class, "\$this->twins->map->only(['badge'])", '{ badge: string }[]'],
        'an appended accessor through ?->' => [HiddenFilterOverrideModel::class, "\$this->twin?->only(['id', 'badge'])", '{ id: number; badge: string } | null'],
        'an appended accessor on a multi-model accessor arm' => [HiddenFilterOverrideModel::class, "\$this->counterpart->only(['id', 'badge'])", '{ id: number; badge: string } | { id: number }'],
        'another model, its own appended accessor' => [AppendingModelFilterOverrideModel::class, "\$this->twins->map->only(['id', 'title', 'badge'])", '{ id: number; badge: string }[]'],
        'a hidden appended accessor, and one not appended' => [HiddenFilterOverrideModel::class, "\$this->only(['id', 'secret', 'nick'])", '{ id: number }'],
        'a hidden appended accessor alone' => [HiddenFilterOverrideModel::class, "\$this->twin?->only(['secret'])", 'Record<string, unknown> | null'],
        'a visible appended accessor, and one outside $visible' => [VisibleFilterOverrideModel::class, "\$this->only(['id', 'label', 'shade'])", '{ id: number; label: string } | null'],
        'the complement of the visible names, an append named like a column once' => [VisibleFilterOverrideModel::class, "\$this->twin?->except(['id'])", '{ name: string; label: string } | null'],
        'a complement selecting nothing' => [VisibleFilterOverrideModel::class, "\$this->except(['id', 'name', 'label'])", 'Record<string, unknown> | null'],
    ]);

    // Only a top-level arm that is a model, or a list of one, is spelled from its columns: a model nested in a shape
    // key is a leaf the reflected docblock already spells `unknown`.
    test('spells a list of models an override returns as a list of their columns where the scope carries no import', function (string $model, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($model), $model, 'toArray', carriesImports: false);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'a list of token-free models' => [ListTypedFilterOverrideModel::class, "\$this->only(['id', 'slug'])", '{ id: number; slug: string }[]'],
        'a model nested in a shape key' => [ListTypedFilterOverrideModel::class, "\$this->except(['id'])", '{ owner: unknown; id: number }'],
        'a list of models whose columns name an enum' => [UserListFilterOverrideModel::class, "\$this->only(['id', 'role', 'password'])", '{ id: number; role: unknown }[]'],
    ]);

    // Support\Collection::only()/except() keep the entries whose keys are listed, so the value is a keyed map
    // whether the collection comes from a cast column, an accessor or a method, and whatever its elements are.
    test('types a filter on a Support\Collection member as Record<string, unknown>, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), CollectionMemberModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, cast column' => [CommentResource::class, null, true, "\$this->options->only(['a', 'b'])", 'Record<string, unknown>'],
        'resource, cast column through $this->resource' => [CommentResource::class, null, true, '$this->resource->options->except($keys)', 'Record<string, unknown>'],
        'resource, AsEncryptedCollection column' => [CommentResource::class, null, true, "\$this->visibility->only(['a'])", 'Record<string, unknown>'],
        'resource, collection column' => [CommentResource::class, null, true, "\$this->content?->only(['a'])", 'Record<string, unknown> | null'],
        'resource, encrypted:collection column through $this->resource' => [CommentResource::class, null, true, '$this->resource->featured_image_url->except($keys)', 'Record<string, unknown>'],
        'resource, accessor of models' => [CommentResource::class, null, true, "\$this->people->only(['id'])", 'Record<string, unknown>'],
        'resource, accessor of models ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->people?->except(['id'])", 'Record<string, unknown> | null'],
        'method body, collection column' => [CollectionMemberModel::class, null, false, "\$this->content->only(['a'])", 'Record<string, unknown>'],
        'method body, accessor of models' => [CollectionMemberModel::class, null, false, "\$this->people->only(['id'])", 'Record<string, unknown>'],
        'getter body, encrypted:collection column' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->featured_image_url->only(['a'])", 'Record<string, unknown>'],
        'getter body, accessor of models' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->people->except(['id'])", 'Record<string, unknown>'],
        'resource, accessor' => [CommentResource::class, null, true, "\$this->stats->except(['a'])", 'Record<string, unknown>'],
        'resource, method' => [CommentResource::class, null, true, '$this->tally()->only($keys)', 'Record<string, unknown>'],
        'resource, method ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->tally()?->only(['a'])", 'Record<string, unknown> | null'],
        'resource, a cast building an Eloquent collection' => [CommentResource::class, null, true, "\$this->metadata->only(['a'])", 'unknown'],
        'method body, cast column ?->' => [CollectionMemberModel::class, null, false, "\$this->options?->except(['a'])", 'Record<string, unknown> | null'],
        'method body, accessor' => [CollectionMemberModel::class, null, false, '$this->stats->only($keys)', 'Record<string, unknown>'],
        'method body, method' => [CollectionMemberModel::class, null, false, "\$this->tally()->except(['a'])", 'Record<string, unknown>'],
        'getter body, cast column' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->options->except($keys)', 'Record<string, unknown>'],
        'getter body, accessor' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->stats->only(['a', 'b'])", 'Record<string, unknown>'],
        'getter body, method' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->tally()->only($keys)', 'Record<string, unknown>'],
        'getter body, a to-many relation' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->comments->only([1])', 'Comment[]'],
    ]);

    // Eloquent\Collection::only()/except() keep whole models by primary key and re-index them, so an accessor holding
    // one publishes a list of its models, as a many-relation does, whatever key type it declares. A cast building one
    // holds decoded JSON, whose elements have no key to filter by.
    test('types a filter on an accessor holding an Eloquent\Collection as a list of its models, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), CollectionMemberModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource' => [CommentResource::class, null, true, '$this->kids->only([1])', 'Comment[]'],
        'resource ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->kids?->except(['id'])", 'Comment[] | null'],
        'resource, no element model' => [CommentResource::class, null, true, '$this->strays->only($keys)', 'unknown[]'],
        'method body' => [CollectionMemberModel::class, null, false, "\$this->kids->only(['id'])", 'unknown[]'],
        'getter body' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->kids->except($keys)', 'Comment[]'],
        'getter body, no element model ?->' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->strays?->only([1])', 'unknown[] | null'],
        'getter body, a cast building one' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->metadata->only(['a'])", 'unknown'],
        'resource, keyed by string, nullable' => [CommentResource::class, null, true, '$this->keyed_kids->only([1])', 'Comment[] | null'],
        'resource, keyed by string, two models ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->mixed_kids?->except(['x'])", '(Comment | User)[] | null'],
        'getter body, keyed by string, two models' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->mixed_kids->only($keys)', '(Comment | User)[]'],
        'method body, keyed by string, nullable' => [CollectionMemberModel::class, null, false, '$this->keyed_kids->except([1])', 'unknown[] | null'],
        'method body, keyed by string, two models' => [CollectionMemberModel::class, null, false, "\$this->mixed_kids->only(['x'])", 'unknown[]'],
    ]);

    test('types a Support\Collection member\'s filter through the real getter and method-body paths', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(CollectionMemberModel::class, 'option_picks')['type'])
            ->toBe('{ options: Record<string, unknown>; id: number }')
            ->and(resolve(MethodReturnTypeResolver::class)->resolve(CollectionMemberModel::class, 'optionFields'))
            ->toBe(['type' => '{ options: Record<string, unknown>; id: number }', 'optional' => false]);
    });

    // Model::only() keys a name it cannot find to null, so a literal list naming nothing typed still returns an
    // attribute-keyed array. RelationFilterHandler declines the relation spelling, and the receiver rules answer it.
    test('types a literal key list that names nothing as Record<string, unknown>, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), Post::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, own model' => [PostResource::class, null, true, "\$this->only(['nope'])", 'Record<string, unknown>'],
        'resource, relation' => [PostResource::class, null, true, "\$this->author->only(['nope'])", 'Record<string, unknown>'],
        'resource, relation ?-> through $this->resource' => [PostResource::class, null, true, "\$this->resource->author?->only(['nope'])", 'Record<string, unknown> | null'],
        'method body, relation' => [Post::class, null, false, "\$this->author->only(['nope'])", 'Record<string, unknown>'],
        'getter body, relation' => [Post::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->author->only(['nope'])", 'Record<string, unknown>'],
    ]);

    test('an earlier ?-> in the chain makes the call nullable once', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('$this->resource?->author->getMorphClass()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string | null')
            ->and($handler->resolve(receiverHandlerExpr('$this->resource?->author?->getMorphClass()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string | null');
    });
});

describe('MethodChainHandler no longer reflects on the wrong receiver', function () {
    test('declines a nullsafe call whose last step is an unresolved morphTo', function () {
        $scope = new AnalysisScope(new ReflectionClass(ImageResource::class), Image::class);
        $expr = new NullsafeMethodCall(new PropertyFetch(new Variable('this'), 'imageable'), 'getTable');

        expect(new MethodChainHandler()->resolve($expr, $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('declines a nullsafe call whose last step is an attribute, not a relation', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);
        $expr = new NullsafeMethodCall(new PropertyFetch(new Variable('this'), 'priority'), 'label');

        expect(new MethodChainHandler()->resolve($expr, $scope, chainHandlersThrowingEngine()))->toBeNull();
    });
});

describe('ReceiverPropertyFetchHandler', function () {
    test('types property chains from a local variable holding a model', function () {
        expect(receiverPropertyTypes())->toMatchArray([
            'post_title' => 'string | null',
            'post_published_at' => 'string | null',
            'post_author_name' => 'string | null',
            'post_title_direct' => 'string',
            'post_title_via_this' => 'string | null',
            'post_title_via_resource' => 'string | null',
        ]);
    });

    test('a chain read through $this->resource publishes the type of its $this twin', function () {
        $props = receiverPropertyTypes();

        expect($props['resource_post_title'])->toBe($props['post_title'])
            ->and($props['resource_post_published_at'])->toBe($props['post_published_at'])
            ->and($props['resource_post_author_name'])->toBe($props['post_author_name'])
            ->and($props['resource_post_title_direct'])->toBe($props['post_title_direct'])
            ->and($props['post_title_via_resource'])->toBe($props['post_title_via_this']);
    });

    test('declines a $this->prop leaf, which belongs to ThisPropertyHandler', function () {
        expect(new ReceiverPropertyFetchHandler()->resolve(receiverHandlerExpr('$this->title'), receiverProbeScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('a reflected property json_encode() writes as an object declines, while one it writes as a string types', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverVarProbe::class);

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverVarProbe::class), 'plainDate')['type'])->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr('$probe->plainDate'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$probe->text'), $scope, chainHandlersThrowingEngine())['type'] ?? null)->toBe('string');
    });

    test('a union receiver declines when only one arm holds a class json_encode() writes as an object', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $union = '($flag ? new '.ReceiverVarProbe::class.' : new '.ReceiverStringDateProbe::class.')->plainDate';

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverStringDateProbe::class), 'plainDate')['type'])->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr('(new '.ReceiverStringDateProbe::class.')->plainDate'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr($union), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('a reflected property naming a model no file is published for declines', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverVarProbe::class);

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverVarProbe::class), 'anyModel')['classFqcns'])->toBe([Model::class])
            ->and($handler->resolve(receiverHandlerExpr('$probe->anyModel'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    // PHP sends an outside read of a non-public or static property to __get(), never to the declaration.
    test('only a public instance property types a read from outside the class', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['post'] = receiverHandlerExpr('new '.MagicPost::class);

        expect($handler->resolve(receiverHandlerExpr('$post->title'), $scope, chainHandlersThrowingEngine())['type'] ?? null)->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr('$post->status'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$post->data'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$post->kind'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('(new '.Fluent::class.')->attributes'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });
});

describe('a property the subject declares wins over the model', function () {
    test('a promoted resource property wins over the model and chains through its own class', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(PostStatsResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toMatchArray([
            // Post::$title is a string column; the subject's own int declaration wins.
            'title' => 'number',
            'stats' => '{ views: number; shares: number } | null',
            'views' => 'number | null',
            'share_count' => 'number | null',
        ]);
    });

    // `resource` is declared by JsonResource, so it is never the subject's own; if that exclusion were
    // dropped, every `$this->resource->…` receiver in the package would stop resolving.
    test('$this->resource->prop still types, whether or not the subject redeclares $resource', function () {
        $inherited = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $redeclared = new ResourceAstAnalyzer(new ReflectionClass(ModelWrappedPropResource::class), Post::class);

        expect($inherited->resolve(receiverHandlerExpr('$this->resource->title'))['type'])->toBe('string')
            ->and($redeclared->resolve(receiverHandlerExpr('$this->resource->title'))['type'])->toBe('string');
    });
});

// The MethodCall/NullsafeMethodCall rows' inert claim: both handlers answer a relation's only() with the same Pick<>,
// so reordering the pair cannot change an answer.
describe('RelationFilterHandler and ReceiverMethodCallHandler are inert against each other', function () {
    test('both claim $this->relation->only([...]) and answer it the same', function (string $php, array $expected) {
        $expr = receiverHandlerExpr($php);
        $scope = fn (): AnalysisScope => new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        $filter = new RelationFilterHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine());
        $receiver = new ReceiverMethodCallHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine());

        expect($filter)->toBe($expected)
            ->and($receiver)->toBe($filter);
    })->with([
        // The Pick<> branch, both spellings, carrying the modelFqcn channel.
        ['$this->post->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]],
        ['$this->post?->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'> | null", 'optional' => false, 'modelFqcn' => Post::class]],
        // The same relation read through the $this->resource proxy.
        ['$this->resource->post->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]],
        ['$this->resource->post?->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'> | null", 'optional' => false, 'modelFqcn' => Post::class]],
        // A runtime key list: both build Record<string, unknown> from ResolvesFilteredRelationTypes.
        ['$this->post->except($fields)', ['type' => 'Record<string, unknown>', 'optional' => false]],
        ['$this->resource->post?->only($fields)', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
        // The inline branch: 'excerpt' is an accessor rather than a column, so relationFilterModelReference()
        // declines on both sides and each falls to resolveFilteredRelationType() — a different channel set.
        ['$this->post->only([\'id\', \'excerpt\'])', [
            'type' => '{ id: number; excerpt: string | null }',
            'optional' => false,
            'embeddedEnumFqcns' => [],
            'embeddedModelFqcns' => [],
            'customImports' => [],
        ]],
    ]);

    // An accessor typed Collection<int, User> names a model, but its filter is the collection's, so both answer
    // Record<string, unknown>. The receiver rules decline an Eloquent\Collection, which RelationFilterHandler types.
    test('agree on a filter on an accessor holding a collection of models', function (string $php, ?array $expected, ?array $receiverExpected) {
        $expr = receiverHandlerExpr($php);
        $scope = fn (): AnalysisScope => new AnalysisScope(new ReflectionClass(CommentResource::class), CollectionMemberModel::class);
        $engine = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), CollectionMemberModel::class);

        expect(new RelationFilterHandler()->resolve($expr, $scope(), $engine))->toBe($expected)
            ->and(new ReceiverMethodCallHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine()))->toBe($receiverExpected);
    })->with([
        'Support\Collection of models' => ["\$this->people->only(['id'])", ['type' => 'Record<string, unknown>', 'optional' => false], ['type' => 'Record<string, unknown>', 'optional' => false]],
        'Support\Collection of models ?->' => ["\$this->resource->people?->except(['id'])", ['type' => 'Record<string, unknown> | null', 'optional' => false], ['type' => 'Record<string, unknown> | null', 'optional' => false]],
        'Eloquent\Collection of models' => ['$this->kids->only([1])', ['type' => 'Comment[]', 'optional' => false, 'modelFqcn' => Comment::class], null],
    ]);
});

// The inert half of the ordering inventory's PropertyFetch row: both handlers really claim
// `$this->relation?->attr`, and they answer it identically, which is why their order is free.
// Swapping the two registrations changed no golden line; see docs/components/ast-engine.md.
describe('PropertyChainHandler and ReceiverPropertyFetchHandler are inert against each other', function () {
    test('both claim $this->relation?->attr and answer it the same', function () {
        $expr = receiverHandlerExpr('$this->post?->title');

        $chain = new PropertyChainHandler()->resolve($expr, receiverPropertyScope(), chainHandlersThrowingEngine());
        $receiver = new ReceiverPropertyFetchHandler()->resolve($expr, receiverPropertyScope(), chainHandlersThrowingEngine());

        expect($chain)->toBe(['type' => 'string | null', 'optional' => false])
            ->and($receiver)->toBe($chain);
    });
});

describe('PropertyChainHandler declines an unknown-only chain', function () {
    test('a nullsafe chain rooted at a variable declines for the receiver handler', function () {
        expect(new PropertyChainHandler()->resolve(receiverHandlerExpr('$post?->title'), receiverPropertyScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('a $this->prop->subProp chain it cannot type declines for the receiver handler', function () {
        expect(new PropertyChainHandler()->resolve(receiverHandlerExpr('$this->post->nonexistent_column'), receiverPropertyScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });
});

/**
 * The classes of each guard binding collectInstanceofGuards() writes for one parsed method body.
 *
 * @return array<string, non-empty-list<class-string>>
 */
function narrowingBindings(string $body): array
{
    $scope = new AnalysisScope(new ReflectionClass(NarrowedParentResource::class), Attachment::class);
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsInstanceofGuards;
        use CollectsLocalVarBindings;

        /** @param  array<Stmt>  $stmts */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->collectInstanceofGuards($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);

    return array_map(fn (array $guard): array => $guard['classes'], $scope->varGuardBindings);
}

describe('narrowing', function () {
    test('an early-return instanceof guard narrows a closure-local variable', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NarrowedParentResource::class), Attachment::class)->analyze()->properties)->keyBy('name');

        expect($props['parent']['type'])->toBe('{ title: string; class: string; morph: string } | null')
            ->and($props['parent']['optional'])->toBeTrue()
            ->and($props['record_title']['type'])->toBe('string | null');
    });

    test('a $this->resource instanceof ternary narrows the model for its true arm', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(TeamSubscriberResource::class), Team::class)->analyze()->properties)->keyBy('name');

        expect($props['subscriber_name']['type'])->toBe('string | null');
    });

    test('an instanceof ternary on a $this->prop narrows the receiver a local variable binds through it', function (string $key, string $type) {
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);

        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NarrowedImageableResource::class), Image::class)->analyze()->properties)->keyBy('name');

        expect($props[$key]['type'])->toBe($type);
    })->with([
        'an || chain' => ['either_id', 'number | null'],
        'a single test' => ['single_id', 'number | null'],
        'the un-narrowed control' => ['open_id', 'number | string | null'],
        'a variable ternary over the narrowed binding' => ['either_title', 'string | null'],
    ]);

    test('a guard whose body reads the variable leaves it un-narrowed, while a clean guard still narrows after it', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NarrowingGuardBodyResource::class), Post::class)->analyze()->properties)->keyBy('name');

        // dirty_label is read inside the branch proving $parent is NOT a Post; User has no `title`, so it drops.
        expect($props['dirty_label']['type'])->toBe('number')
            ->and($props['clean_label']['type'])->toBe('string');
    });

    test('a narrowed variable outranks both its model binding and the local-assignment fallback', function () {
        $scope = new AnalysisScope(new ReflectionClass(NarrowedParentResource::class), Attachment::class);
        $scope->varClassBindings['x'] = [Post::class];
        $scope->varModelBindings['x'] = User::class;
        $scope->localVarBindings['x'] = receiverHandlerExpr('$this->filename');

        expect(resolve(ReceiverClassResolver::class)->resolve(new Variable('x'), $scope)?->classes)->toBe([Post::class]);
    });

    test('a guard on a variable written twice does not narrow', function () {
        expect(narrowingBindings('$a = 1; $a = 2; if (! $a instanceof \Workbench\App\Models\Post) { return; }'))->toBe([]);
    });

    test('a guard body that reads the guarded variable still binds, for the statements after the guard', function () {
        expect(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return $a->title; }'))
            ->toBe(['a' => [Post::class]]);
    });

    test('an early-exit guard narrows only the statements after it, in a method body and in a closure body', function () {
        $props = collect(resolve(AstEngine::class)->analyze(GuardOrderResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe([
            'early' => 'number | string',
            'late' => 'number',
            'deferred' => 'number | string | null',
        ]);
    });

    test('a guard body that reads nothing binds, including through an || chain and a throw exit', function () {
        expect(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return null; }'))
            ->toBe(['a' => [Post::class]])
            ->and(narrowingBindings('$a = $this->author; if (! $a || ! $a instanceof \Workbench\App\Models\Post) { return null; }'))
            ->toBe(['a' => [Post::class]])
            ->and(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { throw new \RuntimeException(); }'))
            ->toBe(['a' => [Post::class]]);
    });

    test('a guard binds only negated tests on a variable, through every || operand and never through &&', function (string $body, array $bindings) {
        expect(narrowingBindings($body))->toBe($bindings);
    })->with([
        'a positive test whose body reads nothing' => ['$a = $this->author; if ($a instanceof \Workbench\App\Models\Post) { return null; }', []],
        'an && of negated tests' => ['$a = $this->author; $b = $this->author; if (! $a instanceof \Workbench\App\Models\Post && ! $b instanceof \Workbench\App\Models\User) { return null; }', []],
        'every negated || operand' => ['$a = $this->author; $b = $this->author; if (! $a instanceof \Workbench\App\Models\Post || ! $b instanceof \Workbench\App\Models\User) { return null; }', ['a' => [Post::class], 'b' => [User::class]]],
        'a property subject' => ['if (! $this->author instanceof \Workbench\App\Models\User) { return null; }', []],
    ]);

    test('an elseif, an else, or a positive instanceof test binds nothing', function () {
        expect(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return null; } elseif ($a) { return null; }'))
            ->toBe([])
            ->and(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return null; } else { return null; }'))
            ->toBe([])
            ->and(narrowingBindings('$a = $this->author; if ($a instanceof \Workbench\App\Models\Post) { return $a->title; }'))
            ->toBe([]);
    });
});

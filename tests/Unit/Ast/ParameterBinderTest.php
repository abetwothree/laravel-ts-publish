<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\RequestRoleHelperEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaHelperParamsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DeclaredCollectionParameterResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DocumentedClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperParameterKindsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LocalAliasResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StaticHelperSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrittenDeclaredParametersResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrittenModelMemberResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SelfTypedHelpers;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\HelperModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\TypedParameterMetadataProvider;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Enums\Status;
use Workbench\App\Events\PostHelperPayloadEvent;
use Workbench\App\Http\Resources\CallableValueResource;
use Workbench\App\Http\Resources\PostHelperParamsResource;
use Workbench\App\Http\Resources\SpreadHelperScopeResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\PostStats;

/**
 * Each property an analysis publishes, as `name => type`, a `?` before the type marking an optional one.
 *
 * @param  iterable<array{name: string, type: string, optional: bool}>  $properties
 * @return array<string, string>
 */
function parameterBinderKeyTypes(iterable $properties): array
{
    $types = [];

    foreach ($properties as $property) {
        $types[$property['name']] = ($property['optional'] ? '?' : '').$property['type'];
    }

    return $types;
}

/**
 * How each argument, a value and the classes it holds, binds the parameter it is passed; one that binds nothing is left
 * out, as CallSite::passedTo() leaves it.
 *
 * @param  array<string, array{value: array<string, mixed>, receiver: ReceiverType|null}>  $arguments
 * @return array<string, mixed>
 */
function parameterBinderPassed(array $arguments): array
{
    $passed = [];

    foreach ($arguments as $name => ['value' => $value, 'receiver' => $receiver]) {
        $binding = resolve(ParameterBinder::class)->passedBinding($value, $receiver);

        if ($binding !== null) {
            $passed[$name] = $binding;
        }
    }

    return $passed;
}

/**
 * Each property HelperParameterKindsResource publishes.
 *
 * @return array<string, string>
 */
function parameterBinderKinds(): array
{
    return parameterBinderKeyTypes(
        resolve(AstEngine::class)->analyze(HelperParameterKindsResource::class, 'toArray', Post::class)->properties,
    );
}

/**
 * The closure or arrow function a parsed `$f = …;` assigns.
 */
function parameterBinderClosure(string $closure): ArrowFunction|Closure
{
    $statement = new AstParser()->parseSource('<?php $f = '.$closure.';')[0];
    assert($statement instanceof Expression && $statement->expr instanceof Assign);
    assert($statement->expr->expr instanceof ArrowFunction || $statement->expr->expr instanceof Closure);

    return $statement->expr->expr;
}

/**
 * The resource profile over a scope the caller also holds, as production shares one.
 */
function parameterBinderEngine(AnalysisScope $scope): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer(new ReflectionClass(HelperParameterKindsResource::class), Post::class, 'toArray', null, $scope);
}

test('a helper body reads its typed parameters through every reading of it', function () {
    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(PostHelperParamsResource::class)->properties))->toBe([
        'id' => 'number',
        'title' => 'string',
        'extra' => 'number',
        'summary' => '{ id: number; title: string; extra: number }',
        'card' => '{ card_id: number; card_title: string }',
        'nested' => '{ inner: { card_id: number; card_title: string } }',
        'static_card' => '{ static_id: number }',
        'headline_id' => 'number',
        'headline' => 'string',
        'maybe' => '{ maybe_title: string | null }',
        'status_label' => '{ label: StatusKind; value: StatusType }',
        'status_payload' => '{ payload_status: StatusType; payload_name: StatusKind }',
        'payload_status' => 'StatusType',
        'payload_name' => 'StatusKind',
    ]);
});

test('each kind of declared parameter binds as the value a call passes it', function () {
    expect(parameterBinderKinds())->toBe([
        'vo_stats' => '{ views: number; shares: number }',
        'vo_views' => 'number',
        'value_object' => '{ vo_stats: { views: number; shares: number }; vo_views: number }',
        'count' => 'number',
        'scalar' => '{ count: number }',
        'nm_title' => 'string | null',
        'nm_post' => 'Post | null',
        'nullable_model' => '{ nm_title: string | null; nm_post: Post | null }',
        'un_id' => 'number',
        'un_owner' => 'Post',
        'union' => '{ un_id: number; un_owner: Post }',
        'va_posts' => 'Post[]',
        'variadic' => '{ va_posts: Post[] }',
        'br_title' => 'string',
        'by_reference' => '{ br_title: string }',
        'df_limit' => 'number',
        'df_title' => 'string | null',
        'defaulted' => '{ df_limit: number; df_title: string | null }',
        'dl_posts' => 'Post[]',
        'docblock_list' => '{ dl_posts: Post[] }',
        'pp_tags' => 'string[]',
        'phpstan_param' => '{ pp_tags: string[] }',
        'ut_title' => 'string',
        'untyped' => '{ ut_title: string }',
        'wr_name' => 'string',
        'written' => '{ wr_name: string }',
        'rq_has' => 'unknown',
        'rl_name' => 'string',
        'rl_title' => 'unknown',
        'relabel' => '{ rl_name: string; rl_title: unknown }',
        'tr_typed' => '?{ t: string }',
        'has_typed' => '?{ t: string }',
        'amount' => 'string',
        'formatted' => '{ amount: string }',
        'n' => 'string',
        'string_cast' => '{ n: string }',
        'tags' => 'string',
        'joined' => '{ tags: string }',
        'p_title' => 'string',
        'filled' => '{ p_title: string }',
        'by_ref_passed' => '?{ r_name: string }',
        'vt_pairs' => '[number, string][]',
    ]);
});

// A value binding ends past the parameter's own top-level `$x = …;`, whose value reads the parameter's old one.
test('a helper that replaces its typed parameter with a value of another type reads what the body wrote', function () {
    expect(parameterBinderKinds())->toMatchArray([
        'amount' => 'string',
        'formatted' => '{ amount: string }',
        'n' => 'string',
        'string_cast' => '{ n: string }',
        'tags' => 'string',
        'joined' => '{ tags: string }',
        'p_title' => 'string',
        'filled' => '{ p_title: string }',
    ]);
});

// The class a receiver reads stays past a write naming none, but the value read takes what the body wrote.
test('a parameter holding a class beside a value reads its value before its own write, and the written value past it', function () {
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);
    $post->setRelation('author', new User()->forceFill(['id' => 3, 'name' => 'Ann']));

    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(WrittenDeclaredParametersResource::class, 'toArray', Post::class)->properties))
        ->toBe([
            'title_of' => 'string | null',
            'copied' => 'Post | null',
            'decoded' => 'unknown',
            'views_of' => 'number',
            'author_name' => '?string',
            'replica_title' => 'string',
            'model_title' => 'string',
            'model_copied' => 'Post',
            'model_decoded' => 'Post',
            'model_through' => 'string',
            'model_same' => 'Post',
            'loaded_name' => '?string',
        ])
        ->and(json_decode((string) json_encode(new WrittenDeclaredParametersResource($post)->resolve(Request::create('/'))), true))
        ->toMatchArray([
            'title_of' => 'Banana',
            'decoded' => [],
            'views_of' => 1,
            'author_name' => 'Ann',
            'replica_title' => 'Banana',
            'model_title' => 'Banana',
            'model_copied' => ['id' => 7, 'title' => 'Banana', 'author' => ['id' => 3, 'name' => 'Ann']],
            'model_decoded' => [],
            'model_through' => 'Banana',
            'model_same' => ['id' => 7, 'title' => 'Banana', 'author' => ['id' => 3, 'name' => 'Ann']],
            'loaded_name' => 'Ann',
        ]);
});

test('a spread call passes its argument to an untyped by-reference parameter', function () {
    expect(parameterBinderKinds())->toMatchArray(['by_ref_passed' => '?{ r_name: string }']);
});

// The write replaces the post with its author: the read the write makes still sees the post, every read after it the
// author, so neither key can publish what a Post column holds.
test('a helper that reassigns its typed parameter reads the new value past the write', function () {
    expect(parameterBinderKinds())->toMatchArray([
        'rl_name' => 'string',
        'rl_title' => 'unknown',
        'relabel' => '{ rl_name: string; rl_title: unknown }',
    ]);
});

test('a typed callable Laravel calls reads the value it is called with', function () {
    expect(parameterBinderKinds())->toMatchArray(['tr_typed' => '?{ t: string }', 'has_typed' => '?{ t: string }'])
        ->and(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(CallableValueResource::class)->properties))
        ->toMatchArray(['supervisor_card' => '?{ name: string } | null']);
});

test('a spread helper reads none of its caller closure\'s names, nor the relation closure\'s model', function () {
    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(SpreadHelperScopeResource::class)->properties))->toBe([
        'has_t' => '?{ v: number; k: number }',
        'transform_t' => '?{ v: number }',
        'loaded_t' => '?{ v: number }',
        'author_named' => '?{ n: unknown }',
        'author_passed' => '?{ n: string }',
        'v' => 'number',
    ]);
});

test('a top-level self:: or static:: helper spread publishes the keys the $this-> spread does', function () {
    $static = parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(StaticHelperSpreadResource::class, 'toArray', Post::class)->properties);
    $viaThis = parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(StaticHelperSpreadResource::class, 'viaThis', Post::class)->properties);

    expect($static)->toBe(['row_id' => 'number', 'row_title' => 'string', 'tail_title' => 'string'])
        ->and($viaThis)->toBe($static);
});

test('a broadcast event\'s helper reads its typed parameter', function () {
    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyzeMethod(PostHelperPayloadEvent::class, 'broadcastWith')->properties))->toBe([
        'post_id' => 'number',
        'post_title' => 'string',
        'row' => '{ post_id: number; post_title: string }',
    ]);
});

test('an Inertia action\'s helper reads its typed parameter', function () {
    expect(new InertiaPageAnalyzer()->analyze(['uses' => InertiaHelperParamsController::class.'@show'])['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { row: { row_title: string }, spread_title: string }');
});

test('a metadata provider\'s helper reads the model it is passed', function () {
    expect(resolve(ModelMetadataAnalyzer::class)->analyze(HelperModelMetadataProvider::class, ['naming', 'keyName'])->types)
        ->toBe(['naming' => '{ table: string }', 'keyName' => 'string']);
});

test('a metadata provider reads its typed scalar parameter beside the model', function () {
    expect(resolve(ModelMetadataAnalyzer::class)->analyze(TypedParameterMetadataProvider::class, ['limit', 'table'])->types)
        ->toBe(['limit' => 'number', 'table' => 'string']);
});

test('binds each parameter into the table its declared type belongs in, and leaves a Request one', function () {
    $reflection = new ReflectionClass(HelperParameterKindsResource::class);
    $scope = new AnalysisScope($reflection, Post::class);

    foreach (['nullableModel', 'union', 'valueObject', 'request', 'scalar'] as $method) {
        resolve(ParameterBinder::class)->bindCall($reflection->getMethod($method), [], $scope);
    }

    expect($scope->varModelBindings)->toBe([])
        ->and($scope->varDeclaredBindings)->toBe(['post' => [Post::class], 'owner' => [Post::class, User::class], 'stats' => [PostStats::class]])
        ->and(array_map(fn (array $value): string => $value['type'], $scope->varValueBindings))
        ->toBe(['post' => 'Post | null', 'owner' => 'Post | User', 'stats' => '{ views: number; shares: number }', 'count' => 'number'])
        ->and($scope->requestVarNames)->toBe([]);
});

// No call is read for a closure, so an untyped parameter binds nothing; `self` names the scope's declaring class.
test('binds a closure\'s parameters by the types they declare, natively or by @param', function () {
    $statement = new AstParser()->parseSource('<?php $f = /** @param int $doc */ function (
        \\Workbench\\App\\Models\\Post $post, ?\\Workbench\\App\\Models\\Post $maybe, int|bool $flag, ?string $label,
        int $n = null, \\Workbench\\App\\Enums\\Status $status, $loose, $doc, self $me, int ...$rest,
    ) {};')[0];
    assert($statement instanceof Expression && $statement->expr instanceof Assign && $statement->expr->expr instanceof Closure);
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);

    resolve(ParameterBinder::class)->bindClosure($statement->expr->expr, $scope);

    expect($scope->varModelBindings)->toBe(['post' => Post::class])
        ->and($scope->varDeclaredBindings)->toBe([
            'maybe' => [Post::class],
            'status' => [Status::class],
            'me' => [HelperParameterKindsResource::class],
        ])
        ->and(array_map(fn (array $value): string => $value['type'], $scope->varValueBindings))->toBe([
            'maybe' => 'Post | null',
            'flag' => 'number | boolean',
            'label' => 'string | null',
            'n' => 'number | null',
            'status' => 'StatusType',
            'doc' => 'number',
            'rest' => 'number[]',
        ]);
});

test('binds a closure argument\'s parameter by the @param written before the argument', function () {
    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(DocumentedClosureResource::class)->properties))
        ->toMatchArray(['doc_param' => 'string | number'])
        ->and(json_encode(new DocumentedClosureResource(new Post)->relabel()->toArray(request())['doc_param']))->toBe('1');
});

// PHP 8.4 reflects `self` and `parent` as spelled, and 8.5 too in a trait's method.
test('reads a parameter typed self as its declaring class', function () {
    $reflection = new ReflectionClass(SelfTypedHelpers::class);
    $scope = new AnalysisScope($reflection, SelfTypedHelpers::class);

    foreach (['matchedWith', 'pairedWith'] as $method) {
        resolve(ParameterBinder::class)->bindCall($reflection->getMethod($method), [], $scope);
    }

    expect($scope->varModelBindings)->toBe(['other' => SelfTypedHelpers::class, 'mate' => SelfTypedHelpers::class])
        ->and($scope->varDeclaredBindings)->toBe(['spare' => [SelfTypedHelpers::class], 'base' => [Model::class]])
        ->and(array_map(fn (array $value): string => $value['type'], $scope->varValueBindings))
        ->toBe(['spare' => 'SelfTypedHelpers | null']);
});

// A closure's `self` and `parent` name its declaring class and that class's parent, as the class names would, and
// `X|null` names its class as `?X` does.
test('binds a closure parameter typed self or parent as the class its name would bind', function () {
    $bound = function (string $parameters): AnalysisScope {
        $statement = new AstParser()->parseSource('<?php $f = function ('.$parameters.') {};')[0];
        assert($statement instanceof Expression && $statement->expr instanceof Assign && $statement->expr->expr instanceof Closure);
        $scope = new AnalysisScope(new ReflectionClass(SelfTypedHelpers::class), SelfTypedHelpers::class);

        resolve(ParameterBinder::class)->bindClosure($statement->expr->expr, $scope);

        return $scope;
    };
    $tables = fn (AnalysisScope $scope): array => [
        $scope->varModelBindings,
        $scope->varDeclaredBindings,
        array_map(fn (array $value): string => $value['type'], $scope->varValueBindings),
    ];
    $named = '\\'.SelfTypedHelpers::class;

    expect($tables($bound('self $me, ?self $maybe, parent $up, self|null $either')))
        ->toBe($tables($bound($named.' $me, ?'.$named.' $maybe, \\'.Model::class.' $up, '.$named.'|null $either')))
        ->toBe([
            ['me' => SelfTypedHelpers::class],
            ['maybe' => [SelfTypedHelpers::class], 'up' => [Model::class], 'either' => [SelfTypedHelpers::class]],
            ['maybe' => 'SelfTypedHelpers | null', 'either' => 'SelfTypedHelpers | null'],
        ]);
});

// An API resource's output was inferred without the request rules, so only another subject binds a request there.
test('binds a Request parameter as a request, unless the subject is an API resource', function () {
    $event = new AnalysisScope(new ReflectionClass(RequestRoleHelperEvent::class));
    $resource = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);

    resolve(ParameterBinder::class)->bindCall(new ReflectionMethod(RequestRoleHelperEvent::class, 'roleOf'), [], $event);
    resolve(ParameterBinder::class)->bindCall(new ReflectionMethod(HelperParameterKindsResource::class, 'request'), [], $resource);

    expect($event->requestVarNames)->toBe(['request' => Request::class])
        ->and($event->varDeclaredBindings)->toBe([])
        ->and($resource->requestVarNames)->toBe([])
        ->and($resource->varDeclaredBindings)->toBe([]);
});

// The parameter holds the value, so whether the caller's key may be absent, and whether `??` fills it, stays there.
test('passes a value without the caller\'s optional or its absent-field marker', function () {
    expect(resolve(ParameterBinder::class)->passedBinding(['type' => 'string', 'optional' => true, 'absentReadsNull' => true], null))
        ->toBe(['model' => null, 'classes' => null, 'elementModel' => null, 'value' => ['type' => 'string', 'optional' => false], 'unsaved' => false, 'refines' => false, 'declaredOnly' => false, 'guard' => null]);
});

test('binds a non-null concrete model parameter in varModelBindings, as a route-bound one is', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostHelperParamsResource::class), Post::class);

    resolve(ParameterBinder::class)->bindCall(new ReflectionMethod(PostHelperParamsResource::class, 'card'), [], $scope);

    expect($scope->varModelBindings)->toBe(['post' => Post::class])
        ->and($scope->varValueBindings)->toBe([]);
});

// The parameter keeps a guarded receiver's guard, or only its element's, and its receiver reads as guarded too.
test('binds a parameter passed a guarded receiver with that guard', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostHelperParamsResource::class), Post::class);
    $binder = resolve(ParameterBinder::class);
    $value = ['type' => 'Comment[]', 'optional' => false, 'modelFqcn' => Comment::class];
    $listed = new ReceiverType([EloquentCollection::class], elementModel: Comment::class);

    $binder->bindPassed([
        'guarded' => $binder->passedBinding($value, $listed->asGuarded()),
        'listed' => $binder->passedBinding($value, $listed->asGuardingElement()),
        'plain' => $binder->passedBinding($value, $listed),
    ], $scope);
    $resolved = fn (string $name): ?ReceiverType => resolve(ReceiverClassResolver::class)->resolve(new Variable($name), $scope);

    expect($scope->guardedParameters)->toBe(['guarded' => 'guarded', 'listed' => 'element'])
        ->and([$resolved('guarded')?->guarded, $resolved('guarded')?->guardsElement])->toBe([true, false])
        ->and([$resolved('listed')?->guarded, $resolved('listed')?->guardsElement])->toBe([false, true])
        ->and([$resolved('plain')?->guarded, $resolved('plain')?->guardsElement])->toBe([false, false]);
});

// A declaration binds a guarded argument as one never passed; only an `unread` callback parameter's guard rides along.
test('keeps a declared model binding unguarded but where an unread callback parameter is passed', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostHelperParamsResource::class), Post::class);
    $scope->varModelBindings = ['unread' => Comment::class, 'guarded' => Comment::class];
    $binding = fn (string $guard): array => ['model' => Comment::class, 'classes' => null, 'elementModel' => null, 'value' => null, 'unsaved' => false, 'refines' => true, 'declaredOnly' => false, 'guard' => $guard];

    resolve(ParameterBinder::class)->bindPassed(['unread' => $binding('unread'), 'guarded' => $binding('guarded')], $scope);

    expect($scope->guardedParameters)->toBe(['unread' => 'unread'])
        ->and($scope->varModelBindings)->toBe(['unread' => Comment::class, 'guarded' => Comment::class]);
});

// seedBody() ends each binding where a write replaces it, so a read before the write still has the parameter's value.
test('binds a parameter the body writes by its classes and its value, and a name already bound stays as it is', function () {
    $reflection = new ReflectionClass(HelperParameterKindsResource::class);
    $written = new AnalysisScope($reflection, Post::class);
    $seeded = new AnalysisScope($reflection, Post::class);
    $seeded->varModelBindings['post'] = User::class;

    foreach (['written', 'filled', 'formatted', 'joined'] as $method) {
        resolve(ParameterBinder::class)->bindCall($reflection->getMethod($method), [], $written);
    }

    resolve(ParameterBinder::class)->bindCall($reflection->getMethod('written'), [], $seeded);

    expect($written->varModelBindings)->toBe(['post' => Post::class])
        ->and($written->varDeclaredBindings)->toBe(['p' => [Post::class]])
        ->and(array_map(fn (array $value): string => $value['type'], $written->varValueBindings))
        ->toBe(['p' => 'Post | null', 'amount' => 'number', 'tags' => 'string[]'])
        ->and($seeded->varModelBindings)->toBe(['post' => User::class]);
});

test('binds what a spread call passes: one concrete model by its class, a nullable one and its classes by value', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $nullable = ['type' => 'Post | null', 'optional' => true, 'modelFqcn' => Post::class];

    resolve(ParameterBinder::class)->bindPassed(parameterBinderPassed([
        'post' => ['value' => ['type' => 'Post', 'optional' => false, 'modelFqcn' => Post::class], 'receiver' => ReceiverType::of(Post::class)],
        'maybe' => ['value' => $nullable, 'receiver' => ReceiverType::of(Post::class)],
        'owner' => ['value' => ['type' => 'Post | User', 'optional' => false], 'receiver' => new ReceiverType([Post::class, User::class])],
        'title' => ['value' => ['type' => 'string', 'optional' => false], 'receiver' => null],
        'vague' => ['value' => ['type' => 'unknown', 'optional' => false], 'receiver' => null],
    ]), $scope);

    expect($scope->varModelBindings)->toBe(['post' => Post::class])
        ->and($scope->varDeclaredBindings)->toBe(['maybe' => [Post::class], 'owner' => [Post::class, User::class]])
        ->and($scope->varValueBindings)->toBe([
            'maybe' => [...$nullable, 'optional' => false],
            'owner' => ['type' => 'Post | User', 'optional' => false],
            'title' => ['type' => 'string', 'optional' => false],
        ]);
});

// A `?->` can end the argument as null, so it binds no class. A model no row backs yet keys as `| null`, which a class
// binding drops: it binds as a `new` of its class, which the receiver resolver reads again, unless it may be another.
test('binds a passed model that may short-circuit by its value alone, and one no row backs yet as a new model', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $post = ['type' => 'Post', 'optional' => false, 'modelFqcn' => Post::class];
    $either = ['type' => 'Post | User', 'optional' => false];

    resolve(ParameterBinder::class)->bindPassed(parameterBinderPassed([
        'shortCircuits' => ['value' => $post, 'receiver' => ReceiverType::of(Post::class, shortCircuits: true)],
        'unsaved' => ['value' => $post, 'receiver' => ReceiverType::of(Post::class)->asUnsaved()],
        'copy' => ['value' => $either, 'receiver' => new ReceiverType([Post::class, User::class], unsaved: true)],
    ]), $scope);

    $unsaved = $scope->closureParamExprBindings['unsaved'] ?? null;

    expect($scope->varModelBindings)->toBe([])
        ->and($scope->varDeclaredBindings)->toBe([])
        ->and(array_keys($scope->closureParamExprBindings))->toBe(['unsaved'])
        ->and($unsaved instanceof New_ && $unsaved->class instanceof Name ? $unsaved->class->toString() : null)->toBe(Post::class)
        ->and(resolve(ReceiverClassResolver::class)->resolve(new Variable('unsaved'), $scope)?->unsaved)->toBeTrue()
        ->and($scope->varValueBindings)->toBe(['shortCircuits' => $post, 'unsaved' => $post, 'copy' => $either]);
});

// varCollectionBindings reads as a list, so a collection whose keys a step such as keyBy() moved binds no element
// there: json_encode() writes it as an object.
test('binds a passed Eloquent collection of one model by its element, unless a step moved its keys', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $comments = ['type' => 'Comment[]', 'optional' => false, 'modelFqcn' => Comment::class];

    resolve(ParameterBinder::class)->bindPassed(parameterBinderPassed([
        'listed' => ['value' => $comments, 'receiver' => new ReceiverType([EloquentCollection::class], elementModel: Comment::class)],
        'keyed' => ['value' => $comments, 'receiver' => new ReceiverType([EloquentCollection::class], elementModel: Comment::class, keyed: true)],
    ]), $scope);

    expect($scope->varCollectionBindings)->toBe(['listed' => ['type' => 'Comment[]', 'modelFqcn' => Comment::class]])
        ->and($scope->varDeclaredBindings)->toBe(['keyed' => [EloquentCollection::class]])
        ->and(resolve(ReceiverClassResolver::class)->resolve(new Variable('listed'), $scope)?->elementModel)->toBe(Comment::class)
        ->and(resolve(ReceiverClassResolver::class)->resolve(new Variable('keyed'), $scope)?->elementModel)->toBeNull();
});

/**
 * A post whose comments 5 and 6 are loaded, as DeclaredCollectionParameterResource's helpers read them.
 */
function parameterBinderCommentedPost(): Post
{
    $post = new Post()->forceFill(['id' => 1, 'title' => 'Hello']);
    $post->setRelation('comments', new EloquentCollection([
        new Comment()->forceFill(['id' => 5, 'content' => 'hi', 'post_id' => 1]),
        new Comment()->forceFill(['id' => 6, 'content' => 'yo', 'post_id' => 1]),
    ]));

    return $post;
}

// A docblock holds only what its author wrote, so the collection an argument is known to hold outranks it, its class
// and keys too; the declaration names the element and keys it leaves unknown, so `support_keyed_arg_all`'s `int` key
// lies about the string keys it sends, and narrows a class only a declaration names. No class widens a native one.
test('binds a @param Collection<int, X> parameter by its element, untyped or natively a collection', function () {
    $comment = fn (int $id, string $content): array => ['id' => $id, 'content' => $content, 'post_id' => 1];

    expect(publishedResourceKeyTypes(DeclaredCollectionParameterResource::class, Post::class))->toBe([
        'typed_callable_first?' => '{ first: string | null }',
        'native_callable_first?' => '{ first: string | null }',
        'support_callable_first?' => '{ first: string | null }',
        'closure_first?' => '{ first: string | null }',
        'untyped_arg_first' => '{ first: string | null }',
        'typed_arg_first' => '{ first: string | null }',
        'map_contents?' => 'string[]',
        'string_keyed_all' => 'Record<string, Comment>',
        'either_key_all' => 'Comment[]',
        'keyed_list_all' => 'Comment[] | Record<string, Comment>',
        'lying_post_id' => '{ post_id: number | null }',
        'lying_value' => 'Comment[]',
        'narrowed_subscriber?' => '{ sub: User | null }',
        'support_arg_only' => 'Record<string, unknown>',
        'support_keyed_arg_all' => 'Comment[]',
        'untyped_support_arg_only' => 'Record<string, unknown>',
        'enumerable_arg_only' => 'Comment[]',
        'support_declared_only' => 'Comment[]',
        'enumerable_declared_first' => '{ first: string | null }',
        'string_opaque_value' => 'Record<string, Comment>',
        'eloquent_opaque_value' => 'Comment[]',
        'literal_list_value' => 'Comment[]',
        'string_known_value' => 'Record<string, Comment>',
        'keyed_filter_value' => 'Comment[] | Record<string, Comment>',
        'typed_property_only' => 'Comment[]',
        'var_declared_only' => 'Comment[]',
    ])->and(json_decode((string) json_encode(new DeclaredCollectionParameterResource(parameterBinderCommentedPost())->resolve(request())), true))->toBe([
        'typed_callable_first' => ['first' => 'hi'],
        'native_callable_first' => ['first' => 'hi'],
        'support_callable_first' => ['first' => 'hi'],
        'closure_first' => ['first' => 'hi'],
        'untyped_arg_first' => ['first' => 'hi'],
        'typed_arg_first' => ['first' => 'hi'],
        'map_contents' => ['hi', 'yo'],
        'string_keyed_all' => ['hi' => $comment(5, 'hi'), 'yo' => $comment(6, 'yo')],
        'either_key_all' => [$comment(5, 'hi'), $comment(6, 'yo')],
        'keyed_list_all' => ['hi' => $comment(5, 'hi'), 'yo' => $comment(6, 'yo')],
        'lying_post_id' => ['post_id' => 1],
        'lying_value' => [$comment(5, 'hi'), $comment(6, 'yo')],
        'support_arg_only' => ['1' => $comment(6, 'yo')],
        'support_keyed_arg_all' => ['hi' => $comment(5, 'hi'), 'yo' => $comment(6, 'yo')],
        'untyped_support_arg_only' => ['1' => $comment(6, 'yo')],
        'enumerable_arg_only' => [$comment(5, 'hi')],
        'support_declared_only' => [$comment(5, 'hi')],
        'enumerable_declared_first' => ['first' => 'hi'],
        'string_opaque_value' => ['hi' => $comment(5, 'hi'), 'yo' => $comment(6, 'yo')],
        'eloquent_opaque_value' => [$comment(5, 'hi'), $comment(6, 'yo')],
        'literal_list_value' => [$comment(5, 'hi'), $comment(6, 'yo')],
        'string_known_value' => ['hi' => $comment(5, 'hi'), 'yo' => $comment(6, 'yo')],
        'keyed_filter_value' => ['hi' => $comment(5, 'hi'), 'yo' => $comment(6, 'yo')],
        'typed_property_only' => [$comment(5, 'hi')],
        'var_declared_only' => [$comment(5, 'hi')],
    ]);
});

// A Support collection keeps its class, and only a bare `string` key is the record its all() sends.
test('binds a @param collection generic with its class and kind of key, in place of a native collection class', function (string $method, array $binding, array $keys) {
    $scope = new AnalysisScope(new ReflectionClass(DeclaredCollectionParameterResource::class), Post::class);

    resolve(ParameterBinder::class)->bindCall(new ReflectionMethod(DeclaredCollectionParameterResource::class, $method), [], $scope);
    $held = resolve(ReceiverClassResolver::class)->resolve(new Variable('comments'), $scope);

    expect($scope->varCollectionBindings)->toBe(['comments' => $binding])
        ->and($scope->varDeclaredBindings)->toBe([])
        ->and([$held?->classes, $held?->elementModel, $held?->keyed, $held?->stringKeyed, $held?->guardsElement])
        ->toBe([$binding['classes'], Comment::class, ...$keys, false]);
})->with([
    'untyped' => ['typedFirst', ['type' => 'Comment[]', 'modelFqcn' => Comment::class, 'classes' => [EloquentCollection::class], 'key' => 'int'], [false, false]],
    'natively a collection' => ['nativeFirst', ['type' => 'Comment[]', 'modelFqcn' => Comment::class, 'classes' => [EloquentCollection::class], 'key' => 'int'], [false, false]],
    'a Support collection' => ['supportFirst', ['type' => 'Comment[]', 'modelFqcn' => Comment::class, 'classes' => [Collection::class], 'key' => 'int'], [false, false]],
    'a string key' => ['stringKeyedAll', ['type' => 'Record<string, Comment>', 'modelFqcn' => Comment::class, 'classes' => [EloquentCollection::class], 'key' => 'string'], [true, true]],
    'keys of either kind' => ['eitherKeyAll', ['type' => 'Comment[] | Record<string, Comment>', 'modelFqcn' => Comment::class, 'classes' => [EloquentCollection::class], 'key' => 'array-key'], [true, false]],
]);

// A narrowing argument outranks a `@param` collection, an `unread` one included, so no declared binding or value stays.
test('replaces a declared collection binding with what a narrowing argument binds, guarded or not', function (?string $element, ?string $guard, array $collections, array $declared) {
    $scope = new AnalysisScope(new ReflectionClass(DeclaredCollectionParameterResource::class), Post::class);
    $binder = resolve(ParameterBinder::class);
    $value = ['type' => 'SubscribedTeam[]', 'optional' => false];

    $binder->bindCall(new ReflectionMethod(DeclaredCollectionParameterResource::class, 'teamSubscriber'), [], $scope);
    $binder->bindPassed(['teams' => [
        'model' => null,
        'classes' => [EloquentCollection::class],
        'elementModel' => $element,
        'value' => $value,
        'unsaved' => false,
        'refines' => true,
        'declaredOnly' => false,
        'guard' => $guard,
    ]], $scope);

    expect($scope->varCollectionBindings)->toBe($collections)
        ->and($scope->varDeclaredBindings)->toBe($declared)
        ->and($scope->varValueBindings)->toBe(['teams' => $value])
        ->and($scope->guardedParameters)->toBe($guard === null ? [] : ['teams' => $guard]);
})->with([
    'its element' => [SubscribedTeam::class, null, ['teams' => ['type' => 'SubscribedTeam[]', 'modelFqcn' => SubscribedTeam::class]], []],
    'its element, unread' => [SubscribedTeam::class, 'unread', ['teams' => ['type' => 'SubscribedTeam[]', 'modelFqcn' => SubscribedTeam::class]], []],
    'its class alone, unread' => [null, 'unread', [], ['teams' => [EloquentCollection::class]]],
]);

// A hint the element's model extends keeps the element's model; an abstract or nullable hint names no row's shape,
// and a `self` one may be any subclass. A `@param` names the class where the element names none, and gives way to it.
test('binds a callback parameter to a collection element: its model, a concrete model hint, else its value', function () {
    $scope = new AnalysisScope(new ReflectionClass(SelfTypedHelpers::class), SelfTypedHelpers::class);
    $bound = fn (?string $model, ?array $value, string $closure, bool $unread = false, bool $guardsElement = false): ?array => ParameterBinder::elementBinding(
        $model,
        $value,
        parameterBinderClosure($closure)->params[0] ?? null,
        $scope,
        $unread,
        $guardsElement,
        (string) parameterBinderClosure($closure)->getDocComment(),
    );
    $binding = fn (?string $model, ?array $value = null, bool $relative = false, ?string $guard = null): array => [
        'model' => $model,
        'classes' => null,
        'elementModel' => null,
        'value' => $value,
        'unsaved' => false,
        'refines' => false,
        'declaredOnly' => $relative,
        'guard' => $guard,
    ];
    $title = ['type' => 'string', 'optional' => false];

    expect($bound(Comment::class, null, 'fn ($c) => $c'))->toBe($binding(Comment::class))
        ->and($bound(Comment::class, $title, 'fn () => 1'))->toBe($binding(Comment::class))
        ->and($bound(null, null, 'fn (\\Workbench\\App\\Models\\Post $p) => $p'))->toBe($binding(Post::class))
        ->and($bound(Comment::class, null, 'fn (\\Workbench\\App\\Models\\Post $p) => $p'))->toBe($binding(Post::class))
        ->and($bound(Comment::class, null, 'fn (\\Illuminate\\Database\\Eloquent\\Model $m) => $m'))->toBe($binding(Comment::class))
        ->and($bound(null, null, 'fn (self $m) => $m'))->toBe($binding(SelfTypedHelpers::class, relative: true))
        ->and($bound(null, null, 'fn (\\Illuminate\\Database\\Eloquent\\Model $m) => $m'))->toBeNull()
        ->and($bound(null, null, 'fn (?\\Workbench\\App\\Models\\Post $p) => $p'))->toBeNull()
        ->and($bound(null, $title, 'fn (\\Illuminate\\Database\\Eloquent\\Model $m) => $m'))->toBe($binding(null, $title))
        ->and($bound(null, $title, 'fn ($t) => $t'))->toBe($binding(null, $title))
        ->and($bound(Comment::class, null, 'fn ($c) => $c', unread: true))->toBe($binding(Comment::class, guard: 'unread'))
        ->and($bound(Comment::class, null, 'fn (\\Workbench\\App\\Models\\Comment $c) => $c', unread: true))->toBe($binding(Comment::class, guard: 'unread'))
        ->and($bound(Comment::class, null, 'fn ($c) => $c', guardsElement: true))->toBe($binding(Comment::class, guard: 'guarded'))
        ->and($bound(Comment::class, null, 'fn (\\Workbench\\App\\Models\\Comment $c) => $c', guardsElement: true))->toBe($binding(Comment::class))
        ->and($bound(null, $title, 'fn ($t) => $t', unread: true))->toBe($binding(null, $title))
        ->and($bound(Comment::class, null, '/** @param \\Workbench\\App\\Models\\Post $p */ fn ($p) => $p'))->toBe($binding(Comment::class))
        ->and($bound(null, null, '/** @param \\Workbench\\App\\Models\\Post $p */ fn ($p) => $p'))->toBe($binding(Post::class))
        ->and($bound(null, null, '/** @param \\Workbench\\App\\Models\\Post|null $p */ fn ($p) => $p'))->toBeNull()
        ->and($bound(Comment::class, null, '/** @param \\Workbench\\App\\Models\\Comment $c */ fn ($c) => $c', guardsElement: true))->toBe($binding(Comment::class));
});

test('binds a callback parameter at each position to the value its caller passes there', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $closure = parameterBinderClosure('function ($value, $key) {}');

    ParameterBinder::bindCallbackArgument($closure, 0, ['type' => 'string', 'optional' => false], $scope, parameterBinderEngine($scope));
    ParameterBinder::bindCallbackArgument($closure, 1, ['type' => 'number', 'optional' => false], $scope, parameterBinderEngine($scope));
    ParameterBinder::bindCallbackArgument($closure, 2, ['type' => 'boolean', 'optional' => false], $scope, parameterBinderEngine($scope));

    expect($scope->varValueBindings)->toBe([
        'value' => ['type' => 'string', 'optional' => false],
        'key' => ['type' => 'number', 'optional' => false],
    ])->and($scope->closureParamExprBindings)->toBe([]);
});

// A read binds as itself, so the closure reads it where it reads its own names; a filled argument is never null.
test('binds a passed read as itself, a variadic parameter to the list it collects, and a filled argument non-null', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $closure = parameterBinderClosure('fn ($read, $vague, ...$rest) => $read');
    $read = new PropertyFetch(new Variable('this'), 'title');
    $engine = parameterBinderEngine($scope);

    ParameterBinder::bindCallbackArgument($closure, 0, $read, $scope, $engine, filled: true);
    ParameterBinder::bindCallbackArgument($closure, 1, ['type' => 'unknown', 'optional' => false], $scope, $engine);
    ParameterBinder::bindCallbackArgument($closure, 2, ['type' => 'string | null', 'optional' => true], $scope, $engine, filled: true);

    $nested = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    ParameterBinder::bindCallbackArgument(parameterBinderClosure('fn (...$all) => $all'), 0, $read, $nested, parameterBinderEngine($nested));

    expect($scope->closureParamExprBindings)->toBe(['read' => $read])
        ->and($scope->varValueBindings)->toBe(['rest' => ['type' => 'string[]', 'optional' => false]])
        ->and(array_keys($scope->nonNullReads))->toBe(['read'])
        ->and($nested->varValueBindings)->toBe(['all' => ['type' => 'string[]', 'optional' => false]]);
});

test('a map callback\'s second parameter holds the key its caller passes, and nothing where it passes none', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperParameterKindsResource::class), Post::class);
    $host = new class
    {
        use BindsMapCallbacks;

        /**
         * Resolve a map callback over string elements, passing it $key where one is given.
         *
         * @param  array<string, mixed>|null  $key
         * @return array<string, mixed>|null
         */
        public function mapped(Expr $callback, ?array $key, AnalysisScope $scope, ExpressionEngine $engine): ?array
        {
            return $this->resolveMapCallback($callback, null, ['type' => 'string', 'optional' => false], false, $scope, $engine, $key);
        }
    };
    $closure = parameterBinderClosure('fn ($row, $key) => [\'row\' => $row, \'key\' => $key]');

    expect($host->mapped($closure, ['type' => 'number', 'optional' => false], $scope, parameterBinderEngine($scope))['type'] ?? null)
        ->toBe('{ row: string; key: number }')
        ->and($host->mapped($closure, null, $scope, parameterBinderEngine($scope))['type'] ?? null)
        ->toBe('{ row: string; key: unknown }');
});

test('reads one parameter\'s @phpstan-param, @psalm-param or @param tag, and declines a form the whitelist refuses', function (string $doc, ?string $expected) {
    expect(resolve(PropertyDocblockTypeReader::class)->extractParamTag($doc, 'posts', new ReflectionClass(PostHelperParamsResource::class)))
        ->toBe($expected);
})->with([
    'a list' => ["/**\n * @param  list<Post>  \$posts\n */", 'list<Post>'],
    'another parameter first' => ["/**\n * @param  int  \$limit\n * @param  list<Post>  \$posts  the posts\n */", 'list<Post>'],
    'a bracket list' => ["/**\n * @param  Post[]  \$posts\n */", 'Post[]'],
    'a form the local whitelist declines' => ["/**\n * @param  class-string<Post>  \$posts\n */", null],
    'an alias the file does not define' => ["/**\n * @param  Pair  \$posts\n */", null],
    '@phpstan-param over @param' => ["/**\n * @param  array  \$posts\n * @phpstan-param  list<Post>  \$posts\n */", 'list<Post>'],
    'a variadic' => ["/**\n * @param  Post  ...\$posts\n */", 'Post'],
    'a by-reference parameter' => ["/**\n * @param  Post  &\$posts\n */", 'Post'],
    'a longer name only' => ["/**\n * @param  Post  \$postsById\n */", null],
    'a list shape' => ["/**\n * @param  list{int, string}  \$posts\n */", 'list{int, string}'],
    'a shape holding a callable, which reads unknown by design' => [
        "/**\n * @param  array{fn: callable(int): string}  \$posts\n */",
        'array{fn: callable(int): string}',
    ],
    'a bare callable' => ["/**\n * @param  callable(int): string  \$posts\n */", null],
    'an unsealed list shape' => ["/**\n * @param  list{int, ...}  \$posts\n */", null],
    'no tag' => ['/** Nothing. */', null],
]);

test('reads a @param naming an alias for an untyped or array parameter', function () {
    $sent = json_decode((string) json_encode(LocalAliasResource::make(new Post)->resolve()), true);

    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(LocalAliasResource::class)->properties))->toMatchArray([
        'helper' => '{ p: { a: number; b: string } }',
        'helper_array' => '{ p: { a: number; b: string } }',
        'helper_renamed' => '{ p: { c: boolean; d: number[] } }',
    ])
        ->and($sent['helper'])->toBe(['p' => ['a' => 1, 'b' => 'x']])
        ->and($sent['helper_array'])->toBe(['p' => ['a' => 1, 'b' => 'x']])
        ->and($sent['helper_renamed'])->toBe(['p' => ['c' => true, 'd' => [1]]]);
});

test('reads a list shape @param as the tuple it declares', function () {
    $reader = resolve(PropertyDocblockTypeReader::class);
    $context = new ReflectionClass(PostHelperParamsResource::class);

    expect($reader->readDeclared((string) $reader->extractParamTag("/**\n * @param  list{int, string}  \$posts\n */", 'posts', $context), $context)['type'] ?? null)
        ->toBe('[number, string]');
});

// Past the write a receiver no longer holds the model: the value is a typed column, whose member PHP cannot read.
test('a member read past a model parameter\'s own typed write reads no member of the model', function () {
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);

    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(WrittenModelMemberResource::class, 'toArray', Post::class)->properties))
        ->toBe(['member_past' => 'unknown'])
        ->and(fn () => new WrittenModelMemberResource($post)->resolve(Request::create('/')))
        ->toThrow(ErrorException::class, 'Attempt to read property "title" on string');
});

// Laravel's code and PHP's functions call a callback in coercive mode: a value the type takes stays as it is, an
// int-backed enum's value included, and any other converts to the declared scalar.
test('reads a value as a scalar parameter receives it in coercive mode', function (string $param, array $value, array $read) {
    $closure = new AstParser()->parseSource('<?php fn ('.$param.') => $v;')[0]->expr;

    expect(ParameterBinder::coercedValue($closure->params[0], $value))->toBe($read);
})->with([
    'a string literal kept by string' => ['string $v', ['type' => "'a'", 'optional' => false], ['type' => "'a'", 'optional' => false]],
    'a number converted to string' => ['string $v', ['type' => 'number', 'optional' => false], ['type' => 'string', 'optional' => false]],
    'a string converted to int' => ['int $v', ['type' => 'string', 'optional' => false], ['type' => 'number', 'optional' => false]],
    'a nullable value kept by a nullable type' => ['?int $v', ['type' => 'number | null', 'optional' => false], ['type' => 'number | null', 'optional' => false]],
    'a null kept by a null default' => ['int $v = null', ['type' => 'string | null', 'optional' => false], ['type' => 'number | null', 'optional' => false]],
    'a union converting to each scalar it names' => ['int|string $v', ['type' => 'boolean', 'optional' => false], ['type' => 'number | string', 'optional' => false]],
    'an int-backed enum value kept by int' => ['int $v', ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class], ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]],
    'an int-backed enum value kept beside a converted arm' => ['int $v', ['type' => 'StatusType | string', 'optional' => false, 'directEnumFqcn' => Status::class], ['type' => 'StatusType | number', 'optional' => false, 'directEnumFqcn' => Status::class]],
    'an int-backed enum value converted by string' => ['string $v', ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class], ['type' => 'string', 'optional' => false]],
    'an enum value a union names, kept beside a number' => ['int $v', ['type' => 'StatusType | string', 'optional' => false, 'embeddedEnumFqcns' => [Status::class]], ['type' => 'StatusType | number', 'optional' => false, 'embeddedEnumFqcns' => [Status::class]]],
    'an enum case name kept by string' => ['string $v', ['type' => 'StatusKind', 'optional' => false, 'directEnumFqcn' => Status::class], ['type' => 'StatusKind', 'optional' => false, 'directEnumFqcn' => Status::class]],
    'a class type left as it is' => ['Post $v', ['type' => 'Post', 'optional' => false], ['type' => 'Post', 'optional' => false]],
    'an unknown value left as it is' => ['int $v', ['type' => 'unknown', 'optional' => false], ['type' => 'unknown', 'optional' => false]],
]);

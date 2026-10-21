<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\RequestRoleHelperEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaHelperParamsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperParameterKindsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StaticHelperSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrittenDeclaredParametersResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\HelperModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\TypedParameterMetadataProvider;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use Workbench\App\Events\PostHelperPayloadEvent;
use Workbench\App\Http\Resources\CallableValueResource;
use Workbench\App\Http\Resources\PostHelperParamsResource;
use Workbench\App\Http\Resources\SpreadHelperScopeResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
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
        'status_label' => '{ label: string; value: number }',
        'status_payload' => '{ payload_status: unknown; payload_name: string }',
        'payload_status' => 'StatusType',
        'payload_name' => 'string',
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
        'nullable_model' => '{ nm_title: string | null; nm_post: unknown }',
        'un_id' => 'number',
        'un_owner' => 'Post',
        'union' => '{ un_id: number; un_owner: unknown }',
        'va_posts' => 'Post[]',
        'variadic' => '{ va_posts: unknown }',
        'br_title' => 'string',
        'by_reference' => '{ br_title: string }',
        'df_limit' => 'number',
        'df_title' => 'string | null',
        'defaulted' => '{ df_limit: number; df_title: string | null }',
        'dl_posts' => 'Post[]',
        'docblock_list' => '{ dl_posts: unknown }',
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
test('a helper\'s parameter that declares a class reads its value before its own write, and the written value past it', function () {
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);

    expect(parameterBinderKeyTypes(resolve(AstEngine::class)->analyze(WrittenDeclaredParametersResource::class, 'toArray', Post::class)->properties))
        ->toBe(['title_of' => 'string | null', 'copied' => 'Post | null', 'decoded' => 'unknown', 'views_of' => 'number'])
        ->and(json_decode((string) json_encode(new WrittenDeclaredParametersResource($post)->resolve(Request::create('/'))), true))
        ->toBe(['title_of' => 'Banana', 'copied' => ['id' => 7, 'title' => 'Banana'], 'decoded' => [], 'views_of' => 1]);
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
        ->toBe(['model' => null, 'classes' => null, 'elementModel' => null, 'value' => ['type' => 'string', 'optional' => false], 'unsaved' => false, 'refines' => false]);
});

test('binds a non-null concrete model parameter in varModelBindings, as a route-bound one is', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostHelperParamsResource::class), Post::class);

    resolve(ParameterBinder::class)->bindCall(new ReflectionMethod(PostHelperParamsResource::class, 'card'), [], $scope);

    expect($scope->varModelBindings)->toBe(['post' => Post::class])
        ->and($scope->varValueBindings)->toBe([]);
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

test('reads one parameter\'s @phpstan-param, @psalm-param or @param tag, and declines a form the whitelist refuses', function (string $doc, ?string $expected) {
    expect(resolve(PropertyDocblockTypeReader::class)->extractParamTag($doc, 'posts', new ReflectionClass(PostHelperParamsResource::class)))
        ->toBe($expected);
})->with([
    'a list' => ["/**\n * @param  list<Post>  \$posts\n */", 'list<Post>'],
    'another parameter first' => ["/**\n * @param  int  \$limit\n * @param  list<Post>  \$posts  the posts\n */", 'list<Post>'],
    'a form the local whitelist declines' => ["/**\n * @param  Post[]  \$posts\n */", null],
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

test('reads a list shape @param as the tuple it declares', function () {
    $reader = resolve(PropertyDocblockTypeReader::class);
    $context = new ReflectionClass(PostHelperParamsResource::class);

    expect($reader->readDeclared((string) $reader->extractParamTag("/**\n * @param  list{int, string}  \$posts\n */", 'posts', $context), $context)['type'] ?? null)
        ->toBe('[number, string]');
});

test('captures a callable tag whole, up to the by-reference name after its return type', function () {
    expect(resolve(PropertyDocblockTypeReader::class)->extractVarType('/** @var callable(int): string &$cb */'))
        ->toBe('callable(int): string');
});

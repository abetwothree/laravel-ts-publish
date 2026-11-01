<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\AliasedHelperController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\CallableReturnsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\CollaboratorPropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\DeclinedGuessController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\EloquentStaticsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\EnumUnionPropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\FilteredRelationsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\HelperCallablePropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ImportlessCastController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaInlineTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaResourcePropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaServiceTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\KeyKeepingSortController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\MappedPagePropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\NullableEnumResourceController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\PropsLocalController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ReassignedRouteModelController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ResolvedApiCardController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ResolvedCardController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ResourceExtrasController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\RouteBoundReadsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\SameNamedEnumPairController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\SignedInUserController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\TopLevelKeysController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\TypedActionParametersController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ViewerBadgeController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\WrappedEnumBesideModelController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NoteSignatureCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SignedInViewerResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ViewerCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NameLengthMember;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers\QueryMacroServiceProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\CardPropsBuilder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\QueriedIdStats;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithAmbiguousCastSpelling;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithCastKeyEdges;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithDelegatedProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithSignatureCastSpelling;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithSpreadProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithTagSignatureBranches;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithTierProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithTraitAction;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithUnionProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithValidatedProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\DeclinedGuessMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\FinalTierPropsBuilder;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSignedInUser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\NinthTierPropsBuilder;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\TierPropsBuilder;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Controllers\InertiaEnumCaseController;
use Workbench\App\Http\Controllers\InertiaKeyEdgesController;
use Workbench\App\Http\Controllers\InertiaNamedCollectionsController;
use Workbench\App\Http\Controllers\InertiaPaginationsController;
use Workbench\App\Http\Controllers\InertiaPaginatorKindsController;
use Workbench\App\Http\Controllers\InertiaPreserveKeysController;
use Workbench\App\Http\Controllers\InertiaResourceSharedTemplate;
use Workbench\App\Http\Controllers\InertiaSingleResourceController;
use Workbench\App\Http\Controllers\InertiaTsCastsController;
use Workbench\App\Http\Controllers\InertiaUserShapesController;
use Workbench\App\Http\Controllers\PostInertiaController;
use Workbench\App\Http\Controllers\UiDashboardController;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostStubCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Grade;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;
use Workbench\Database\Factories\UserFactory;

afterEach(function () {
    JsonResource::flushState();
});

// ─── componentToFqn() ─────────────────────────────────────────────

test('converts simple component name to FQN', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('Dashboard'))
        ->toBe('Inertia.Pages.Dashboard');
});

test('converts slash-separated component to dot-separated FQN', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('Settings/General'))
        ->toBe('Inertia.Pages.Settings.General');
});

test('converts kebab-case component segments to StudlyCase', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('settings/two-factor'))
        ->toBe('Inertia.Pages.Settings.TwoFactor');
});

test('converts double-colon separator to dots', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('Admin::Dashboard'))
        ->toBe('Inertia.Pages.Admin.Dashboard');
});

test('treats dots as directory separators like the Inertia view finder', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('PaymentMethods.ManagePaymentMethods'))
        ->toBe('Inertia.Pages.PaymentMethods.ManagePaymentMethods');
});

test('studly-cases every segment of a kebab-case dotted component', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('payment-methods.manage-payment-methods'))
        ->toBe('Inertia.Pages.PaymentMethods.ManagePaymentMethods');
});

test('normalizes a component mixing all three separators', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('Admin::billing.payment-methods/Index'))
        ->toBe('Inertia.Pages.Admin.Billing.PaymentMethods.Index');
});

test('drops empty segments rather than emitting doubled dots', function () {
    expect(new InertiaPageAnalyzer()->componentToFqn('Settings//General'))
        ->toBe('Inertia.Pages.Settings.General')
        ->and(new InertiaPageAnalyzer()->componentToFqn('Settings.'))
        ->toBe('Inertia.Pages.Settings')
        ->and(new InertiaPageAnalyzer()->componentToFqn(''))
        ->toBe('Inertia.Pages');
});

// ─── analyze() prop inference ─────────────────────────────────────

it('types Eloquent finders, collections and paginators from the model their chain is rooted at', function () {
    $show = pageData(InertiaUserShapesController::class.'@show');
    $index = pageData(InertiaUserShapesController::class.'@index');
    $paginated = pageData(PostInertiaController::class.'@index');

    expect($show['pageType'])->toBe('Inertia.SharedData & { post: Post, draft: Post | null }')
        ->and($show['classFqcns'])->toBe([Post::class])
        ->and($index['pageType'])->toBe('Inertia.SharedData & { users: User[], posts: Post[], page: number }')
        ->and($index['classFqcns'])->toBe([User::class, Post::class])
        ->and($paginated['pageType'])->toBe('Inertia.SharedData & { posts: LengthAwarePaginator<Post> }')
        ->and($paginated['externalImports'])->toBe(['@tolki/types' => ['LengthAwarePaginator']]);
});

it('marks the lazy Inertia wrappers optional and keeps the wrapped value type', function () {
    $data = pageData(InertiaUserShapesController::class.'@deferred');

    expect($data['pageType'])->toBe('Inertia.SharedData & { comments?: Comment[], tally?: number }')
        ->and($data['classFqcns'])->toBe([Comment::class]);
});

it('keeps a ternary prop required over plain arms and optional over a lazy one', function () {
    $data = pageData(ControllerWithUnionProps::class.'@index');

    expect($data['pageType'])->toBe('Inertia.SharedData & { y: number | string, z?: number }');
});

// A field absent from the request reads null, which `??` and a short `?:` replace; a direct read, a full ternary and a
// closure keep the field's optional, and so does an optional prop, which `??` never sees as null.
it('drops a form-request field\'s optional across ?? and ?:, and keeps it across a ternary, a closure or an optional prop', function () {
    $data = pageData(ControllerWithValidatedProps::class.'@index');

    expect($data['pageType'])->toBe('Inertia.SharedData & { a: string, b: number, c?: string, d?: string, e: { search: string }, f: string, h: string, i?: string, j?: string }');
});

it('reads compact() keys and array_merge() props as the array literal each is equivalent to', function () {
    $compacted = pageData(InertiaUserShapesController::class.'@compacted');
    $merged = pageData(InertiaUserShapesController::class.'@merged');

    expect($compacted['pageType'])->toBe('Inertia.SharedData & { post: Post, comments: Comment[] }')
        ->and($merged['pageType'])->toBe('Inertia.SharedData & { title: string, extra: boolean }');
});

it('merges a ternary-assigned props array so a key only one arm sets is optional', function () {
    $data = pageData(InertiaUserShapesController::class.'@toggled');

    expect($data['component'])->toBe('UserShapes/Toggled')
        ->and($data['pageType'])->toBe('Inertia.SharedData & { post: Post | null, views?: number }')
        ->and($data['classFqcns'])->toBe([Post::class]);
});

// Each oracle is what Inertia sends for one request; the render call copies the array, so a later write never lands.
it('reads a props local through its writes', function (string $action, string $type, array $queries, array $sent) {
    $props = array_map(function (array $query) use ($action): array {
        $request = Request::create('/', 'GET', $query);

        return (array) inertiaProps(new PropsLocalController()->{$action}($request), $request);
    }, $queries);

    expect(pageData(PropsLocalController::class.'@'.$action)['pageType'])->toBe('Inertia.SharedData & '.$type)
        ->and($props)->toEqual($sent);
})->with([
    'grown' => ['grown', '{ heading: string, total: number }', [[]], [['heading' => 'x', 'total' => 5]]],
    'in a block' => ['inBlock', '{ heading: string, n: number }', [['x' => 1]], [['heading' => 'x', 'n' => 1]]],
    'joined' => [
        'joined',
        '{ heading: string, n?: number }',
        [['full' => 1], []],
        [['heading' => 'full', 'n' => 1], ['heading' => 'short']],
    ],
    'plus' => ['plus', '{ a: number, k: number }', [['x' => 1], []], [['a' => 1, 'k' => 1], ['a' => 2, 'k' => 1]]],
    'plus inside a prop' => [
        'plusProp',
        '{ merged: { a: number; k: number } }',
        [['x' => 1], []],
        [['merged' => (object) ['a' => 1, 'k' => 1]], ['merged' => (object) ['a' => 2, 'k' => 1]]],
    ],
    'plus over a parameter\'s write that reads it' => [
        'plusParamSelf',
        '{ m: { a: number; k: number } }',
        [[]],
        [['m' => (object) ['a' => 1, 'k' => 1]]],
    ],
    'plus over a key the left side may lack' => [
        'plusOptional',
        '{ a: number | string }',
        [['x' => 1], []],
        [['a' => 1], ['a' => 'x']],
    ],
    'a ??= over a key a block may set' => [
        'coalesceAssigned',
        '{ heading: string, a: number | string }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => 'none']],
    ],
    'a ??= over an optional prop' => [
        'coalesceOptionalProp',
        '{ heading: string, a?: number }',
        [[]],
        [['heading' => 'x']],
    ],
    'a ??= over a deferred prop' => [
        'coalesceDeferProp',
        '{ heading: string, a?: number }',
        [[]],
        [['heading' => 'x']],
    ],
    'a ??= over a closure resolving to null' => [
        'coalesceClosureNull',
        '{ heading: string, a: null }',
        [[]],
        [['heading' => 'x', 'a' => null]],
    ],
    'a ??= over a closure that may resolve to null' => [
        'coalesceClosureNullable',
        '{ heading: string, a: number | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => null]],
    ],
    'a ??= over an optional prop that may resolve to null' => [
        'coalesceOptionalNullable',
        '{ heading: string, a?: number | null }',
        [[]],
        [['heading' => 'x']],
    ],
    'a ??= over a deferred prop that may resolve to null' => [
        'coalesceDeferNullable',
        '{ heading: string, a?: number | null }',
        [[]],
        [['heading' => 'x']],
    ],
    'a ??= over an optional prop resolving to null' => [
        'coalesceOptionalOnlyNull',
        '{ heading: string, a?: null }',
        [[]],
        [['heading' => 'x']],
    ],
    'a ??= over a closure a block may set' => [
        'coalesceClosureInBlock',
        '{ heading: string, a: string | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => null], ['heading' => 'x', 'a' => 'none']],
    ],
    'a ??= over a closure or a null' => [
        'coalesceClosureOrNull',
        '{ heading: string, a: number | string }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => 'none']],
    ],
    'repeated ??= over a closure a block may set' => [
        'coalesceClosureRepeated',
        '{ heading: string, a: string | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => null], ['heading' => 'x', 'a' => 'none']],
    ],
    'a ??= over a local holding a closure that may resolve to null' => [
        'coalesceClosureLocal',
        '{ heading: string, a: number | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => null]],
    ],
    'a ??= over a new optional prop that may resolve to null' => [
        'newOptionalProp',
        '{ heading: string, a?: number | null }',
        [['x' => 1], []],
        [['heading' => 'x'], ['heading' => 'x']],
    ],
    'a new optional prop that may resolve to null' => [
        'newOptionalPropPlain',
        '{ heading: string, a?: number | null }',
        [['x' => 1], []],
        [['heading' => 'x'], ['heading' => 'x']],
    ],
    'the literal of that local' => [
        'newOptionalLiteral',
        '{ heading: string, a?: number | null }',
        [['x' => 1], []],
        [['heading' => 'x'], ['heading' => 'x']],
    ],
    'a ??= over ?? over a local holding a closure that may resolve to null' => [
        'coalesceObjectLeft',
        '{ heading: string, a: number | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => null]],
    ],
    '?? over a local holding a closure that may resolve to null' => [
        'coalesceObjectLeftPlain',
        '{ heading: string, a: number | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => null]],
    ],
    'a ??= over a first-class callable resolving to null' => [
        'coalesceFirstClassNullable',
        '{ heading: string, a: string | null }',
        [[]],
        [['heading' => 'x', 'a' => null]],
    ],
    'a ??= over an always prop over a value that may be null' => [
        'coalesceAlwaysNullable',
        '{ heading: string, a: string | null }',
        [[]],
        [['heading' => 'x', 'a' => null]],
    ],
    'a ??= over a closure on either arm of a ternary' => [
        'coalesceTernaryClosures',
        '{ heading: string, a: number | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => null], ['heading' => 'x', 'a' => 1]],
    ],
    'a ??= over a closure or null from a ternary' => [
        'coalesceTernaryClosureOrNull',
        '{ heading: string, a: string | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => null], ['heading' => 'x', 'a' => 'none']],
    ],
    'a ??= over a closure that never resolves to null or null from a ternary' => [
        'coalesceTernaryIntClosureOrNull',
        '{ heading: string, a: number | string }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => 'none']],
    ],
    'a ??= over a local holding a closure or null from a ternary' => [
        'coalesceLocalTernary',
        '{ heading: string, a: string | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => null], ['heading' => 'x', 'a' => 'none']],
    ],
    'a ??= over a request field or a closure past ??' => [
        'coalesceFieldOrClosure',
        '{ heading: string, a: string | null }',
        [['q' => 'w'], []],
        [['heading' => 'x', 'a' => 'w'], ['heading' => 'x', 'a' => null]],
    ],
    'a ??= over a closure on every arm of a match' => [
        'coalesceMatchClosures',
        '{ heading: string, a: number | null }',
        [['k' => 'a'], []],
        [['heading' => 'x', 'a' => null], ['heading' => 'x', 'a' => 1]],
    ],
    'a ??= over true or a closure past ?:' => [
        'coalesceTrueOrClosure',
        '{ heading: string, a: boolean | null }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => true], ['heading' => 'x', 'a' => null]],
    ],
    'a write reading the local itself' => [
        'coalesceSelfRead',
        '{ heading: string, a?: number | string }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => 'none']],
    ],
    'a whole write reading the local it replaces' => [
        'selfReplaced',
        '{ a?: number | string }',
        [['x' => 1], []],
        [['a' => 1], ['a' => 'none']],
    ],
    'a ??= over a key a loop may set to a value no reading types' => [
        'loopedCoalesce',
        '{ heading: string, a: unknown }',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => 'none']],
    ],
    'written after the render call' => ['late', '{ heading: string }', [[]], [['heading' => 'x']]],
    'written in a loop' => [
        'looped',
        '{ heading: string, [key: `k${string}`]: unknown | undefined }',
        [[]],
        [['heading' => 'x', 'k1' => 1, 'k2' => 2]],
    ],
]);

it('throws for the request the in-block props local is never assigned on', function () {
    expect(fn () => new PropsLocalController()->inBlock(Request::create('/')))->toThrow(NotFoundHttpException::class);
});

// The props hold the lazy prop or the closure, which is not null, so `??=` lands its default only past a plain null.
it('sends what a ??= over a lazy prop or callable leaves on the partial reload that asks for it', function (string $action, array $query, array $sent) {
    $request = partialReload('PropsLocal/'.ucfirst($action), 'a');
    $request->query->add($query);

    expect((array) inertiaProps(new PropsLocalController()->{$action}($request), $request))->toBe($sent);
})->with([
    'optional' => ['coalesceOptionalProp', [], ['a' => 1]],
    'deferred' => ['coalesceDeferProp', [], ['a' => 1]],
    'optional, resolving to a number' => ['coalesceOptionalNullable', ['x' => 1], ['a' => 1]],
    'optional, resolving to null' => ['coalesceOptionalNullable', [], ['a' => null]],
    'deferred, resolving to a number' => ['coalesceDeferNullable', ['x' => 1], ['a' => 1]],
    'deferred, resolving to null' => ['coalesceDeferNullable', [], ['a' => null]],
    'optional, only null' => ['coalesceOptionalOnlyNull', [], ['a' => null]],
    'a local closure, resolving to a number' => ['coalesceClosureLocal', ['x' => 1], ['a' => 1]],
    'a local closure, resolving to null' => ['coalesceClosureLocal', [], ['a' => null]],
    'a new optional prop, resolving to a number' => ['newOptionalProp', ['x' => 1], ['a' => 1]],
    'a new optional prop, resolving to null' => ['newOptionalProp', [], ['a' => null]],
    'a new optional prop with no ??=, resolving to null' => ['newOptionalPropPlain', [], ['a' => null]],
    'a new optional prop in a literal, resolving to null' => ['newOptionalLiteral', [], ['a' => null]],
    'a closure past ?? on a local, resolving to a number' => ['coalesceObjectLeft', ['x' => 1], ['a' => 1]],
    'a closure past ?? on a local, resolving to null' => ['coalesceObjectLeft', [], ['a' => null]],
    'a closure past ?? on a local with no ??=, resolving to null' => ['coalesceObjectLeftPlain', [], ['a' => null]],
    'a first-class callable, resolving to null' => ['coalesceFirstClassNullable', [], ['a' => null]],
    'an always prop, resolving to null' => ['coalesceAlwaysNullable', [], ['a' => null]],
    'a ternary of closures, resolving to null' => ['coalesceTernaryClosures', ['x' => 1], ['a' => null]],
    'a ternary of closures, resolving to a number' => ['coalesceTernaryClosures', [], ['a' => 1]],
    'a ternary of a closure, resolving to null' => ['coalesceTernaryClosureOrNull', ['x' => 1], ['a' => null]],
    'a ternary of null, filled' => ['coalesceTernaryClosureOrNull', [], ['a' => 'none']],
    'a ternary of a closure, resolving to a number' => ['coalesceTernaryIntClosureOrNull', ['x' => 1], ['a' => 1]],
    'a ternary of null beside that closure, filled' => ['coalesceTernaryIntClosureOrNull', [], ['a' => 'none']],
    'a local ternary of a closure, resolving to null' => ['coalesceLocalTernary', ['x' => 1], ['a' => null]],
    'a local ternary of null, filled' => ['coalesceLocalTernary', [], ['a' => 'none']],
    'a request field past ??' => ['coalesceFieldOrClosure', ['q' => 'w'], ['a' => 'w']],
    'a closure past ??, resolving to null' => ['coalesceFieldOrClosure', [], ['a' => null]],
    'a match of closures, resolving to null' => ['coalesceMatchClosures', ['k' => 'a'], ['a' => null]],
    'a match of closures, resolving to a number' => ['coalesceMatchClosures', [], ['a' => 1]],
    'true past ?:' => ['coalesceTrueOrClosure', ['x' => 1], ['a' => true]],
    'a closure past ?:, resolving to null' => ['coalesceTrueOrClosure', [], ['a' => null]],
]);

// Inertia calls a closure prop once: it sends a callable that call returns as `{}`, which only `unknown` admits, so
// `??` there reads as it always has (the closure known gap), while a prop object it returns, or an array's callable,
// Inertia resolves too. A `new` of a prop over such a closure keeps the `unknown` it always had.
it('reads ?? over a held callable a called closure returns as before, and a held prop as Inertia resolves it', function (string $action, string $type, array $visits, array $reloads) {
    $send = function (array $query, bool $reload) use ($action): string {
        $request = $reload ? partialReload('CallableReturns/'.ucfirst($action), 'a') : Request::create('/');
        $request->query->add($query);

        return (string) json_encode(inertiaProps(new CallableReturnsController()->{$action}($request), $request));
    };

    expect(pageData(CallableReturnsController::class.'@'.$action)['pageType'])->toBe('Inertia.SharedData & '.$type)
        ->and(array_map(fn (array $query): string => $send($query, false), [['x' => 1], []]))->toBe($visits)
        ->and(array_map(fn (array $query): string => $send($query, true), [['x' => 1], []]))->toBe($reloads);
})->with([
    'an arrow function' => ['arrow', '{ a: number }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'a closure' => ['closure', '{ a: number }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'an always prop over one' => ['always', '{ a: number }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'an optional prop over one' => ['optional', '{ h: string, a?: number }', ['{"h":"x"}', '{"h":"x"}'], ['{"a":{}}', '{"a":{}}']],
    'a local holding one' => ['local', '{ a: number }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'one over a local closure or null' => ['joined', '{ a: number }', ['{"a":5}', '{"a":5}'], ['{"a":5}', '{"a":5}']],
    'one over a first-class callable' => ['firstClass', '{ a: string }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'a props local holding one' => ['propsLocal', '{ h: string, a: number }', ['{"h":"x","a":{}}', '{"h":"x","a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'one over a held prop' => ['heldProp', '{ a: number | null }', ['{"a":1}', '{"a":null}'], ['{"a":1}', '{"a":null}']],
    'one returning an array' => ['inArray', '{ a: { k: number | null } }', ['{"a":{"k":1}}', '{"a":{"k":null}}'], ['{"a":{"k":1}}', '{"a":{"k":null}}']],
    'a new always prop over one' => ['newAlways', '{ a: unknown }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
    'a new optional prop over one' => ['newOptional', '{ h: string, a?: unknown }', ['{"h":"x"}', '{"h":"x"}'], ['{"a":{}}', '{"a":{}}']],
    'a new always prop over one returning a closure' => ['newAlwaysClosure', '{ a: unknown }', ['{"a":{}}', '{"a":{}}'], ['{"a":{}}', '{"a":{}}']],
]);

// Each published type would reject a value the oracle shows: the left side of `+` may hold the right side's key unread,
// a `+=`, a loop's `continue` or a `try` or `switch` keeps a value the walk reads past, and `??` drops an untyped key.
it('reads no props from a local the walk cannot follow', function (string $action, array $queries, array $sent) {
    $props = array_map(function (array $query) use ($action): array {
        $request = Request::create('/', 'GET', $query);

        return (array) inertiaProps(new PropsLocalController()->{$action}($request), $request);
    }, $queries);

    expect(pageData(PropsLocalController::class.'@'.$action)['pageType'])->toBe('Inertia.SharedData')
        ->and($props)->toEqual($sent);
})->with([
    'a callback\'s own local' => ['shadowed', [[]], [['own' => 1]]],
    'a + over a spread it cannot read' => ['opaquePlus', [['k' => 'x']], [['k' => 'x', 'a' => 1]]],
    'a + over an index signature' => ['loopedPlus', [[]], [['heading' => 'x', 'k1' => 1, 'k2' => 2]]],
    'a +=' => [
        'plusAssigned',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1], ['heading' => 'x', 'a' => 'none']],
    ],
    'a continue in a loop' => ['continued', [['x' => 1], []], [['a' => 1], ['a' => 'x']]],
    'a try' => ['tried', [['x' => 1], []], [['a' => 1], ['a' => 'x']]],
    'a switch' => ['switched', [['x' => 1], []], [['heading' => 'x', 'n' => 1], ['heading' => 'x', 'n' => 'two']]],
    'a write reading a key of the local no reading types' => [
        'loopedSelfRead',
        [['x' => 1], []],
        [['heading' => 'x', 'a' => 1, 'b' => 1], ['heading' => 'x', 'b' => 'none']],
    ],
]);

it('reads a props local\'s values as Inertia sends them, as the same literal reads', function () {
    app(Router::class)->get('/props-local/{post}/stamped', [PropsLocalController::class, 'stamped']);
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $card = (object) ['id' => $user->id, 'name' => 'Ada'];
    $sent = inertiaProps(new PropsLocalController()->stamped($post));
    $type = '{ stamp: string, card: { data: UserCardResource }, extra: { data: UserCardResource; meta: number } }';

    expect(pageData(PropsLocalController::class.'@stamped')['pageType'])->toBe('Inertia.SharedData & '.$type)
        ->and(pageData(PropsLocalController::class.'@stampedLiteral')['pageType'])->toBe('Inertia.SharedData & '.$type)
        ->and(new RouteTransformer(PropsLocalController::class)->typeImports)->toBe(['../resources' => ['UserCardResource']])
        ->and($sent->stamp)->toMatch('/^\d{4}-\d{2}-\d{2}T/')
        ->and([$sent->card, $sent->extra])->toEqual([(object) ['data' => $card], (object) ['data' => $card, 'meta' => 1]]);
});

// Both branches name Post, so a merge that appended per occurrence instead of per key would emit
// the FQCN twice and the transformer would import it twice.
it('merges two renders of one component into a single page type and one import per class', function () {
    $data = pageData(InertiaUserShapesController::class.'@branched');

    expect($data['component'])->toBe('UserShapes/Branched')
        ->and($data['pageType'])->toBe('Inertia.SharedData & { post: Post | null, detail?: string }')
        ->and($data['classFqcns'])->toBe([Post::class]);
});

it('falls back to bare SharedData for a render with no props', function () {
    $data = pageData(PostInertiaController::class.'@show');
    $none = pageData(PostInertiaController::class.'@create');

    expect($data['pageType'])->toBe('Inertia.SharedData & { post: Post }')
        ->and($none['pageType'])->toBe('Inertia.SharedData')
        ->and($none['classFqcns'])->toBe([]);
});

it('types the route-bound model parameter and the injected service call', function () {
    $data = pageData(InertiaUserShapesController::class.'@bound');

    expect($data['pageType'])->toBe('Inertia.SharedData & { post: Post, post_id: number, stats: { views: number; likes: number } }');
});

it('types a route-bound model\'s relation reads by the models they load', function () {
    expect(pageData(TypedActionParametersController::class.'@relations')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { author: User, comments: Comment[] }');
});

// `$post->author` reads as the relation's model through a model-bound variable, so `bare` follows it there; a copy of
// the same model, in a branch or not, keeps the parameter's binding.
it('reads a route-bound parameter past its reassignment through what the action assigned', function () {
    expect(pageData(ReassignedRouteModelController::class.'@show')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { title: unknown, name: string, bare: User }')
        ->and(pageData(ReassignedRouteModelController::class.'@branch')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { title: string }')
        ->and(pageData(ReassignedRouteModelController::class.'@fresh')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { title: string }')
        ->and(pageData(ReassignedRouteModelController::class.'@coalesceAssign')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { title: string }')
        ->and(pageData(ReassignedRouteModelController::class.'@compacted')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { post: User }')
        ->and(pageData(ReassignedRouteModelController::class.'@capturedByValue')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { title: string }')
        ->and(pageData(ReassignedRouteModelController::class.'@capturedByReference')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { title: unknown }');
});

// Inertia sends what json_encode() writes, so a list asort() keys out of order arrives as an object.
it('types a list a key-keeping sort leaves out of order as an object of its elements', function () {
    expect(pageData(KeyKeepingSortController::class.'@show')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { tags: Record<number, string>, sorted: string[], member: { x: Record<number, string> } }');
});

// keyBy() keys the rows by id, so Inertia sends an object unless the ids run 0..n-1.
it('a keyed map and an offset read type in a page\'s props', function () {
    expect(pageData(MappedPagePropsController::class.'@show')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { rows: { id: number }[] | Record<string, { id: number }>, a: number }');
});

// Laravel passes null for an optional route model left out of the URL, so a bare read keeps the null arm. An abstract
// `Model` names no class a published file exports, so its bare read publishes unknown, importing nothing.
it('types the props an action reads from its scalar, enum, union and nullable model parameters', function () {
    $show = pageData(TypedActionParametersController::class.'@show');
    $abstract = pageData(TypedActionParametersController::class.'@abstractModel');

    expect($show['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { id: number, status: StatusType, title: string | null, post: Post | null }')
        ->and($show['classFqcns'] ?? null)->toBe([Status::class, Post::class])
        ->and($abstract['pageType'] ?? null)->toBe('Inertia.SharedData & { model: unknown }')
        ->and($abstract['classFqcns'] ?? null)->toBe([])
        ->and(pageData(TypedActionParametersController::class.'@filter')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { term: string | null, key: string | number }');
});

it('types a resource collection from what it wraps', function () {
    $paginated = pageData(InertiaSingleResourceController::class.'@resourcePaginatedCollection');
    $anonymous = pageData(InertiaSingleResourceController::class.'@resourceAnonymousCollection');

    expect($paginated['pageType'])->toBe('Inertia.SharedData & { warehouses: JsonResourcePaginator<WarehouseResource> }')
        ->and($paginated['classFqcns'])->toBe([WarehouseResource::class])
        ->and($paginated['externalImports'])->toBe(['@tolki/types' => ['JsonResourcePaginator']])
        ->and($anonymous['pageType'])->toBe(
            'Inertia.SharedData & { warehouse_get: AnonymousResourceCollection<WarehouseResource>, warehouse_all: AnonymousResourceCollection<WarehouseResource> }'
        );
});

// Inertia sends a resource prop as its toResponse() data, at any depth, so a single resource arrives in its wrap key.
it('types a single resource page prop as what Inertia sends', function () {
    $wrapped = pageData(InertiaSingleResourceController::class.'@resource');
    $shared = pageData(InertiaResourceSharedTemplate::class.'@resource');

    JsonResource::withoutWrapping();

    expect($wrapped['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { warehouse_first: { data: WarehouseResource }, warehouse_find: { data: WarehouseResource } }')
        ->and($wrapped['classFqcns'] ?? null)->toBe([WarehouseResource::class])
        ->and($shared['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { warehouse_first: { data: WarehouseResource }, warehouse_find: { data: WarehouseResource } }')
        ->and(pageData(InertiaSingleResourceController::class.'@resource')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { warehouse_first: WarehouseResource, warehouse_find: WarehouseResource }');
});

it('wraps a resource nested in a prop array, a toResource() call, an enum list and a body without its wrap key', function () {
    $data = pageData(InertiaResourcePropsController::class.'@show');

    expect($data['pageType'] ?? null)->toBe('Inertia.SharedData & { '
        .'built: { data: WarehouseResource }, made: { data: WarehouseResource }, guessed: { data: WarehouseResource }, '
        .'named: { data: WarehouseResource }, nested: { inner: { data: WarehouseResource } }, statuses: { data: AsEnum<typeof Status>[] }, '
        .'custom: { data: ItemsKeyPostCollection }, custom_page: { data: ItemsKeyPostCollection } & ResourcePagination, '
        .'held: DataKeyPostResource }')
        ->and($data['externalImports'] ?? null)->toBe(['@tolki/types' => ['ResourcePagination']]);
});

// Inertia sends each API resource still inside what resolve() returns as its response, in each item of a list too.
// The workbench CommentResource throws once its user is loaded (a closure typed Status returns null), so none renders.
it('keeps the body resolve() returns, and the response a fluent call on a resource hands back', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $item = inertiaProps(Inertia::render('Resources/Chained', ['list' => (new PostCollection(Post::all()))->resolve()]))->list[0];
    $added = WarehouseResource::make(Warehouse::create(['name' => 'Main']))->additional(['meta' => 1]);
    $comment = "Omit<CommentResource, 'author' | 'author_new' | 'author_direct' | 'post' | 'post_new' | 'post_direct'> & { "
        .'author?: { data: UserResource }; author_new?: { data: UserResource }; author_direct: { data: UserResource }; '
        .'post?: { data: PostResource }; post_new?: { data: PostResource }; post_direct: { data: PostResource } }';

    expect(pageData(InertiaResourcePropsController::class.'@chained')['pageType'] ?? null)->toBe('Inertia.SharedData & { '
        .'resolved: WarehouseResource, made_resolved: WarehouseResource, additional: { data: WarehouseResource; meta: number }, '
        ."list: (Omit<PostResource, 'comments_resolved'> & { comments_resolved?: ({$comment})[] })[], body: ItemsKeyPostCollection }")
        ->and([$item->title, $item->comments_resolved])->toBe(['First', []])
        ->and(inertiaProps(Inertia::render('Resources/Chained', ['additional' => $added]))->additional->meta)->toBe(1);
});

it('types props delegated to a collaborator, and reads both inertia() helper forms', function () {
    $delegated = pageData(ControllerWithDelegatedProps::class.'@index');
    $helper = pageData(ControllerWithDelegatedProps::class.'@helper');
    $chain = pageData(ControllerWithDelegatedProps::class.'@helperChain');

    expect($delegated['component'])->toBe('Dashboard/Delegated')
        ->and($delegated['pageType'])->toBe('Inertia.SharedData & { heading: string, total: number }')
        ->and($helper['component'])->toBe('Dashboard/Helper')
        ->and($helper['pageType'])->toBe('Inertia.SharedData & { label: string }')
        ->and($chain['component'])->toBe('Dashboard/HelperChain')
        ->and($chain['pageType'])->toBe('Inertia.SharedData & { label: string }');
});

it('reads a delegated props body as the collaborator\'s class only where that class is final', function () {
    app()->bind(TierPropsBuilder::class, NinthTierPropsBuilder::class);

    // The container may inject a subclass of a class that is not final, whose `static::` write the body only assumes.
    expect(pageData(ControllerWithTierProps::class.'@index')['pageType'])->toBe('Inertia.SharedData & { tier: unknown }')
        ->and(app(TierPropsBuilder::class)->build())->toBe(['tier' => 9])
        ->and(pageData(ControllerWithTierProps::class.'@exact')['pageType'])->toBe('Inertia.SharedData & { tier: string | number }')
        ->and(app(FinalTierPropsBuilder::class)->build())->toBe(['tier' => 9]);
});

it('types an action a trait in another file supplies', function () {
    $data = pageData(ControllerWithTraitAction::class.'@helper');

    expect($data)->not->toBeNull()
        ->and($data['component'])->toBe('Dashboard/Helper')
        ->and($data['pageType'])->toBe('Inertia.SharedData & { label: string }');
});

// Task 32 review, fix round 2: the props array literal passed to Inertia::render() is a whole
// return shape in its own right, so a top-level resource spread inside it must flatten too. Inertia then sends each
// resource still inside what resolve() returned as its response.
it('flattens a top-level resource spread inside the render props array', function () {
    $data = pageData(ControllerWithSpreadProps::class.'@index');
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    expect($data['component'])->toBe('Dashboard/Spread')
        ->and($data['pageType'])->toBe(
            'Inertia.SharedData & { id: number, name: string, email: string, role: AsEnum<typeof Role> | null, '.
            'profile?: Profile | null, posts?: AnonymousResourceCollection<PostResource>, phone?: string | null, '.
            'avatar?: string, posts_count?: number, comments_count?: number, flag: boolean }'
        )
        ->and(inertiaProps(new ControllerWithSpreadProps()->index($user->load('posts')))->posts->data[0]->title)->toBe('First')
        ->and(inertiaProps(new ControllerWithSpreadProps()->index($user->fresh() ?? $user)))->not->toHaveProperty('posts');
});

it('returns null for an action that renders no Inertia response', function () {
    expect(pageData(PostInertiaController::class.'@destroy'))->toBeNull()
        ->and(pageData(PostInertiaController::class.'@missingMethod'))->toBeNull()
        ->and(pageData('NonExistent\\Controller@index'))->toBeNull()
        ->and(pageData('Closure'))->toBeNull();
});

// A class basename colliding with a TypeScript utility head must not be kept alive by the
// `Record<string, X>` a preserve-keys collection renders — that would import a type the page
// never names, which is the TS2304 shape the token gate exists to catch.
it('does not treat a TypeScript utility head as a class the page type names', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        public function expose(string $pageType, string $name): bool
        {
            return $this->typeSpells($pageType, $name);
        }
    };

    $preserveKeys = "Inertia.SharedData & { teams: Omit<JsonResourcePaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> } }";

    expect($analyzer->expose($preserveKeys, 'Record'))->toBeFalse()
        ->and($analyzer->expose($preserveKeys, 'Omit'))->toBeFalse()
        ->and($analyzer->expose($preserveKeys, 'TeamResource'))->toBeTrue()
        ->and($analyzer->expose('Inertia.SharedData & { audit: Record }', 'Record'))->toBeTrue();
});

// ─── analyze() exception boundary ─────────────────────────────────

test('analyze() degrades to null and records a warning when analysis throws', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        protected function analyzerFor(MethodContext $context, AnalysisScope $scope): never
        {
            throw new TypeError('addComponent(): Argument #2 must be of type ArrayType');
        }
    };

    $result = $analyzer->analyze(['uses' => PostInertiaController::class.'@index']);

    expect($result)->toBeNull();

    $warnings = AnalysisWarnings::all();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['subject'])->toBe(PostInertiaController::class.'@index')
        ->and($warnings[0]['message'])->toContain('TypeError');
});

// ─── #[TsCasts] overrides ─────────────────────────────────────────

it('applies #[TsCasts] overrides and their imports', function () {
    $data = pageData(InertiaTsCastsController::class.'@index');

    expect($data['pageType'])->toBe('Inertia.SharedData & { count: string, meta: PageMeta }')
        ->and($data['externalImports'])->toBe(['@workbench/types' => ['PageMeta']]);
});

test('parseTsCastsFromMethod returns empty arrays for a non-existent class', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importPaths: array<string, string>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose('NonExistent\\Controller', 'index');

    expect($result['overrides'])->toBeEmpty()
        ->and($result['importPaths'])->toBeEmpty();
});

test('parseTsCastsFromMethod returns empty arrays when the method has no TsCasts attribute', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importPaths: array<string, string>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose(InertiaPageAnalyzer::class, '__construct');

    expect($result['overrides'])->toBeEmpty()
        ->and($result['importPaths'])->toBeEmpty();
});

test('parseTsCastsFromMethod extracts both the overrides and each one\'s import path', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importPaths: array<string, string>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose(InertiaTsCastsController::class, 'index');

    expect($result['overrides'])->toHaveKey('count', 'string')
        ->and($result['overrides'])->toHaveKey('meta', 'PageMeta')
        ->and($result['importPaths'])->toBe(['meta' => '@workbench/types']);
});

// ─── analyze() paginated Resource::collection() ───────────────────

test('analyze() types Resource::collection($paginator) as JsonResourcePaginator for InertiaNamedCollectionsController@resourceAnonymousPaginated', function () {
    $result = pageData(InertiaNamedCollectionsController::class.'@resourceAnonymousPaginated');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('JsonResourcePaginator<PostResource>')
        ->and($result['pageType'])->not->toContain('AnonymousResourceCollection<unknown>');
});

test('analyze() types Resource::collection($paginator) as JsonResourcePaginator for InertiaSingleResourceController@resourcePaginatedCollection', function () {
    $result = pageData(InertiaSingleResourceController::class.'@resourcePaginatedCollection');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('JsonResourcePaginator<WarehouseResource>')
        ->and($result['pageType'])->not->toContain('AnonymousResourceCollection<unknown>');
});

test('analyze() types Resource::collection($paginator) as JsonResourcePaginator for InertiaResourceSharedTemplate@resourcePaginatedCollection', function () {
    $result = pageData(InertiaResourceSharedTemplate::class.'@resourcePaginatedCollection');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('JsonResourcePaginator<WarehouseResource>')
        ->and($result['pageType'])->not->toContain('AnonymousResourceCollection<unknown>');
});

// ─── analyze() paginator generics ─────────────────────────────────

test('analyze() types the LengthAwarePaginator generic as the model for InertiaPaginationsController@lengthAware', function () {
    $result = pageData(InertiaPaginationsController::class.'@lengthAware');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('LengthAwarePaginator<Post>')
        ->and($result['pageType'])->not->toContain('<number, string>');
});

test('analyze() types the SimplePaginator generic as the model for InertiaPaginationsController@simple', function () {
    $result = pageData(InertiaPaginationsController::class.'@simple');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('SimplePaginator<Post>')
        ->and($result['pageType'])->not->toContain('<number, string>');
});

test('analyze() types the CursorPaginator generic as the model for InertiaPaginationsController@cursor', function () {
    $result = pageData(InertiaPaginationsController::class.'@cursor');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('CursorPaginator<Post>')
        ->and($result['pageType'])->not->toContain('<number, string>');
});

// ─── analyze() paginated resource collections ─────────────────────

test('analyze() intersects a wrapped paginated collection with ResourcePagination', function () {
    $named = pageData(InertiaPreserveKeysController::class.'@namedPaginated');
    $inline = pageData(InertiaPreserveKeysController::class.'@inlinePaginated');

    expect($named['pageType'])->toBe('Inertia.SharedData & { teams: PreserveKeysCollection & ResourcePagination }')
        ->and($named['externalImports'])->toBe(['@tolki/types' => ['ResourcePagination']])
        ->and($inline['pageType'])->toBe('Inertia.SharedData & { teams: PreserveKeysCollection & ResourcePagination }');
});

test('analyze() emits the keyed record shape for a preserve-keys paginated collection', function () {
    $flat = pageData(InertiaPreserveKeysController::class.'@flatPaginated');
    $anonymous = pageData(InertiaPreserveKeysController::class.'@anonymousPaginated');

    expect($flat['pageType'])->toBe(
        "Inertia.SharedData & { teams: Omit<JsonResourcePaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> } }"
    )
        ->and($anonymous['pageType'])->toBe(
            "Inertia.SharedData & { teams: Omit<JsonResourcePaginator<PreserveKeysTeamResource>, 'data'> & { data: Record<string, PreserveKeysTeamResource> } }"
        );
});

test('analyze() types a resource collection over each paginator kind with what that kind sends', function () {
    $named = pageData(InertiaPaginatorKindsController::class.'@named');
    $anonymous = pageData(InertiaPaginatorKindsController::class.'@anonymous');

    expect($named['pageType'] ?? null)->toBe(
        'Inertia.SharedData & { simple: PostCollection & ResourceSimplePagination, cursor: PostCollection & ResourceCursorPagination, '
        .'either: PostCollection & (ResourceSimplePagination | ResourcePagination), sometimes: PostCollection | (PostCollection & ResourcePagination) }'
    )
        ->and($named['externalImports'] ?? null)->toBe(['@tolki/types' => ['ResourceSimplePagination', 'ResourceCursorPagination', 'ResourcePagination']])
        ->and($anonymous['pageType'] ?? null)->toBe(
            'Inertia.SharedData & { simple: JsonResourceSimplePaginator<PostResource>, cursor: JsonResourceCursorPaginator<PostResource>, '
            .'sometimes: AnonymousResourceCollection<PostResource> | JsonResourceSimplePaginator<PostResource> }'
        )
        ->and($anonymous['externalImports'] ?? null)
        ->toBe(['@tolki/types' => ['JsonResourceSimplePaginator', 'JsonResourceCursorPaginator', 'AnonymousResourceCollection']]);
});

test('analyze() types a flat collection over a simple or cursor paginator as that paginator\'s response, keyed when it keeps keys', function () {
    $flat = pageData(InertiaPaginatorKindsController::class.'@flat');

    expect($flat['pageType'] ?? null)->toBe(
        'Inertia.SharedData & { simple: JsonResourceSimplePaginator<PostResource>, cursor: JsonResourceCursorPaginator<PostResource>, '
        ."keyed: Omit<JsonResourceCursorPaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> } }"
    );
})->skip(fn () => ! version_compare(app()->version(), '13', '>='));

test('analyze() types toResourceCollection() as the response of the collection Laravel guesses or is given', function () {
    $guessed = pageData(InertiaPaginatorKindsController::class.'@guessed');

    expect($guessed['pageType'] ?? null)->toBe(
        'Inertia.SharedData & { named: PostCollection & ResourceSimplePagination, anonymous: JsonResourceCursorPaginator<WarehouseResource>, '
        .'explicit: JsonResourcePaginator<WarehouseResource>, unpaged: PostCollection }'
    );
});

// No oracle: over model items Laravel throws, as the guess wraps each model in the collection #[UseResource] names.
it('publishes a declined collection guess on a page as shared data publishes it', function () {
    expect(pageData(DeclinedGuessController::class.'@index')['pageType'])
        ->toBe('Inertia.SharedData & { cogs: '.sharedProps(DeclinedGuessMiddleware::class)['cogs'].' }')
        ->and(sharedProps(DeclinedGuessMiddleware::class)['cogs'])->toBe('InheritedBodyParentCollection[]');
})->skip(
    ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResource'),
    'UseResource attribute requires Laravel 12.29+',
);

// Review Focus 2: the intersection is what Laravel sends only while the stub's own interface carries the `data` key.
test('the stub collection over simplePaginate() publishes its data beside the simple pagination block', function () {
    config()->set('ts-publish.output_to_files', false);

    expect(pageData(InertiaPaginatorKindsController::class.'@stubPage')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { posts: PostStubCollection & ResourceSimplePagination }')
        ->and(resolve(ResourceGenerator::class, ['findable' => PostStubCollection::class])->content)
        ->toContain('data: PostResource[];');
});

// ─── analyze() shared-template isolation ──────────────────────────

test('analyze() isolates props when different methods share the same component name - paginated collection method', function () {
    $result = pageData(InertiaResourceSharedTemplate::class.'@resourcePaginatedCollection');

    // Must only contain the prop declared in this method
    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('warehouses');
});

test('analyze() isolates props when different methods share the same component name - anon collection method', function () {
    $result = pageData(InertiaResourceSharedTemplate::class.'@resourceAnonymousCollection');

    // Must only contain props declared in this method
    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('warehouse_get')
        ->and($result['pageType'])->toContain('warehouse_all')
        ->and($result['pageType'])->not->toContain('warehouses')
        ->and($result['pageType'])->not->toContain('warehouse_first')
        ->and($result['pageType'])->not->toContain('warehouse_find');
});

test('analyze() isolates props when different methods share the same component name - resource method', function () {
    $result = pageData(InertiaResourceSharedTemplate::class.'@resource');

    // Must only contain props declared in this method
    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('warehouse_first')
        ->and($result['pageType'])->toContain('warehouse_find')
        ->and($result['pageType'])->not->toContain('warehouses')
        ->and($result['pageType'])->not->toContain('warehouse_get')
        ->and($result['pageType'])->not->toContain('warehouse_all');
});

// ─── Inertia UI Table props ───────────────────────────────────────

test('analyze() types an Inertia UI Table prop as its TableResource', function () {
    $result = pageData(InertiaTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

test('analyze() types a service-layer Inertia UI Table prop as its TableResource', function () {
    $result = pageData(InertiaTableController::class.'@service');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

test('analyze() resolves the table model from a query() method rather than a $resource property', function () {
    $result = pageData(InertiaTableController::class.'@queryBased');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }');
});

test('analyze() types the table a service-backed table controller\'s index action renders', function () {
    $result = pageData(InertiaServiceTableController::class.'@index');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }');
});

// ─── Sibling actions of a table-bearing controller ────────────────

// These used to lose their page type: the whole file was treated as unanalyzable because deep
// analysis of a table risked autoloading its optional export dependencies. The engine reads the
// props expression instead, so a table-free sibling action is typed like any other.
test('analyze() types a table-free sibling action on a table-bearing controller', function () {
    $service = pageData(InertiaTableController::class.'@serviceCreate');
    $inline = pageData(InertiaInlineTableController::class.'@form');

    expect($service['component'])->toBe('Tables/Create')
        ->and($service['pageType'])->toBe('Inertia.SharedData & { mode: string }')
        ->and($inline['component'])->toBe('Tables/Form')
        ->and($inline['pageType'])->toBe('Inertia.SharedData & { mode: string }');
});

// The literal 'create' the props expression carries infers as `string`, so the narrowed union is
// only reachable through the attribute — the sibling at @serviceCreate above pins the inferred half.
test('analyze() applies the #[TsCasts] override on a sibling of a table action', function () {
    $result = pageData(InertiaTableController::class.'@castedCreate');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe("Inertia.SharedData & { mode: 'create' | 'edit' }");
});

test('analyze() returns null for a non-Inertia action on a table-bearing controller', function () {
    expect(pageData(InertiaServiceTableController::class.'@store'))->toBeNull();
});

describe('a docblock-filled index signature in page props merged from several branches', function () {
    test('keys from another render call join the union', function () {
        expect(pageData(ControllerWithTagSignatureBranches::class.'@show')['pageType'])->toBe(
            'Inertia.SharedData & { [key: `${string}_tag`]: string | number | undefined, id: number, price_tag?: number }',
        );
    });

    test('a resource-typed key from the other ternary arm puts the fill back', function () {
        expect(pageData(ControllerWithTagSignatureBranches::class.'@ternary')['pageType'])->toBe(
            'Inertia.SharedData & { [key: `${string}_tag`]: unknown | undefined, price_tag?: { data: PostResource } }',
        );
    });

    test('a key the controller method\'s #[TsCasts] adds joins the union', function () {
        expect(pageData(ControllerWithTagSignatureBranches::class.'@cast')['pageType'])->toBe(
            'Inertia.SharedData & { [key: `${string}_tag`]: string | boolean | undefined, extra_tag: boolean }',
        );
    });

    // The cast says nothing about optional, so the key only one arm sets keeps its `?`.
    test('a ternary arm\'s key the controller method\'s #[TsCasts] retypes joins with its cast type', function () {
        expect(pageData(ControllerWithTagSignatureBranches::class.'@ternaryCast')['pageType'])->toBe(
            'Inertia.SharedData & { [key: `${string}_tag`]: string | number | undefined, price_tag?: number }',
        );
    });

    test('the union keeps its undefined arm beside a cast that names undefined only in a literal', function () {
        expect(pageData(ControllerWithTagSignatureBranches::class.'@undefinedLiteral')['pageType'])->toBe(
            'Inertia.SharedData & { [key: `${string}_tag`]: string | number | \'undefined\' | undefined, '
            .'price_tag?: number, state_tag: \'undefined\' }',
        );
    });
});

test('a controller method\'s #[TsCasts] key with the backslashes a single-quoted PHP string leaves retypes the signature', function () {
    expect(pageData(ControllerWithSignatureCastSpelling::class.'@show')['pageType'])
        ->toBe('Inertia.SharedData & { [key: `${string}\\\\_cast`]: number, id: number }');
});

test('a controller method\'s cast key that spells more than one signature warns', function () {
    AnalysisWarnings::reset();

    pageData(ControllerWithAmbiguousCastSpelling::class.'@show');

    expect(AnalysisWarnings::all())->toBe([[
        'subject' => ControllerWithAmbiguousCastSpelling::class.'@show',
        'message' => 'The #[TsCasts] key "[key: `${string}\\\\r`]" spells more than one index signature, so it retypes none; cast each by its exact name.',
    ]]);

    AnalysisWarnings::reset();
});

test('a parser override returning the 2.7 shape builds the page and applies its overrides', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, string>} */
        protected function parseTsCastsFromMethod(string $controllerClass, string $methodName): array
        {
            return ['overrides' => ['heading' => 'Headline'], 'importMap' => ['Headline' => '@/types/headline']];
        }
    };

    $data = $analyzer->analyze(['uses' => ControllerWithCastKeyEdges::class.'@numeric']);

    expect($data['pageType'])->toContain('heading: Headline');
});

test('a controller method\'s cast key holding a raw CR retypes the CR signature', function () {
    expect(pageData(ControllerWithSignatureCastSpelling::class.'@rawCr')['pageType'])
        ->toBe('Inertia.SharedData & { [key: `${string}\r`]: number, id: number }');
});

// ─── #[TsCasts] key edges ────────────────────────────────────────

// PropsResolver::resolve() array_merge()s the props over shared data, which renumbers their int keys from 0.
test('a page cast on a numeric key lands on the key Laravel renumbers it to, and warns', function () {
    expect(pageData(ControllerWithCastKeyEdges::class.'@numeric')['pageType'])
        ->toBe('Inertia.SharedData & { heading: string, "0": boolean }')
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => ControllerWithCastKeyEdges::class.'@numeric',
            'message' => 'The key "42" is renumbered to "0" by Laravel at the top level of a page; use a string key.',
        ]]);
});

test('a top-level int key of a page publishes on the key Laravel renumbers it to, and a nested one keeps its own', function () {
    expect(pageData(ControllerWithCastKeyEdges::class.'@numericBody')['pageType'])
        ->toBe('Inertia.SharedData & { heading: string, "0": boolean, codes: { "7": string } }')
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => ControllerWithCastKeyEdges::class.'@numericBody',
            'message' => 'The key "42" is renumbered to "0" by Laravel at the top level of a page; use a string key.',
        ]]);
});

test('a collection keyed by numbers keeps its keys in a page prop', function () {
    expect(pageData(ControllerWithCastKeyEdges::class.'@collectedCodes')['pageType'])
        ->toBe('Inertia.SharedData & { codes: { "200": string; "404": string } }');
});

test('an int key a page cast adds renumbers after the props\' own', function () {
    expect(pageData(ControllerWithCastKeyEdges::class.'@numericCastAdded')['pageType'])
        ->toBe('Inertia.SharedData & { heading: string, "0": boolean, "1": string }')
        ->and(array_column(AnalysisWarnings::all(), 'message'))->toBe([
            'The key "42" is renumbered to "0" by Laravel at the top level of a page; use a string key.',
            'The key "7" is renumbered to "1" by Laravel at the top level of a page; use a string key.',
        ]);
});

test('a losing page cast spelling brings no import', function () {
    $data = pageData(ControllerWithCastKeyEdges::class.'@losing');

    expect($data['pageType'])->toBe('Inertia.SharedData & { [key: `${string}\\\\_cast`]: number, id: number }')
        ->and($data['externalImports'])->toBe([]);
});

it('keeps a page cast\'s import once when only one rendered component returns its key', function () {
    $data = pageData(ControllerWithCastKeyEdges::class.'@twoComponents');

    expect($data['component'])->toBe(['Edges/WithMeta', 'Edges/WithoutMeta'])
        ->and($data['pageType'][0])->toBe('Inertia.SharedData & { meta: PageMeta, id: number }')
        ->and($data['externalImports'])->toBe(['@/types/meta' => ['PageMeta']]);
});

it('honors a page cast\'s optional flag, and keeps a prop\'s own ? under a type-only cast', function () {
    expect(pageData(ControllerWithCastKeyEdges::class.'@optionalCast')['pageType'])
        ->toBe('Inertia.SharedData & { heading?: Heading, id: number, [key: `${string}_note`]: number | undefined }')
        ->and(pageData(ControllerWithCastKeyEdges::class.'@typeOnlyCast')['pageType'])
        ->toBe('Inertia.SharedData & { meta?: PageMeta, note: Note, id: number }');
});

test('a prop typed from a request\'s import-aware cast carries the import', function () {
    $data = pageData(ControllerWithCastKeyEdges::class.'@validated');

    expect($data['pageType'])->toBe('Inertia.SharedData & { attributes?: PostAttributes }')
        ->and($data['externalImports'])->toBe(['@js/types/posts' => ['PostAttributes']]);
});

it('quotes a page prop key that is not an identifier', function () {
    expect(pageData(InertiaKeyEdgesController::class.'@show')['pageType'])
        ->toBe('Inertia.SharedData & { "can-edit": boolean, ok: number }');
});

it('types a bare enum case prop as its enum, and imports it once, beside a case name and a new value', function () {
    $data = pageData(InertiaEnumCaseController::class.'@show');

    expect($data['pageType'])
        ->toBe('Inertia.SharedData & { status: StatusType, kept: StatusType, status_name: StatusKind, nested: { status: StatusType }, cases: StatusType[], reading: '.LaravelTsPublishService::DATE_TIME_OBJECT_TYPE.' }')
        ->and($data['classFqcns'])->toBe([Status::class]);
});

test('the page type prints no ? after a signature, whatever flag reaches it', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /**
         * The type string joined from the members the builder prints.
         *
         * @param  array<string, array{type: string, optional: bool}>  $props
         * @param  array<string, string>  $overrides
         */
        public function build(array $props, array $overrides): string
        {
            return FileImportNames::objectMember(new MethodAnalysis, $this->buildMembersWithOverrides($props, $overrides))['type'];
        }
    };

    expect($analyzer->build(
        ['[key: `${string}_flag`]' => ['type' => 'boolean', 'optional' => true], 'id' => ['type' => 'number', 'optional' => true]],
        ['a-b' => 'string'],
    ))->toBe('{ [key: `${string}_flag`]: boolean, id?: number, "a-b": string }');
});

it("imports a docblock-typed accessor's class and override on a page", function () {
    $data = pageData(UiDashboardController::class.'@index');
    $sent = inertiaProps(new UiDashboardController()->index(request()));

    expect($data['pageType'])->toContain('uploader: User | null, gauge_from_docblock: UiGauge }')
        ->and($data['externalImports']['@js/types/ui'])->toContain('UiGauge')
        ->and($data['externalImports']['@js/types/ui'])->toBe(['UiNode', 'UiGauge', 'UiAmount'])
        ->and(new RouteTransformer(UiDashboardController::class)->typeImports['../../models'])->toBe(['User'])
        ->and($sent->uploader)->toBeNull()
        ->and($sent->gauge_from_docblock)->toBeObject()->kind->toBe('node')
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('types the route-bound reads #86 lists, as Inertia sends them', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id])->fresh();
    Comment::create(['content' => 'alpha', 'post_id' => $post->id, 'user_id' => $author->id]);

    expect(pageData(RouteBoundReadsController::class.'@show')['pageType'])
        ->toBe('Inertia.SharedData & { status: AsEnum<typeof Status>, authorPosts: number, comments: number, fresh: Post | null }')
        ->and($sent = inertiaProps(new RouteBoundReadsController()->show($post)))
        ->and([$sent->status->name, $sent->authorPosts, $sent->comments, $sent->fresh->id])
        ->toBe([$post->status->name, 1, 1, $post->id]);
});

// No JsonResource::filter() runs over a page prop, so Inertia sends the wrap around null as the `[]` resolve() returns.
it('admits the empty list Inertia sends for an EnumResource over a null enum', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $hidden = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id])->fresh();
    $shown = Post::create(['title' => 'Second', 'content' => 'Body', 'user_id' => $author->id, 'visibility' => Visibility::Public])->fresh();

    expect(pageData(NullableEnumResourceController::class.'@show')['pageType'])
        ->toBe('Inertia.SharedData & { visibility: AsEnum<typeof Visibility> | never[] }')
        ->and(inertiaProps(new NullableEnumResourceController()->show($hidden))->visibility)->toBe([])
        ->and(inertiaProps(new NullableEnumResourceController()->show($shown))->visibility->name)->toBe(Visibility::Public->name);
});

// filter() sends a resolved body's own wrap around null as null, but never enters a map() kept as a Collection.
it('admits the empty list a resolved body sends for an EnumResource over a null enum in a map kept as a Collection', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $hidden = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id])->fresh();
    $shown = Post::create(['title' => 'Second', 'content' => 'Body', 'user_id' => $author->id, 'visibility' => Visibility::Public])->fresh();
    $card = fn (Post $post): array => json_decode((string) json_encode(inertiaProps(new NullableEnumResourceController()->resolved($post))->card), true);

    expect(pageData(NullableEnumResourceController::class.'@resolved')['pageType'])
        ->toBe('Inertia.SharedData & { card: Omit<PostVisibilitiesResource, \'visibilities\'> & '
            .'{ visibilities: (AsEnum<typeof Visibility> | never[])[] } }')
        ->and($card($hidden))->toBe(['visibility' => null, 'visibilities' => [[]]])
        ->and([$card($shown)['visibility']['name'], $card($shown)['visibilities'][0]['name']])
        ->toBe([Visibility::Public->name, Visibility::Public->name]);
});

// The `[]` a wrap around null sends is no list, and holds none of the enums a union's wraps take in turn.
it('keeps the empty list of an EnumResource over a null enum beside a bare read of it and beside another wrap', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $hidden = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id])->fresh();
    $shown = Post::create([
        'title' => 'Second', 'content' => 'Body', 'user_id' => $author->id,
        'priority' => Priority::High, 'visibility' => Visibility::Public,
    ])->fresh();
    $sent = fn (string $action, Post $post, array $query): array => json_decode((string) json_encode(inertiaProps(
        new NullableEnumResourceController()->{$action}(Request::create('/', 'GET', $query), $post),
    )), true);
    $names = fn (array $props): array => array_map(fn (mixed $value): mixed => $value['name'] ?? $value, $props);

    expect(pageData(NullableEnumResourceController::class.'@ternaries')['pageType'])
        ->toBe('Inertia.SharedData & { mixed: AsEnum<typeof Priority> | PriorityType | never[] | null, '
            .'nested: AsEnum<typeof Priority> | PriorityType | never[] | null, '
            .'either: AsEnum<typeof Visibility> | never[] | AsEnum<typeof Status> }')
        ->and(pageData(NullableEnumResourceController::class.'@branches')['pageType'])
        ->toBe('Inertia.SharedData & { either: AsEnum<typeof Visibility> | never[] | AsEnum<typeof Status> }')
        ->and($names($sent('ternaries', $hidden, ['c' => '1', 'd' => '1'])))->toBe(['mixed' => [], 'nested' => [], 'either' => []])
        ->and($names($sent('ternaries', $hidden, ['c' => '0'])))->toBe(['mixed' => null, 'nested' => null, 'either' => 'Draft'])
        ->and($names($sent('ternaries', $shown, ['c' => '1', 'd' => '1'])))->toBe(['mixed' => 'High', 'nested' => 'High', 'either' => 'Public'])
        ->and($names($sent('ternaries', $shown, ['c' => '1', 'd' => '0'])))->toBe(['mixed' => 'High', 'nested' => 2, 'either' => 'Public'])
        ->and([$names($sent('branches', $hidden, ['c' => '1'])), $names($sent('branches', $shown, ['c' => '0']))])
        ->toBe([['either' => []], ['either' => 'Draft']]);
});

// A page runs RelationFilterHandler, as shared data does, so a `map` proxy's filter types as the rows it keeps.
it('types the relation filters a page reads, as Inertia sends them', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id])->fresh();
    $comment = Comment::create(['content' => 'alpha', 'post_id' => $post->id, 'user_id' => $author->id]);
    $page = pageData(FilteredRelationsController::class.'@show');
    $sent = inertiaProps(new FilteredRelationsController($post)->show($post));
    $kept = ['id', 'title', 'user_id', 'status', 'published_at', 'metadata', 'rating', 'category', 'options', 'deleted_at',
        'category_id', 'visibility', 'priority', 'word_count', 'reading_time_minutes', 'featured_image_url', 'is_pinned'];

    expect($page['pageType'])->toBe('Inertia.SharedData & { posts: { id: number; title: string }[], '
        .'trimmed: ({ id: number; title: string; user_id: number; status: StatusType; published_at: string | null; '
        .'metadata: unknown[] | null; rating: string | null; category: string; options: Record<string, string> | null; '
        .'deleted_at: string | null; category_id: number | null; visibility: VisibilityType | null; '
        .'priority: PriorityType | null; word_count: number | null; reading_time_minutes: number | null; '
        .'featured_image_url: string | null; is_pinned: boolean })[], '
        .'comments: { id: number; content: string }[], held: { id: number }[] }')
        ->and($page['classFqcns'])->toBe([Status::class, Visibility::class, Priority::class])
        ->and($sent->posts)->toEqual([(object) ['id' => $post->id, 'title' => 'First']])
        ->and(array_keys((array) $sent->trimmed[0]))->toBe($kept)
        ->and($sent->trimmed[0]->status)->toBe(0)
        ->and($sent->comments)->toEqual([(object) ['id' => $comment->id, 'content' => 'alpha']])
        ->and($sent->held)->toEqual([(object) ['id' => $comment->id]]);
});

it('imports a class an import-less page cast spells', function () {
    app(Router::class)->get('/importless-cast/{post}', [ImportlessCastController::class, 'show']);
    $page = pageData(ImportlessCastController::class.'@show');
    $post = (new Post)->forceFill(['title' => 'First']);

    expect($page['pageType'])->toContain('post: Post | null')
        ->and($page['classFqcns'])->toContain(Post::class)
        ->and(new RouteTransformer(ImportlessCastController::class)->typeImports)
        ->toBe(['../../../../../../workbench/app/models' => ['Post']])
        ->and(inertiaProps(new ImportlessCastController()->show($post))->post->title)->toBe('First');
});

it('publishes a props helper\'s own casts inside its prop as the engine publishes them for an API resource', function () {
    $page = pageData(ImportlessCastController::class.'@notes');
    $prop = TsTypeShape::members(substr($page['pageType'], strlen('Inertia.SharedData & ')))[0] ?? null;
    $resource = resolve(AstEngine::class)->analyze(NoteSignatureCastResource::class)->properties;

    expect($prop[1] ?? null)->toBe('{ x_note: string; n: number; [key: `${string}_note`]: number | string | undefined }')
        ->and(TsTypeShape::members($prop[1] ?? ''))
        ->toBe(array_map(fn (array $property): array => [$property['name'], $property['type']], $resource))
        ->and(inertiaProps(new ImportlessCastController()->notes())->notes)->toEqual((object) ['x_note' => 'y', 'n' => 1]);
});

it('imports a class one page cast displaced for another import-less cast that spells it', function () {
    app(Router::class)->get('/importless-cast/{post}/carried', [ImportlessCastController::class, 'carried']);
    $author = (new User)->forceFill(['name' => 'Ada']);
    $request = bindRequestUser($author);
    $sent = inertiaProps(new ImportlessCastController()->carried((new Post)->setRelation('author', $author)), $request);

    expect(pageData(ImportlessCastController::class.'@carried')['classFqcns'])->toBe([User::class])
        ->and(new RouteTransformer(ImportlessCastController::class)->typeImports)
        ->toBe(['../../../../../../workbench/app/models' => ['User']])
        ->and([$sent->meta->author->name, $sent->owner->name])->toBe(['Ada', 'Ada']);
});

it('aliases an import-less page cast\'s class as the key beside it, where another page imports its name', function () {
    app(Router::class)->get('/importless-cast/{post}/module', [ImportlessCastController::class, 'castFromModule']);
    app(Router::class)->get('/importless-cast/{post}/beside', [ImportlessCastController::class, 'castBesideModel']);
    $author = (new User)->forceFill(['name' => 'Ada']);
    $request = bindRequestUser($author);
    $post = (new Post)->setRelation('author', $author);
    $transformer = new RouteTransformer(ImportlessCastController::class);
    $sent = inertiaProps(new ImportlessCastController()->castBesideModel($post), $request);

    expect(array_column($transformer->actions, 'pageType', 'originalMethodName'))->toBe([
        'castFromModule' => 'Inertia.SharedData & { author: User }',
        'castBesideModel' => 'Inertia.SharedData & { owner: WorkbenchUser | null, author: WorkbenchUser }',
    ])->and($transformer->typeImports)->toBe([
        '@js/types/ui' => ['User'],
        '../../../../../../workbench/app/models' => ['User as WorkbenchUser'],
    ])->and([$sent->owner->name, $sent->author->name])->toBe(['Ada', 'Ada'])
        ->and(inertiaProps(new ImportlessCastController()->castFromModule($post))->author->name)->toBe('Ada');
});

it('imports a model one page cast displaced whole for another import-less cast that spells it', function () {
    app(Router::class)->get('/importless-cast/{post}/carried-direct', [ImportlessCastController::class, 'carriedDirect']);
    $author = (new User)->forceFill(['name' => 'Ada']);
    $request = bindRequestUser($author);
    $page = pageData(ImportlessCastController::class.'@carriedDirect');
    $sent = inertiaProps(new ImportlessCastController()->carriedDirect((new Post)->setRelation('author', $author)), $request);

    expect($page['pageType'])->toBe('Inertia.SharedData & { author: Record<string, unknown>, owner: User | null }')
        ->and($page['classFqcns'])->toBe([User::class])
        ->and(new RouteTransformer(ImportlessCastController::class)->typeImports)
        ->toBe(['../../../../../../workbench/app/models' => ['User']])
        ->and([$sent->author->name, $sent->owner->name])->toBe(['Ada', 'Ada']);
});

it('binds a spread helper\'s own cast to the module it imports from, not to the same-named model beside it', function () {
    app(Router::class)->get('/importless-cast/{post}/spread', [ImportlessCastController::class, 'spreadCastFromModule']);
    $post = (new Post)->setRelation('author', (new User)->forceFill(['name' => 'Ada']));
    $transformer = new RouteTransformer(ImportlessCastController::class);
    $sent = inertiaProps(new ImportlessCastController()->spreadCastFromModule($post));

    expect($transformer->actions[0]['pageType'] ?? null)->toBe('Inertia.SharedData & { owner: User, writer: WorkbenchUser }')
        ->and($transformer->typeImports)->toBe([
            '@js/types/ui' => ['User'],
            '../../../../../../workbench/app/models' => ['User as WorkbenchUser'],
        ])
        ->and([$sent->owner->name, $sent->writer->name])->toBe(['Ada', 'Ada']);
});

it('aliases a cast\'s class beside the model in one component branch, whichever branch imports its name first', function () {
    app(Router::class)->get('/importless-cast/{post}/module-first', [ImportlessCastController::class, 'moduleBranchFirst']);
    app(Router::class)->get('/importless-cast/{post}/model-first', [ImportlessCastController::class, 'modelBranchFirst']);
    $author = (new User)->forceFill(['name' => 'Ada']);
    $post = (new Post)->setRelation('author', $author);
    $transformer = new RouteTransformer(ImportlessCastController::class);
    $pages = array_map(
        fn (array $action): array => array_combine((array) $action['component'], (array) $action['pageType']),
        $transformer->actions,
    );
    $request = bindRequestUser($author);
    $model = inertiaProps(new ImportlessCastController()->moduleBranchFirst($post), $request);
    app()->instance('request', $request = Request::create('/', 'GET', ['module' => '1']));
    $module = inertiaProps(new ImportlessCastController()->moduleBranchFirst($post), $request);

    expect(array_column($pages, 'ImportlessCast/ModelBranch'))
        ->toBe(array_fill(0, 2, 'Inertia.SharedData & { writer: WorkbenchUser, note: WorkbenchUser | null }'))
        ->and(array_column($pages, 'ImportlessCast/ModuleBranch'))
        ->toBe(array_fill(0, 2, 'Inertia.SharedData & { owner: User, note: User | null }'))
        ->and($transformer->typeImports)->toBe([
            '@js/types/ui' => ['User'],
            '../../../../../../workbench/app/models' => ['User as WorkbenchUser'],
        ])
        ->and([$model->writer->name, $model->note->name, $module->owner->name, $module->note])
        ->toBe(['Ada', 'Ada', 'Ada', null]);
});

it('spells a wrapped enum\'s const after typeof beside the same-named model, where another page imports that name', function () {
    app(Router::class)->get('/wrapped-enum/module', [WrappedEnumBesideModelController::class, 'module']);
    app(Router::class)->get('/wrapped-enum/{grade}', [WrappedEnumBesideModelController::class, 'page']);
    $transformer = new RouteTransformer(WrappedEnumBesideModelController::class);
    $sent = inertiaProps(new WrappedEnumBesideModelController()->page((new Grade)->forceFill(['subject' => 'Math'])));

    expect(array_column($transformer->actions, 'pageType', 'originalMethodName'))->toBe([
        'module' => 'Inertia.SharedData & { ui: Grade }',
        'page' => 'Inertia.SharedData & { grade: WorkbenchGrade, badge: AsEnum<typeof WorkbenchGrade2> }',
    ])->and($transformer->typeImports)->toBe([
        '@js/types/ui' => ['Grade'],
        '@tolki/ts' => ['AsEnum'],
        '../../../../../../workbench/app/enums' => ['Grade as WorkbenchGrade2'],
        '../../../../../../workbench/app/models' => ['Grade as WorkbenchGrade'],
    ])->and([$sent->grade->subject, $sent->badge->name, $sent->badge->value])->toBe(['Math', 'Pass', 'pass']);
});

it('binds each same-named enum token to its own class on either side of a key the printer renumbers or marks optional', function () {
    app(Router::class)->get('/pair/around', [SameNamedEnumPairController::class, 'aroundPrintedKeys']);
    $transformer = new RouteTransformer(SameNamedEnumPairController::class);

    expect($transformer->actions[0]['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { a: WorkbenchStatusType, "0": string, note?: string, b: CrmStatusType }')
        ->and($transformer->typeImports)->toBe([
            '../../../../../../workbench/app/enums' => ['StatusType as WorkbenchStatusType'],
            '../../../../../../workbench/crm/enums' => ['StatusType as CrmStatusType'],
        ])
        ->and(inertiaProps(new SameNamedEnumPairController()->aroundPrintedKeys()))
        ->toEqual((object) ['a' => 0, '0' => 'seven', 'note' => 'x', 'b' => 'lead']);
});

it('types each arm of an enum-resource ternary as Inertia sends it', function () {
    app(Router::class)->get('/enum-unions', [EnumUnionPropsController::class, 'show']);
    [$picked, $other] = array_map(function (int $c): stdClass {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaProps(new EnumUnionPropsController()->show($request), $request);
    }, [1, 0]);

    expect(pageData(EnumUnionPropsController::class.'@show')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { either: AsEnum<typeof Role> | AsEnum<typeof Status>, mixed: AsEnum<typeof Status> | StatusType }')
        ->and(new RouteTransformer(EnumUnionPropsController::class)->typeImports)->toBe([
            '@tolki/ts' => ['AsEnum'],
            '../../../../../../workbench/app/enums' => ['Role', 'Status', 'StatusType'],
        ])
        ->and([$picked->either->name, $picked->mixed->name, $other->either->name, $other->mixed])
        ->toBe([Role::Admin->name, Status::Draft->name, Status::Draft->name, Status::Published->value]);
});

// No arm record tells a collection wrap from one beside a make(), so `mixed` keeps the wrapped case either may send.
it('keeps the wrap key of each enum-resource collection arm of a ternary', function () {
    [$picked, $other] = array_map(function (int $c): stdClass {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaProps(new EnumUnionPropsController()->collections($request), $request);
    }, [1, 0]);

    expect(pageData(EnumUnionPropsController::class.'@collections')['pageType'] ?? null)->toBe('Inertia.SharedData & { '
        .'either: { data: AsEnum<typeof Role>[] } | { data: AsEnum<typeof Status>[] }, '
        .'mixed: AsEnum<typeof Status> | { data: AsEnum<typeof Status>[] } | StatusType }')
        ->and([$picked->either->data[0]->name, $picked->mixed->data[0]->name, $other->either->data[0]->name, $other->mixed])
        ->toBe([Role::Admin->name, Status::Draft->name, Status::Draft->name, Status::Published->value]);
});

it('keeps a wrapped case beside an enum-resource collection and a bare case of its enum', function () {
    [$made, $collected, $bare] = array_map(function (int $n): stdClass {
        $request = Request::create('/', 'GET', ['n' => $n]);

        return inertiaProps(new EnumUnionPropsController()->threeArms($request), $request);
    }, [1, 2, 3]);

    expect(pageData(EnumUnionPropsController::class.'@threeArms')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { status: AsEnum<typeof Status> | StatusType | { data: AsEnum<typeof Status>[] } }')
        ->and([$made->status->name, $collected->status->data[0]->name, $bare->status])
        ->toBe([Status::Draft->name, Status::Draft->name, Status::Published->value]);
});

it('types a short ternary that checks an enum resource as that resource alone', function () {
    expect(pageData(EnumUnionPropsController::class.'@elvis')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { status: AsEnum<typeof Status> }')
        ->and(inertiaProps(new EnumUnionPropsController()->elvis())->status->name)->toBe(Status::Draft->name);
});

// The engine cannot type `$request->enum()`, so that arm drops as any untypable union arm does (see the known gaps).
it('keeps a short ternary\'s other arm where the enum resource it checks cannot be typed', function () {
    [$picked, $absent] = array_map(function (array $query): stdClass {
        $request = Request::create('/', 'GET', $query);

        return inertiaProps(new EnumUnionPropsController()->untypedElvis($request), $request);
    }, [['s' => 1], []]);

    expect(pageData(EnumUnionPropsController::class.'@untypedElvis')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { orNull: null, orCase: StatusType }')
        ->and([$picked->orNull->name, $picked->orCase->name, $absent->orNull, $absent->orCase])
        ->toBe([Status::Published->name, Status::Published->name, [], []]);
});

// A helper's body is read as Inertia sends it, unfiltered, so the other arm never runs. Its wrap around null sends
// `[]`, which this type does not admit, as the `| null` the helper's own reading keeps does not.
it('types a short ternary a props helper returns over an enum resource as that resource alone', function () {
    [$picked, $absent] = array_map(function (array $query): stdClass {
        $request = Request::create('/', 'GET', $query);

        return inertiaProps(new EnumUnionPropsController()->helperElvis($request), $request);
    }, [['s' => 1], []]);

    expect(pageData(EnumUnionPropsController::class.'@helperElvis')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { status: AsEnum<typeof Status> }')
        ->and([$picked->status->name, $absent->status])->toBe([Status::Published->name, []]);
});

// The helper's own reading and its reading under the resource's filter() are two memo entries, so neither order of the
// two reads lends the other its answer.
it('reads a helper beside a resolved resource whose body reads it, each as Inertia sends it', function (string $action) {
    [$picked, $absent] = array_map(function (array $query) use ($action): stdClass {
        $request = Request::create('/', 'GET', $query);
        app()->instance('request', $request);

        return inertiaProps(new EnumUnionPropsController()->{$action}($request), $request);
    }, [['s' => 1], []]);
    $page = pageData(EnumUnionPropsController::class.'@'.$action)['pageType'] ?? '';
    $types = array_column(TsTypeShape::members(substr($page, strlen('Inertia.SharedData & '))) ?? [], 1, 0);

    expect([$types['a'] ?? null, $types['helperTryFrom'] ?? null])->toBe(['AsEnum<typeof Status>', 'AsEnum<typeof Status> | null'])
        ->and([$picked->a->name, $picked->helperTryFrom->name])->toBe([Status::Published->name, Status::Published->name])
        ->and([$absent->a, $absent->helperTryFrom])->toBe([[], null]);
})->with(['helperBesideSpread', 'spreadBesideHelper']);

it('reads a props helper a trait supplies under an alias, called or spread, as under its own name', function () {
    $sent = inertiaProps(new AliasedHelperController()->index());
    $shape = '{ tk: number; tn: string }';

    expect(pageData(AliasedHelperController::class.'@index')['pageType'] ?? null)
        ->toBe("Inertia.SharedData & { meta: {$shape}, spread: {$shape}, guarded: {$shape}, base: {$shape} }")
        ->and(json_decode((string) json_encode($sent), true))->toBe(array_fill_keys(
            ['meta', 'spread', 'guarded', 'base'],
            ['tk' => 1, 'tn' => 'x'],
        ));
});

it('reads a first-class callable in props a helper builds as Inertia calls it', function () {
    expect(pageData(HelperCallablePropsController::class.'@show')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { meta: { stamp: string } }')
        ->and(AnalysisWarnings::all())->toBe([])
        ->and(inertiaProps(new HelperCallablePropsController()->show())->meta->stamp)->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

it('reads props a collaborator builds as Inertia sends them', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = inertiaProps(new CollaboratorPropsController(new CardPropsBuilder)->show($user));

    expect(pageData(CollaboratorPropsController::class.'@show')['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { stamp: string, card: { data: UserCardResource } }')
        ->and(AnalysisWarnings::all())->toBe([])
        ->and($sent->stamp)->toMatch('/^\d{4}-\d{2}-\d{2}T/')
        ->and([$sent->card->data->id, $sent->card->data->name])->toBe([$user->id, 'Ada']);
});

// The same helper read for anything but Inertia keeps the Closure json_encode() writes as `{}`.
it('keeps a helper read outside Inertia as it was', function () {
    expect(array_column(resolve(AstEngine::class)->analyzeMethod(HelperCallablePropsController::class, 'meta')->properties, 'type', 'name'))
        ->toBe(['stamp' => 'Record<string, never>']);
});

// Inertia sends each API resource still inside the keys resolve() returns as its response.
it('sends the resources a resolved resource prop holds as their responses', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $sent = inertiaProps(new ResolvedCardController()->show($post));

    expect(pageData(ResolvedCardController::class.'@show')['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<PostCardResource, 'author'> & { author: { data: UserCardResource } } }")
        ->and(resourceProps(PostCardResource::class)['author'])->toBe('UserCardResource')
        ->and([$sent->card->title, $sent->card->author->data->id])->toBe(['First', $user->id])
        ->and(pageData(ResolvedCardController::class.'@plain')['pageType'])->toBe('Inertia.SharedData & { card: UserCardResource }')
        ->and(inertiaProps(new ResolvedCardController()->plain($user))->card)->toEqual((object) ['id' => $user->id, 'name' => 'Ada']);
});

it('sends a resource built by new and resolved as one built by make()', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    expect(pageData(ResolvedCardController::class.'@built')['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<PostCardResource, 'author'> & { author: { data: UserCardResource } } }")
        ->and(inertiaProps(new ResolvedCardController()->built($post))->card->author->data->name)->toBe('Ada');
});

it('sends the resources each item of a resolved resource collection holds as their responses', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    expect(pageData(ResolvedCardController::class.'@index')['pageType'])
        ->toBe("Inertia.SharedData & { cards: (Omit<PostCardResource, 'author'> & { author: { data: UserCardResource } })[] }")
        ->and(inertiaProps(new ResolvedCardController()->index())->cards[0]->author->data->name)->toBe('Ada');
});

it('sends the resources each item of a resolved key-preserving collection holds as their responses', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    (new Team)->forceFill(['name' => 'Core', 'slug' => 'core', 'owner_id' => $user->id])->save();
    $sent = inertiaProps(new ResolvedCardController()->teams())->teams;
    $team = "Omit<TeamResource, 'owner' | 'members'> & { owner?: { data: UserResource }; members?: AnonymousResourceCollection<TeamMemberResource> }";

    expect(pageData(ResolvedCardController::class.'@teams')['pageType'])
        ->toBe("Inertia.SharedData & { teams: Record<string, {$team}> }")
        ->and([$sent->core->name, $sent->core->owner->data->name])->toBe(['Core', 'Ada']);
});

// resolve() runs the resource's filter(), which sends a nested resource built around null as null.
it('keeps the null a resolved resource sends for a nested resource around null', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $guest = inertiaProps(new ResolvedCardController()->viewer($post));
    auth()->setUser($user);
    $member = inertiaProps(new ResolvedCardController()->viewer($post));

    expect(pageData(ResolvedCardController::class.'@viewer')['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<ViewerCardResource, 'viewer'> & { viewer: { data: UserCardResource } | null } }")
        ->and(resourceProps(ViewerCardResource::class)['viewer'])->toBe('UserCardResource | null')
        ->and([$guest->card->viewer, $member->card->viewer->data->name])->toBe([null, 'Ada']);
});

// The resource's filter() reaches what a service it calls returns, so the card built there around a guest is null too,
// but never enters a map() kept as a Collection, whose cards Inertia sends as their responses.
it('keeps the null a resolved resource sends for a nested resource a service builds around null', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $guest = inertiaProps(new ResolvedCardController()->helperViewer($post));
    auth()->setUser($user);
    $member = inertiaProps(new ResolvedCardController()->helperViewer($post));
    $sent = '{ viewer: { data: UserCardResource } | null; authors: { data: UserCardResource }[] }';

    expect(pageData(ResolvedCardController::class.'@helperViewer')['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<HelperViewerCardResource, 'viewer' | 'authors'> & {$sent} }")
        ->and([$guest->card->viewer, $member->card->viewer->data->name])->toBe([null, 'Ada'])
        ->and([$guest->card->authors[0]->data->name, $member->card->authors[0]->data->name])->toBe(['Ada', 'Ada']);
});

// make() is `new static`, so a factory's self::make() and static::make() are sent as the response a named make() is.
it('sends a resource a self::make() or static::make() factory builds as its response', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $guest = inertiaProps(new ViewerBadgeController()->page());
    auth()->setUser($user);
    $member = inertiaProps(new ViewerBadgeController()->page());

    expect(pageData(ViewerBadgeController::class.'@page')['pageType'])
        ->toBe('Inertia.SharedData & { selfMake: { data: ViewerBadgeResource }, staticMake: { data: ViewerBadgeResource } }')
        ->and([$guest->selfMake->data->id, $guest->staticMake->data->id])->toBe([null, null])
        ->and([$member->selfMake->data->id, $member->staticMake->data->id])->toBe([$user->id, $user->id]);
});

// filter() sends the factory's badge around a guest as null, but never enters the map() kept as a Collection.
it('sends a factory-built resource in a resolved body as filter() and Inertia send it', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $guest = inertiaProps(new ViewerBadgeController()->resolved($post))->card;
    auth()->setUser($user);
    $member = inertiaProps(new ViewerBadgeController()->resolved($post))->card;
    $sent = '{ badges: { data: ViewerBadgeResource }[]; badge: { data: ViewerBadgeResource } | null }';

    expect(pageData(ViewerBadgeController::class.'@resolved')['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<ViewerBadgeListResource, 'badges' | 'badge'> & {$sent} }")
        ->and([$guest->badges[0]->data->id, $guest->badge])->toBe([null, null])
        ->and([$member->badges[0]->data->id, $member->badge->data->id])->toBe([$user->id, $user->id]);
});

it('keeps the null a spread resolved resource sends for a nested resource around null', function () {
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret'])->id]);

    expect(pageData(ResolvedCardController::class.'@viewerSpread')['pageType'])
        ->toBe('Inertia.SharedData & { id: number, viewer: { data: UserCardResource } | null }')
        ->and(inertiaProps(new ResolvedCardController()->viewerSpread($post))->viewer)->toBeNull();
});

// A JSON:API resource object's attributes reach Inertia as a stdClass its PropsResolver does not walk, so json_encode()
// sends the API resources in them bare.
it('keeps a resolved JSON:API resource collection as its resource objects', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

    expect(pageData(ResolvedApiCardController::class.'@index')['pageType'])
        ->toBe('Inertia.SharedData & { cards: CardAttributesApiResource[] }')
        ->and(inertiaProps(new ResolvedApiCardController()->index())->cards[0]->attributes->author)
        ->toEqual((object) ['id' => $user->id, 'name' => 'Ada']);
})->skip(fn (): bool => ! JsonApiResources::shipped(), 'JsonApiResource ships from Laravel 12.45.');

// A cast is the type its key publishes wherever the resource is read, and the resource's own file imports its names.
it('keeps the keys a resolved resource\'s casts retype as the resource publishes them', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $card = inertiaProps(new ResolvedCardController()->cast(Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id])))->card;
    $page = pageData(ResolvedCardController::class.'@cast');

    expect([$page['pageType'], $page['externalImports']])
        ->toBe(["Inertia.SharedData & { card: Omit<CastCardResource, 'writer'> & { writer: { data: UserCardResource } } }", []])
        ->and(resourceProps(CastCardResource::class))
        ->toBe(['author' => 'Record<string, unknown>', 'editor' => 'EditorCard', 'writer' => 'UserCardResource'])
        ->and([$card->author->data->name, $card->editor->data->name, $card->writer->data->name])->toBe(['Ada', 'Ada', 'Ada']);
});

// The guard: under resolve()'s filter() a short ternary keeps its null arm, so only the keys holding a resource move.
it('keeps a resolved resource\'s short ternaries over a resource around null', function () {
    $user = (new User)->forceFill(['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.com']);
    $filled = Request::create('/', 'GET', ['s' => 1]);
    $filled->merge(['u' => $user]);
    app()->instance('request', $filled);
    $full = inertiaProps(new ResolvedCardController()->elvis(), $filled)->card;
    app()->instance('request', $empty = Request::create('/'));
    $none = inertiaProps(new ResolvedCardController()->elvis(), $empty)->card;
    $sent = '{ data: UserResource } | null';

    expect(pageData(ResolvedCardController::class.'@elvis')['pageType'])->toBe('Inertia.SharedData & { card: '
        ."Omit<CheckedResourceElvisResource, 'user' | 'helperUser' | 'mwUser'> & { user: {$sent}; helperUser: {$sent}; mwUser: {$sent} } }")
        ->and([$full->tryFrom->name, $full->user->data->name, $full->helperUser->data->name, $full->mwUser->data->name])
        ->toBe([Status::Published->name, 'Ada', 'Ada', 'Ada'])
        ->and([$none->tryFrom, $none->helperTryFrom, $none->user, $none->helperUser, $none->mwUser])
        ->toBe([null, null, null, null, null]);
});

it('imports the resources a resolved resource prop restates', function () {
    app(Router::class)->get('/resolved-card/{post}', [ResolvedCardController::class, 'show']);

    expect(new RouteTransformer(ResolvedCardController::class)->typeImports)
        ->toBe(['../resources' => ['PostCardResource', 'UserCardResource']]);
});

// Inertia sends a resource prop as its whole response, so the keys additional() and with() add arrive beside data.
it('publishes the keys additional() and with() add beside a resource prop\'s data', function () {
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret'])->id]);
    $sent = inertiaProps(new ResourceExtrasController()->show($post));

    expect(pageData(ResourceExtrasController::class.'@show')['pageType'])->toBe('Inertia.SharedData & { '
        .'one: { data: UserCardResource; meta: { n: number } }, '
        .'many: AnonymousResourceCollection<UserCardResource> & { meta: { n: number } }, '
        .'withed: { data: WithMetaResource; meta: { v: number } } }')
        ->and([$sent->one->data->name, $sent->one->meta->n, $sent->many->data[0]->name, $sent->many->meta->n])
        ->toBe(['Ada', 1, 'Ada', 1])
        ->and([$sent->withed->data->title, $sent->withed->meta->v])->toBe(['First', 1]);
});

// With no wrap key, Laravel's ResourceResponse::wrap() nests the body under `data` once a key is sent beside it.
it('nests a resource prop\'s body under data beside its added keys after JsonResource::withoutWrapping()', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    JsonResource::withoutWrapping();
    $sent = inertiaProps(new ResourceExtrasController()->show($post));

    expect(pageData(ResourceExtrasController::class.'@show')['pageType'])->toBe('Inertia.SharedData & { '
        .'one: { data: UserCardResource; meta: { n: number } }, '
        .'many: { data: UserCardResource[]; meta: { n: number } }, '
        .'withed: { data: WithMetaResource; meta: { v: number } } }')
        ->and([$sent->one->data->name, $sent->many->data[0]->name, $sent->withed->data->title])->toBe(['Ada', 'Ada', 'First'])
        ->and(pageData(ResourceExtrasController::class.'@plain')['pageType'])->toBe('Inertia.SharedData & { card: UserCardResource }')
        ->and(inertiaProps(new ResourceExtrasController()->plain($post))->card)->toEqual((object) ['id' => $user->id, 'name' => 'Ada'])
        ->and(pageData(ResourceExtrasController::class.'@maybe')['pageType'])
        ->toBe('Inertia.SharedData & { card: UserCardResource | { data: UserCardResource } }')
        ->and(inertiaProps(new ResourceExtrasController()->maybe(Request::create('/'), $post))->card->name)->toBe('Ada')
        ->and(inertiaProps(new ResourceExtrasController()->maybe(Request::create('/', 'GET', ['meta' => 1]), $post))->card->data->name)
        ->toBe('Ada');
});

// Those keys reach Inertia already encoded, so a resource among them is sent bare and a callable as `[]`; a later
// additional() replaces them, and array_merge_recursive() merges a key with() adds too: two objects, or else a list.
it('types the keys added beside a resource prop\'s data as json_encode() sends them', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = inertiaProps(new ResourceExtrasController()->encoded(Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id])));

    expect(pageData(ResourceExtrasController::class.'@encoded')['pageType'])->toBe('Inertia.SharedData & { '
        .'card: { data: UserCardResource; author: UserCardResource; stamp: never[] }, '
        .'replaced: { data: UserCardResource; b: string }, '
        .'merged: { data: WithMetaResource; meta: { v: number; n: number } }, '
        .'clashed: { data: WithMetaResource; meta: unknown } }')
        ->and([$sent->card->author, $sent->card->stamp])->toEqual([(object) ['id' => $user->id, 'name' => 'Ada'], []])
        ->and(array_keys((array) $sent->replaced))->toBe(['data', 'b'])
        ->and($sent->merged->meta)->toEqual((object) ['v' => 1, 'n' => 1])
        ->and((array) $sent->clashed->meta)->toEqual(['v' => 1, '0' => 2]);
});

// Over a paginator Laravel merges the added keys into the pagination block with array_merge_recursive().
it('publishes the keys a collection\'s $with default and an additional() call add beside its data', function () {
    User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $page = pageData(ResourceExtrasController::class.'@collections');
    $sent = inertiaProps(new ResourceExtrasController()->collections());

    expect($page['pageType'])->toBe('Inertia.SharedData & { '
        .'versioned: VersionedUserCardCollection & { version: string }, '
        .'paged: JsonResourcePaginator<UserCardResource> & { meta: { n: number } } }')
        ->and($page['externalImports'])->toBe(['@tolki/types' => ['JsonResourcePaginator']])
        ->and([$sent->versioned->data[0]->name, $sent->versioned->version])->toBe(['Ada', '1.0'])
        ->and([$sent->paged->data[0]->name, $sent->paged->meta->n, $sent->paged->meta->total])->toBe(['Ada', 1, 1]);
});

// Laravel merges the added keys into the response once with array_merge_recursive(), so a key the response holds
// already is sent as a list unless both values are arrays, whatever type the response publishes.
it('merges a key added beside a collection\'s data with the one its response holds, wrapped or not', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $card = (object) ['id' => $user->id, 'name' => 'Ada'];
    $wrapped = pageData(ResourceExtrasController::class.'@collisions')['pageType'];
    $sent = inertiaProps(new ResourceExtrasController()->collisions());
    JsonResource::withoutWrapping();
    $bare = inertiaProps(new ResourceExtrasController()->collisions());

    expect($wrapped)->toBe('Inertia.SharedData & { '
        .'versioned: VersionedUserCardCollection & { version: unknown }, '
        ."listed: Omit<AnonymousResourceCollection<UserCardResource>, 'data'> & { data: unknown } }")
        ->and(pageData(ResourceExtrasController::class.'@collisions')['pageType'])->toBe('Inertia.SharedData & { '
            .'versioned: { data: VersionedUserCardCollection; version: unknown }, listed: { data: unknown } }')
        ->and([$sent->versioned->version, $sent->listed->data])->toEqual([['1.0', '2.0'], [$card, 1]])
        ->and([$bare->versioned->version, $bare->listed->data])->toEqual([['1.0', '2.0'], [$card, 1]]);
});

// Keys no read types may be any key, so each key beside them may collide, and Laravel sends a collision as a list.
it('types every key beside keys no read types unknown, wrapped or not', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $wrapped = pageData(ResourceExtrasController::class.'@loose')['pageType'];
    $sent = inertiaProps(new ResourceExtrasController()->loose(Request::create('/', 'GET', ['v' => 1]), $post));
    JsonResource::withoutWrapping();
    $bare = inertiaProps(new ResourceExtrasController()->loose(Request::create('/'), $post));

    expect($wrapped)->toBe('Inertia.SharedData & { card: { data: LooseWithResource; meta: unknown; n: unknown }, '
        .'cards: VersionedUserCardCollection & { version: unknown } }')
        ->and(pageData(ResourceExtrasController::class.'@loose')['pageType'])->toBe('Inertia.SharedData & { '
            .'card: { data: LooseWithResource; meta: unknown; n: unknown }, '
            .'cards: { data: VersionedUserCardCollection; version: unknown } }')
        ->and([$sent->card->meta, $sent->card->n, $sent->cards->version])->toBe([[1, 2], 3, ['1.0', '2.0']])
        ->and([$bare->card->data->title, $bare->card->meta, $bare->cards->version])->toBe(['First', [1, 2], '1.0']);
});

it('imports the classes the keys added beside a resource prop\'s data name', function () {
    app(Router::class)->get('/resource-extras/{post}/classes', [ResourceExtrasController::class, 'classes']);
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id, 'status' => Status::Draft]);
    $sent = inertiaProps(new ResourceExtrasController()->classes($post));

    expect(pageData(ResourceExtrasController::class.'@classes')['pageType'])
        ->toBe('Inertia.SharedData & { card: { data: UserCardResource; post: Post; status: StatusType } }')
        ->and(new RouteTransformer(ResourceExtrasController::class)->typeImports)->toBe([
            '../../../../../../workbench/app/enums' => ['StatusType'],
            '../../../../../../workbench/app/models' => ['Post'],
            '../resources' => ['UserCardResource'],
        ])
        ->and([$sent->card->data->name, $sent->card->post->title, $sent->card->status])->toBe(['Ada', 'First', Status::Draft->value]);
});

// ─── Top-level keys as Inertia sends them ─────────────────────────

// PropsResolver::resolve() array_merge()s each props array over shared data, renumbering its int keys from 0, then
// unpackDotProps() writes each top-level dotted key into its path with Arr::set(), after every other key.
it('publishes top-level keys as Inertia sends them', function (string $action, string $type, array $sent) {
    expect(pageData(TopLevelKeysController::class.'@'.$action)['pageType'])->toBe('Inertia.SharedData & '.$type);

    foreach ($sent as $c => $json) {
        $request = Request::create('/', 'GET', ['c' => $c]);

        expect(inertiaPropsJson(new TopLevelKeysController()->{$action}($request), $request))->toBe($json);
    }
})->with([
    'dotted' => ['dotted', '{ auth: { user: string }, "1": { "5": string } }', [0 => '{"auth":{"user":"x"},"1":{"5":"y"}}']],
    'into an existing key' => ['intoExisting', '{ auth: { id: number; name: string } }', [0 => '{"auth":{"id":1,"name":"x"}}']],
    'existing key after' => ['existingAfter', '{ auth: { id: number; name: string } }', [0 => '{"auth":{"id":1,"name":"x"}}']],
    'over a scalar' => ['overScalar', '{ a: { b: number } }', [0 => '{"a":{"b":1}}']],
    'int keys per literal' => ['ternary', '{ "0": string, x?: number }', [1 => '["a"]', 0 => '{"x":1,"0":"b"}']],
]);

it('renumbers the int keys of each props array a component may send apart, warning for each', function (string $action) {
    $sent = array_map(function (int $c) use ($action): string {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaPropsJson(new TopLevelKeysController()->{$action}($request), $request);
    }, [1, 0]);

    expect(pageData(TopLevelKeysController::class.'@'.$action)['pageType'])
        ->toBe('Inertia.SharedData & { "0": string, x?: number }')
        ->and(array_column(AnalysisWarnings::all(), 'message'))->toBe([
            'The key "7" is renumbered to "0" by Laravel at the top level of a page; use a string key.',
            'The key "8" is renumbered to "0" by Laravel at the top level of a page; use a string key.',
        ])
        ->and($sent)->toBe(['["a"]', '{"x":1,"0":"b"}']);
})->with(['ternary', 'twoRenders']);

it('lays a cast on a dotted key by its dotted name, then nests it', function () {
    expect(pageData(TopLevelKeysController::class.'@castDotted')['pageType'])
        ->toBe('Inertia.SharedData & { plain: number, auth: { user: "fixed" } }')
        ->and(inertiaPropsJson(new TopLevelKeysController()->castDotted()))->toBe('{"plain":1,"auth":{"user":"fixed"}}');
});

it('nests a dotted key and renumbers an int key a props local is written', function () {
    expect(pageData(TopLevelKeysController::class.'@walked')['pageType'])
        ->toBe('Inertia.SharedData & { plain: number, "0": string, auth: { user: string } }')
        ->and(array_column(AnalysisWarnings::all(), 'message'))->toBe([
            'The key "7" is renumbered to "0" by Laravel at the top level of a page; use a string key.',
        ])
        ->and(inertiaPropsJson(new TopLevelKeysController()->walked()))->toBe('{"plain":1,"0":"y","auth":{"user":"x"}}');
});

it('nests a key of three segments and keys with an empty segment as Arr::set() writes them', function () {
    expect(pageData(TopLevelKeysController::class.'@segments')['pageType'])
        ->toBe('Inertia.SharedData & { a: { b: { c: number } }, d: { "": { e: number } }, "": { f: number }, g: { "": number } }')
        ->and(inertiaPropsJson(new TopLevelKeysController()->segments()))
        ->toBe('{"a":{"b":{"c":1}},"d":{"":{"e":2}},"":{"f":3},"g":{"":4}}');
});

it('keeps a dotted key only one props array holds optional inside the head it writes into', function () {
    $sent = array_map(function (int $c): string {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaPropsJson(new TopLevelKeysController()->optionalDotted($request), $request);
    }, [1, 0]);

    expect(pageData(TopLevelKeysController::class.'@optionalDotted')['pageType'])
        ->toBe('Inertia.SharedData & { auth: { id: number; name?: string } }')
        ->and($sent)->toBe(['{"auth":{"id":1,"name":"x"}}', '{"auth":{"id":2}}']);
});

it('binds each same-named enum token under a dotted head to its own class', function () {
    app(Router::class)->get('/top-level/same-named', [TopLevelKeysController::class, 'sameNamedNested']);
    $transformer = new RouteTransformer(TopLevelKeysController::class);

    expect($transformer->actions[0]['pageType'] ?? null)
        ->toBe('Inertia.SharedData & { a: WorkbenchStatusType, pair: { a: WorkbenchStatusType; b: CrmStatusType } }')
        ->and($transformer->typeImports)->toBe([
            '../../../../../../workbench/app/enums' => ['StatusType as WorkbenchStatusType'],
            '../../../../../../workbench/crm/enums' => ['StatusType as CrmStatusType'],
        ])
        ->and(inertiaPropsJson(new TopLevelKeysController()->sameNamedNested()))->toBe('{"a":0,"pair":{"a":0,"b":"lead"}}');
});

it('sends an int key a props local or a `+` may lack on each number a key before it leaves', function (string $action, string $type) {
    $sent = array_map(function (int $c) use ($action): string {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaPropsJson(new TopLevelKeysController()->{$action}($request), $request);
    }, [1, 0]);

    expect(pageData(TopLevelKeysController::class.'@'.$action)['pageType'])->toBe('Inertia.SharedData & '.$type)
        ->and($sent)->toBe([
            'walkedIntKeys' => ['{"x":1,"0":"a"}', '{"x":1,"0":5}'],
            'plusIntKeys' => ['["a","b"]', '["z","b"]'],
        ][$action]);
})->with([
    'a local written one of two' => ['walkedIntKeys', '{ x: number, "0"?: string | number, "1"?: number }'],
    'a `+` after one of two' => ['plusIntKeys', '{ "0": string, "1"?: string, "2"?: string }'],
]);

// PropsResolver::resolve() leaves an optional prop out after the merge has numbered it.
it('keeps the number of an int key after an optional prop Inertia leaves out', function () {
    expect(pageData(TopLevelKeysController::class.'@optionalIntKey')['pageType'])
        ->toBe('Inertia.SharedData & { "0"?: string, "1": string }')
        ->and(inertiaPropsJson(new TopLevelKeysController()->optionalIntKey()))->toBe('{"1":"b"}');
});

it('drops a cast on an int key another props array sends another key on, with a warning', function () {
    $sent = array_map(function (int $c): string {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaPropsJson(new TopLevelKeysController()->castSharedNumber($request), $request);
    }, [1, 0]);

    expect(pageData(TopLevelKeysController::class.'@castSharedNumber')['pageType'])
        ->toBe('Inertia.SharedData & { "0": string, x?: number }')
        ->and(array_column(AnalysisWarnings::all(), 'message'))->toContain(
            'The #[TsCasts] key "8" retypes none: Laravel renumbers it to "0", which another props array sends another key '
            .'on; use a string key.',
        )
        ->and($sent)->toBe(['["a"]', '{"x":1,"0":"b"}']);
});

it('sends the last int key of a local written five it may lack on each number they leave it', function () {
    $sent = array_map(function (array $query): string {
        $request = Request::create('/', 'GET', $query);

        return inertiaPropsJson(new TopLevelKeysController()->manyIntKeys($request), $request);
    }, [[], ['b' => 1, 'd' => 1], ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1, 'e' => 1]]);

    expect(pageData(TopLevelKeysController::class.'@manyIntKeys')['pageType'])
        ->toBe('Inertia.SharedData & { "0": string, "1"?: string, "2"?: string, "3"?: string, "4"?: string, "5"?: string }')
        ->and($sent)->toBe(['["f"]', '["b","d","f"]', '["a","b","c","d","e","f"]']);
});

// PropsResolver::resolve() leaves a deferred or optional prop out of a first visit after the merge has numbered it.
it('keeps a deferred int key of a local or a `+` optional on each number it may be sent on', function (string $action, string $type, array $sent) {
    expect(pageData(TopLevelKeysController::class.'@'.$action)['pageType'])->toBe('Inertia.SharedData & '.$type);

    foreach ($sent as $c => $json) {
        $request = Request::create('/', 'GET', ['c' => $c]);

        expect(inertiaPropsJson(new TopLevelKeysController()->{$action}($request), $request))->toBe($json);
    }
})->with([
    'a local' => ['walkedDeferred', '{ "0"?: string, "1"?: string }', [0 => '{"1":"b"}']],
    'a `+`' => ['plusDeferred', '{ "0"?: string, "1"?: string }', [0 => '{"1":"b"}']],
    'a local written in a block' => ['walkedDeferredInBlock', '{ "0"?: string, "1"?: string }', [1 => '{"1":"b"}', 0 => '["b"]']],
    'a `+` after a ternary' => [
        'plusOptionalInTernary',
        '{ "0"?: string, y?: number, "1"?: string }',
        [1 => '{"1":"b"}', 0 => '{"y":1,"0":"b"}'],
    ],
]);

it('keeps the value an optional dotted key replaces beside its own', function () {
    $sent = array_map(function (int $c): string {
        $request = Request::create('/', 'GET', ['c' => $c]);

        return inertiaPropsJson(new TopLevelKeysController()->optionalOverDeeper($request), $request);
    }, [1, 0]);

    expect(pageData(TopLevelKeysController::class.'@optionalOverDeeper')['pageType'])
        ->toBe('Inertia.SharedData & { a: { b: { y: number } | { c: number } } }')
        ->and($sent)->toBe(['{"a":{"b":{"y":1}}}', '{"a":{"b":{"c":1}}}']);
});

// TypeScript checks every key a signature covers against it (TS2411), a key the unpack nests included.
it('widens a signature that covers a dotted key\'s head with the head\'s type', function () {
    expect(pageData(TopLevelKeysController::class.'@signatureHead')['pageType'])
        ->toBe('Inertia.SharedData & { [key: `${string}_tag`]: string | undefined | { b: number }, x_tag: { b: number } }')
        ->and(inertiaPropsJson(new TopLevelKeysController()->signatureHead()))
        ->toBe('{"east_tag":"v","west_tag":"v","x_tag":{"b":1}}');
});

/** Seed an author and the one post Eloquent statics read, `First`, with a comment. */
function eloquentStaticsPost(): Post
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Comment::create(['content' => 'alpha', 'post_id' => $post->id, 'user_id' => $user->id]);

    return $post;
}

// The test app's factory guesser names a class that does not exist, so the oracle builds the users from the factory.
it('reads a prop through an Eloquent static as the query it forwards to', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(Inertia::render('Eloquent/Statics', [
        'findTitle' => Post::find(1)?->title,
        'failTitle' => Post::findOrFail(1)->title,
        'firstId' => Post::query()->pluck('id')->first(),
        'made' => UserFactory::new()->count(2)->make(),
        'queryTitle' => Post::query()->first()?->title,
        'whereFirst' => Post::where('id', 1)->first(),
        'found' => Post::find(1),
    ]));

    expect(pageData(EloquentStaticsController::class.'@show')['pageType'])->toBe('Inertia.SharedData & { '
        .'findTitle: string | null, failTitle: string, firstId: number | null, made: User[], '
        .'queryTitle: string | null, whereFirst: Post | null, found: Post | null }')
        ->and([$sent->findTitle, $sent->failTitle, $sent->firstId, $sent->queryTitle])->toBe(['First', 'First', 1, 'First'])
        ->and($sent->made)->toHaveCount(2)
        ->and([$sent->whereFirst->title, $sent->found->title])->toBe(['First', 'First']);
});

it('types a query chain from each step it takes before and after its terminal', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->steps());

    expect(pageData(EloquentStaticsController::class.'@steps')['pageType'])->toBe('Inertia.SharedData & { '
        .'ordered: string | null, conditional: Post[], trashed: Post | null, titles: string[], lastTitle: string | null, '
        .'comments: Comment[], author: User | null, rows: Record<string, unknown>[], ids: number[], listed: Post[], '
        .'query: { pendingAttributes: Record<string, unknown> | never[] }, proxied: Post[], nullCallback: Post | null, '
        .'heldCallback: Post[] }')
        ->and([$sent->ordered, $sent->trashed->title, $sent->titles, $sent->lastTitle, $sent->ids])
        ->toBe(['First', 'First', ['First'], 'First', [1]])
        ->and([$sent->conditional[0]->title, $sent->comments[0]->content, $sent->author->name, $sent->rows[0]->title])
        ->toBe(['First', 'alpha', 'Ada', 'First'])
        ->and([$sent->listed[0]->title, $sent->query->pendingAttributes, $sent->proxied[0]->title, $sent->nullCallback->title])
        ->toBe(['First', [], 'First', 'First'])
        ->and($sent->heldCallback[0]->title)->toBe('First');
});

// A step no body proves or disproves hands the query on keeps the root model's reading; past a step, a branch that
// returns a count throws, so only the query arm is sent. Sent bare, the macro sends the count, so it stays `unknown`.
it('keeps the root model\'s reading past a user-defined step whose hand-back is unproven', function () {
    eloquentStaticsPost();
    $macros = new ReflectionProperty(Builder::class, 'macros');
    $saved = $macros->getValue();
    app()->register(QueryMacroServiceProvider::class, force: true);

    try {
        $type = pageData(EloquentStaticsController::class.'@unproven')['pageType'];
        $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->unproven());
    } finally {
        $macros->setValue(null, $saved);
    }

    expect($type)->toBe('Inertia.SharedData & { viaHelper: ScopedPost[], branched: Post[], branchedBare: unknown }')
        ->and([$sent->viaHelper[0]->title, $sent->branched[0]->title, $sent->branchedBare])->toBe(['First', 'First', 1]);
});

// A model's own method that lists posts or finds one hands that on, so a terminal or list step past it reads it.
it('types a terminal past a model\'s own method that returns a list or a model', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->ownMethods());

    expect(pageData(EloquentStaticsController::class.'@ownMethods')['pageType'])->toBe('Inertia.SharedData & { '
        .'popularFirst: ListingPost | null, popularCount: number, popularAll: ListingPost[], typedFirst: ListingPost | null, '
        .'documentedFirst: ListingPost | null, newestCount: number, siblingsFirst: ListingPost | null, rowsViaFirst: ListingPost | null }')
        ->and([$sent->popularFirst->title, $sent->popularCount, $sent->popularAll[0]->title, $sent->typedFirst->title])
        ->toBe(['First', 1, 'First', 'First'])
        ->and([$sent->documentedFirst->title, $sent->newestCount, $sent->siblingsFirst->title, $sent->rowsViaFirst->title])
        ->toBe(['First', 1, 'First', 'First']);
});

// Arms naming a list and one model, or two models' lists, keep the root model's reading for a terminal past them.
it('keeps the root model\'s reading past a model\'s own method whose branches return different things', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->joinedReturns());

    expect(pageData(EloquentStaticsController::class.'@joinedReturns')['pageType'])->toBe('Inertia.SharedData & { '
        .'eitherFirst: ListingPost | null, eitherCount: number, twoFirst: ListingPost | null, twoCount: number }')
        ->and([$sent->eitherFirst->title, $sent->eitherCount, $sent->twoFirst->title, $sent->twoCount])->toBe(['First', 1, 'First', 1]);
});

// Item-keeping steps keep the models, as dot() and undot() do, a list of lists counts, and a step that replaces the
// items ends the chain. Past a list of lists first() keeps the root model's reading, which the sent list won't match.
it('types terminals past collection steps that keep, list or replace the models', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->collectionSteps());

    expect(pageData(EloquentStaticsController::class.'@collectionSteps')['pageType'])->toBe('Inertia.SharedData & { '
        .'shuffled: Post | null, merged: Post | null, concatenated: Post | null, lazyFirst: Post | null, lazyAll: Post[], '
        .'lazyCount: number, lazyByIdFirst: Post | null, cursorFiltered: Post | null, groupedCount: number, chunkedCount: number, '
        .'groupFirstCount: number, groupFirst: Post | null, mapped: unknown, keyedValues: Post[], dotted: Post | null, '
        .'dottedAll: Post[], undotted: Post | null }')
        ->and([$sent->shuffled->title, $sent->merged->title, $sent->concatenated->title, $sent->lazyFirst->title])
        ->toBe(['First', 'First', 'First', 'First'])
        ->and([$sent->lazyAll[0]->title, $sent->lazyCount, $sent->lazyByIdFirst->title, $sent->cursorFiltered->title])
        ->toBe(['First', 1, 'First', 'First'])
        ->and([$sent->groupedCount, $sent->chunkedCount, $sent->groupFirstCount, $sent->groupFirst[0]->title])
        ->toBe([1, 1, 1, 'First'])
        ->and([$sent->mapped, $sent->keyedValues[0]->title])->toBe(['First', 'First'])
        ->and([$sent->dotted->title, $sent->dottedAll[0]->title, $sent->undotted->title])->toBe(['First', 'First', 'First']);
});

// A step the engine cannot read is presumed to hand the query on only for terminals every model-rooted chain was typed
// by; a column the step's real query reads may be another model's, as the integer content sent here is.
it('leaves a column past an unread step unknown', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->opaqueColumns());

    expect(pageData(EloquentStaticsController::class.'@opaqueColumns')['pageType'])
        ->toBe('Inertia.SharedData & { contents: unknown, content: unknown }')
        ->and([$sent->contents, $sent->content])->toBe([[0], 0]);
});

// Builder::__call() runs a local macro a global scope's extend() adds before it would throw, so such a call, and one a
// builder a constructor picks answers, keeps the root model's reading.
it('keeps the root model\'s reading past a call a local macro or a runtime builder answers', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->localMacros());

    expect(pageData(EloquentStaticsController::class.'@localMacros')['pageType'])->toBe('Inertia.SharedData & { '
        .'archived: ArchivingPost[], archivedFirst: ArchivingPost | null, constructed: ConstructedBuilderPost[] }')
        ->and([$sent->archived[0]->title, $sent->archivedFirst->title, $sent->constructed[0]->title])->toBe(['First', 'First', 'First']);
});

it('types a chain past a custom builder\'s typed methods, a relation\'s scope and a relation\'s base-query step', function () {
    $post = eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->customSteps());

    expect(pageData(EloquentStaticsController::class.'@customSteps')['pageType'])->toBe('Inertia.SharedData & { '
        .'published: BuiltPost[], withContent: BuiltPost | null, scopedComments: ScopedComment[], listedComments: Comment[] }')
        ->and([$sent->published[0]->title, $sent->withContent->title, $sent->scopedComments[0]->content])
        ->toBe(['First', 'First', 'alpha'])
        ->and($sent->listedComments[0]->post_id)->toBe($post->id);
});

// A base query's rows are plain objects of the selected columns, which no model casts, in each paginator alike.
it('types a base query\'s paginator over its rows', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->basePages());

    expect(pageData(EloquentStaticsController::class.'@basePages')['pageType'])->toBe('Inertia.SharedData & { '
        .'paged: LengthAwarePaginator<Record<string, unknown>>, simple: SimplePaginator<Record<string, unknown>>, '
        .'cursor: CursorPaginator<Record<string, unknown>>, comments: LengthAwarePaginator<Record<string, unknown>> }')
        ->and([$sent->paged->data[0]->title, $sent->simple->data[0]->title, $sent->cursor->data[0]->title])
        ->toBe(['First', 'First', 'First'])
        ->and($sent->comments->data[0]->content)->toBe('alpha');
});

// Scout's search() returns a builder Eloquent does not define, read as the model its terminals return.
it('types a terminal on a builder a model\'s own static returns that Eloquent does not define', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->searched());

    expect(pageData(EloquentStaticsController::class.'@searched')['pageType'])->toBe('Inertia.SharedData & { '
        .'rows: SearchedPost[], filtered: SearchedPost[], paged: LengthAwarePaginator<SearchedPost>, first: SearchedPost | null }')
        ->and([$sent->rows[0]->title, $sent->filtered[0]->title, $sent->paged->data[0]->title, $sent->first->title])
        ->toBe(['First', 'First', 'First', 'First']);
});

// A model's own static named like a terminal runs as declared; a step past a find() of a variable key reads where a
// model and a list of it answer alike.
it('reads a model\'s own static as declared, and a step past a find() a variable keys', function () {
    eloquentStaticsPost();
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->ownStatics());

    expect(pageData(EloquentStaticsController::class.'@ownStatics')['pageType'])
        ->toBe('Inertia.SharedData & { cursor: number, value: string, queried: string | null, firstFound: Post | null }')
        ->and([$sent->cursor, $sent->value, $sent->queried, $sent->firstFound->title])->toBe([7, 'own-title', 'First', 'First']);
});

// R8: make() and create() build a list once count(), times() or a number passed to factory() sets a count; a count no
// literal spells may build either.
it('publishes a factory build as a list once a count is set, else as one model', function () {
    $type = pageData(EloquentStaticsController::class.'@factories')['pageType'];
    Factory::guessFactoryNamesUsing(fn (string $model): string => 'Workbench\\Database\\Factories\\'.class_basename($model).'Factory');
    $sent = inertiaProps(new EloquentStaticsController(new QueriedIdStats)->factories());

    expect($type)->toBe('Inertia.SharedData & { '
        .'one: User, counted: User[], timed: User[], argued: User[], cleared: User, single: User, firstMade: User | null, '
        .'either: User | User[], eitherFirst: User | null }')
        ->and($sent->one->name)->toBe('One')
        ->and([count($sent->counted), count($sent->timed), count($sent->argued), count($sent->either)])->toBe([2, 2, 2, 2])
        ->and([$sent->cleared, $sent->single, $sent->firstMade, $sent->eitherFirst])->each->toHaveProperty('email');
});

// Every body Inertia sends reads the chain alike; the resource's own interface, read as json_encode() sends it,
// stays `unknown`.
it('reads a query chain past its builder in a resolved resource, a helper and a collaborator alike', function () {
    $post = eloquentStaticsPost();
    $controller = new EloquentStaticsController(new QueriedIdStats);

    expect(pageData(EloquentStaticsController::class.'@resolvedCard')['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<QueriedIdCardResource, 'firstId'> & { firstId: number | null } }")
        ->and(pageData(EloquentStaticsController::class.'@helperMeta')['pageType'])
        ->toBe('Inertia.SharedData & { meta: { firstId: number | null } }')
        ->and(pageData(EloquentStaticsController::class.'@collaboratorStats')['pageType'])
        ->toBe('Inertia.SharedData & { stats: { firstId: number | null } }')
        ->and(inertiaProps($controller->resolvedCard($post))->card->firstId)->toBe(1)
        ->and(inertiaProps($controller->helperMeta())->meta->firstId)->toBe(1)
        ->and(inertiaProps($controller->collaboratorStats())->stats->firstId)->toBe(1);
});

/** Configure an `admin` guard beside the default, sign a user in on the default one, and bind a request reading it. */
function signInOnDefaultGuard(): User
{
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Auth::setUser($user);
    // Rebinding the request sets its user resolver to the auth guard's, as a request through the kernel has it.
    app()->instance('request', Request::create('/'));

    return $user;
}

it('reads the signed-in user through each spelling Laravel offers', function () {
    signInOnDefaultGuard();
    $sent = inertiaProps(new SignedInUserController()->show(request()));

    expect(pageData(SignedInUserController::class.'@show')['pageType'])->toBe('Inertia.SharedData & { '
        .'helper: string | null, facade: string | null, request: string | null, user: User | null, '
        .'injected: string | null, named: unknown }')
        ->and([$sent->helper, $sent->facade, $sent->request, $sent->user->name, $sent->injected, $sent->named])
        ->toBe(['Ada', 'ada@example.com', 'Ada', 'Ada', 'Ada', null]);
});

it('sends null for each spelling of the signed-in user to a guest', function () {
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    app()->instance('request', Request::create('/'));

    expect((array) inertiaProps(new SignedInUserController()->show(request())))
        ->toBe(['helper' => null, 'facade' => null, 'request' => null, 'user' => null, 'injected' => null, 'named' => null]);
});

// A guest's `->name` reads a property of null, which Laravel throws on, so nothing is sent.
it('reads past the signed-in user without a nullsafe call, through a relation, and through the default guard', function () {
    $user = signInOnDefaultGuard();
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    $sent = inertiaProps(new SignedInUserController()->steps());
    Auth::forgetUser();

    expect(pageData(SignedInUserController::class.'@steps')['pageType'])->toBe('Inertia.SharedData & { '
        .'helper: string, facade: string, request: string, posts: number, maybePosts: number | null, '
        .'guard: string | null, helperGuard: string | null, namedGuard: unknown, namedRequest: unknown }')
        ->and((array) $sent)->toBe([
            'helper' => 'Ada', 'facade' => 'ada@example.com', 'request' => 'Ada', 'posts' => 1, 'maybePosts' => 1,
            'guard' => 'Ada', 'helperGuard' => 'Ada', 'namedGuard' => null, 'namedRequest' => null,
        ])
        ->and(fn () => new SignedInUserController()->steps())->toThrow(ErrorException::class);
});

it('reads the signed-in user alike in shared data and in an API resource', function () {
    $user = signInOnDefaultGuard();
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Inertia::share(new MiddlewareWithSignedInUser()->share(request()));
    $shared = inertiaProps(Inertia::render('Shared', []));

    expect(sharedProps(MiddlewareWithSignedInUser::class))
        ->toBe(['helper' => 'string | null', 'facade' => 'string | null', 'request' => 'string | null', 'user' => 'User | null'])
        ->and(resourceProps(SignedInViewerResource::class))
        ->toBe(['id' => 'number', 'helper' => 'string | null', 'facade' => 'string | null', 'request' => 'string | null'])
        ->and([$shared->helper, $shared->facade, $shared->request, $shared->user->name])->toBe(['Ada', 'ada@example.com', 'Ada', 'Ada'])
        ->and((array) sentAsJson(new SignedInViewerResource($post)->resolve(request())))
        ->toBe(['id' => $post->id, 'helper' => 'Ada', 'facade' => 'ada@example.com', 'request' => 'Ada']);
});

/** Sign a user of the given model in on an `admin` guard over it, made the default as `auth:admin` makes it. */
function signInThroughRouteGuard(string $model): stdClass
{
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
    config()->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => $model]);
    $type = pageData(SignedInUserController::class.'@routeGuard')['pageType'];
    Auth::guard('admin')->setUser($model::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']));
    Auth::shouldUse('admin');
    app()->instance('request', Request::create('/'));

    return (object) ['type' => $type, 'sent' => inertiaProps(new SignedInUserController()->routeGuard())];
}

// A route's `auth:admin` middleware makes the admin guard the default, so its model's user is the one sent.
it('declines the signed-in user where another guard names another model, which a route may sign in', function () {
    $page = signInThroughRouteGuard(NameLengthMember::class);

    expect($page->type)
        ->toBe('Inertia.SharedData & { helper: unknown, facade: unknown, request: unknown, guard: unknown, user: unknown }')
        ->and([$page->sent->helper, $page->sent->facade, $page->sent->request, $page->sent->guard, $page->sent->user->name])
        ->toBe([3, 3, 3, 3, 3]);
});

it('reads the signed-in user where every guard names the one model', function () {
    $page = signInThroughRouteGuard(User::class);

    expect($page->type)->toBe('Inertia.SharedData & { '
        .'helper: string | null, facade: string | null, request: string | null, guard: string | null, user: User | null }')
        ->and([$page->sent->helper, $page->sent->facade, $page->sent->request, $page->sent->guard, $page->sent->user->name])
        ->toBe(['Ada', 'Ada', 'Ada', 'Ada', 'Ada']);
});

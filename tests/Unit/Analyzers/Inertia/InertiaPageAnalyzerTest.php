<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\InertiaInlineTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\InertiaServiceTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\InertiaTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithDelegatedProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithSpreadProps;
use Workbench\App\Http\Controllers\InertiaNamedCollectionsController;
use Workbench\App\Http\Controllers\InertiaPaginationsController;
use Workbench\App\Http\Controllers\InertiaPreserveKeysController;
use Workbench\App\Http\Controllers\InertiaResourceSharedTemplate;
use Workbench\App\Http\Controllers\InertiaSingleResourceController;
use Workbench\App\Http\Controllers\InertiaTsCastsController;
use Workbench\App\Http\Controllers\InertiaUserShapesController;
use Workbench\App\Http\Controllers\PostInertiaController;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Analyze one `Controller@action` through the page analyzer. */
function pageData(string $uses): ?array
{
    return new InertiaPageAnalyzer()->analyze(['uses' => $uses]);
}

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

    expect($data['pageType'])->toBe('Inertia.SharedData & { post: Post, stats: { views: number; likes: number } }');
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

// Task 32 review, fix round 2: the props array literal passed to Inertia::render() is a whole
// return shape in its own right, so a top-level resource spread inside it must flatten too.
it('flattens a top-level resource spread inside the render props array', function () {
    $data = pageData(ControllerWithSpreadProps::class.'@index');

    expect($data['component'])->toBe('Dashboard/Spread')
        ->and($data['pageType'])->toBe(
            'Inertia.SharedData & { id: number, name: string, email: string, role: RoleType | null, '.
            'profile?: Profile | null, posts?: PostResource[], phone?: string | null, avatar?: string, '.
            'posts_count?: number, comments_count?: number, flag: boolean }'
        );
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
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose('NonExistent\\Controller', 'index');

    expect($result['overrides'])->toBeEmpty()
        ->and($result['importMap'])->toBeEmpty();
});

test('parseTsCastsFromMethod returns empty arrays when the method has no TsCasts attribute', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose(InertiaPageAnalyzer::class, '__construct');

    expect($result['overrides'])->toBeEmpty()
        ->and($result['importMap'])->toBeEmpty();
});

test('parseTsCastsFromMethod extracts both the overrides and the import map', function () {
    $analyzer = new class extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose(InertiaTsCastsController::class, 'index');

    expect($result['overrides'])->toHaveKey('count', 'string')
        ->and($result['overrides'])->toHaveKey('meta', 'PageMeta')
        ->and($result['importMap'])->toBe(['@workbench/types' => ['PageMeta']]);
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

test('analyze() short-circuits Inertia UI Table props to the table analyzer', function () {
    $result = pageData(InertiaTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

test('analyze() short-circuits service-layer Inertia UI Table props to the table analyzer', function () {
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

test('analyze() short-circuits the Inertia index action on a service-backed table controller', function () {
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

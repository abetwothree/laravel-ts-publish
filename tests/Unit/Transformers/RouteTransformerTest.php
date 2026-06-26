<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Workbench\Accounting\Http\Controllers\TwoFactorController;
use Workbench\Accounting\Http\Requests\VerifyTwoFactorRequest;
use Workbench\App\Http\Controllers\CustomKeyController;
use Workbench\App\Http\Controllers\CustomKeyNameController;
use Workbench\App\Http\Controllers\CustomRouteKeyController;
use Workbench\App\Http\Controllers\Delete;
use Workbench\App\Http\Controllers\DeleteController;
use Workbench\App\Http\Controllers\DocBlockInvokableController;
use Workbench\App\Http\Controllers\DomainController;
use Workbench\App\Http\Controllers\EnumBoundController;
use Workbench\App\Http\Controllers\ExcludableController;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Controllers\InertiaFormRequestController;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\InvokableInertiaController;
use Workbench\App\Http\Controllers\InvokableModelBoundController;
use Workbench\App\Http\Controllers\InvokableModelBoundPlusController;
use Workbench\App\Http\Controllers\MultiRouteController;
use Workbench\App\Http\Controllers\NamedInvokableController;
use Workbench\App\Http\Controllers\Nested\NestedController;
use Workbench\App\Http\Controllers\OptionalParamController;
use Workbench\App\Http\Controllers\ParameterCaseController;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Controllers\PrimaryKeyController;
use Workbench\App\Http\Controllers\TypedParamController;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Requests\UpdatePostRequest;

beforeEach(function () {
    RouteTransformer::clearModelInstanceCache();

    config()->set('ts-publish.routes.only', []);
    config()->set('ts-publish.routes.except', []);
    config()->set('ts-publish.routes.exclude_middleware', []);
    config()->set('ts-publish.routes.only_named', false);
    config()->set('ts-publish.routes.method_casing', 'camel');
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
});

test('transforms PostController with correct controller name', function () {
    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->controllerName)->toBe('PostController');
});

test('transforms PostController with correct namespace path', function () {
    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->namespacePath)->toBe('app/http/controllers');
});

test('transforms PostController filename to kebab case', function () {
    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->filename())->toBe('post-controller');
});

test('transforms PostController with correct description', function () {
    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->description)->toBe('Manages blog posts');
});

test('transforms PostController with five actions', function () {
    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->actions)->toHaveCount(5);
});

test('transforms PostController index action', function () {
    $transformer = new RouteTransformer(PostController::class);
    $index = collect($transformer->actions)->firstWhere('methodName', 'index');

    expect($index)->not->toBeNull()
        ->and($index['name'])->toBe('posts.index')
        ->and($index['uri'])->toBe('/posts')
        ->and($index['methods'])->toContain('get')
        ->and($index['args'])->toBe([]);
});

test('transforms PostController show action with model binding arg', function () {
    $transformer = new RouteTransformer(PostController::class);
    $show = collect($transformer->actions)->firstWhere('methodName', 'show');

    expect($show)->not->toBeNull()
        ->and($show['uri'])->toBe('/posts/{post}')
        ->and($show['args'])->toHaveCount(1)
        ->and($show['args'][0]['name'])->toBe('post')
        ->and($show['args'][0]['required'])->toBeTrue()
        ->and($show['args'][0])->toHaveKey('_routeKey')
        ->and($show['args'][0]['_routeKey'])->toBe('id');
});

test('transforms PostController store action with no args', function () {
    $transformer = new RouteTransformer(PostController::class);
    $store = collect($transformer->actions)->firstWhere('methodName', 'store');

    expect($store)->not->toBeNull()
        ->and($store['methods'])->toContain('post')
        ->and($store['args'])->toBe([]);
});

test('transforms PostController destroy action with delete method', function () {
    $transformer = new RouteTransformer(PostController::class);
    $destroy = collect($transformer->actions)->firstWhere('methodName', 'destroy');

    expect($destroy)->not->toBeNull()
        ->and($destroy['methods'])->toContain('delete')
        ->and($destroy['args'])->toHaveCount(1);
});

test('excludes actions with TsExclude attribute', function () {
    $transformer = new RouteTransformer(ExcludableController::class);

    $actionNames = collect($transformer->actions)->pluck('methodName')->all();

    expect($actionNames)->toContain('show')
        ->and($actionNames)->not->toContain('secret');
});

test('data() returns a TsRouteDto', function () {
    $transformer = new RouteTransformer(PostController::class);
    $dto = $transformer->data();

    expect($dto->controllerName)->toBe('PostController')
        ->and($dto->fqcn)->toBe(PostController::class)
        ->and($dto->filePath)->toBe('app/http/controllers/post-controller')
        ->and($dto->actions)->toHaveCount(5);
});

test('does not include HEAD method in action methods', function () {
    $transformer = new RouteTransformer(PostController::class);
    $index = collect($transformer->actions)->firstWhere('methodName', 'index');

    expect($index['methods'])->not->toContain('head');
});

test('invokable controller methodName is __invoke when route is unnamed', function () {
    $transformer = new RouteTransformer(InvokableController::class);
    $action = $transformer->actions[0];

    expect($action['methodName'])->toBe('invoke');
});

test('named invokable controller methodName is always invoke', function () {
    $transformer = new RouteTransformer(NamedInvokableController::class);
    $action = $transformer->actions[0];

    expect($action['methodName'])->toBe('invoke')
        ->and($action['name'])->toBe('named.invokable');
});

test('invokable controller with model-bound param preserves _routeKey binding metadata', function () {
    $transformer = new RouteTransformer(InvokableModelBoundController::class);
    $action = $transformer->actions[0];

    expect($action['args'])->toHaveCount(1)
        ->and($action['args'][0]['name'])->toBe('post')
        ->and($action['args'][0]['required'])->toBeTrue()
        ->and($action['args'][0])->toHaveKey('_routeKey')
        ->and($action['args'][0]['_routeKey'])->toBe('id');
});

test('mixed invokable and regular methods all collected with correct binding metadata', function () {
    $transformer = new RouteTransformer(InvokableModelBoundPlusController::class);

    expect($transformer->actions)->toHaveCount(3);

    $actions = collect($transformer->actions)->keyBy('methodName');

    // __invoke route → always 'invoke', route name last segment is ignored
    expect($actions)->toHaveKey('invoke')
        ->and($actions['invoke']['name'])->toBe('invokable.model.bound.plus')
        ->and($actions['invoke']['args'][0])->toHaveKey('_routeKey')
        ->and($actions['invoke']['args'][0]['_routeKey'])->toBe('id');

    // regular 'extra' method
    expect($actions)->toHaveKey('extra')
        ->and($actions['extra']['name'])->toBe('invokable.model.bound.extra')
        ->and($actions['extra']['args'][0])->toHaveKey('_routeKey')
        ->and($actions['extra']['args'][0]['_routeKey'])->toBe('id');

    // regular 'surprise' method
    expect($actions)->toHaveKey('surprise')
        ->and($actions['surprise']['name'])->toBe('invokable.model.bound.surprise')
        ->and($actions['surprise']['args'][0])->toHaveKey('_routeKey')
        ->and($actions['surprise']['args'][0]['_routeKey'])->toBe('id');
});

test('optional single param has required false', function () {
    $transformer = new RouteTransformer(OptionalParamController::class);
    $show = collect($transformer->actions)->firstWhere('methodName', 'show');

    expect($show['args'])->toHaveCount(1)
        ->and($show['args'][0]['name'])->toBe('param')
        ->and($show['args'][0]['required'])->toBeFalse();
});

test('optional multi params both have required false', function () {
    $transformer = new RouteTransformer(OptionalParamController::class);
    $multi = collect($transformer->actions)->firstWhere('methodName', 'multi');

    expect($multi['args'])->toHaveCount(2)
        ->and($multi['args'][0]['name'])->toBe('one')
        ->and($multi['args'][0]['required'])->toBeFalse()
        ->and($multi['args'][1]['name'])->toBe('two')
        ->and($multi['args'][1]['required'])->toBeFalse();
});

test('enum-bound param emits _enumValues with int backing values', function () {
    $transformer = new RouteTransformer(EnumBoundController::class);
    $action = collect($transformer->actions)->firstWhere('methodName', 'byStatus');

    expect($action['args'])->toHaveCount(1)
        ->and($action['args'][0]['name'])->toBe('status')
        ->and($action['args'][0]['required'])->toBeTrue()
        ->and($action['args'][0])->toHaveKey('_enumValues')
        ->and($action['args'][0]['_enumValues'])->toBe([0, 1])
        ->and($action['args'][0])->not->toHaveKey('_routeKey');
});

test('explicit route key binding emits _routeKey from bindingFieldFor', function () {
    $transformer = new RouteTransformer(CustomKeyController::class);
    $show = collect($transformer->actions)->firstWhere('methodName', 'show');

    expect($show['args'])->toHaveCount(1)
        ->and($show['args'][0]['name'])->toBe('article')
        ->and($show['args'][0]['required'])->toBeTrue()
        ->and($show['args'][0])->toHaveKey('_routeKey')
        ->and($show['args'][0]['_routeKey'])->toBe('slug');
});

test('model with custom getRouteKeyName emits correct _routeKey via instantiation', function () {
    $transformer = new RouteTransformer(CustomRouteKeyController::class);
    $show = collect($transformer->actions)->firstWhere('methodName', 'show');

    expect($show['args'])->toHaveCount(1)
        ->and($show['args'][0]['name'])->toBe('slugPost')
        ->and($show['args'][0])->toHaveKey('_routeKey')
        ->and($show['args'][0]['_routeKey'])->toBe('slug');
});

test('camelCase param name is preserved exactly', function () {
    $transformer = new RouteTransformer(ParameterCaseController::class);
    $camel = collect($transformer->actions)->firstWhere('methodName', 'camel');

    expect($camel['args'][0]['name'])->toBe('camelCase');
});

test('snake_case param name is preserved exactly', function () {
    $transformer = new RouteTransformer(ParameterCaseController::class);
    $snake = collect($transformer->actions)->firstWhere('methodName', 'snake');

    expect($snake['args'][0]['name'])->toBe('snake_case');
});

test('SCREAMING_SNAKE param name is preserved exactly', function () {
    $transformer = new RouteTransformer(ParameterCaseController::class);
    $screaming = collect($transformer->actions)->firstWhere('methodName', 'screaming');

    expect($screaming['args'][0]['name'])->toBe('SCREAMING_SNAKE');
});

test('nested controller namespacePath includes nested segment', function () {
    $transformer = new RouteTransformer(NestedController::class);

    expect($transformer->namespacePath)->toBe('app/http/controllers/nested');
});

test('two routes same action deduplication keeps named route only', function () {
    $transformer = new RouteTransformer(MultiRouteController::class);
    $action = collect($transformer->actions)->firstWhere('methodName', 'action');

    expect($action)->not->toBeNull()
        ->and($action['name'])->toBe('multi.action')
        ->and($action['uri'])->toBe('/multi-2');
});

test('two routes same action deduplication result has exactly one action', function () {
    $transformer = new RouteTransformer(MultiRouteController::class);

    expect($transformer->actions)->toHaveCount(1);
});

test('reserved keyword method names get Method suffix', function () {
    $transformer = new RouteTransformer(DeleteController::class);
    $methodNames = collect($transformer->actions)->pluck('methodName')->all();

    expect($methodNames)->toContain('deleteMethod')
        ->and($methodNames)->toContain('exportMethod')
        ->and($methodNames)->not->toContain('delete')
        ->and($methodNames)->not->toContain('export');
});

test('invokable controller extracts description from __invoke docblock', function () {
    $transformer = new RouteTransformer(DocBlockInvokableController::class);
    $action = $transformer->actions[0];

    expect($action['description'])->toBe('Performs the invokable action.');
});

test('reserved-keyword controller name is preserved in controllerName property', function () {
    $transformer = new RouteTransformer(Delete::class);

    // controllerName stores the raw PHP class name — sanitization happens at output time
    expect($transformer->controllerName)->toBe('Delete');
});

test('route filtering skips actions that do not pass shouldIncludeRoute', function () {
    config()->set('ts-publish.routes.only', ['posts.index']);

    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->actions)->toHaveCount(1)
        ->and($transformer->actions[0]['methodName'])->toBe('index');
});

test('domain route includes url with domain prefix', function () {
    $transformer = new RouteTransformer(DomainController::class);
    $action = $transformer->actions[0];

    expect($action['url'])->toContain('api.example.com')
        ->and($action['domain'])->toBe('api.example.com');
});

test('builtin int type-hinted param does not emit _routeKey or _enumValues', function () {
    $transformer = new RouteTransformer(TypedParamController::class);
    $action = collect($transformer->actions)->firstWhere('methodName', 'showInt');

    expect($action['args'])->toHaveCount(1)
        ->and($action['args'][0]['name'])->toBe('id')
        ->and($action['args'][0])->not->toHaveKey('_routeKey')
        ->and($action['args'][0])->not->toHaveKey('_enumValues');
});

test('non-backed enum type-hinted param does not emit _enumValues', function () {
    $transformer = new RouteTransformer(TypedParamController::class);
    $action = collect($transformer->actions)->firstWhere('methodName', 'showRole');

    expect($action['args'])->toHaveCount(1)
        ->and($action['args'][0]['name'])->toBe('role')
        ->and($action['args'][0])->not->toHaveKey('_routeKey')
        ->and($action['args'][0])->not->toHaveKey('_enumValues');
});

test('where constraint on route param emits where metadata', function () {
    $transformer = new RouteTransformer(TypedParamController::class);
    $action = collect($transformer->actions)->firstWhere('methodName', 'showInt');

    expect($action['args'][0])->toHaveKey('where')
        ->and($action['args'][0]['where'])->toBe('[0-9]+');
});

test('model with overridden $primaryKey emits correct _routeKey', function () {
    $transformer = new RouteTransformer(PrimaryKeyController::class);
    $show = collect($transformer->actions)->firstWhere('methodName', 'show');

    expect($show['args'])->toHaveCount(1)
        ->and($show['args'][0])->toHaveKey('_routeKey')
        ->and($show['args'][0]['_routeKey'])->toBe('uuid');
});

test('model with overridden getKeyName emits correct _routeKey', function () {
    $transformer = new RouteTransformer(CustomKeyNameController::class);
    $show = collect($transformer->actions)->firstWhere('methodName', 'show');

    expect($show['args'])->toHaveCount(1)
        ->and($show['args'][0])->toHaveKey('_routeKey')
        ->and($show['args'][0]['_routeKey'])->toBe('custom_key');
});

test('method name is derived from the controller method, not the route name', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $setup = collect($transformer->actions)->firstWhere('originalMethodName', 'setup');
    $verify = collect($transformer->actions)->firstWhere('originalMethodName', 'verify');

    expect($setup['methodName'])->toBe('setup')
        ->and($verify['methodName'])->toBe('verify');
});

test('inertia actions do not include component or pageType when inertia is disabled', function () {
    config()->set('ts-publish.inertia.enabled', false);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard)->not->toHaveKey('component')
        ->and($dashboard)->not->toHaveKey('pageType');
});

test('inertia actions include component and pageType when inertia is enabled', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => 'Dashboard',
                    'pageType' => 'Inertia.SharedData & { stats: { users: number, posts: number, views: number } }',
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard)->toHaveKey('component')
        ->and($dashboard['component'])->toBe('Dashboard')
        ->and($dashboard)->toHaveKey('pageType')
        ->and($dashboard['pageType'])->toContain('Inertia.SharedData');
});

test('non-inertia actions do not get component or pageType even when inertia is enabled', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')->andReturn(null);

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(PostController::class);
    $index = collect($transformer->actions)->firstWhere('methodName', 'index');

    expect($index)->not->toBeNull()
        ->and($index)->not->toHaveKey('component')
        ->and($index)->not->toHaveKey('pageType');
});

test('normalizeComponent returns unique keys when component basenames collide', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => ['Admin/Dashboard', 'User/Dashboard'],
                    'pageType' => null,
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['component'])->toBeArray()
        ->and($dashboard['component'])->toHaveKeys(['adminDashboard', 'userDashboard'])
        ->and($dashboard['component']['adminDashboard'])->toBe('Admin/Dashboard')
        ->and($dashboard['component']['userDashboard'])->toBe('User/Dashboard');
});

test('normalizeComponent returns plain key when component basenames are distinct', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => ['Admin/Overview', 'User/Dashboard'],
                    'pageType' => null,
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['component'])->toBeArray()
        ->and($dashboard['component'])->toHaveKeys(['overview', 'dashboard'])
        ->and($dashboard['component']['overview'])->toBe('Admin/Overview')
        ->and($dashboard['component']['dashboard'])->toBe('User/Dashboard');
});

test('normalizeComponent resolves unique keys for backslash-separated component paths', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => ['Admin\\Dashboard', 'User\\Dashboard'],
                    'pageType' => null,
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['component'])->toBeArray()
        ->and($dashboard['component'])->toHaveKeys(['adminDashboard', 'userDashboard'])
        ->and($dashboard['component']['adminDashboard'])->toBe('Admin\\Dashboard')
        ->and($dashboard['component']['userDashboard'])->toBe('User\\Dashboard');
});

test('normalizeComponent resolves unique keys for dot-separated component paths', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => ['Admin.Overview', 'User.Dashboard'],
                    'pageType' => null,
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['component'])->toBeArray()
        ->and($dashboard['component'])->toHaveKeys(['overview', 'dashboard'])
        ->and($dashboard['component']['overview'])->toBe('Admin.Overview')
        ->and($dashboard['component']['dashboard'])->toBe('User.Dashboard');
});

test('normalizeComponent resolves unique keys for unseparated single-segment component paths', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => ['Overview', 'Dashboard'],
                    'pageType' => null,
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['component'])->toBeArray()
        ->and($dashboard['component'])->toHaveKeys(['overview', 'dashboard'])
        ->and($dashboard['component']['overview'])->toBe('Overview')
        ->and($dashboard['component']['dashboard'])->toBe('Dashboard');
});

test('normalizeComponent falls back to keyed path when all depths produce colliding keys', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => ['Dashboard', 'Dashboard'],
                    'pageType' => null,
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);
    $dashboard = collect($transformer->actions)->firstWhere('methodName', 'dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['component'])->toBeArray()
        ->and($dashboard['component'])->toHaveKey('dashboard')
        ->and($dashboard['component']['dashboard'])->toBe('Dashboard');
});

test('invokable inertia controller action receives @__invoke uses string and returns component data', function () {
    // Laravel stores invokable routes with just the FQCN (no @method). RouteTransformer
    // normalises this to Controller@__invoke before passing to InertiaPageAnalyzer, so
    // Ranger's analyzeRoute() can correctly explode the uses string and find __invoke.
    config()->set('ts-publish.inertia.enabled', true);

    $capturedAction = null;

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) use (&$capturedAction) {
            $capturedAction = $action;

            return [
                'component' => 'Profile',
                'pageType' => 'Inertia.SharedData & { name: string }',
                'classFqcns' => [],
            ];
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InvokableInertiaController::class);
    $invoke = collect($transformer->actions)->firstWhere('methodName', 'invoke');

    // The uses string passed to the analyzer must use @__invoke so that
    // AnalyzesRoutes::analyzeRoute() can split controller from method.
    expect($capturedAction)->not->toBeNull()
        ->and($capturedAction['uses'])->toEndWith('@__invoke')
        ->and($invoke)->not->toBeNull()
        ->and($invoke)->toHaveKey('component')
        ->and($invoke['component'])->toBe('Profile')
        ->and($invoke)->toHaveKey('pageType')
        ->and($invoke['pageType'])->toContain('Inertia.SharedData');
});

test('resolvePageTypeImports maps TOLKI_TYPES_MAP FQCNs to @tolki/types import', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => 'Dashboard',
                    'pageType' => 'Inertia.SharedData & { posts: LengthAwarePaginator<unknown> }',
                    'classFqcns' => ['Illuminate\\Pagination\\LengthAwarePaginator'],
                    'externalImports' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);

    expect($transformer->typeImports)
        ->toHaveKey('@tolki/types')
        ->and($transformer->typeImports['@tolki/types'])->toContain('LengthAwarePaginator');
});

test('resolvePageTypeImports merges externalImports from InertiaPageAnalyzer', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => 'Dashboard',
                    'pageType' => 'Inertia.SharedData & { posts: PostCollection }',
                    'classFqcns' => ['Workbench\\App\\Http\\Resources\\PostCollection'],
                    'externalImports' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);

    expect($transformer->typeImports)
        ->toHaveKey('../resources')
        ->and($transformer->typeImports['../resources'])->toContain('PostCollection');
});

test('resolvePageTypeImports includes TableResource from externalImports and model import for table props', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => 'Dashboard',
                    'pageType' => 'Inertia.SharedData & { posts: TableResource<Post> }',
                    'classFqcns' => ['Workbench\\App\\Models\\Post'],
                    'externalImports' => ['@inertiaui/table-vue' => ['TableResource']],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);

    expect($transformer->typeImports)
        ->toHaveKey('@inertiaui/table-vue')
        ->and($transformer->typeImports['@inertiaui/table-vue'])->toContain('TableResource')
        ->and($transformer->typeImports)->toHaveKey('../../models')
        ->and($transformer->typeImports['../../models'])->toContain('Post');
});

test('resolvePageTypeImports deduplicates @tolki/types entries from FQCNs and externalImports', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => 'Dashboard',
                    'pageType' => 'Inertia.SharedData & { posts: LengthAwarePaginator<unknown> }',
                    'classFqcns' => ['Illuminate\\Pagination\\LengthAwarePaginator'],
                    // Same key also appears in externalImports - should dedup
                    'externalImports' => ['@tolki/types' => ['LengthAwarePaginator']],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $transformer = new RouteTransformer(InertiaController::class);

    $tolkiImports = $transformer->typeImports['@tolki/types'] ?? [];
    $dedupedCount = count(array_unique($tolkiImports));

    expect($dedupedCount)->toBe(count($tolkiImports))
        ->and($tolkiImports)->toContain('LengthAwarePaginator');
});

test('PostController store action detects StorePostRequest fqcn', function () {
    $transformer = new RouteTransformer(PostController::class);
    $store = collect($transformer->actions)->firstWhere('methodName', 'store');

    expect($store)->not->toBeNull()
        ->and($store)->toHaveKey('requestFqcn')
        ->and($store['requestFqcn'])->toBe(StorePostRequest::class);
});

test('PostController store action requestTypeAlias is StorePostRequest', function () {
    $transformer = new RouteTransformer(PostController::class);
    $store = collect($transformer->actions)->firstWhere('methodName', 'store');

    expect($store['requestTypeAlias'])->toBe('StorePostRequest');
});

test('PostController store action requestImportPath points to requests directory', function () {
    $transformer = new RouteTransformer(PostController::class);
    $store = collect($transformer->actions)->firstWhere('methodName', 'store');

    expect($store['requestImportPath'])->toBe('../requests/store-post-request');
});

test('PostController update action detects UpdatePostRequest fqcn', function () {
    $transformer = new RouteTransformer(PostController::class);
    $update = collect($transformer->actions)->firstWhere('methodName', 'update');

    expect($update)->not->toBeNull()
        ->and($update)->toHaveKey('requestFqcn')
        ->and($update['requestFqcn'])->toBe(UpdatePostRequest::class);
});

test('PostController update action requestTypeAlias is UpdatePostRequest', function () {
    $transformer = new RouteTransformer(PostController::class);
    $update = collect($transformer->actions)->firstWhere('methodName', 'update');

    expect($update['requestTypeAlias'])->toBe('UpdatePostRequest');
});

test('PostController update action requestImportPath points to requests directory', function () {
    $transformer = new RouteTransformer(PostController::class);
    $update = collect($transformer->actions)->firstWhere('methodName', 'update');

    expect($update['requestImportPath'])->toBe('../requests/update-post-request');
});

test('PostController transformer sets hasRequestTypes when FormRequest found', function () {
    $transformer = new RouteTransformer(PostController::class);
    $dto = $transformer->data();

    expect($dto->hasRequestTypes)->toBeTrue();
});

test('TwoFactorController verify action detects VerifyTwoFactorRequest fqcn', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $verify = collect($transformer->actions)->firstWhere('originalMethodName', 'verify');

    expect($verify)->not->toBeNull()
        ->and($verify)->toHaveKey('requestFqcn')
        ->and($verify['requestFqcn'])->toBe(VerifyTwoFactorRequest::class);
});

test('TwoFactorController verify action requestTypeAlias is VerifyTwoFactorRequest', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $verify = collect($transformer->actions)->firstWhere('originalMethodName', 'verify');

    expect($verify['requestTypeAlias'])->toBe('VerifyTwoFactorRequest');
});

test('TwoFactorController verify action requestImportPath points to module requests directory', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $verify = collect($transformer->actions)->firstWhere('originalMethodName', 'verify');

    expect($verify['requestImportPath'])->toBe('../requests/verify-two-factor-request');
});

test('TwoFactorController transformer sets hasRequestTypes when FormRequest found', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $dto = $transformer->data();

    expect($dto->hasRequestTypes)->toBeTrue();
});

test('TwoFactorController setup action has no requestFqcn (GET route, no request body)', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $setup = collect($transformer->actions)->firstWhere('originalMethodName', 'setup');

    expect($setup)->not->toBeNull()
        ->and($setup)->not->toHaveKey('requestFqcn');
});

test('PostController index action has no requestFqcn', function () {
    $transformer = new RouteTransformer(PostController::class);
    $index = collect($transformer->actions)->firstWhere('methodName', 'index');

    expect($index)->not->toBeNull()
        ->and($index)->not->toHaveKey('requestFqcn');
});

test('PostController destroy action has no requestFqcn', function () {
    $transformer = new RouteTransformer(PostController::class);
    $destroy = collect($transformer->actions)->firstWhere('methodName', 'destroy');

    expect($destroy)->not->toBeNull()
        ->and($destroy)->not->toHaveKey('requestFqcn');
});

test('InvokableController has no requestFqcn on its single action', function () {
    $transformer = new RouteTransformer(InvokableController::class);
    $action = $transformer->actions[0];

    expect($action)->not->toHaveKey('requestFqcn');
});

test('PostController typeImports contains both StorePostRequest and UpdatePostRequest', function () {
    $transformer = new RouteTransformer(PostController::class);
    $allImportedTypes = collect($transformer->typeImports)->flatten()->all();

    expect($allImportedTypes)
        ->toContain('StorePostRequest')
        ->toContain('UpdatePostRequest');
});

test('PostController typeImports uses separate import paths for each request', function () {
    $transformer = new RouteTransformer(PostController::class);

    expect($transformer->typeImports)
        ->toHaveKey('../requests/store-post-request')
        ->toHaveKey('../requests/update-post-request');
});

test('InertiaFormRequestController store action has both pageType and requestFqcn when Inertia enabled', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockAnalyzer = Mockery::mock(InertiaPageAnalyzer::class);
    $mockAnalyzer->shouldReceive('analyze')
        ->andReturnUsing(function (array $action): ?array {
            if (str_contains((string) $action['uses'], 'InertiaFormRequestController@store')) {
                return [
                    'component' => 'InertiaFormRequest/Success',
                    'pageType' => 'Inertia.SharedData & { title: string }',
                    'classFqcns' => [],
                ];
            }

            return null;
        });
    app()->instance(InertiaPageAnalyzer::class, $mockAnalyzer);

    $transformer = new RouteTransformer(InertiaFormRequestController::class);
    $store = collect($transformer->actions)->firstWhere('methodName', 'store');

    expect($store)
        ->toHaveKey('pageType')
        ->toHaveKey('requestFqcn')
        ->and($store['requestFqcn'])->toBe(StorePostRequest::class)
        ->and($store['pageType'])->toContain('Inertia.SharedData');
});

test('InertiaFormRequestController create action has pageType but no requestFqcn', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockAnalyzer = Mockery::mock(InertiaPageAnalyzer::class);
    $mockAnalyzer->shouldReceive('analyze')
        ->andReturnUsing(function (array $action): ?array {
            if (str_contains((string) $action['uses'], 'InertiaFormRequestController@create')) {
                return [
                    'component' => 'InertiaFormRequest/Create',
                    'pageType' => 'Inertia.SharedData',
                    'classFqcns' => [],
                ];
            }

            return null;
        });
    app()->instance(InertiaPageAnalyzer::class, $mockAnalyzer);

    $transformer = new RouteTransformer(InertiaFormRequestController::class);
    $create = collect($transformer->actions)->firstWhere('methodName', 'create');

    expect($create)
        ->toHaveKey('pageType')
        ->not->toHaveKey('requestFqcn');
});

test('isInvokable is true for invokable controllers and false otherwise', function () {
    expect((new RouteTransformer(InvokableController::class))->data()->isInvokable)->toBeTrue()
        ->and((new RouteTransformer(InvokableModelBoundPlusController::class))->data()->isInvokable)->toBeTrue()
        ->and((new RouteTransformer(PostController::class))->data()->isInvokable)->toBeFalse();
});

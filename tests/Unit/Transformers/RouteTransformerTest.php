<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Workbench\Accounting\Http\Controllers\TwoFactorController;
use Workbench\App\Http\Controllers\CustomKeyController;
use Workbench\App\Http\Controllers\CustomRouteKeyController;
use Workbench\App\Http\Controllers\Delete;
use Workbench\App\Http\Controllers\DeleteController;
use Workbench\App\Http\Controllers\DocBlockInvokableController;
use Workbench\App\Http\Controllers\DomainController;
use Workbench\App\Http\Controllers\EnumBoundController;
use Workbench\App\Http\Controllers\ExcludableController;
use Workbench\App\Http\Controllers\InvokableController;
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

test('digit-leading route name segment is prefixed with underscore', function () {
    $transformer = new RouteTransformer(TwoFactorController::class);
    $setup = collect($transformer->actions)->firstWhere('originalMethodName', 'setup');
    $verify = collect($transformer->actions)->firstWhere('originalMethodName', 'verify');

    expect($setup['methodName'])->toBe('_2faSetup')
        ->and($verify['methodName'])->toBe('_2faVerify');
});

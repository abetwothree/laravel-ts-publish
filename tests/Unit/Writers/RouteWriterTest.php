<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Workbench\App\Http\Controllers\CustomKeyController;
use Workbench\App\Http\Controllers\Delete;
use Workbench\App\Http\Controllers\DeleteController;
use Workbench\App\Http\Controllers\EnumBoundController;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\NamedInvokableController;
use Workbench\App\Http\Controllers\Nested\NestedController;
use Workbench\App\Http\Controllers\OptionalParamController;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Controllers\Prism\Prism\PrismController as NestedPrismController;
use Workbench\App\Http\Controllers\Prism\PrismController;
use Workbench\App\Http\Controllers\TypedParamController;

beforeEach(function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.routes.enabled', true);
    config()->set('ts-publish.routes.only', []);
    config()->set('ts-publish.routes.except', []);
    config()->set('ts-publish.routes.exclude_middleware', []);
    config()->set('ts-publish.routes.only_named', false);
    config()->set('ts-publish.routes.method_casing', 'camel');
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
});

test('route writer generates TypeScript content with defineRoute import', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain("import { defineRoute } from '@tolki/ts'")
        ->not->toContain('RouteQueryOptions');
});

test('route writer does not import RouteQueryOptions for inertia components', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return ['component' => 'Dashboard', 'pageType' => null, 'classFqcns' => []];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain("import { defineRoute } from '@tolki/ts'")
        ->not->toContain('RouteQueryOptions');
});

test('route writer generates export const for each action', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain('export const index = defineRoute(')
        ->toContain('export const show = defineRoute(')
        ->toContain('export const store = defineRoute(')
        ->toContain('export const update = defineRoute(')
        ->toContain('export const destroy = defineRoute(');
});

test('route writer includes route URL in output', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain("url: '/posts'")
        ->toContain("url: '/posts/{post}'");
});

test('route writer includes HTTP methods in output', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain("'get'")
        ->toContain("'post'")
        ->toContain("'delete'");
});

test('route writer includes model binding args', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain('_routeKey: \'id\'');
});

test('route writer includes named route name when present', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain("name: 'posts.index'")
        ->toContain("name: 'posts.show'");
});

test('route writer includes default export with all actions', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain('const PostController = {')
        ->toContain('    index,')
        ->toContain('    show,')
        ->toContain('export default PostController');
});

test('route writer includes @see reference to controller FQCN', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)
        ->toContain('@see '.PostController::class);
});

test('route writer writes file to disk when output_to_files is enabled', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-route-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.routes.output_path', $outputDir.'/routes');

    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect(file_exists("$outputDir/routes/app/http/controllers/post-controller.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('route barrel writer generates correct barrel content', function () {
    $runner = resolve(Runner::class);
    $runner->run();

    expect($runner->routeModularBarrels)->toBeArray()->not->toBeEmpty();

    $allBarrelContent = implode("\n\n", $runner->routeModularBarrels);
    expect($allBarrelContent)->toContain("export { default as PostController } from './post-controller'");
});

test('runner generates route generators when routes enabled', function () {
    $runner = resolve(Runner::class);
    $runner->run();

    expect($runner->routeGenerators)
        ->toBeCollection()
        ->not->toBeEmpty()
        ->and($runner->routeGenerators->first())->toBeInstanceOf(RouteGenerator::class);
});

test('runner route generators are empty when routes disabled', function () {
    config()->set('ts-publish.routes.enabled', false);

    $runner = resolve(Runner::class);
    $runner->run();

    expect($runner->routeGenerators)->toBeCollection()->toBeEmpty();
});

test('route content does not include HEAD method', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)->not->toContain("'head'");
});

test('invokable controller output uses __invoke as method name when unnamed', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => InvokableController::class]);

    expect($generator->content)
        ->toContain('export const invoke = defineRoute(')
        ->toContain("    '__invoke': invoke,");
});

test('named invokable controller output always uses invoke as method name', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => NamedInvokableController::class]);

    expect($generator->content)
        ->toContain('export const invoke = defineRoute(')
        ->toContain("name: 'named.invokable'");
});

test('optional param arg includes required false in output', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => OptionalParamController::class]);

    expect($generator->content)
        ->toContain('required: false');
});

test('enum bound arg includes _enumValues array in output', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => EnumBoundController::class]);

    expect($generator->content)
        ->toContain('_enumValues:');
});

test('explicit key binding arg includes _routeKey slug in output', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => CustomKeyController::class]);

    expect($generator->content)
        ->toContain("_routeKey: 'slug'");
});

test('nested controller barrel writes to nested path', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-route-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.routes.output_path', $outputDir.'/routes');

    resolve(RouteGenerator::class, ['findable' => NestedController::class]);

    expect(file_exists("$outputDir/routes/app/http/controllers/nested/nested-controller.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('prism and nested prism each get their own barrel file', function () {
    $writer = resolve(RouteWriter::class);

    /** @var Collection<int, RouteGenerator> $generators */
    $generators = collect([
        resolve(RouteGenerator::class, ['findable' => PrismController::class]),
        resolve(RouteGenerator::class, ['findable' => NestedPrismController::class]),
    ]);

    $barrels = $writer->writeRouteBarrels($generators);

    expect($barrels)->toHaveKey('app/http/controllers/prism')
        ->and($barrels)->toHaveKey('app/http/controllers/prism/prism')
        ->and($barrels['app/http/controllers/prism'])->toContain("from './prism-controller'")
        ->and($barrels['app/http/controllers/prism/prism'])->toContain("from './prism-controller'");
});

test('route writer does not emit reserved keyword as const name', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => DeleteController::class]);

    expect($generator->content)
        ->toContain('export const deleteMethod = defineRoute(')
        ->toContain('export const exportMethod = defineRoute(')
        ->not->toContain('export const delete ')
        ->not->toContain('export const export ');
});

// ─── export type PageProps alias ─────────────────────────────────

test('route output emits export type PageProps alias for single inertia component', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return ['component' => 'Dashboard', 'pageType' => 'Inertia.SharedData & { stats: { users: number } }', 'classFqcns' => []];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain('export type DashboardPageProps = Inertia.SharedData & { stats: { users: number } };');
});

test('route output emits export type PageProps alias before JSDoc and defineRoute const', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return ['component' => 'Dashboard', 'pageType' => 'Inertia.SharedData & { stats: { users: number } }', 'classFqcns' => []];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    $content = $generator->content;
    $typePos = strpos($content, 'export type DashboardPageProps');
    $constPos = strpos($content, 'export const dashboard');

    expect($typePos)->toBeLessThan($constPos);
});

test('route output emits per-key export type aliases for conditional inertia components', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@conditional')) {
                return [
                    'component' => ['Conditional/Authenticated', 'Conditional/Guest'],
                    'pageType' => ['Inertia.SharedData & { user: unknown }', 'Inertia.SharedData & { message: string }'],
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain('export type ConditionalAuthenticatedPageProps = Inertia.SharedData & { user: unknown };')
        ->toContain('export type ConditionalGuestPageProps = Inertia.SharedData & { message: string };');
});

test('route output does not emit export type alias when pageType is null', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return ['component' => 'Dashboard', 'pageType' => null, 'classFqcns' => []];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)->not->toContain('PageProps');
});

test('route output does not emit export type alias when inertia is disabled', function () {
    config()->set('ts-publish.inertia.enabled', false);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)->not->toContain('PageProps');
});

test('PascalCase controller name matching lowercase keyword is unchanged — only lowercase triggers suffix', function () {
    $writer = resolve(RouteWriter::class);

    /** @var Collection<int, RouteGenerator> $generators */
    $generators = collect([
        resolve(RouteGenerator::class, ['findable' => Delete::class]),
    ]);

    $barrels = $writer->writeRouteBarrels($generators);

    // 'Delete' (capital D) is not a JS reserved word — safeJsIdentifier is case-sensitive
    expect($barrels['app/http/controllers'])
        ->toContain("export { default as Delete } from './delete'");
});

test('route barrel writer writes barrel files to disk when output_to_files enabled', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-barrel-write-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.routes.output_path', $outputDir.'/routes');

    $writer = resolve(RouteWriter::class);

    /** @var Collection<int, RouteGenerator> $generators */
    $generators = collect([
        resolve(RouteGenerator::class, ['findable' => PostController::class]),
    ]);

    $barrels = $writer->writeRouteBarrels($generators);

    expect(file_exists("$outputDir/routes/app/http/controllers/index.ts"))->toBeTrue()
        ->and($barrels)->toHaveKey('app/http/controllers');

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('route writer includes where constraint in output', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => TypedParamController::class]);

    expect($generator->content)->toContain("where: '[0-9]+'");
});

// ─── Inertia route output ─────────────────────────────────────────

test('route output includes .component for single inertia component', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return [
                    'component' => 'Dashboard',
                    'pageType' => 'Inertia.SharedData & { stats: { users: number } }',
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain("component: 'Dashboard',")
        ->not->toContain('dashboard.component')
        ->not->toContain('dashboard.withComponent');
});

test('route output includes .component map for conditional inertia components', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@conditional')) {
                return [
                    'component' => ['Conditional/Authenticated', 'Conditional/Guest'],
                    'pageType' => 'Inertia.SharedData & { user: unknown } | Inertia.SharedData & { message: string }',
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain("authenticated: 'Conditional/Authenticated'")
        ->toContain("guest: 'Conditional/Guest'")
        ->toContain('as const,')
        ->not->toContain('conditional.component')
        ->not->toContain('conditional.withComponent');
});

test('route output uses component_casing config for component map keys', function () {
    config()->set('ts-publish.inertia.enabled', true);
    config()->set('ts-publish.inertia.component_casing', 'snake');

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@conditional')) {
                return [
                    'component' => ['Conditional/CustomerLogin', 'Conditional/NotFoundPortal'],
                    'pageType' => 'Inertia.SharedData',
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain("customer_login: 'Conditional/CustomerLogin'")
        ->toContain("not_found_portal: 'Conditional/NotFoundPortal'");
});

test('route output does not include component when inertia is disabled', function () {
    config()->set('ts-publish.inertia.enabled', false);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->not->toContain('component:')
        ->not->toContain('.component')
        ->not->toContain('.withComponent');
});

// ─── Phase 2: annotatePageProps annotation ─────────────────────────

test('route output emits annotatePageProps annotation for single-component no-args inertia route', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return ['component' => 'Dashboard', 'pageType' => 'Inertia.SharedData & { stats: { users: number } }', 'classFqcns' => []];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain('export const dashboard = annotatePageProps<DashboardPageProps>()(defineRoute({')
        ->toContain("import { defineRoute, annotatePageProps } from '@tolki/ts'");
});

test('route output does not emit annotatePageProps for non-inertia routes', function () {
    $generator = resolve(RouteGenerator::class, ['findable' => PostController::class]);

    expect($generator->content)->not->toContain('annotatePageProps');
});

test('route output emits annotatePageProps with union type for multi-component inertia route', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@conditional')) {
                return [
                    'component' => ['authenticated' => 'Conditional/Authenticated', 'guest' => 'Conditional/Guest'],
                    'pageType' => ['authenticated' => 'Inertia.SharedData & { user: unknown }', 'guest' => 'Inertia.SharedData & { message: string }'],
                    'classFqcns' => [],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain('export const conditional = annotatePageProps<ConditionalAuthenticatedPageProps | ConditionalGuestPageProps>()(defineRoute({')
        ->toContain("import { defineRoute, annotatePageProps } from '@tolki/ts'");
});

test('route output does not emit annotatePageProps when inertia route has no pageType', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@dashboard')) {
                return ['component' => 'Dashboard', 'pageType' => null, 'classFqcns' => []];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)->not->toContain('annotatePageProps');
});

test('route output emits annotatePageProps for inertia route with args', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@post')) {
                return ['component' => 'PostShow', 'pageType' => 'Inertia.SharedData & { post: Post }', 'classFqcns' => ['Workbench\App\Models\Post']];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain('export const post = annotatePageProps<PostPageProps>()(defineRoute({')
        ->toContain("import { defineRoute, annotatePageProps } from '@tolki/ts'");
});

test('route output emits import type for PHP model referenced in page props', function () {
    config()->set('ts-publish.inertia.enabled', true);

    $mockConverter = Mockery::mock(InertiaPageAnalyzer::class);
    $mockConverter->shouldReceive('analyze')
        ->andReturnUsing(function (array $action) {
            if (str_contains($action['uses'], 'InertiaController@post')) {
                return [
                    'component' => 'PostShow',
                    'pageType' => 'Inertia.SharedData & { post: Post }',
                    'classFqcns' => ['Workbench\App\Models\Post'],
                ];
            }

            return null;
        });

    app()->instance(InertiaPageAnalyzer::class, $mockConverter);

    $generator = resolve(RouteGenerator::class, ['findable' => InertiaController::class]);

    expect($generator->content)
        ->toContain("import type { Post } from '../../models';");
});

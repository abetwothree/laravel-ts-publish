<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ClassTypeGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\UiGadgetBroadcast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiGadgetPageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiGadgetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiPanelHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiGadgetHolder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash\Chart as ClashChart;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GadgetShade;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Unimported;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ClassTypeTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\WatcherJsonWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;

/**
 * Runs the class-type generator for one class and returns the module it wrote.
 */
function composedModule(string $fqcn): string
{
    /** @var ClassTypeGenerator $generator */
    $generator = resolve(ClassTypeGenerator::class, ['findable' => $fqcn]);
    $path = Config::string('ts-publish.output_directory').'/'.$generator->namespacePath().'/'.$generator->filename().'.ts';

    expect(file_get_contents($path))->toBe($generator->content);

    return (string) file_get_contents($path);
}

/**
 * Turns off in config each phase that writes a barrel but the ones named, so a flag that skips them skips nothing.
 */
function disableBarrelPhasesBut(string ...$kept): void
{
    foreach (['enums', 'models', 'model_metadata', 'resources', 'form_requests', 'broadcast_events'] as $feature) {
        if (! in_array($feature, $kept, true)) {
            Config::set("ts-publish.{$feature}.enabled", false);
        }
    }
}

/**
 * A runner that publishes only what a test turns back on.
 */
function classTypesOnlyRunner(): Runner
{
    $runner = new Runner;
    $runner->shouldPublishEnums = false;
    $runner->shouldPublishModels = false;
    $runner->shouldPublishModelMetadata = false;
    $runner->shouldPublishResources = false;
    $runner->shouldPublishRoutes = false;
    $runner->shouldPublishFormRequests = false;
    $runner->shouldPublishBroadcastChannels = false;
    $runner->shouldPublishBroadcastEvents = false;

    return $runner;
}

beforeEach(function () {
    $this->out = sys_get_temp_dir().'/ts-publish-class-types-'.uniqid();

    Config::set('ts-publish.output_to_files', true);
    Config::set('ts-publish.output_directory', $this->out);
    Config::set('ts-publish.inertia.enabled', false);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->out);

    if (isset($this->cacheDir)) {
        (new Filesystem)->deleteDirectory($this->cacheDir);
    }
});

it('writes a composed class module with its contributors imported once', function () {
    expect(composedModule(Chart::class))->toBe(<<<'TS'
        import type { UiHello, UiIcon, UiNode } from '@js/types/ui';

        export interface ChartType extends UiHello, UiIcon, UiNode {}

        TS);
});

it('extends a contributor with no import without importing it', function () {
    expect(composedModule(Unimported::class))->toBe(<<<'TS'
        import type { UiNode } from '@js/types/ui';

        export interface UnimportedType extends UiLocal, UiNode {}

        TS);
});

it('aliases an extended name equal to the composed name', function () {
    expect(composedModule(ClashChart::class))->toBe(<<<'TS'
        import type { ChartType as ChartTypeBase, UiNode } from '@js/types/ui';

        export interface ChartType extends ChartTypeBase, UiNode {}

        TS);
});

it('writes a module for a scanned class no value uses', function () {
    $runner = classTypesOnlyRunner();
    $runner->run();

    $directory = $this->out.'/workbench/app/packages/ui';

    expect($runner->classTypeGenerators->map(fn (ClassTypeGenerator $generator): string => $generator->findable)->all())
        ->toBe([Chart::class, Dial::class])
        ->and(file_get_contents("$directory/dial.ts"))->toBe(<<<'TS'
            import type { UiDial, UiNode } from '@js/types/ui';

            export interface DialType extends UiDial, UiNode {}

            TS)
        ->and(file_get_contents("$directory/index.ts"))->toBe("export * from './chart';\nexport * from './dial';")
        ->and($runner->classTypeModularBarrels)->toBe([
            'workbench/app/packages/ui' => "export * from './chart';\nexport * from './dial';",
        ]);
});

it('scans no directory unless one is configured', function () {
    Config::set('ts-publish.class_types.additional_directories', []);

    $runner = classTypesOnlyRunner();
    $runner->run();

    expect($runner->classTypeGenerators)->toBeEmpty()
        ->and(is_dir($this->out.'/workbench/app/packages/ui'))->toBeFalse();
});

it('writes a module for a composed class a value reaches outside the scan', function () {
    disableBarrelPhasesBut('resources');
    Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
    Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);

    $runner = classTypesOnlyRunner();
    $runner->shouldPublishResources = true;
    $runner->run();

    $module = $this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/gadget.ts';

    expect($runner->classTypeGenerators->map(fn (ClassTypeGenerator $generator): string => $generator->findable)->all())
        ->toBe([Chart::class, Dial::class, Gadget::class])
        ->and(ComposedClassTypes::registered())->toBe([Gadget::class])
        ->and(file_get_contents($module))->toBe(<<<'TS'
            import type { UiIcon, UiNode } from '@js/types/ui';

            export interface GadgetType extends UiIcon, UiNode {}

            TS)
        ->and(file_get_contents($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/index.ts'))
        ->toBe("export * from './gadget';");
});

it('keeps the exports another phase wrote in a shared namespace directory', function () {
    disableBarrelPhasesBut('enums', 'resources');
    Config::set('ts-publish.enums.included', [GadgetShade::class]);
    Config::set('ts-publish.enums.additional_directories', [GadgetShade::class]);
    Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
    Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);

    $runner = classTypesOnlyRunner();
    $runner->shouldPublishEnums = true;
    $runner->shouldPublishResources = true;
    $runner->run();

    expect(file_get_contents($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/index.ts'))
        ->toBe("export * from './gadget';\nexport * from './gadget-shade';");
});

it('drops a module export a run with no skipped phase no longer writes', function () {
    disableBarrelPhasesBut();

    $directory = $this->out.'/workbench/app/packages/ui';
    mkdir($directory, recursive: true);
    file_put_contents("$directory/index.ts", "export * from './chart';\nexport * from './stale';");

    $runner = classTypesOnlyRunner();
    $runner->run();

    expect(file_get_contents("$directory/index.ts"))->toBe("export * from './chart';\nexport * from './dial';");
});

it('keeps every existing export while a flag skips a phase that writes barrels', function () {
    $directory = $this->out.'/workbench/app/packages/ui';
    mkdir($directory, recursive: true);
    file_put_contents("$directory/index.ts", "export * from './other';");

    $runner = classTypesOnlyRunner();
    $runner->run();

    expect(file_get_contents("$directory/index.ts"))
        ->toBe("export * from './chart';\nexport * from './dial';\nexport * from './other';");
});

it('lists the composed classes in the JSON output', function () {
    Config::set('ts-publish.json.enabled', true);

    $runner = classTypesOnlyRunner();
    $runner->run();

    expect(json_decode($runner->jsonContent, true)['classTypes'])->toBe([
        Chart::class => ['name' => 'ChartType', 'extends' => ['UiHello', 'UiIcon', 'UiNode']],
        Dial::class => ['name' => 'DialType', 'extends' => ['UiDial', 'UiNode']],
    ]);
});

it('lists the scanned composed classes in the watcher file, and no class only a value reaches', function () {
    Config::set('ts-publish.output_to_files', false);
    Config::set('ts-publish.watcher.enabled', true);

    // A value reaching a class registers it, which the watcher does not read.
    ComposedClassTypes::register(Gadget::class);

    /** @var list<string> $paths */
    $paths = json_decode(new WatcherJsonWriter(new Filesystem)->write(), true);

    expect($paths)
        ->toContain('app/Packages/Ui/Chart.php')
        ->toContain('app/Packages/Ui/Dial.php')
        ->not->toContain('app/Packages/Ui/Spark.php')
        ->and(array_filter($paths, fn (string $path): bool => str_ends_with($path, 'Ui/Gadget.php')))->toBe([]);
});

it('lists no composed class in the watcher file when class types are disabled', function () {
    Config::set('ts-publish.output_to_files', false);
    Config::set('ts-publish.watcher.enabled', true);
    Config::set('ts-publish.class_types.enabled', false);

    /** @var list<string> $paths */
    $paths = json_decode(new WatcherJsonWriter(new Filesystem)->write(), true);

    expect($paths)->toContain('app/Events/UiBoardUpdated.php')->not->toContain('app/Packages/Ui/Chart.php');
});

it('publishes no module when class types are disabled', function () {
    Config::set('ts-publish.class_types.enabled', false);

    $runner = classTypesOnlyRunner();
    $runner->run();

    expect($runner->classTypeGenerators)->toBeEmpty()
        ->and($runner->classTypeModularBarrels)->toBe([])
        ->and(is_dir($this->out.'/workbench/app/packages/ui'))->toBeFalse();
});

it('writes modules to the class types output directory when one is set', function () {
    $classTypesOut = $this->out.'/class-types';
    Config::set('ts-publish.class_types.output_directory', $classTypesOut);

    $runner = classTypesOnlyRunner();
    $runner->run();

    expect(file_exists("$classTypesOut/workbench/app/packages/ui/chart.ts"))->toBeTrue()
        ->and(file_exists("$classTypesOut/workbench/app/packages/ui/index.ts"))->toBeTrue()
        ->and(file_exists($this->out.'/workbench/app/packages/ui/chart.ts'))->toBeFalse();
});

describe('each publisher reports the composed classes its file imports', function () {
    /** Asserts a transformer reports the class, and so does the one its cache snapshot rehydrates. */
    $reports = function (CoreTransformer $transformer): void {
        expect($transformer->composedClassTypes())->toBe([Gadget::class])
            ->and(unserialize(serialize($transformer))->composedClassTypes())->toBe([Gadget::class]);
    };

    it('in an API resource', function () use ($reports) {
        $reports(new ResourceTransformer(UiGadgetResource::class));
    });

    it('in a broadcast event', function () use ($reports) {
        $reports(new BroadcastEventTransformer(UiGadgetBroadcast::class));
    });

    it('in an Inertia page', function () use ($reports) {
        Config::set('ts-publish.inertia.enabled', true);
        Route::get('ui-gadget', [UiGadgetPageController::class, 'show'])->name('ui.gadget');

        $reports(new RouteTransformer(UiGadgetPageController::class));
    });

    it('in a model accessor', function () use ($reports) {
        Schema::create('ui_gadget_holders', function (Blueprint $table) {
            $table->id();
        });

        $reports(new ModelTransformer(UiGadgetHolder::class));
    });

    it('in a model metadata companion', function () {
        // A provider sends plain data, so the snapshot stands in for a transform that typed a composed value.
        $transformer = new ReflectionClass(ModelMetadataTransformer::class)->newInstanceWithoutConstructor();
        $transformer->__unserialize(['typeImports' => [ComposedClassTypes::marker(Gadget::class) => ['GadgetType']]]);

        expect($transformer->composedClassTypes())->toBe([Gadget::class]);
    });

    it('as none in a file that reaches no composed class', function () {
        expect(new ResourceTransformer(UiPanelHelperResource::class)->composedClassTypes())->toBe([])
            ->and(new ClassTypeTransformer(Chart::class)->composedClassTypes())->toBe([]);
    });
});

describe('the generation cache', function () {
    beforeEach(function () {
        $this->cacheDir = sys_get_temp_dir().'/ts-publish-class-types-cache-'.uniqid();

        Config::set('ts-publish.cache.enabled', true);
        Config::set('ts-publish.cache.store', null);
        Config::set('ts-publish.cache.directory', $this->cacheDir);

        // Only the phases these tests read run, so each publish stays quick.
        disableBarrelPhasesBut('resources');
        Config::set('ts-publish.routes.enabled', false);
        Config::set('ts-publish.broadcast_channels.enabled', false);
    });

    it('republishes a composed module when an ancestor\'s override changes', function () {
        // PHP cannot reload a class, so the ancestor is a temp copy the test can edit; the fingerprint hashes content.
        $suffix = bin2hex(random_bytes(4));
        $sources = "$this->cacheDir-src";
        $namespace = 'AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Stubs\\ComposedAncestor\\';
        $resource = $namespace.'WidgetResource'.$suffix;

        Config::set('ts-publish.resources.additional_directories', [$resource]);
        Config::set('ts-publish.resources.included', [$resource]);

        // A cache hit rehydrates without the container, so each resolution here is a rebuild.
        $builds = ['modules' => 0, 'resources' => 0];
        $this->app->resolving(ClassTypeGenerator::class, function () use (&$builds): void {
            $builds['modules']++;
        });
        $this->app->resolving(ResourceGenerator::class, function () use (&$builds): void {
            $builds['resources']++;
        });

        try {
            mkdir($sources);

            foreach (['Base', 'Widget', 'WidgetResource'] as $name) {
                $stub = (string) file_get_contents(__DIR__.'/../Fixtures/Stubs/ComposedAncestor/'.$name.'.php.stub');
                file_put_contents($sources.'/'.$name.'.php', str_replace('__SUFFIX__', $suffix, $stub));
                require_once $sources.'/'.$name.'.php';
            }

            Config::set('ts-publish.class_types.additional_directories', []);

            expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
                ->and($builds)->toBe(['modules' => 1, 'resources' => 1]);

            expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
                ->and($builds)->toBe(['modules' => 1, 'resources' => 1]);

            file_put_contents($sources.'/Base.php', "\n// edited\n", FILE_APPEND);

            expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
                ->and($builds)->toBe(['modules' => 2, 'resources' => 2]);
        } finally {
            array_map(unlink(...), glob($sources.'/*.php') ?: []);
            @rmdir($sources);
        }
    });

    it('writes a composed module every cached consumer imports', function () {
        Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
        Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);

        $resourceBuilds = 0;
        $this->app->resolving(ResourceGenerator::class, function () use (&$resourceBuilds): void {
            $resourceBuilds++;
        });

        $directory = $this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui';

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);

        $module = (string) file_get_contents("$directory/gadget.ts");
        unlink("$directory/gadget.ts");

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and($resourceBuilds)->toBe(1)
            // The cached resource reached no class this run, so its stored imports named the module.
            ->and(ComposedClassTypes::registered())->toBe([])
            ->and(file_get_contents("$directory/gadget.ts"))->toBe($module)
            ->and(file_get_contents("$directory/index.ts"))->toBe("export * from './gadget';");
    });

    it('lists every composed class a partial run skips or republishes as the last full run did', function (string $flag) {
        Config::set('ts-publish.json.enabled', true);
        Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
        Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);
        $json = $this->out.'/'.Config::string('ts-publish.json.filename');

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
        $classTypes = json_decode((string) file_get_contents($json), true)['classTypes'];

        expect(array_keys($classTypes))->toBe([Chart::class, Dial::class, Gadget::class]);

        expect(Artisan::call('ts:publish', [$flag => true, '--quiet' => true]))->toBe(0)
            ->and(json_decode((string) file_get_contents($json), true)['classTypes'])->toBe($classTypes)
            ->and(file_get_contents($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/index.ts'))
            ->toBe("export * from './gadget';");
    })->with(['--only-class-types', '--only-resources']);
});

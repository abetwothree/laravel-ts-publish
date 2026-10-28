<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\ClassTypeGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\UiChartKindBroadcast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\UiGadgetBroadcast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiChartKindPageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiGadgetPageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiGadgetWidget;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiHelloController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiNodeController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiChartKindResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiGadgetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiPanelHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiTwoChartsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiChartKindHolder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiGadgetHolder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\FailingModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\UiChartCastMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\UiChartMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\UiChartZoneMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash\Chart as ClashChart;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GadgetShade;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Greets;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\HiddenGadget;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\HiddenGreets;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\SelfNamed;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\TwoPaths;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\UiShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Unimported;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ClassTypeTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\WatcherJsonWriter;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Events\UiBoardUpdated;
use Workbench\App\Http\Controllers\UiDashboardController;
use Workbench\App\Http\Resources\UiPanelResource;
use Workbench\App\Models\UiBoard;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Badge;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;

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

it('writes an interface\'s module as it would a class\'s', function () {
    expect(LaravelTsPublish::toTsType(Greets::class))
        ->toMatchArray(['type' => 'GreetsType', 'customImports' => [ComposedClassTypes::marker(Greets::class) => ['GreetsType']]])
        ->and(composedModule(Greets::class))->toBe(<<<'TS'
            import type { UiHello, UiIcon } from '@js/types/ui';

            export interface GreetsType extends UiHello, UiIcon {}

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

it('asks which barrel exports to keep only when a module is written', function () {
    Config::set('ts-publish.class_types.additional_directories', []);

    $runner = new class extends Runner
    {
        public int $asked = 0;

        /**
         * Count the question, then answer it as the runner does.
         *
         * @return Closure(string, string): bool
         */
        protected function preservedClassTypeBarrelExports(): Closure
        {
            $this->asked++;

            return parent::preservedClassTypeBarrelExports();
        }
    };
    $runner->shouldPublishEnums = false;
    $runner->shouldPublishModels = false;
    $runner->shouldPublishModelMetadata = false;
    $runner->shouldPublishResources = false;
    $runner->shouldPublishRoutes = false;
    $runner->shouldPublishFormRequests = false;
    $runner->shouldPublishBroadcastChannels = false;
    $runner->shouldPublishBroadcastEvents = false;
    $runner->run();

    expect($runner->classTypeGenerators)->toBeEmpty()
        ->and($runner->classTypeModularBarrels)->toBe([])
        ->and($runner->asked)->toBe(0);
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
        ->toBe([Gadget::class, Chart::class, Dial::class])
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

it('drops a stale export another phase wrote a file of that name for in another namespace', function () {
    disableBarrelPhasesBut('models');
    Config::set('ts-publish.models.included', [User::class]);

    $directory = $this->out.'/workbench/app/packages/ui';
    mkdir($directory, recursive: true);
    file_put_contents("$directory/index.ts", "export * from './chart';\nexport * from './user';");

    $runner = classTypesOnlyRunner();
    $runner->shouldPublishModels = true;
    $runner->run();

    expect(file_exists($this->out.'/workbench/app/models/user.ts'))->toBeTrue()
        ->and(file_get_contents("$directory/index.ts"))->toBe("export * from './chart';\nexport * from './dial';");
});

it('keeps a failed metadata companion\'s export in its own namespace\'s barrel, and in no other', function () {
    disableBarrelPhasesBut('models', 'model_metadata');
    Config::set('ts-publish.models.included', [User::class]);
    Config::set('ts-publish.model_metadata.provider_class', FailingModelMetadataProvider::class);

    // A composed class in the models' namespace, so the class-type phase rewrites their barrel too.
    $sources = $this->out.'-src';
    mkdir($sources);
    file_put_contents("$sources/UiModelsChart.php", (string) file_get_contents(__DIR__.'/../Fixtures/Stubs/ComposedModelNamespace/Chart.php.stub'));

    if (! class_exists('Workbench\\App\\Models\\UiModelsChart')) {
        require_once "$sources/UiModelsChart.php";
    }
    Config::set('ts-publish.class_types.additional_directories', [workbench_path('app/Packages/Ui'), 'Workbench\\App\\Models\\UiModelsChart']);

    $models = $this->out.'/workbench/app/models';
    $ui = $this->out.'/workbench/app/packages/ui';
    mkdir($models, recursive: true);
    mkdir($ui, recursive: true);
    file_put_contents("$models/index.ts", "export * from './user';\nexport * from './user_meta';");
    file_put_contents("$ui/index.ts", "export * from './chart';\nexport * from './user_meta';");

    try {
        $runner = classTypesOnlyRunner();
        $runner->shouldPublishModels = true;
        $runner->shouldPublishModelMetadata = true;
        $runner->run();

        expect($runner->modelMetadataFailures)->toHaveCount(1)
            ->and(file_get_contents("$models/index.ts"))
            ->toBe("export * from './ui-models-chart';\nexport * from './user';\nexport * from './user_meta';")
            ->and(file_get_contents("$ui/index.ts"))->toBe("export * from './chart';\nexport * from './dial';");
    } finally {
        (new Filesystem)->deleteDirectory($sources);
    }
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

it('lists the scanned composed classes and their contributors in the watcher file, and no class only a value reaches', function () {
    Config::set('ts-publish.output_to_files', false);
    Config::set('ts-publish.watcher.enabled', true);

    // A value reaching a class registers it, which the watcher does not read.
    ComposedClassTypes::register(Gadget::class);

    /** @var list<string> $paths */
    $paths = json_decode(new WatcherJsonWriter(new Filesystem)->write(), true);

    expect($paths)
        ->toContain('app/Packages/Ui/Chart.php')
        ->toContain('app/Packages/Ui/Dial.php')
        ->toContain('app/Packages/Ui/Node.php')
        ->toContain('app/Packages/Ui/HasIcon.php')
        ->toContain('app/Packages/Ui/Concerns/AnnouncesHello.php')
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

it('warns when a module shares the directory of a route barrel', function () {
    disableBarrelPhasesBut();
    Config::set('ts-publish.routes.enabled', true);
    Config::set('ts-publish.routes.only', ['ui.gadget']);
    Config::set('ts-publish.class_types.additional_directories', [UiGadgetWidget::class]);
    Route::get('ui-gadget', [UiGadgetPageController::class, 'show'])->name('ui.gadget');

    $runner = classTypesOnlyRunner();
    $runner->shouldPublishRoutes = true;
    $runner->run();

    expect(array_column(AnalysisWarnings::all(), 'message', 'subject'))
        ->toHaveKey(UiGadgetWidget::class)
        ->and(array_column(AnalysisWarnings::all(), 'message', 'subject')[UiGadgetWidget::class])
        ->toContain('abe-two-three/laravel-ts-publish/tests/fixtures/http/controllers')
        ->toContain('route');
});

it('warns when an override with no import spells the composed name', function () {
    expect(composedModule(SelfNamed::class))->toContain('export interface SelfNamedType extends SelfNamedType, UiNode {}')
        ->and(array_column(AnalysisWarnings::all(), 'message', 'subject')[SelfNamed::class] ?? '')->toContain('TS2310');
});

it('warns when two overrides import one name from two modules', function () {
    expect(composedModule(TwoPaths::class))->toBe(<<<'TS'
        import type { UiNode } from '@js/types/ui';
        import type { UiNode } from '@js/types/ui-next';

        export interface TwoPathsType extends UiNode {}

        TS)
        ->and(array_column(AnalysisWarnings::all(), 'message', 'subject')[TwoPaths::class] ?? '')->toContain('TS2300');
});

describe('a class that may not compose', function () {
    it('does not compose when class types are disabled', function () {
        Config::set('ts-publish.class_types.enabled', false);

        // Turning class types off is the project's choice, so the nearest override wins without a warning.
        expect(LaravelTsPublish::toTsType(Chart::class))
            ->toMatchArray(['type' => 'UiHello', 'customImports' => ['@js/types/ui' => ['UiHello']]])
            ->and(array_column(AnalysisWarnings::all(), 'message', 'subject'))->not->toHaveKey(Chart::class);

        disableBarrelPhasesBut('resources');
        Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
        Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);

        $runner = classTypesOnlyRunner();
        $runner->shouldPublishResources = true;
        $runner->run();

        $resource = (string) file_get_contents($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/http/resources/ui-gadget-resource.ts');

        expect($runner->classTypeGenerators)->toBeEmpty()
            ->and(ComposedClassTypes::registered())->toBe([])
            ->and(file_exists($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/gadget.ts'))->toBeFalse()
            ->and($resource)->toContain("import type { UiIcon } from '@js/types/ui';")
            ->toContain('gadget: UiIcon;')
            ->not->toContain('GadgetType')
            ->not->toContain('ts-publish:');
    });

    it('reads a missing class_types block as enabled', function () {
        Config::set('ts-publish', Arr::except(Config::array('ts-publish'), 'class_types'));
        disableBarrelPhasesBut('resources');
        Config::set('ts-publish.routes.enabled', false);
        Config::set('ts-publish.broadcast_channels.enabled', false);
        Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
        Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);

        expect(Config::has('ts-publish.class_types'))->toBeFalse()
            ->and(ComposedClassTypes::composes(Chart::class))->toBeTrue()
            ->and(LaravelTsPublish::toTsType(Chart::class)['type'])->toBe('ChartType')
            ->and(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and(file_exists($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/gadget.ts'))->toBeTrue();
    });

    it('does not compose an excluded class', function (string $class, array $config) {
        foreach ($config as $key => $value) {
            Config::set("ts-publish.class_types.{$key}", $value);
        }

        expect(ComposedClassTypes::composes($class))->toBeFalse()
            ->and(LaravelTsPublish::toTsType($class))
            ->toMatchArray(['type' => 'UiIcon', 'customImports' => ['@js/types/ui' => ['UiIcon']]])
            ->and(array_column(AnalysisWarnings::all(), 'message', 'subject'))->not->toHaveKey($class);
    })->with([
        'listed in class_types.excluded' => [Gadget::class, ['excluded' => [Gadget::class]]],
        'outside class_types.included' => [Gadget::class, ['included' => [Dial::class]]],
        'marked #[TsExclude]' => [HiddenGadget::class, []],
    ]);

    it('does not compose an interface marked #[TsExclude]', function () {
        expect(ComposedClassTypes::composes(HiddenGreets::class))->toBeFalse()
            ->and(LaravelTsPublish::toTsType(HiddenGreets::class))
            ->toMatchArray(['type' => 'UiHello', 'customImports' => ['@js/types/ui' => ['UiHello']]])
            ->and(ComposedClassTypes::registered())->toBe([]);
    });
});

describe('a controller the routes publisher writes', function () {
    beforeEach(function () {
        Route::get('ui-hello', [UiHelloController::class, 'index'])->name('ui.hello');
        Route::get('ui-node', [UiNodeController::class, 'index'])->name('ui.node');
    });

    it('publishes its nearest class-chain override, or nothing, and leaves a trait\'s and an interface\'s out', function (string $controller, ?string $type) {
        $override = ClassTypeOverride::resolve($controller);

        expect($override['type'] ?? null)->toBe($type)
            ->and($override['composed'] ?? false)->toBeFalse()
            ->and(array_column(AnalysisWarnings::all(), 'message', 'subject')[$controller] ?? '')
            ->toContain('leaves out [UiHello] from ['.AnnouncesHello::class.'], [UiIcon] from ['.HasIcon::class.']')
            ->toContain('route controller');
    })->with([
        'with no override on its class chain' => [UiHelloController::class, null],
        'whose parent class declares one' => [UiNodeController::class, 'UiNode'],
    ]);

    it('stays owned while the routes filters leave it out', function () {
        Config::set('ts-publish.routes.only', ['ui.node']);
        Config::set('ts-publish.routes.except', ['ui.hello']);

        expect(ClassTypeOverride::resolve(UiHelloController::class))->toBeNull()
            ->and(LaravelTsPublish::toTsType(UiHelloController::class)['type'])->not->toBe('UiHelloControllerType');
    });

    it('writes no module at its route file\'s path', function () {
        disableBarrelPhasesBut();
        Config::set('ts-publish.routes.enabled', true);
        Config::set('ts-publish.routes.only', ['ui.hello', 'ui.node']);
        Config::set('ts-publish.class_types.additional_directories', [UiHelloController::class, UiNodeController::class]);

        $runner = classTypesOnlyRunner();
        $runner->shouldPublishRoutes = true;
        $runner->run();

        $directory = $this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/http/controllers';

        expect($runner->classTypeGenerators)->toBeEmpty()
            ->and(file_get_contents("$directory/ui-hello-controller.ts"))->toContain('ui-hello')->not->toContain('export interface')
            ->and(file_get_contents("$directory/ui-node-controller.ts"))->toContain('ui-node')->not->toContain('export interface');
    });
});

it('owns a controller a route names in another case', function () {
    Route::get('ui-hello-cased', strtolower(UiHelloController::class).'@index');

    expect(ClassTypeOverride::resolve(UiHelloController::class))->toBeNull();
});

describe('imports a composed type from its module in each publisher', function () {
    /** Asserts no import line a transformer writes names a marker, and that its rehydrated snapshot still reports. */
    $resolvedAndReported = function (CoreTransformer $transformer, array $typeImports, array $classes): void {
        expect(array_filter(array_keys($typeImports), ComposedClassTypes::isMarker(...)))->toBe([])
            ->and($transformer->composedClassTypes())->toBe($classes)
            ->and(unserialize(serialize($transformer))->composedClassTypes())->toBe($classes);
    };

    it('in a broadcast event', function () use ($resolvedAndReported) {
        $transformer = new BroadcastEventTransformer(UiBoardUpdated::class);
        $event = new UiBoardUpdated(new Panel, new Gauge, new Pennies(5), new Badge, charts: [new Chart, new Chart]);
        $broadcast = new BroadcastEvent($event);
        $payload = sentAsJson(new ReflectionMethod($broadcast, 'getPayloadFromEvent')->invoke($broadcast, $event));

        expect(array_slice(array_map(fn (array $property): string => $property['type'], $transformer->properties), 5, 4))
            ->toBe(['chart' => 'ChartType', 'dial' => 'DialType', 'maybeChart' => 'ChartType | null', 'charts' => 'ChartType[]'])
            ->and($transformer->typeImports)->toBe([
                '@js/types/ui' => ['UiAmount', 'UiGauge', 'UiIcon', 'UiNode'],
                '../packages/ui' => ['ChartType', 'DialType'],
            ])
            ->and($payload->chart)->toBeObject()
            ->and($payload->dial)->toBeObject()
            ->and($payload->maybeChart)->toBeNull()
            ->and($payload->charts)->toBeArray()->toHaveCount(2);

        $resolvedAndReported($transformer, $transformer->typeImports, [Chart::class, Dial::class]);
    });

    it('in an API resource', function () use ($resolvedAndReported) {
        $transformer = new ResourceTransformer(UiPanelResource::class);
        $body = fn (bool $dial): mixed => sentAsJson(new UiPanelResource(null)->resolve(Request::create('/', 'GET', $dial ? ['dial' => 1] : [])));

        expect(array_map(fn (array $property): string => $property['type'], $transformer->properties))
            ->toMatchArray(['chart' => 'ChartType', 'dial' => 'DialType | null', 'maybe_chart' => 'ChartType | null'])
            ->and($transformer->typeImports)->toBe(['@js/types/ui' => ['UiAmount', 'UiGauge', 'UiNode', 'UiToneDto'], '../../packages/ui' => ['ChartType', 'DialType']])
            ->and($body(true)->chart)->toBeObject()
            ->and($body(true)->dial)->toBeObject()
            ->and($body(false)->dial)->toBeNull();

        $resolvedAndReported($transformer, $transformer->typeImports, [Chart::class, Dial::class]);
    });

    it('in an Inertia page', function () use ($resolvedAndReported) {
        Config::set('ts-publish.inertia.enabled', true);

        $transformer = new RouteTransformer(UiDashboardController::class);
        $request = Request::create('/ui/dashboard');
        $request->headers->set('X-Inertia', 'true');
        $props = sentAsJson(json_decode((string) new UiDashboardController()->index($request)->toResponse($request)->getContent(), true)['props']);

        expect($transformer->typeImports)->toBe(['@js/types/ui' => ['UiAmount', 'UiGauge', 'UiNode'], '../../packages/ui' => ['ChartType']])
            ->and($props->chart)->toBeObject()
            ->and($props->composed)->toBeObject();

        $resolvedAndReported($transformer, $transformer->typeImports, [Chart::class]);
    });

    it('in a model', function () use ($resolvedAndReported) {
        $transformer = new ModelTransformer(UiBoard::class);
        $data = $transformer->data()->toArray();

        expect($data['typeImports'])->toBe(['@js/types/ui' => ['UiNode'], '../packages/ui' => ['ChartType']])
            ->and($data['appends']['chart']['type'] ?? $data['mutators']['chart']['type'] ?? null)->toBe('ChartType')
            ->and(sentAsJson(UiBoard::query()->create()->fresh()?->toArray())->chart)->toBeObject();

        $resolvedAndReported($transformer, $data['typeImports'], [Chart::class]);
    });

    it('in shared data', function () {
        $shared = analyzeSharedDataFor(UiShareMiddleware::class);

        expect($shared['sharedPageProps'] ?? null)->toBe('{ node: UiNode, pennies: UiAmount, chart: ChartType }')
            ->and($shared['typeImports'] ?? null)->toBe(['./workbench/app/packages/ui' => ['ChartType'], '@js/types/ui' => ['UiAmount', 'UiNode']])
            ->and(sentAsJson(new UiShareMiddleware()->share(request()))->chart)->toBeObject();
    });

    it('in a model metadata companion', function () use ($resolvedAndReported) {
        Config::set('ts-publish.model_metadata.provider_class', UiChartMetadataProvider::class);

        $transformer = new ModelMetadataTransformer(UiBoard::class);
        $typeImports = $transformer->data()->typeImports;

        expect($transformer->propertyTypes)->toBe(['chart' => 'ChartType'])
            ->and($typeImports)->toBe(['../packages/ui' => ['ChartType']]);

        $resolvedAndReported($transformer, $typeImports, [Chart::class]);
    });

    it('in a model metadata companion, its names sorted where a cast imports from the same path', function () {
        Config::set('ts-publish.model_metadata.provider_class', UiChartZoneMetadataProvider::class);

        expect(new ModelMetadataTransformer(UiBoard::class)->data()->typeImports)
            ->toBe(['../packages/ui' => ['ChartType', 'ZoneType']]);
    });

    it('in a model metadata companion that throws for one name from two paths, naming the path it imports', function () {
        Config::set('ts-publish.model_metadata.provider_class', UiChartCastMetadataProvider::class);

        expect(fn () => new ModelMetadataTransformer(UiBoard::class))->toThrow(
            InvalidArgumentException::class,
            'imports [ChartType] from both [@js/types/ui] and [../packages/ui]',
        );
    });

    it('in analyze()', function () {
        $result = resolve(AstEngine::class)->analyze(UiPanelResource::class, fromNamespacePath: 'workbench/app/http/resources');

        expect($result->typeImports)->toBe(['@js/types/ui' => ['UiAmount', 'UiGauge', 'UiNode', 'UiToneDto'], '../../packages/ui' => ['ChartType', 'DialType']]);
    });
});

describe('aliases an enum type that a composed name also spells', function () {
    $composed = '../../../../../workbench/app/packages/ui';

    it('in an API resource', function () use ($composed) {
        $transformer = new ResourceTransformer(UiChartKindResource::class);

        expect(array_map(fn (array $property): string => $property['type'], $transformer->properties))
            ->toBe(['chart' => 'ChartType', 'kind' => 'FixturesChartType'])
            ->and($transformer->typeImports)->toBe([
                '../'.$composed => ['ChartType'],
                '../../enums' => ['ChartType as FixturesChartType'],
            ]);
    });

    it('in a broadcast event', function () use ($composed) {
        $transformer = new BroadcastEventTransformer(UiChartKindBroadcast::class);

        expect(array_map(fn (array $property): string => $property['type'], $transformer->properties))
            ->toBe(['chart' => 'ChartType', 'kind' => 'FixturesChartType'])
            ->and($transformer->typeImports)->toBe([
                $composed => ['ChartType'],
                '../enums' => ['ChartType as FixturesChartType'],
            ]);
    });

    it('in a model', function () use ($composed) {
        Schema::create('ui_chart_kind_holders', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
        });

        $data = new ModelTransformer(UiChartKindHolder::class)->data()->toArray();

        expect($data['columns']['kind']['type'])->toBe('FixturesChartType')
            ->and($data['typeImports'])->toBe([
                $composed => ['ChartType'],
                '../enums' => ['ChartType as FixturesChartType'],
            ]);
    });

    it('but not on an Inertia page, which aliases no import (a known gap)', function () use ($composed) {
        Config::set('ts-publish.inertia.enabled', true);
        Route::get('ui-chart-kind', [UiChartKindPageController::class, 'show'])->name('ui.chart-kind');

        expect(new RouteTransformer(UiChartKindPageController::class)->typeImports)->toBe([
            '../'.$composed => ['ChartType'],
            '../../enums' => ['ChartType'],
        ]);
    });
});

it('imports two composed classes of one name twice, and warns (a known gap)', function () {
    disableBarrelPhasesBut('resources');
    Config::set('ts-publish.resources.included', [UiTwoChartsResource::class]);
    Config::set('ts-publish.resources.additional_directories', [UiTwoChartsResource::class]);

    $runner = classTypesOnlyRunner();
    $runner->shouldPublishResources = true;
    $runner->run();

    $transformer = $runner->resourceGenerators->first()?->transformer;

    expect(array_map(fn (array $property): string => $property['type'], $transformer?->properties ?? []))
        ->toBe(['chart' => 'ChartType', 'clash' => 'ChartType'])
        ->and($transformer?->typeImports)->toBe([
            '../../../../../../workbench/app/packages/ui' => ['ChartType'],
            '../../ui/clash' => ['ChartType'],
        ])
        ->and(array_column(AnalysisWarnings::all(), 'message', 'subject')[UiTwoChartsResource::class] ?? '')
        ->toBe('Imports [ChartType] from composed classes ['.Chart::class.'] and ['.ClashChart::class.'], so its file '
            .'imports that name twice (TS2300), and its globals entry, if any, reads them all as ['.Chart::class.']. '
            .'Give all but one a #[TsType] of its own with inherit: false.');
});

it('writes no marker path in any generated file', function () {
    $marked = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(workbench_path('resources/js/types/data'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_contains((string) file_get_contents($file->getPathname()), ComposedClassTypes::MARKER_PREFIX)) {
            $marked[] = $file->getPathname();
        }
    }

    expect($marked)->toBe([]);
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

    it('rebuilds no module when an only-class-types run comes between two full runs', function () {
        Config::set('ts-publish.resources.enabled', false);
        Config::set('ts-publish.models.enabled', true);
        Config::set('ts-publish.models.included', [User::class]);
        // Retaining a skipped feature for these files builds the model set, which would hide the run that does not.
        Config::set('ts-publish.globals.enabled', false);
        Config::set('ts-publish.json.enabled', false);

        $builds = 0;
        $this->app->resolving(ClassTypeGenerator::class, function () use (&$builds): void {
            $builds++;
        });

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and($builds)->toBe(2)
            ->and(Artisan::call('ts:publish', ['--only-class-types' => true, '--quiet' => true]))->toBe(0)
            ->and($builds)->toBe(2)
            ->and(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
            ->and($builds)->toBe(2);
    });

    it('keeps the composed-class order when a consumer is edited', function () {
        // PHP cannot reload a class, so each reader is a temp copy the test can edit.
        $suffix = bin2hex(random_bytes(4));
        $sources = "$this->cacheDir-src";
        $namespace = 'AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Stubs\\ComposedOrder\\';
        $readers = [$namespace.'GadgetReader'.$suffix, $namespace.'UnimportedReader'.$suffix];

        Config::set('ts-publish.json.enabled', true);
        Config::set('ts-publish.resources.additional_directories', $readers);
        Config::set('ts-publish.resources.included', $readers);
        Config::set('ts-publish.class_types.additional_directories', []);
        $json = $this->out.'/'.Config::string('ts-publish.json.filename');

        try {
            mkdir($sources);

            foreach (['GadgetReader', 'UnimportedReader'] as $name) {
                $stub = (string) file_get_contents(__DIR__.'/../Fixtures/Stubs/ComposedOrder/'.$name.'.php.stub');
                file_put_contents($sources.'/'.$name.'.php', str_replace('__SUFFIX__', $suffix, $stub));
                require_once $sources.'/'.$name.'.php';
            }

            expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
            $published = (string) file_get_contents($json);

            // Only the second reader rebuilds, so it alone registers its class this run.
            file_put_contents($sources.'/UnimportedReader.php', "\n// edited\n", FILE_APPEND);

            expect(array_keys(json_decode($published, true)['classTypes']))->toBe([Gadget::class, Unimported::class])
                ->and(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
                ->and(file_get_contents($json))->toBe($published);
        } finally {
            array_map(unlink(...), glob($sources.'/*.php') ?: []);
            @rmdir($sources);
        }
    });

    it('lists every composed class a partial run skips or republishes as the last full run did', function (string $flag) {
        Config::set('ts-publish.json.enabled', true);
        Config::set('ts-publish.resources.included', [UiGadgetResource::class]);
        Config::set('ts-publish.resources.additional_directories', [UiGadgetResource::class]);
        $json = $this->out.'/'.Config::string('ts-publish.json.filename');

        expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0);
        $classTypes = json_decode((string) file_get_contents($json), true)['classTypes'];

        expect(array_keys($classTypes))->toBe([Gadget::class, Chart::class, Dial::class]);

        expect(Artisan::call('ts:publish', [$flag => true, '--quiet' => true]))->toBe(0)
            ->and(json_decode((string) file_get_contents($json), true)['classTypes'])->toBe($classTypes)
            ->and(file_get_contents($this->out.'/abe-two-three/laravel-ts-publish/tests/fixtures/ui/index.ts'))
            ->toBe("export * from './gadget';");
    })->with(['--only-class-types', '--only-resources']);
});

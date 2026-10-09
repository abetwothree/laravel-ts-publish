<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CountingTsTypeString;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\FacilityRoster;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RecordingModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RosterEntry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Http\Resources\FacilityResource;
use Workbench\App\Http\Resources\ImageMorphResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\BaseExtendableModel;
use Workbench\App\Models\ExcludedModel;
use Workbench\App\Models\Facility;
use Workbench\App\Models\Laravel13Connection;
use Workbench\App\Packages\Audit\Models\AuditTrail;

beforeEach(function () {
    config()->set('ts-publish.output_to_files', false);
});

test('generates single enum from FQCN', function () {
    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->run();

    expect($runner->enumGenerators)->toHaveCount(1)
        ->and($runner->enumGenerators->first())->toBeInstanceOf(EnumGenerator::class)
        ->and($runner->enumGenerators->first()->transformer->enumName)->toBe('Status')
        ->and($runner->modelGenerators)->toHaveCount(0);
});

test('warns once of an enum method value whose integer keys an EnumResource response re-indexes', function () {
    (new RunnerForSource('Workbench\App\Enums\Priority'))->run();

    $warning = [
        'subject' => 'Workbench\App\Enums\Priority',
        'message' => 'Method [filterByMinimum] returns an array whose numeric keys are not 0 to n-1 in order, so the '
            .'published enum writes it as an object while an EnumResource response re-indexes it into a list. Wrap it '
            .'in array_values() for a list, or use non-numeric keys for an object.',
    ];

    expect(array_keys(AnalysisWarnings::all(), $warning, true))->toHaveCount(1);
});

test('generates single model from FQCN', function () {
    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelGenerators->first())->toBeInstanceOf(ModelGenerator::class)
        ->and($runner->modelGenerators->first()->transformer->modelName)->toBe('User')
        ->and($runner->modelMetadataGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators->first())->toBeInstanceOf(ModelMetadataGenerator::class)
        ->and($runner->enumGenerators)->toHaveCount(0);
});

test('does not generate model metadata from source when its phase is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->shouldPublishModelMetadata = false;
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators)->toBeEmpty();
});

test('does not generate source metadata excluded by its phase filters', function () {
    config()->set('ts-publish.model_metadata.excluded', ['Workbench\App\Models\User']);

    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators)->toBeEmpty();
});

test('does not generate a source model outside its included filter', function () {
    config()->set('ts-publish.models.included', ['Workbench\App\Models\Address']);
    config()->set('ts-publish.model_metadata.included', []);

    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->run();

    expect($runner->modelGenerators)->toBeEmpty()
        ->and($runner->modelMetadataGenerators)->toHaveCount(1);
});

test('throws when source model and metadata filters both exclude the model', function () {
    config()->set('ts-publish.models.excluded', ['Workbench\\App\\Models\\User']);
    config()->set('ts-publish.model_metadata.excluded', ['Workbench\\App\\Models\\User']);

    $runner = new RunnerForSource('Workbench\\App\\Models\\User');
    $runner->run();
})->throws(
    InvalidArgumentException::class,
    'Nothing to publish for Workbench\\App\\Models\\User: models are excluded by filters; model metadata is excluded by filters',
);

test('names the disabled phase when a flag skipped it and filters excluded the other', function () {
    config()->set('ts-publish.model_metadata.excluded', ['Workbench\\App\\Models\\User']);

    $runner = new RunnerForSource('Workbench\\App\\Models\\User');
    $runner->shouldPublishModels = false;
    $runner->run();
})->throws(
    InvalidArgumentException::class,
    'Nothing to publish for Workbench\\App\\Models\\User: models are disabled; model metadata is excluded by filters',
);

test('generates single enum from file path', function () {
    $filePath = workbench_path('app/Enums/Status.php');

    $runner = new RunnerForSource($filePath);
    $runner->run();

    expect($runner->enumGenerators)->toHaveCount(1)
        ->and($runner->enumGenerators->first()->transformer->enumName)->toBe('Status');
});

test('generates single model from file path', function () {
    $filePath = workbench_path('app/Models/User.php');

    $runner = new RunnerForSource($filePath);
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelGenerators->first()->transformer->modelName)->toBe('User');
});

test('throws for non-existent class', function () {
    $runner = new RunnerForSource('App\NonExistent\FakeClass');
    $runner->run();
})->throws(InvalidArgumentException::class, 'Class does not exist');

test('throws for class that is not enum or model', function () {
    $runner = new RunnerForSource(RunnerForSource::class);
    $runner->run();
})->throws(InvalidArgumentException::class, 'not a publishable enum, model, resource, controller, form request, or broadcast event');

test('throws for file that does not contain a class', function () {
    $runner = new RunnerForSource(workbench_path('routes/web.php'));
    $runner->run();
})->throws(InvalidArgumentException::class);

test('barrel and globals content remain empty', function () {
    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->run();

    expect($runner->enumModularBarrels)->toBe([])
        ->and($runner->modelModularBarrels)->toBe([])
        ->and($runner->globalsContent)->toBe('')
        ->and($runner->jsonContent)->toBe('')
        ->and($runner->watcherJsonContent)->toBe('');
});

test('writes single enum file to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-source-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->run();

    expect(file_exists("$outputDir/workbench/app/enums/status.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('writes single model and metadata files to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-source-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->run();

    expect(file_exists("$outputDir/workbench/app/models/user.ts"))->toBeTrue();
    expect(file_exists("$outputDir/workbench/app/models/user_meta.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('throws when enum publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->shouldPublishEnums = false;
    $runner->run();
})->throws(InvalidArgumentException::class, 'Nothing to publish for Workbench\\App\\Enums\\Status: enums are disabled');

test('generates model metadata when model publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->shouldPublishModels = false;
    $runner->run();

    expect($runner->modelGenerators)->toBeEmpty()
        ->and($runner->modelMetadataGenerators)->toHaveCount(1);
});

test('throws when model and model metadata publishing are disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->shouldPublishModels = false;
    $runner->shouldPublishModelMetadata = false;
    $runner->run();
})->throws(
    InvalidArgumentException::class,
    'Nothing to publish for Workbench\\App\\Models\\User: models are disabled; model metadata is disabled',
);

test('generates single resource from FQCN', function () {
    $runner = new RunnerForSource('Workbench\App\Http\Resources\PostResource');
    $runner->run();

    expect($runner->resourceGenerators)->toHaveCount(1)
        ->and($runner->resourceGenerators->first())->toBeInstanceOf(ResourceGenerator::class)
        ->and($runner->enumGenerators)->toHaveCount(0)
        ->and($runner->modelGenerators)->toHaveCount(0);
});

test('throws when resource publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Http\Resources\PostResource');
    $runner->shouldPublishResources = false;
    $runner->run();
})->throws(InvalidArgumentException::class, 'resources are disabled');

test('generates single route from controller FQCN', function () {
    $runner = new RunnerForSource('Workbench\App\Http\Controllers\PostController');
    $runner->run();

    expect($runner->routeGenerators)->toHaveCount(1)
        ->and($runner->routeGenerators->first())->toBeInstanceOf(RouteGenerator::class)
        ->and($runner->enumGenerators)->toHaveCount(0)
        ->and($runner->modelGenerators)->toHaveCount(0)
        ->and($runner->resourceGenerators)->toHaveCount(0);
});

test('throws when route publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Http\Controllers\PostController');
    $runner->shouldPublishRoutes = false;
    $runner->run();
})->throws(InvalidArgumentException::class, 'routes are disabled');

test('throws for controller with TsExclude attribute', function () {
    $runner = new RunnerForSource('Workbench\App\Http\Controllers\ExcludedController');
    $runner->run();
})->throws(InvalidArgumentException::class, 'not a publishable enum, model, resource, controller, form request, or broadcast event');

test('generates single broadcast event from FQCN', function () {
    $runner = new RunnerForSource('Workbench\App\Events\OrderShipped');
    $runner->run();

    expect($runner->broadcastEventGenerators)->toHaveCount(1)
        ->and($runner->broadcastEventGenerators->first())->toBeInstanceOf(BroadcastEventGenerator::class)
        ->and($runner->broadcastEventGenerators->first()->transformer->eventName)->toBe('OrderShipped')
        ->and($runner->enumGenerators)->toHaveCount(0)
        ->and($runner->modelGenerators)->toHaveCount(0);
});

test('generates single broadcast event from file path', function () {
    $filePath = workbench_path('app/Events/OrderShipped.php');

    $runner = new RunnerForSource($filePath);
    $runner->run();

    expect($runner->broadcastEventGenerators)->toHaveCount(1)
        ->and($runner->broadcastEventGenerators->first()->transformer->eventName)->toBe('OrderShipped');
});

test('throws when broadcast event publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Events\OrderShipped');
    $runner->shouldPublishBroadcastEvents = false;
    $runner->run();
})->throws(InvalidArgumentException::class, 'broadcast events are disabled');

test('a --source run clears a full run\'s stale registry instead of narrowing against it', function () {
    config()->set('ts-publish.resources.excluded', [UserResource::class]);

    $fullRunner = new Runner;
    $fullRunner->run();

    expect(PublishedResourceRegistry::isPublished(UserResource::class))->toBeFalse();

    $sourceRunner = new RunnerForSource('Workbench\App\Http\Resources\MerchantResource');
    $sourceRunner->run();

    expect($sourceRunner->resourceGenerators->first())->toBeInstanceOf(ResourceGenerator::class);

    expect($sourceRunner->resourceGenerators->first()->content)
        ->toContain('owner_via_closure?: UserResource | null;')
        ->not->toContain('owner_via_closure?: unknown;');
});

test('a --source run drops the global qualifications an earlier run memoized', function () {
    $service = new CountingTsTypeString;
    TsTypeString::swap($service);
    TsTypeString::qualifyGlobalType('User', ['app.models' => ['User']]);

    new RunnerForSource('Workbench\App\Enums\Status')->run();
    TsTypeString::qualifyGlobalType('User', ['app.models' => ['User']]);

    expect($service->qualifications)->toBe(2);
});

test('a --source run clears a leftover AnalysisWarnings entry instead of leaking it', function () {
    AnalysisWarnings::add('Some\Stale\Controller@index', 'stale warning from an earlier run');

    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->run();

    expect(AnalysisWarnings::all())->toBe([]);
});

test('a --source run drops a class map memoized before it so the disk is rescanned', function () {
    $cache = new ReflectionProperty(CoreCollector::class, 'classMaps');
    $cache->setValue(null, ['/a/directory/scanned/by/an/earlier/run' => []]);

    $runner = new RunnerForSource('Workbench\\App\\Enums\\Status');
    $runner->run();

    expect($cache->getValue())->not->toHaveKey('/a/directory/scanned/by/an/earlier/run');
});

test('a model --source run reads the same published model set a full run does', function () {
    $runner = new RunnerForSource(Facility::class);
    $runner->run();

    expect(PublishedModelRegistry::isPublished(AuditTrail::class))->toBeTrue()
        ->and(PublishedModelRegistry::isPublished(ExcludedModel::class))->toBeFalse()
        ->and($runner->modelGenerators->first()->content)
        ->toContain('audit_trails: AuditTrail[];')
        ->not->toContain('excluded_records');
});

test('a model --source run builds the morph map once through a project\'s buildMorphTargetMap() override', function () {
    $resolver = new RecordingModelAttributeResolver;
    app()->instance(ModelAttributeResolver::class, $resolver);

    (new RunnerForSource(Facility::class))->run();

    expect($resolver->morphTargetMapBuilds)->toHaveCount(1)
        ->and($resolver->morphTargetMapBuilds[0])->toContain(Facility::class, AuditTrail::class);
});

test('a --source run generates the file of a model that is only published on demand', function () {
    $runner = new RunnerForSource(AuditTrail::class);
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelGenerators->first()->content)
        ->toContain('export interface AuditTrail')
        ->toContain('notes: AuditNote[];');
});

test('a --source run of a class that is no model reads the same published model set', function () {
    $runner = new RunnerForSource(FacilityResource::class);
    $runner->run();

    expect(PublishedModelRegistry::isPublished(AuditTrail::class))->toBeTrue()
        ->and($runner->resourceGenerators->first()->content)
        ->toContain('audit_trails: AuditTrail[];')
        ->toContain('excluded_records: unknown;')
        ->not->toContain('ExcludedModel');
});

test('a resource --source run types a morphTo as a full run does', function () {
    $runner = new RunnerForSource(ImageMorphResource::class);
    $runner->run();

    expect($runner->resourceGenerators->first()->content)
        ->toContain('imageable: Post | Product | WorkbenchUser | CrmUser;');
});

test('an enum --source run reads no model set', function () {
    (new Runner)->run();
    (new RunnerForSource('Workbench\App\Enums\Status'))->run();

    expect(PublishedModelRegistry::isEmpty())->toBeTrue();
});

test('a resource --source run reads the tables of the models it reads, not those of every collected model', function () {
    // The first query of a test migrates the lazily refreshed database, so it must not be counted.
    DB::select('select 1');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    (new RunnerForSource(FacilityResource::class))->run();

    $warned = array_column(AnalysisWarnings::all(), 'subject');

    expect($queries)->toBeLessThan(resolve(ModelsCollector::class)->collect()->count())
        ->and($warned)->not->toContain(BaseExtendableModel::class)->not->toContain(Laravel13Connection::class);
});

test('a --source run of a model that is only published on demand writes no metadata companion', function () {
    $runner = new RunnerForSource(AuditTrail::class);
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelMetadataGenerators)->toBeEmpty();
});

test('a --source run publishes a model on a database view when a collected model relates to it', function () {
    DB::statement('create view roster_entries as select id, name from facilities');
    config()->set('ts-publish.models.additional_directories', [
        ...config()->array('ts-publish.models.additional_directories'),
        FacilityRoster::class,
    ]);

    $runner = new RunnerForSource(FacilityRoster::class);
    $runner->run();

    expect(PublishedModelRegistry::isPublished(RosterEntry::class))->toBeTrue()
        ->and($runner->modelGenerators->first()->content)->toContain('entries: RosterEntry[];');
});

test('a --source run starts with an empty analysis memo', function () {
    $memo = resolve(AnalysisMemo::class);
    $memo->remember('an-earlier-run', fn (): string => 'stale');

    (new RunnerForSource('Workbench\\App\\Enums\\Status'))->run();

    // The registry versions alone reject a stale answer, so only a dropped entry proves reset().
    expect((fn (): array => $this->entries)->call($memo))->not->toHaveKey('an-earlier-run');
});

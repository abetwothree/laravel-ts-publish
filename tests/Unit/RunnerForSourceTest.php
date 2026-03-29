<?php

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use Illuminate\Filesystem\Filesystem;

use function Orchestra\Testbench\workbench_path;

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

test('generates single model from FQCN', function () {
    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->run();

    expect($runner->modelGenerators)->toHaveCount(1)
        ->and($runner->modelGenerators->first())->toBeInstanceOf(ModelGenerator::class)
        ->and($runner->modelGenerators->first()->transformer->modelName)->toBe('User')
        ->and($runner->enumGenerators)->toHaveCount(0);
});

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
})->throws(InvalidArgumentException::class, 'not a publishable enum, model, resource, or controller');

test('throws for file that does not contain a class', function () {
    $runner = new RunnerForSource(workbench_path('routes/web.php'));
    $runner->run();
})->throws(InvalidArgumentException::class);

test('barrel and globals content remain empty', function () {
    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->run();

    expect($runner->enumBarrelContent)->toBe('')
        ->and($runner->modelBarrelContent)->toBe('')
        ->and($runner->enumModularBarrels)->toBe([])
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

    expect(file_exists("$outputDir/enums/status.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('writes single model file to disk', function () {
    $outputDir = sys_get_temp_dir().'/laravel-ts-publish-source-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.output_to_files', true);

    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->run();

    expect(file_exists("$outputDir/models/user.ts"))->toBeTrue();

    // Cleanup
    (new Filesystem)->deleteDirectory($outputDir);
});

test('throws when enum publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Enums\Status');
    $runner->shouldPublishEnums = false;
    $runner->run();
})->throws(InvalidArgumentException::class, 'Enum publishing is disabled');

test('throws when model publishing is disabled', function () {
    $runner = new RunnerForSource('Workbench\App\Models\User');
    $runner->shouldPublishModels = false;
    $runner->run();
})->throws(InvalidArgumentException::class, 'Model publishing is disabled');

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
})->throws(InvalidArgumentException::class, 'Resource publishing is disabled');

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;
use Illuminate\Filesystem\Filesystem;

test('writes json content when enabled', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);

    expect($decoded)
        ->toHaveKey('models')
        ->toHaveKey('enums')
        ->and($decoded['models'])->toHaveKey('Workbench\App\Models\User')
        ->and($decoded['models']['Workbench\App\Models\User']['name'])->toBe('User')
        ->and($decoded['enums'])->toHaveKey('Workbench\App\Enums\Status')
        ->and($decoded['enums']['Workbench\App\Enums\Status']['name'])->toBe('Status');
});

test('models map keys by FQCN so same-basename models cannot collapse', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $decoded = json_decode($writer->write($runner), true);

    expect($decoded['models'])->toHaveKey('Workbench\App\Models\Sales\Report\Report')
        ->and($decoded['models'])->toHaveKey('Workbench\App\Models\Marketing\Report\Report')
        ->and($decoded['models']['Workbench\App\Models\Sales\Report\Report']['name'])->toBe('Report')
        ->and($decoded['models'])->toHaveKey('Workbench\App\Models\User')
        ->and($decoded['models'])->toHaveKey('Workbench\Crm\Models\User')
        ->and($decoded['models'])->toHaveKey('Workbench\App\Models\TrackingEvent')
        ->and($decoded['models'])->toHaveKey('Workbench\Shipping\Models\TrackingEvent');
});

test('every discovered model appears in the definitions json', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $decoded = json_decode($writer->write($runner), true);

    // Relative to the discovered set (absolute count lives in ModelsFinderTest): a same-basename
    // key collapse still fails, since the map would shrink below the generator count.
    expect($decoded['models'])->toHaveCount($runner->modelGenerators->count());
});

test('enums map keys by FQCN so same-basename enums cannot collapse', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $decoded = json_decode($writer->write($runner), true);

    expect($decoded['enums'])->toHaveKey('Workbench\App\Enums\Status')
        ->and($decoded['enums'])->toHaveKey('Workbench\Crm\Enums\Status')
        ->and($decoded['enums']['Workbench\Crm\Enums\Status']['name'])->toBe('Status')
        ->and($decoded['enums'])->toHaveCount($runner->enumGenerators->count());
});

test('resources map keys by FQCN so same-basename resources cannot collapse', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $decoded = json_decode($writer->write($runner), true);

    expect($decoded['resources'])->toHaveKey('Workbench\App\Http\Resources\UserResource')
        ->and($decoded['resources'])->toHaveKey('Workbench\Crm\Http\Resources\UserResource')
        ->and($decoded['resources']['Workbench\Crm\Http\Resources\UserResource']['name'])->toBe('UserResource')
        ->and($decoded['resources'])->toHaveCount($runner->resourceGenerators->count());
});

test('form requests map keys by FQCN and carries the short type name', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $decoded = json_decode($writer->write($runner), true);

    expect($decoded['formRequests'])->toHaveKey('Workbench\App\Http\Requests\StorePostRequest')
        ->and($decoded['formRequests']['Workbench\App\Http\Requests\StorePostRequest']['name'])->toBe('StorePostRequest');
});

test('broadcast events map keys by FQCN and carries the short type name, not the echo channel string', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $decoded = json_decode($writer->write($runner), true);
    $event = $decoded['broadcastEvents']['Workbench\App\Events\OrderShipped'];

    expect($decoded['broadcastEvents'])->toHaveKey('Workbench\App\Events\OrderShipped')
        ->and($event['name'])->toBe('OrderShipped')
        ->and($event['eventName'])->toBe('OrderShipped')
        ->and($event['broadcastName'])->not->toBe($event['name']);
});

test('returns empty string when json output is disabled', function () {
    config()->set('ts-publish.json.enabled', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('json models contain columns as name/type pairs', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);
    $userModel = $decoded['models']['Workbench\App\Models\User'];

    $nameField = collect($userModel['properties'])->firstWhere('name', 'name');
    expect($userModel['name'])->toBe('User')
        ->and($nameField)->toBe(['name' => 'name', 'type' => 'string']);
});

test('json enums contain cases and methods', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);

    $decoded = json_decode($content, true);
    $status = $decoded['enums']['Workbench\App\Enums\Status'];

    expect($status)
        ->toHaveKey('name')
        ->toHaveKey('cases')
        ->toHaveKey('caseKinds')
        ->toHaveKey('caseTypes')
        ->toHaveKey('methods')
        ->toHaveKey('staticMethods');
});

test('json resources include typeAlias for flat collections', function () {
    config()->set('ts-publish.json.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new JsonWriter(new Filesystem);
    $content = $writer->write($runner);
    $decoded = json_decode($content, true);

    expect($decoded['resources'])->toHaveKey('Workbench\App\Http\Resources\PostFlatCollection');
    expect($decoded['resources']['Workbench\App\Http\Resources\PostFlatCollection'])
        ->toBe(['name' => 'PostFlatCollection', 'typeAlias' => 'PostResource[]']);
})->skip(fn () => ! version_compare(app()->version(), '13', '>='));

test('writes json file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.json.enabled', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-definitions.json') && str_contains($content, '"models"');
        });

    config()->set('ts-publish.output_to_files', true);

    $runner = resolve(Runner::class);
    $runner->shouldPublishModelMetadata = false;
    $runner->run();

    $writer = new JsonWriter($filesystem);
    $writer->write($runner);
});

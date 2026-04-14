<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\LaravelTsPublishServiceProvider;
use AbeTwoThree\LaravelTsPublish\Listeners\PostMigrateRunner;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Events\Dispatcher;

test('service provider configures package correctly', function () {
    expect(config('ts-publish'))->toBeArray()
        ->and(config('ts-publish.models.writer_class'))->not->toBeNull()
        ->and(config('ts-publish.enums.writer_class'))->not->toBeNull();
});

test('service provider registers the ts:publish command', function () {
    $this->artisan('ts:publish', ['--preview' => 'true'])
        ->assertSuccessful();
});

test('service provider skips listener registration in unit tests', function () {
    config()->set('ts-publish.run_after_migrate', true);
    config()->set('ts-publish.output_to_files', true);

    /** @var Dispatcher $events */
    $events = app()->make(Dispatcher::class);

    PostMigrateRunner::$shouldRun = false;
    $events->dispatch(new MigrationsEnded('default'));

    expect(PostMigrateRunner::$shouldRun)->toBeFalse();
});

test('packageBooted registers listeners when conditions are met', function () {
    config()->set('ts-publish.run_after_migrate', true);
    config()->set('ts-publish.output_to_files', true);

    // Create a partial mock of the app that returns false for runningUnitTests
    $app = Mockery::mock(app())->makePartial();
    $app->shouldReceive('runningUnitTests')->andReturn(false);

    $provider = new LaravelTsPublishServiceProvider($app);

    // Call packageBooted — it should register listeners
    $provider->packageBooted();

    // Now MigrationsEnded should set shouldRun to true
    PostMigrateRunner::$shouldRun = false;

    /** @var Dispatcher $events */
    $events = $app->make(Dispatcher::class);
    $events->dispatch(new MigrationsEnded('default'));

    expect(PostMigrateRunner::$shouldRun)->toBeTrue();

    // Reset
    PostMigrateRunner::$shouldRun = false;
});

test('packageBooted skips listeners when run_after_migrate is false', function () {
    config()->set('ts-publish.run_after_migrate', false);
    config()->set('ts-publish.output_to_files', true);

    $app = Mockery::mock(app())->makePartial();
    $app->shouldReceive('runningUnitTests')->andReturn(false);

    $provider = new LaravelTsPublishServiceProvider($app);
    $provider->packageBooted();

    PostMigrateRunner::$shouldRun = false;

    /** @var Dispatcher $events */
    $events = $app->make(Dispatcher::class);
    $events->dispatch(new MigrationsEnded('default'));

    expect(PostMigrateRunner::$shouldRun)->toBeFalse();
});

test('packageBooted skips listeners when output_to_files is false', function () {
    config()->set('ts-publish.run_after_migrate', true);
    config()->set('ts-publish.output_to_files', false);

    $app = Mockery::mock(app())->makePartial();
    $app->shouldReceive('runningUnitTests')->andReturn(false);

    $provider = new LaravelTsPublishServiceProvider($app);
    $provider->packageBooted();

    PostMigrateRunner::$shouldRun = false;

    /** @var Dispatcher $events */
    $events = $app->make(Dispatcher::class);
    $events->dispatch(new MigrationsEnded('default'));

    expect(PostMigrateRunner::$shouldRun)->toBeFalse();
});

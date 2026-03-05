<?php

use AbeTwoThree\LaravelTsPublish\Listeners\PostMigrateRunner;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

test('handle runs ts:publish when shouldRun is true', function () {
    PostMigrateRunner::$shouldRun = true;

    $artisan = Mockery::mock(Artisan::class);
    $artisan->shouldReceive('call')->once();

    $listener = new PostMigrateRunner($artisan);

    $event = new CommandFinished(
        'migrate',
        new ArrayInput([]),
        new BufferedOutput,
        0
    );

    $listener->handle($event);

    expect(PostMigrateRunner::$shouldRun)->toBeFalse();
});

test('handle does nothing when shouldRun is false', function () {
    PostMigrateRunner::$shouldRun = false;

    $artisan = Mockery::mock(Artisan::class);
    $artisan->shouldNotReceive('call');

    $listener = new PostMigrateRunner($artisan);

    $event = new CommandFinished(
        'migrate',
        new ArrayInput([]),
        new BufferedOutput,
        0
    );

    $listener->handle($event);

    expect(PostMigrateRunner::$shouldRun)->toBeFalse();
});

test('handle resets shouldRun to false after execution', function () {
    PostMigrateRunner::$shouldRun = true;

    $artisan = Mockery::mock(Artisan::class);
    $artisan->shouldReceive('call')->once();

    $listener = new PostMigrateRunner($artisan);

    $event = new CommandFinished(
        'migrate',
        new ArrayInput([]),
        new BufferedOutput,
        0
    );

    $listener->handle($event);
    // Second call should not trigger artisan
    $listener->handle($event);
});

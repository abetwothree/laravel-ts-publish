<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Commands\TsPublishCommand;
use AbeTwoThree\LaravelTsPublish\Listeners\PostMigrateRunner;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

test('handle runs ts:publish with --fresh when shouldRun is true', function () {
    PostMigrateRunner::$shouldRun = true;

    $artisan = Mockery::mock(Artisan::class);
    $artisan->shouldReceive('call')
        ->once()
        ->with(TsPublishCommand::class, ['--fresh' => true], Mockery::any());

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

it('does not republish when the command that finished after migrations was not migrate', function () {
    PostMigrateRunner::$shouldRun = true;

    $artisan = Mockery::mock(Artisan::class);
    $artisan->shouldNotReceive('call');

    (new PostMigrateRunner($artisan))->handle(new CommandFinished('app:deploy', new ArrayInput([]), new NullOutput, 0));

    expect(PostMigrateRunner::$shouldRun)->toBeTrue();
});

it('republishes after any migrate:* command', function (string $command) {
    PostMigrateRunner::$shouldRun = true;

    $artisan = Mockery::mock(Artisan::class);
    $artisan->shouldReceive('call')->once()->withArgs(fn ($cmd, $args) => $args === ['--fresh' => true]);

    (new PostMigrateRunner($artisan))->handle(new CommandFinished($command, new ArrayInput([]), new NullOutput, 0));

    expect(PostMigrateRunner::$shouldRun)->toBeFalse();
})->with(['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:rollback']);

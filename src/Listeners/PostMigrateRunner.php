<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Listeners;

use AbeTwoThree\LaravelTsPublish\Commands\TsPublishCommand;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel as Artisan;

class PostMigrateRunner
{
    public static bool $shouldRun = false;

    public function __construct(protected Artisan $artisan) {}

    /**
     * Handle the event.
     */
    public function handle(CommandFinished $event): void
    {
        if (! self::$shouldRun) {
            return;
        }

        self::$shouldRun = false;

        $this->artisan->call(TsPublishCommand::class, [], $event->output);
    }
}

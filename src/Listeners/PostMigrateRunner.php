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

        // Migrations change the database schema, which is NOT part of the
        // generation cache fingerprint (it has no source file). Run with
        // --fresh so the post-migration republish always reflects the new
        // schema — restoring the pre-cache "always full rebuild" behavior.
        $this->artisan->call(TsPublishCommand::class, ['--fresh' => true], $event->output);
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Commands\TsPublishCommand;
use AbeTwoThree\LaravelTsPublish\Listeners\PostMigrateRunner;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Events\Dispatcher;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTsPublishServiceProvider extends PackageServiceProvider
{
    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(ModelAttributeResolver::class);
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-ts-publish')
            ->hasConfigFile()
            ->hasCommand(TsPublishCommand::class)
            ->hasViews('laravel-ts-publish');
    }

    #[Override]
    public function packageBooted(): void
    {
        if (! $this->app->runningUnitTests()
            && config()->boolean('ts-publish.run_after_migrate')
            && config()->boolean('ts-publish.output_to_files')) {
            /** @var Dispatcher $events */
            $events = $this->app->make(Dispatcher::class);

            $events->listen(CommandFinished::class, PostMigrateRunner::class);
            $events->listen(MigrationsEnded::class, function (): void {
                PostMigrateRunner::$shouldRun = true;
            });
        }
    }
}

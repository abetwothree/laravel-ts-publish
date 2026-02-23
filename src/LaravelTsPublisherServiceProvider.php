<?php

namespace AbeTwoThree\LaravelTsPublisher;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use AbeTwoThree\LaravelTsPublisher\Commands\LaravelTsPublisherCommand;

class LaravelTsPublisherServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-ts-publisher')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_ts_publisher_table')
            ->hasCommand(LaravelTsPublisherCommand::class);
    }
}

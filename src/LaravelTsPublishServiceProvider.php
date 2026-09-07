<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\CallChainWalker;
use AbeTwoThree\LaravelTsPublish\Ast\CallMatcher;
use AbeTwoThree\LaravelTsPublish\Ast\InertiaRenderLocator;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;
use AbeTwoThree\LaravelTsPublish\Commands\TsPublishCommand;
use AbeTwoThree\LaravelTsPublish\Listeners\PostMigrateRunner;
use AbeTwoThree\LaravelTsPublish\Support\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\TsNaming;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Config;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTsPublishServiceProvider extends PackageServiceProvider
{
    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(ModelAttributeResolver::class);
        $this->app->singleton(AstEngine::class);
        $this->app->singleton(AstParser::class);
        $this->app->singleton(MethodLocator::class);
        $this->app->singleton(CallMatcher::class);
        $this->app->singleton(InertiaRenderLocator::class);
        $this->app->singleton(CallChainWalker::class);
        $this->app->singleton(TsCastsReader::class);
        $this->app->singleton(ValueResolver::class);
        $this->app->singleton(JsEmitter::class);
        $this->app->singleton(TsTypeString::class);
        $this->app->singleton(TsNaming::class);

        $this->app->bind(CacheRepository::class, fn () => CacheBootstrap::repository());
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
            && Config::boolean('ts-publish.run_after_migrate')
            && Config::boolean('ts-publish.output_to_files')) {
            /** @var Dispatcher $events */
            $events = $this->app->make(Dispatcher::class);

            $events->listen(CommandFinished::class, PostMigrateRunner::class);
            $events->listen(MigrationsEnded::class, function (): void {
                PostMigrateRunner::$shouldRun = true;
            });
        }
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Http\Middleware\HandleInertiaRequests;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', HandleInertiaRequests::class);

        $this->leaveOutJsonApiFixtures();
    }

    /**
     * Keep the publish from loading the JSON:API fixtures where their parent class is missing.
     *
     * They extend JsonApiResource, which Laravel 12.45 added, and loading a class whose parent is missing is fatal.
     * The resources collector skips an excluded class before loading it; the Inertia middleware lookup loads every
     * class under its path, so it is pointed at the middleware alone.
     */
    private function leaveOutJsonApiFixtures(): void
    {
        $jsonApiResource = 'Illuminate\Http\Resources\JsonApi\JsonApiResource';

        if (class_exists($jsonApiResource)) {
            return;
        }

        config()->push('ts-publish.resources.excluded', app_path('Http/Resources/JsonApi'));
        config()->set('ts-publish.inertia.inertia_middleware_path', app_path('Http/Middleware'));
    }
}

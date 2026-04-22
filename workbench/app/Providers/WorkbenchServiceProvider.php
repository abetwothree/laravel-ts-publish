<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Foundation\Http\Kernel;
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
    }
}

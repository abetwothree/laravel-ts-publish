<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

class MiddlewareWithDocblockReturn
{
    /**
     * Define the props that are shared by default.
     *
     * @return array{
     *      auth: array{
     *          user: array{
     *              id: int,
     *              name: string,
     *              email: string
     *          }|null
     *      },
     *      flash: array{
     *          success: string|null,
     *          error: string|null
     *      },
     *      appName: string
     *  }
     */
    public function share(Request $request): array
    {
        return [];
    }
}

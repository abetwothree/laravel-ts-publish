<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Every key infers as number, so each rendered type can only have come from the docblock. */
class MiddlewareWithDocblockReturn
{
    /**
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
        return [
            'auth' => 1,
            'flash' => 2,
            'appName' => 3,
        ];
    }
}

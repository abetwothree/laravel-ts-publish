<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;

/** Shared props whose share() docblock retypes a key whose value embeds the model it names. */
final class MiddlewareWithDocblockOverEmbeddedModel extends Middleware
{
    /**
     * Shares the signed-in user in a list.
     *
     * @return array{team: list<User>}
     */
    public function share(Request $request): array
    {
        return ['team' => [$request->user()]];
    }
}

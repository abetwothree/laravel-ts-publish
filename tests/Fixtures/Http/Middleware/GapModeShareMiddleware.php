<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props whose share() types its own key and spreads a helper typing one of its own. */
final class GapModeShareMiddleware extends Middleware
{
    /**
     * A typed key beside a documented helper's keys.
     *
     * @return array{shared: string}
     */
    public function share(Request $request): array
    {
        return ['shared' => $this->sharedRead(), ...$this->sharedPart()];
    }

    /**
     * A typed key beside an untyped one.
     *
     * @return array{part: string}
     */
    private function sharedPart(): array
    {
        return ['part' => $this->partRead(), 'loose' => $this->looseRead()];
    }

    /**
     * share()'s own value.
     */
    private function sharedRead(): string
    {
        return 'shared';
    }

    /**
     * The helper's typed value.
     */
    private function partRead(): string
    {
        return 'part';
    }

    /**
     * The helper's untyped value.
     */
    private function looseRead(): string
    {
        return 'loose';
    }
}

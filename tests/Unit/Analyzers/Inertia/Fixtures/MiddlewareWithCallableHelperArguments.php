<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** Helpers passed a first-class callable, which Inertia calls wherever it sits in the shared data. */
class MiddlewareWithCallableHelperArguments extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'held_value' => $this->hold($this->label(...)),
            ...$this->holdSpread($this->label(...)),
        ];
    }

    /**
     * @param  mixed  $held
     * @return array<string, mixed>
     */
    protected function hold($held): array
    {
        return ['held' => $held];
    }

    /**
     * @param  mixed  $held
     * @return array<string, mixed>
     */
    protected function holdSpread($held): array
    {
        return ['spread_held' => $held];
    }

    /**
     * The label the callable returns.
     */
    protected function label(): string
    {
        return 'Ada';
    }
}

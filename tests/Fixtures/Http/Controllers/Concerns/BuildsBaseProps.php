<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\Concerns;

/** Supplies a props helper that a controller reaches under aliases. */
trait BuildsBaseProps
{
    /**
     * The base props.
     *
     * @return array<string, mixed>
     */
    public function buildBase(): array
    {
        return ['tk' => 1, 'tn' => 'x'];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TraitBodies;

use Illuminate\Http\Request;

/** A collection's whole toArray(), supplied from a file of its own. */
trait ShapesCollectionLabel
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['label' => 'stacked'];
    }
}

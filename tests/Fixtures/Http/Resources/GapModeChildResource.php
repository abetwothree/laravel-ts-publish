<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** A child whose `@return` types a key, returning its parent's toArray() whole. */
final class GapModeChildResource extends GapModeParentResource
{
    /**
     * The parent's keys, under a docblock typing one.
     *
     * @return array{k: string}
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}

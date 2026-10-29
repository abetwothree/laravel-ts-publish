<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `self::` helper joins, with `+`, a method only its subclass LateMethodResource declares.
 */
class LateMethodBaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['joined' => self::joined()];
    }

    /** The runtime class's part, joined with a key of its own. */
    protected function joined()
    {
        return $this->latePart() + ['x' => 1];
    }
}

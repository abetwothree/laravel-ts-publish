<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` reads an untyped `$tier` only its subclasses write, and an untyped `$level` it
 * writes itself with a literal of another type.
 */
class BaseWrittenTierResource extends JsonResource
{
    protected $tier = 'gold';

    protected $level = 1;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'tier' => $this->tier,
            'level' => $this->level,
        ];
    }

    /** Raise the level to its named maximum. */
    public function maxLevel(): static
    {
        $this->level = 'max';

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource root whose helpers spread `parent::with()` and `$this->only()` inside a nested array. */
class NestedUnreadSpreadBaseResource extends JsonResource
{
    /** Make `with()` send `k` as a string. */
    public function tag(): static
    {
        $this->with = ['k' => 'x'];

        return $this;
    }

    /** @return array<string, mixed> */
    protected function nestedWith(Request $request): array
    {
        return ['w' => ['k' => 1, ...parent::with($request)]];
    }

    /** @return array<string, mixed> */
    protected function nestedFilter(): array
    {
        $keys = ['title'];

        return ['w' => ['title' => 1, ...$this->only($keys)]];
    }
}

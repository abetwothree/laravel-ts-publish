<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource that preserves keys, whose untyped array a whole write may reorder two levels down. */
final class KeptReorderedTagsResource extends JsonResource
{
    public $preserveKeys = true;

    protected $deep = ['a' => ['b' => ['k' => 1, 0 => 'x', 1 => 5]]];

    /** Replace the array with one holding its innermost numbers in another order. */
    public function reorder(): static
    {
        $this->deep = ['a' => ['b' => ['k' => 1, 1 => 5, 0 => 'x']]];

        return $this;
    }

    /** Drop the named key the innermost array starts with. */
    public function dropLetters(): static
    {
        unset($this->deep['a']['b']['k']);

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['deep' => $this->deep];
    }
}

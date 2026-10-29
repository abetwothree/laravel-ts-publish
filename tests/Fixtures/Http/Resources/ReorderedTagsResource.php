<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource, which re-indexes, whose untyped arrays a whole write may renumber or reorder. */
final class ReorderedTagsResource extends JsonResource
{
    protected $reordered = ['a' => 1, 0 => 'x', 1 => 5];

    protected $prepended = ['a' => 1, 0 => 'x'];

    protected $lengthened = ['a' => 1, 0 => 'x', 1 => 5];

    protected $alike = ['a' => 1, 0 => 'x', 1 => 'y'];

    protected $nested = ['a' => ['k' => 1, 0 => 'x', 1 => 5]];

    protected $slid = ['a' => 1, 0 => 'x', 1 => 'y'];

    /** Replace each array with one holding its numbers in another order. */
    public function reorder(): static
    {
        $this->reordered = ['a' => 1, 1 => 5, 0 => 'x'];
        $this->prepended = ['a' => 1, 1 => 5, 0 => 'x'];
        $this->lengthened = ['a' => 1, 1 => 5, 0 => 'x', 2 => true];
        $this->alike = ['a' => 1, 1 => 'y', 0 => 'x'];
        $this->nested = ['a' => ['k' => 1, 1 => 5, 0 => 'x']];

        return $this;
    }

    /** Replace the slid array with one holding its numbers one higher. */
    public function slide(): static
    {
        $this->slid = ['a' => 1, 1 => 'y', 2 => 'z'];

        return $this;
    }

    /** Drop the named key each array, or its member, starts with. */
    public function dropLetters(): static
    {
        unset($this->reordered['a'], $this->prepended['a'], $this->lengthened['a'], $this->alike['a']);
        unset($this->nested['a']['k'], $this->slid['a']);

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reordered' => $this->reordered,
            'prepended' => $this->prepended,
            'lengthened' => $this->lengthened,
            'alike' => $this->alike,
            'nested' => $this->nested,
            'slid' => $this->slid,
        ];
    }
}

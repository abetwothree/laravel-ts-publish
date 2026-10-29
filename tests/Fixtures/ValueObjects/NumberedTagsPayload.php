<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only plain class, which no filter re-indexes, whose untyped arrays are keyed by numbers or by name. */
final class NumberedTagsPayload
{
    protected $fived = [5 => 's'];

    protected $numbered = [1 => 'a', 2 => 5];

    protected $rows = [1 => ['x' => 1], 2 => ['x' => 2]];

    protected $named = ['a' => 1];

    protected $swapped = ['a' => 1];

    /** Key a number in beside the numbered entry. */
    public function keyFived(): static
    {
        $this->fived['k'] = 1;

        return $this;
    }

    /** Key a flag in under the number 7. */
    public function keySeven(): static
    {
        $this->numbered['7'] = true;

        return $this;
    }

    /** Key a number into the first row. */
    public function extendRow(): static
    {
        $this->rows[1]['y'] = 2;

        return $this;
    }

    /** Replace the named array with another named one. */
    public function swapNamed(): static
    {
        $this->named = ['b' => 'x'];

        return $this;
    }

    /** Key a number into the named array under the key passed. */
    public function keyNamed(string $key): static
    {
        $this->named[$key] = 1;

        return $this;
    }

    /** Replace the swapped array with one keyed only by the number 5. */
    public function swap(): static
    {
        $this->swapped = [5 => 's'];

        return $this;
    }

    /** Key a number into the swapped array under the key passed. */
    public function keySwapped(string $key): static
    {
        $this->swapped[$key] = 1;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'fived' => $this->fived,
            'numbered' => $this->numbered,
            'rows' => $this->rows,
            'named' => $this->named,
            'swapped' => $this->swapped,
        ];
    }
}

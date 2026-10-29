<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource whose untyped arrays an unset or a whole write can leave keyed only by numbers. */
final class RenumberedTagsResource extends JsonResource
{
    protected $renumbered = ['a' => 1];

    protected $numbered = ['a' => 1];

    protected $swapped = ['a' => 1];

    protected $fived = [5 => 's'];

    protected $rows = [1 => ['x' => 1], 2 => ['x' => 2]];

    /** Key a number in under the key passed. */
    public function renumber(string $key): static
    {
        $this->renumbered[$key] = 2;

        return $this;
    }

    /** Drop the one key the renumbered array starts with. */
    public function dropRenumbered(): static
    {
        unset($this->renumbered['a']);

        return $this;
    }

    /** Key a string in under the number 5. */
    public function number(): static
    {
        $this->numbered[5] = 's';

        return $this;
    }

    /** Drop the one key the numbered array starts with. */
    public function dropNumbered(): static
    {
        unset($this->numbered['a']);

        return $this;
    }

    /** Replace the swapped array with one keyed only by a number. */
    public function swap(): static
    {
        $this->swapped = [5 => 's'];

        return $this;
    }

    /** Key a number into the swapped array. */
    public function key(): static
    {
        $this->swapped['k'] = 1;

        return $this;
    }

    /** Key a number in beside the numbered entry. */
    public function keyFived(): static
    {
        $this->fived['k'] = 1;

        return $this;
    }

    /** Key a number into the first row. */
    public function extendRow(): static
    {
        $this->rows[1]['y'] = 2;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'renumbered' => $this->renumbered,
            'numbered' => $this->numbered,
            'swapped' => $this->swapped,
            'fived' => $this->fived,
            'rows' => $this->rows,
        ];
    }
}

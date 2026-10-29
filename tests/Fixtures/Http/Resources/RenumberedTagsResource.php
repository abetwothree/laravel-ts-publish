<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource, which re-indexes, whose untyped arrays start keyed only by numbers or can end so. */
final class RenumberedTagsResource extends JsonResource
{
    protected $renumbered = ['a' => 1];

    protected $numbered = ['a' => 1];

    protected $swapped = ['a' => 1];

    protected $fived = [5 => 's'];

    protected $rows = [1 => ['x' => 1], 2 => ['x' => 2]];

    protected $shifted = ['a' => 1, 0 => 'x', 1 => 'y'];

    protected $gapped = ['a' => 1, 0 => 'x', 1 => 'y', 2 => 'z'];

    protected $extended = ['a' => 1, 0 => 'x'];

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

    /** Drop the named key and the lowest number, which shifts the next one down. */
    public function dropShifted(): static
    {
        unset($this->shifted['a'], $this->shifted[0]);

        return $this;
    }

    /** Drop the named key and a number between two others. */
    public function dropGapped(): static
    {
        unset($this->gapped['a'], $this->gapped[1]);

        return $this;
    }

    /** Key a number in under the next number. */
    public function extend(): static
    {
        $this->extended[1] = 5;

        return $this;
    }

    /** Drop the named key the extended array starts with. */
    public function dropExtendedLetter(): static
    {
        unset($this->extended['a']);

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
            'shifted' => $this->shifted,
            'gapped' => $this->gapped,
            'extended' => $this->extended,
        ];
    }
}

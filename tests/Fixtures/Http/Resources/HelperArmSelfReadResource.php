<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose locals are written from reads of themselves beside helpers that leave out a value they
 * cannot type: the walk first reads such a write under its alias, and the helper's warning stands whichever read ran.
 *
 * @mixin Post
 */
final class HelperArmSelfReadResource extends JsonResource
{
    /** Each self-read beside a helper, then a helper read alone before the self-read that reuses it. */
    public function toArray(Request $request): array
    {
        return [
            'coalesced' => $this->coalesced(),
            'spread_self' => $this->spreadSelf(),
            'key_reads_other' => $this->keyReadsOther(),
            'read_first' => $this->first(),
            'reread' => $this->reread(),
        ];
    }

    /**
     * A key that defaults to itself, or else to a helper.
     */
    private function coalesced(): array
    {
        $d = ['id' => $this->id];

        if ($this->id > 1) {
            $d['k'] = 5;
        }

        $d['k'] = $d['k'] ?? $this->fallback();

        return $d;
    }

    /**
     * The local spread into itself beside a helper's value.
     */
    private function spreadSelf(): array
    {
        $d = ['id' => $this->id];
        $d = [...$d, 'k' => $this->picked()];

        return $d;
    }

    /**
     * A key whose helper another key of the local chooses.
     */
    private function keyReadsOther(): array
    {
        $d = ['id' => $this->id];
        $d['k'] = $d['id'] > 1 ? $this->chosen() : 'none';

        return $d;
    }

    /**
     * A key that defaults to itself, or else to a helper `toArray()` read before.
     */
    private function reread(): array
    {
        $d = ['id' => $this->id];
        $d['k'] = $d['k'] ?? $this->first();

        return $d;
    }

    /**
     * The title, or a value no rule types.
     */
    private function fallback()
    {
        return $this->id > 1 ? $this->title : cache('fallback');
    }

    /**
     * The title, or a value no rule types.
     */
    private function picked()
    {
        return $this->id > 1 ? $this->title : cache('picked');
    }

    /**
     * The title, or a value no rule types.
     */
    private function chosen()
    {
        return $this->id > 1 ? $this->title : cache('chosen');
    }

    /**
     * The title, or a value no rule types.
     */
    private function first()
    {
        return $this->id > 1 ? $this->title : cache('first');
    }
}

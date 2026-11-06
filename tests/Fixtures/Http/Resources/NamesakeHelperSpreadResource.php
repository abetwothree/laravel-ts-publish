<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Spreads two helpers' returned arrays into the payload: a literal beside a variable return, and a variable returned
 * after a closure's own return.
 *
 * @mixin Post
 */
final class NamesakeHelperSpreadResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->mixedMeta(),
            ...$this->closureFirst(),
        ];
    }

    /** A literal return beside a variable one. */
    private function mixedMeta(): array
    {
        if ($this->is_pinned) {
            return ['a' => 1];
        }

        $meta = ['a' => 2, 'b' => 'x'];

        return $meta;
    }

    /** A closure's return ahead of the variable the method returns. */
    private function closureFirst(): array
    {
        $active = $this->comments->filter(function ($child) {
            return $child->is_flagged;
        });
        $meta = ['slug' => $this->title, 'active' => $active->count()];

        return $meta;
    }
}

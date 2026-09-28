<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Bulletin;
use Workbench\App\Models\BulletinBoard;

/**
 * Overrides two reads of Bulletin's accessors, so neither `Comment` nor the `User` of `lead_pick` survives in their
 * types. `owner_list` still names `User`, so only `Comment` has nothing left to import.
 *
 * @mixin BulletinBoard
 */
#[TsCasts([
    'lists' => '{ id: number; content: string }[][]',
    'lead_pick' => '{ id: number; name: string } | null',
])]
final class BulletinCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lists' => $this->bulletins->map(fn (Bulletin $bulletin) => $bulletin->comment_list),
            'lead_pick' => $this->lead?->author_pick,
            'owner_list' => $this->bulletins->map(fn (Bulletin $bulletin) => $bulletin->owner),
        ];
    }
}

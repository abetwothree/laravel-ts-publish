<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Exercises the bug where findBestArrayReturn() selects a nested closure's
 * return array (more items) over the actual toArray() return (fewer items
 * due to ...parent::toArray() spread counting as one).
 *
 * The outer toArray() return has 2 items: ...parent::toArray() + 'metadata'.
 * The closure inside whenLoaded has 4 items, so the old recursive finder
 * would pick the closure's array, flattening it as top-level properties
 * and losing the parent spread + metadata key entirely.
 *
 * @mixin User
 */
class SpreadWithClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'metadata' => $this->whenLoaded('profile', function () {
                return [
                    'profile_bio' => $this->profile->bio,
                    'profile_avatar' => $this->profile->avatar,
                    'profile_theme' => $this->profile->theme,
                    'profile_locale' => $this->profile->locale,
                ];
            }),
        ];
    }
}

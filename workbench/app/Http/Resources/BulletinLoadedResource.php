<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Bulletin;
use Workbench\App\Models\BulletinBoard;

/**
 * Reads Bulletin's accessors inside whenLoaded() closures: a relation chain, a closure parameter and pluck().
 *
 * @mixin BulletinBoard
 */
final class BulletinLoadedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_list' => $this->whenLoaded('lead', fn () => $this->lead->comment_list),
            'lead_owner' => $this->whenLoaded('lead', fn (Bulletin $bulletin) => $bulletin->owner),
            'own_picks' => $this->whenLoaded('bulletins', fn ($bulletins) => $bulletins->pluck('own_pick')),
        ];
    }
}

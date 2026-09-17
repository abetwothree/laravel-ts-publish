<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\BulletinBoard;

/**
 * Reads Bulletin's accessors through a relation chain and an untyped closure parameter.
 *
 * @mixin BulletinBoard
 */
final class BulletinFeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_list' => $this->lead->comment_list,
            'owner_list' => $this->bulletins->map(fn ($bulletin) => $bulletin->owner),
        ];
    }
}

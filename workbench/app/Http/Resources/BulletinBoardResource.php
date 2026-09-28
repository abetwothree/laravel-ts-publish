<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Bulletin;
use Workbench\App\Models\BulletinBoard;

/**
 * Reads Bulletin's accessors through a typed closure parameter and a nullsafe relation chain, beside a model method
 * body that reads them without imports. Keys differ from BulletinBoard's own accessors, so no name lookup imports them.
 *
 * @mixin BulletinBoard
 */
final class BulletinBoardResource extends JsonResource
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
            'summary' => $this->summary(),
        ];
    }
}

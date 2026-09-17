<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Bulletin;
use Workbench\App\Models\BulletinBoard;

/**
 * Reads Bulletin's accessors through pluck() and inside a shape a closure parameter builds.
 *
 * @mixin BulletinBoard
 */
final class BulletinArchiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plucked' => $this->bulletins->pluck('comment_list'),
            'rows' => $this->bulletins->map(fn (Bulletin $bulletin) => ['author' => $bulletin->author_pick]),
            'own_picks' => $this->bulletins->pluck('own_pick'),
        ];
    }
}

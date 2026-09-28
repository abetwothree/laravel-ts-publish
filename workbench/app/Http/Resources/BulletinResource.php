<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Bulletin;

/**
 * Reads its own model's accessors through `$this->resource`, whenAppended() and whenHas(), under keys no accessor shares.
 *
 * @mixin Bulletin
 */
final class BulletinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list' => $this->resource->comment_list,
            'picked' => $this->whenAppended('author_pick'),
            'own' => $this->whenHas('own_pick'),
        ];
    }
}

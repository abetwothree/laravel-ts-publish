<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\ReportCards;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Lives under a multi-word namespace segment, which the globals file must spell as an identifier.
 *
 * @mixin Post
 */
class SummaryCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
}

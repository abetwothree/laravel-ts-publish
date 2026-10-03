<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\ReportCards\SummaryCardResource;
use Workbench\App\Models\Post;

/**
 * Nests a resource from a multi-word namespace segment, so the globals file qualifies it across namespaces.
 *
 * @mixin Post
 */
class ReportCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary' => SummaryCardResource::make($this->resource),
        ];
    }
}

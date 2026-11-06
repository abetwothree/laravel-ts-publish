<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\PostStats;

/**
 * A resource over a value object rather than a model, read through `$this->resource` and the proxy.
 *
 * @extends JsonResource<PostStats>
 */
final class StatsSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'views' => $this->resource->views,
            'shares' => $this->shares,
            'summary' => $this->summary(),
        ];
    }
}

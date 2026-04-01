<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

/**
 * Resource using a positive instanceof guard (not negated).
 * Also includes inline arrays with optional keys and an empty inline array
 * to exercise additional coverage paths.
 */
class MediaTypePositiveInstanceOfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof MediaType) {
            return [
                'name' => $this->resource->name,
                'value' => $this->resource->value,
                'meta' => [
                    'label' => $this->when(true, 'test'),
                ],
                'empty' => [],
            ];
        }

        return [];
    }
}

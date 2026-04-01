<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

class MediaTypeInstanceOfResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof MediaType) {
            return [];
        }

        return [
            'name' => $this->resource->name,
            'value' => $this->resource->value,
            'meta' => [
                'extensions' => $this->resource->extensions(),
                'maxSizeMb' => $this->maxSizeMb(),
                'sizeUnit' => $this->sizeUnit(),
                'icon' => $this->icon(),
            ],
        ];
    }

    protected function sizeUnit(): string
    {
        return 'MB';
    }
}

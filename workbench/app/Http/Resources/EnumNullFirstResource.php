<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

/**
 * Resource for testing @var null|Type docblock ordering (null-first convention).
 */
class EnumNullFirstResource extends JsonResource
{
    /** @var null|MediaType */
    public $resource;

    public static $wrap = '';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value,
        ];
    }
}

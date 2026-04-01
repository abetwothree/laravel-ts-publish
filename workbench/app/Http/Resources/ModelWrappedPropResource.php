<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Resource for testing that $this->resource->prop on a model-backed resource resolves to the model attribute type.
 */
class ModelWrappedPropResource extends JsonResource
{
    /** @var Post|null */
    public $resource;

    public static $wrap = '';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->resource->title,
        ];
    }
}

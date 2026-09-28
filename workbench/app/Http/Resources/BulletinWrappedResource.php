<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Bulletin;

/**
 * Reads its model's accessors through a `$resource` property its docblock types, under keys no accessor shares.
 */
final class BulletinWrappedResource extends JsonResource
{
    /** @var Bulletin|null */
    public $resource;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'list' => $this->resource->comment_list,
            'owned_by' => $this->resource->owner,
        ];
    }
}

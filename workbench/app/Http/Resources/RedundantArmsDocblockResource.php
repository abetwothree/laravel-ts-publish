<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * A `@return` that types an arm the body cannot, beside a case value its enum's alias admits (GH-166).
 *
 * @mixin Post
 */
class RedundantArmsDocblockResource extends JsonResource
{
    /**
     * @return array{status: Status|int, media: MediaType}
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->id > 1 ? Status::Draft->value : cache('status'),
            'media' => $this->id > 1 ? MediaType::Image->value : cache('media'),
        ];
    }
}

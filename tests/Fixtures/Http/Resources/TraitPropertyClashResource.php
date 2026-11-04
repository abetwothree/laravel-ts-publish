<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\HoldsStatusAndPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Enums\Status;
use Workbench\Crm\Models\User as Post;

/** Reads its trait's properties, while this file imports another `Status` and `Post` than the trait does. */
final class TraitPropertyClashResource extends JsonResource
{
    use HoldsStatusAndPost;

    /** @var Post */
    public $resource;

    /**
     * The trait's properties, beside a key reading this file's own `Status`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'post' => $this->post,
            'is_lead' => $this->resource->status === Status::Lead,
        ];
    }
}

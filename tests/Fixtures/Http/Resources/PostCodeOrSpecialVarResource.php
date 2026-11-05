<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasCode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SpecialPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** A resource that redeclares $resource as a post with a code, or a subclass of post that has none. */
final class PostCodeOrSpecialVarResource extends JsonResource
{
    /** @var (Post&HasCode)|SpecialPost */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'proxy_code' => $this->code,
            'title' => $this->resource->title,
        ];
    }
}

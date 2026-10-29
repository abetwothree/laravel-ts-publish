<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * A resource whose `$resource` is a post or an image, read under an `if` that tests for Model, which rules out
 * neither, around a value nested five deep.
 */
final class ProxyModelGuardResource extends JsonResource
{
    /** @var Post|Image */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Model) {
            return [
                'nested' => ['a' => ['b' => ['c' => ['d' => ['e' => strtoupper(strtolower(trim((string) $this->resource->getKey())))]]]]],
                'proven' => $this->resource,
                'narrowed' => $this->resource instanceof Post ? $this->resource->title : null,
            ];
        }

        return [];
    }
}

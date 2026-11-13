<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Query chains rooted at a model, which find a model, a list of them or a column, inside whenHas() and bare.
 *
 * @mixin Post
 */
class FinderChainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'find' => $this->whenHas('title', fn () => User::find(1)),
            'plain_find' => User::find(1),
            'pluck_first' => Post::query()->pluck('id')->first(),
            'where_get' => User::query()->where('id', 1)->get(),
        ];
    }
}

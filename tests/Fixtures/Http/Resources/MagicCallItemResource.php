<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Overrides `__call()`, so a method it does not declare returns a fixed label rather than running on its post.
 *
 * @mixin Post
 */
class MagicCallItemResource extends JsonResource
{
    /**
     * Answers every undeclared method with a fixed label.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): string
    {
        return 'magic';
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [];
    }
}

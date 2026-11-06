<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Overrides `__get()`, so a property it does not declare reads a fixed label rather than its post's member.
 *
 * @mixin Post
 */
class MagicGetItemResource extends JsonResource
{
    /** Reads every undeclared property as a fixed label. */
    public function __get($key): string
    {
        return 'magic';
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [];
    }
}

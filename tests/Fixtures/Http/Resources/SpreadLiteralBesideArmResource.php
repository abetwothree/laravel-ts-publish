<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a literal before a resource's resolved keys.
 *
 * @mixin Post
 */
final class SpreadLiteralBesideArmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'literal_then_resource' => [...['name' => $this->id], ...UserResource::make($this->author)->resolve($request)],
            'key_literal_resource' => ['by' => $this->author, ...['by' => $this->title], ...UserResource::make($this->author)->resolve($request)],
        ];
    }
}

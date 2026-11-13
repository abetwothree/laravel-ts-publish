<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose model key a local overrides before a resource's resolved keys.
 *
 * @mixin Post
 */
final class SpreadValueKeyBesideArmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $titled = ['by' => $this->title];

        return [
            'key_value_resource' => ['by' => $this->author, ...$titled, ...UserResource::make($this->author)->resolve($request)],
        ];
    }
}

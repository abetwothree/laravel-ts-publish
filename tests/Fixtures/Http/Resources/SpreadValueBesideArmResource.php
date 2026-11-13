<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading locals before and after a resource's resolved keys, nested in a value.
 *
 * @mixin Post
 */
final class SpreadValueBesideArmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $named = ['name' => $this->id];
        $titled = ['by' => $this->title];
        $maybe = [];

        if ($request->boolean('titled')) {
            $maybe['by'] = $this->title;
        }

        return [
            'value_then_resource' => [...$named, ...UserResource::make($this->author)->resolve($request)],
            'resource_then_value' => [...UserResource::make($this->author)->resolve($request), ...$named],
            'key_value_resource' => ['by' => $this->author, ...$titled, ...UserResource::make($this->author)->resolve($request)],
            'key_maybe_resource' => ['by' => $this->author, ...$maybe, ...UserResource::make($this->author)->resolve($request)],
        ];
    }
}

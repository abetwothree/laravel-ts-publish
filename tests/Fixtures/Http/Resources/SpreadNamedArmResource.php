<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource writing keys before a resource's or a model's spread, which PHP sends over them.
 *
 * @mixin Post
 */
final class SpreadNamedArmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $maybe = [];

        if ($request->boolean('titled')) {
            $maybe['name'] = 1;
        }

        return [
            'key_then_resource' => ['id' => 'x', ...UserResource::make($this->author)->resolve($request)],
            'helper_then_resource' => [...$this->userBits(), ...UserResource::make($this->author)->resolve($request)],
            'untyped_maybe_resource' => [
                'name' => json_decode('{"a":1}'),
                ...$maybe,
                ...UserResource::make($this->author)->resolve($request),
            ],
            'typed_maybe_resource' => ['name' => $this->author, ...$maybe, ...UserResource::make($this->author)->resolve($request)],
            'resource_then_key' => [...UserResource::make($this->author)->resolve($request), 'id' => 'x'],
            'key_then_model' => ['name' => $this->id, 'title' => $this->id, ...$this->author->toArray()],
        ];
    }

    /** @return array<string, mixed> */
    private function userBits(): array
    {
        return ['id' => 'x', 'email' => 1];
    }
}

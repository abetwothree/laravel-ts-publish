<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * User account resource.
 *
 * @mixin User
 */
#[TsResource(model: User::class)]
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => EnumResource::make($this->role),
            'profile' => $this->whenLoaded('profile'),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'phone' => $this->whenHas('phone'),
            'avatar' => $this->whenNotNull($this->avatar),
            'posts_count' => $this->whenCounted('posts'),
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}

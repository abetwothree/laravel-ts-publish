<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;

/**
 * @mixin Comment
 */
#[TsResourceCasts([
    'metadata' => 'Record<string, unknown>',
    'flagged_at' => ['type' => 'string | null', 'optional' => true],
])]
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'is_flagged' => $this->is_flagged,
            'flagged_at' => $this->flagged_at,
            'metadata' => $this->metadata,
            'author' => UserResource::make($this->whenLoaded('user')),
            'author_new' => new UserResource($this->whenLoaded('user')),
            'author_direct' => new UserResource($this->user),
            'post' => PostResource::make($this->whenLoaded('post')),
            'post_new' => new PostResource($this->whenLoaded('post')),
            'post_direct' => new PostResource($this->post),
            'post_limited' => $this->post->only(['id', 'title']), // relation with only specific attributes
            'post_extended' => $this->post?->except(['created_at', 'updated_at']), // relation with except specific attributes
            'post_title' => $this->whenLoaded('post', fn () => $this->post->title), // relation with no return type annotation on closure — analyzer should resolve type from body/relation column attributes
            'post_content' => $this->whenLoaded('post', fn () => $this->post?->content), // relation with return type annotation on closure and nullsafe traversal — analyzer should skip proxy step and resolve type from body/relation column attributes; annotation is only a fallback
            'post_title_display' => $this->whenLoaded('post', fn () => $this->post?->title_display), // relation with accessor return type annotation on closure and nullsafe traversal — analyzer should skip proxy step and resolve type from accessor return type; annotation is only a fallback
            'post_author' => $this->whenLoaded('post', fn () => $this->post->author?->name), // relation with multi-hop nullsafe traversal — analyzer should resolve type from body/relation column attributes
            'post_resource_title' => $this->whenLoaded('post', fn () => $this->resource->post->title), // Same as post_title but accessed via $this->resource
            'post_resource_content' => $this->whenLoaded('post', fn () => $this->resource?->post?->content),  // Same as post_content but accessed via $this->resource with additional nullsafe traversal
            'post_resource_title_display' => $this->whenLoaded('post', fn () => $this->resource->post?->title_display), // Same as post_title_display but accessed via $this->resource
            'post_resource_author' => $this->whenLoaded('post', fn () => $this->resource->post->author?->name), // Same as post_author but accessed via $this->resource
            'user_name' => $this->whenLoaded('user', fn (): ?string => $this->user->name),
            'user_email' => $this->whenLoaded('user', fn (): ?string => $this->resource->user->email), // non-nullsafe chain traversal test — 3-deep chain resolved via analyzePropertyChain; body wins over ?string annotation → string
            'user_email_annotated' => $this->whenLoaded('user', fn (): ?string => strtolower((string) $this->user->email)), // annotation fallback test — FuncCall body is unresolvable, so ?string annotation kicks in → string|null
            'unresolvable_status' => $this->whenLoaded('user', fn () => strtolower((string) $this->user->email)), // no annotation, FuncCall body unresolvable → unknown
            'resolvable_status' => $this->whenLoaded('user', fn (): Status => strtolower((string) $this->user->email)), // annotation fallback test — FuncCall body unresolvable, Status annotation resolves to StatusType with FQCN
            'user_name_nullable' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
            'user_email_nullable' => $this->whenLoaded('user', fn (): ?string => $this->resource->user?->email),
            'user_role' => $this->user?->role,
            'user_profile' => $this->resource->user?->profile,
            'user_profile_bio' => $this->user?->profile?->bio,
            'user_profile_avatar_url' => $this->resource->user?->profile?->avatar_url,
        ];
    }
}

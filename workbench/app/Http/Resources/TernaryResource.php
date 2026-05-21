<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises: ternary operator in various return-value positions.
 *
 * All properties in this resource use the ternary operator (`? :`) or the
 * Elvis operator (`?:`) as the value expression. The scenarios cover:
 *   - enum resource vs null
 *   - enum resource vs enum resource (same / different enum class)
 *   - named resource vs null
 *   - named resource vs named resource (same / different class)
 *   - resource collection vs null
 *   - scalar vs null (string, integer)
 *   - string literal vs string literal
 *   - Elvis / short-ternary
 *   - ternary nested inside a whenLoaded closure
 *   - ternary with $this->resource accessor
 *
 * @mixin Post
 */
class TernaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Enum resource vs null ──────────────────────────────────────
            // Both branches: one side is a resolved enum, other is null.
            // Expected TypeScript: Status enum type, optional.
            'status_or_null' => $this->is_pinned
                ? EnumResource::make($this->status)
                : null,

            // ── Enum resource vs enum resource (same class) ────────────────
            // Both branches resolve to the same enum type.
            // Expected TypeScript: Status enum type, not optional.
            'status_or_status' => $this->is_pinned
                ? EnumResource::make($this->status)
                : new EnumResource($this->status),

            // ── Enum resource vs enum type ────────────────
            // Branches resolve to either the AsEnum<typeof StatusType> or just StatusType.
            // Expected TypeScript: AsEnum<typeof StatusType> | StatusType, not optional.
            'status_resource_or_type' => $this->is_pinned
                ? EnumResource::make($this->status)
                : $this->status,

            // ── Enum resource vs enum resource (different classes) ─────────
            // Branches resolve to two different enum types.
            // Expected TypeScript: AsEnum<typeof Status> | AsEnum<typeof Visibility> union.
            'status_or_visibility' => $this->is_pinned
                ? EnumResource::make($this->status)
                : new EnumResource($this->visibility),

            // ── Named resource::make vs null ───────────────────────────────
            // One side is a nested resource, the other is null.
            // Expected TypeScript: CategoryResource, optional.
            'category_or_null' => $this->is_pinned
                ? CategoryResource::make($this->categoryRel)
                : null,

            // ── Named resource::make vs resource::make (same class) ────────
            // Both sides resolve to the same resource class.
            // Expected TypeScript: CategoryResource, not optional.
            'category_or_category' => $this->is_pinned
                ? CategoryResource::make($this->categoryRel)
                : CategoryResource::make($this->categoryRel),

            // ── Named resource::make vs resource::make (different classes) ─
            // Branches resolve to two different resource classes.
            // Expected TypeScript: CategoryResource | UserResource union, or unknown.
            'category_or_user' => $this->is_pinned
                ? CategoryResource::make($this->categoryRel)
                : UserResource::make($this->author),

            // ── new Resource(...) vs null ──────────────────────────────────
            // `new` instantiation on one branch, null on the other.
            // Expected TypeScript: ImageResource, optional.
            'image_or_null' => $this->is_pinned
                ? new ImageResource($this->images->first())
                : null,

            // ── Resource collection vs null ────────────────────────────────
            // Collection call on one branch, null on the other.
            // Expected TypeScript: CommentResource[], optional.
            'comments_or_null' => $this->published_at
                ? CommentResource::collection($this->comments)
                : null,

            // ── Resource collection vs resource collection (same class) ────
            // Both sides are collections of the same resource type.
            // Expected TypeScript: CommentResource[], not optional.
            'comments_or_comments' => $this->is_pinned
                ? CommentResource::collection($this->comments)
                : CommentResource::collection($this->comments),

            // ── Scalar string vs null ──────────────────────────────────────
            // A string property on one branch, null on the other.
            // Expected TypeScript: string, optional.
            'title_or_null' => $this->published_at
                ? $this->title
                : null,

            // ── Scalar integer vs null ─────────────────────────────────────
            // An integer property on one branch, null on the other.
            // Expected TypeScript: number, optional.
            'word_count_or_null' => $this->word_count
                ? $this->word_count
                : null,

            // ── String literal vs string literal ───────────────────────────
            // Both branches are string scalar values.
            // Expected TypeScript: string, not optional.
            'pin_label' => $this->is_pinned
                ? 'pinned'
                : 'normal',

            // ── Elvis / short-ternary (expr ?: fallback) ───────────────────
            // Non-null coalescing with a scalar fallback.
            // Expected TypeScript: string, not optional.
            'title_fallback' => $this->title ?: 'Untitled',

            // ── Ternary inside a whenLoaded closure ────────────────────────
            // The outer expression is a whenLoaded; the closure body uses a ternary.
            // Expected TypeScript: CategoryResource, optional.
            'category_when_loaded_or_null' => $this->whenLoaded(
                'categoryRel',
                fn () => $this->is_pinned
                    ? CategoryResource::make($this->categoryRel)
                    : null,
            ),

            // ── Ternary with $this->resource property access ───────────────
            // The condition and both branches use $this->resource instead of $this.
            // Expected TypeScript: CategoryResource, optional.
            'category_resource_or_null' => $this->resource->is_pinned
                ? CategoryResource::make($this->resource->categoryRel)
                : null,

            // ── Nested ternary ─────────────────────────────────────────────
            // The inner ternary resolves to string; the outer wraps it in a nullable union.
            // Expected TypeScript: string | null.
            'nested_ternary_label' => $this->is_pinned
                ? ($this->word_count ? 'pinned-post' : 'unpinned-post')
                : null,
        ];
    }
}

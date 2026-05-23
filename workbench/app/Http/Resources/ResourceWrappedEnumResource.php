<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises issue #43: EnumResource wrapping an enum accessed via `$this->resource->property`
 * returns `unknown` instead of the correct `AsEnum` utility type.
 *
 * Each entry below represents a distinct code pattern where the enum is reached
 * through the underlying model accessor (`$this->resource->prop`) rather than the
 * Laravel Resource magic shorthand (`$this->prop`).  All entries should resolve
 * to the same TypeScript type as their `$this->prop` counterparts.
 *
 * @mixin Post
 */
class ResourceWrappedEnumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // ── Direct access ──────────────────────────────────────────────

            // EnumResource::make() with $this->resource->prop
            'status_make' => EnumResource::make($this->resource->status),

            // new EnumResource() with $this->resource->prop
            'status_new' => new EnumResource($this->resource->status),

            // Different enum via ::make
            'visibility_make' => EnumResource::make($this->resource->visibility),

            // Different enum via new
            'priority_new' => new EnumResource($this->resource->priority),

            // ── when() ────────────────────────────────────────────────────

            // when() with a pre-evaluated EnumResource::make value (no closure)
            'status_when_make' => $this->when(
                $this->resource->status !== null,
                EnumResource::make($this->resource->status)
            ),

            // when() with arrow closure body containing $this->resource->prop
            'status_when_arrow' => $this->when(
                $this->resource->is_pinned,
                fn () => EnumResource::make($this->resource->status)
            ),

            // when() with full closure body containing $this->resource->prop
            'visibility_when_full' => $this->when(
                $this->resource->is_pinned,
                function () {
                    return new EnumResource($this->resource->visibility);
                }
            ),

            // ── whenNotNull() ─────────────────────────────────────────────

            // whenNotNull() with pre-evaluated value
            'priority_when_not_null_make' => $this->whenNotNull(
                $this->resource->priority,
                EnumResource::make($this->resource->priority)
            ),

            // whenNotNull() with arrow closure
            'status_when_not_null_arrow' => $this->whenNotNull(
                $this->resource->status,
                fn () => EnumResource::make($this->resource->status)
            ),

            // whenNotNull() with full closure
            'visibility_when_not_null_full' => $this->whenNotNull(
                $this->resource->visibility,
                function () {
                    return new EnumResource($this->resource->visibility);
                }
            ),

            // ── Ternary ───────────────────────────────────────────────────

            // Ternary: enum resource vs null
            'status_ternary_null' => $this->resource->is_pinned
                ? EnumResource::make($this->resource->status)
                : null,

            // Ternary: enum resource vs enum resource (same type, different accessor)
            'status_ternary_both' => $this->resource->is_pinned
                ? EnumResource::make($this->resource->status)
                : new EnumResource($this->resource->status),

            // Ternary: two different enum types via resource accessor
            'status_or_visibility_ternary' => $this->resource->is_pinned
                ? EnumResource::make($this->resource->status)
                : new EnumResource($this->resource->visibility),

            // ── Inline array ──────────────────────────────────────────────

            // Nested array where every enum value goes through $this->resource->prop
            'enums_array' => [
                'status' => EnumResource::make($this->resource->status),
                'visibility' => new EnumResource($this->resource->visibility),
                'priority' => EnumResource::make($this->resource->priority),
            ],

            // Mixed type and enum instance type array where every enum value goes through $this->resource->prop
            'mixed_enums_array' => [
                'status_type' => $this->status,
                'visibility_type' => $this->visibility,
                'priority_type' => $this->priority,
                'status_resource_type' => $this->resource->status,
                'visibility_resource_type' => $this->resource->visibility,
                'priority_resource_type' => $this->resource->priority,
                'status_enum' => EnumResource::make($this->resource->status),
                'visibility_enum' => new EnumResource($this->resource->visibility),
                'priority_enum' => EnumResource::make($this->resource->priority),
            ],

            // ── mergeWhen() ───────────────────────────────────────────────

            // mergeWhen() with inline array containing $this->resource->prop enums
            $this->mergeWhen($this->resource->is_pinned, [
                'merged_status' => EnumResource::make($this->resource->status),
                'merged_visibility' => new EnumResource($this->resource->visibility),
            ]),

            // mergeWhen() with arrow closure returning array
            $this->mergeWhen(
                $this->resource->status !== null,
                fn () => [
                    'deferred_status' => EnumResource::make($this->resource->status),
                    'deferred_priority' => new EnumResource($this->resource->priority),
                ]
            ),

            // ── whenLoaded() ──────────────────────────────────────────────

            // whenLoaded() arrow closure that accesses the outer $this->resource->prop
            'category_status' => $this->whenLoaded(
                'categoryRel',
                fn () => EnumResource::make($this->resource->status)
            ),

            // whenLoaded() full closure that accesses the outer $this->resource->prop
            'category_visibility' => $this->whenLoaded(
                'categoryRel',
                function () {
                    return new EnumResource($this->resource->visibility);
                }
            ),
        ];
    }
}

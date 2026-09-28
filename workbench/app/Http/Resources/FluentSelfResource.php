<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/**
 * Regression fixture for Task 17C: a fluent method chained onto a receiver that resolves to a
 * resource (`new self($x)`, `self::make($x)`, or a chain of both) keeps the receiver's type when
 * the method's declared return type hands the same instance back.
 *
 * @mixin Category
 */
class FluentSelfResource extends JsonResource
{
    /**
     * The eaglesys shape: a native `: static` return type.
     */
    public function markPreview(): static
    {
        return $this;
    }

    /**
     * No native return type — only the `@return $this` docblock signals it preserves the instance.
     *
     * @return $this
     */
    public function withoutMetadata()
    {
        return $this;
    }

    /**
     * Negative case: declares a real return type that is not self-returning.
     */
    public function summary(): array
    {
        return ['id' => $this->id];
    }

    /**
     * Nullable native return type — the declared shape includes null, so the emitted type must too.
     */
    public function whenAuthorized(): ?static
    {
        return $this->is_active ? $this : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // new self($x)->fluentMethod() where fluentMethod() declares `: static`.
            'parent_fluent' => $this->whenLoaded('parent', fn () => new self($this->parent)->markPreview()),
            // SomeResource::make($x)->fluentMethod().
            'parent_fluent_make' => $this->whenLoaded('parent', fn () => self::make($this->parent)->markPreview()),
            // A two-call chain: the rule composes across more than one fluent link.
            'parent_fluent_chain' => $this->whenLoaded('parent', fn () => new self($this->parent)->markPreview()->withoutMetadata()),
            // Docblock-only `@return $this` fallback, no native return type declared.
            'parent_fluent_docblock' => $this->whenLoaded('parent', fn () => new self($this->parent)->withoutMetadata()),
            // summary() declares `: array`, so the expression is no longer the resource — the analyzer
            // resolves the method body instead of degrading, giving `{ id: number }`.
            'parent_summary' => $this->whenLoaded('parent', fn () => new self($this->parent)->summary()),
            // The receiver is a *foreign* resource class, so its body is resolved by a second analyzer
            // through the method-body fallback, giving `{ slug: string }`. It must never be "improved"
            // to CategoryResource, which the payload is not.
            'foreign_summary' => $this->whenLoaded('parent', fn () => new CategoryResource($this->parent)->summary()),
            // `?static` — must keep the resource type but add `| null`, not just the bare resource.
            'parent_fluent_nullable' => $this->whenLoaded('parent', fn () => new self($this->parent)->whenAuthorized()),
        ];
    }
}

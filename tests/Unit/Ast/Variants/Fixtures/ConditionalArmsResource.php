<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Variants\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * One method per form whose arms a handler hands over, each returning that form alone.
 *
 * @mixin Post
 */
final class ConditionalArmsResource extends JsonResource
{
    /**
     * The keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [];
    }

    /** A ternary on a read. */
    public function ternary(): ?string
    {
        return $this->is_pinned ? $this->title : null;
    }

    /** A ternary whose condition narrows the resource it reads. */
    public function narrowedTernary(): ?string
    {
        return $this->resource instanceof Post ? $this->resource->title : null;
    }

    /** A short ternary, which has no condition of its own. */
    public function shortTernary(): string
    {
        return $this->title ?: 'x';
    }

    /** A match on a read, with two cases in its first arm. */
    public function matchCases(): string|int
    {
        return match ($this->priority) {
            Priority::Low, Priority::Medium => 'a',
            default => 1,
        };
    }

    /** A match whose default throws. */
    public function matchThrows(): string
    {
        return match ($this->priority) {
            Priority::Low => 'a',
            default => throw new LogicException('Unsupported priority.'),
        };
    }

    /** A match with no default, which throws for a value no arm names. */
    public function matchWithoutDefault(): string|int
    {
        return match ($this->status) {
            Status::Draft => 'a',
            Status::Published => 1,
        };
    }

    /** A match whose default is written before the arm it falls back from. */
    public function matchDefaultFirst(): string|int
    {
        return match ($this->priority) {
            default => 1,
            Priority::Low => 'a',
        };
    }

    /** A when() with no default. */
    public function whenWithoutDefault(): mixed
    {
        return $this->when($this->is_pinned, $this->updated_at);
    }

    /** An unless() with a default. */
    public function unlessWithDefault(): mixed
    {
        return $this->unless($this->is_pinned, 'a', 'b');
    }

    /** A when() whose default needs more arguments than Laravel passes it. */
    public function uncallableDefault(): mixed
    {
        return $this->when($this->is_pinned, fn ($x) => 1, fn ($a, $b) => 2);
    }

    /** A when() whose value Laravel may remove. */
    public function removableValue(): mixed
    {
        return $this->when($this->is_pinned, $this->whenLoaded('user'));
    }
}

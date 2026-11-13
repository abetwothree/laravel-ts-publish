<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Casts and `+` over values that may be null: `(array)` and `+` of a nullable list, `(array) null`, and `(object)` of
 * an enum's value, read directly and through a nullsafe chain.
 *
 * @mixin Post
 */
final class NullableOperandsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'listed' => (array) $this->maybeList(),
            'emptied' => (array) null,
            'added' => $this->maybeList() + ['y', 'z'],
            'status' => (object) $this->status->value,
            'priority' => (object) $this->priority?->value,
        ];
    }

    /** @return list<string>|null */
    public function maybeList(): ?array
    {
        return ['a'];
    }

    /** @return array{a: string, b: int}|null */
    public function maybeShape(): ?array
    {
        return ['a' => 'x', 'b' => 1];
    }
}

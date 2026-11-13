<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AliasedToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Callbacks whose parameter declares a scalar type, which PHP converts each passed value to, and maps over a local a
 * join holds or a value nothing types.
 *
 * @mixin Post
 */
final class CallbackCoercionResource extends JsonResource
{
    /** A function's name, held by a property. */
    public string $lengthOf = 'strlen';

    /**
     * A function's name, or a pair a method writes.
     *
     * @var string|array{self, string}
     */
    public string|array $measure = 'strtoupper';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $comments = $this->resource->comments;
        $twice = fn ($v) => 1;

        if ($this->resource->id > 5) {
            $twice = fn ($v) => 2;
        }

        if ($this->resource->id > 0) {
            $comments = $this->opaque($this->resource->comments);
        }

        return [
            'joined_typed' => collect($comments)->map(fn (Comment $c) => $c->content)->all(),
            'joined_untyped' => collect($comments)->map(fn ($c) => $c->content)->all(),
            'opaque_typed' => collect($this->opaque(['first' => $this->resource->comments->first()]))->map(fn (Comment $c) => $c->id)->all(),
            'enum_value_int' => collect([Status::from(1)->value])->map(fn (int $v) => $v)->all(),
            'enum_value_array_map_int' => array_map(fn (int $v) => $v, [Status::from(1)->value]),
            'enum_value_string' => collect([Status::from(1)->value])->map(fn (string $v) => $v)->all(),
            'enum_value_or_zero' => collect([$this->resource->status?->value ?? 0])->map(fn (int $v) => $v)->all(),
            'models_as_strings' => array_map(fn (string $c) => $c, $this->resource->comments->all()),
            'models_mapped_as_strings' => $this->resource->comments->map(fn (string $c) => $c)->all(),
            'when_has_string' => $this->whenHas('id', fn (string $v) => $v),
            'transform_string' => $this->transform($this->resource->id, fn (string $v) => $v),
            'exists_loaded_string' => $this->whenExistsLoaded('comments', fn (string $f) => $f),
            'transform_function' => $this->transform($this->resource->title, 'strlen'),
            'transform_pair' => $this->transform($this->resource->title, [$this, 'length']),
            'alias_as_string' => collect([new AliasedToken('a')])->map(fn (string $v) => $v)->all(),
            'alias_transformed' => $this->transform(new AliasedToken('a'), fn (string $v) => $v),
            'transform_property' => $this->transform($this->resource->title, $this->lengthOf),
            'transform_ternary' => $this->transform($this->resource->title, $this->resource->id > 0 ? 'strlen' : 'ucfirst'),
            'transform_written_property' => $this->transform($this->resource->title, $this->measure),
            'transform_two_writes' => $this->transform($this->resource->title, $twice),
            'transform_closure_ternary' => $this->transform($this->resource->title, $this->resource->id > 0 ? fn ($v) => 1 : fn ($v) => 2),
        ];
    }

    /** The length of a string, a pair names. */
    public function length(string $value): int
    {
        return strlen($value);
    }

    /** Measure by length. */
    public function measureLength(): void
    {
        $this->measure = [$this, 'length'];
    }

    /** A value nothing types, as it comes back from serialization. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}

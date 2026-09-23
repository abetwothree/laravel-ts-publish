<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/**
 * An inline `@var` types a local only where the engine's reading of the assigned value is vague: an arm it cannot
 * type, or a value it cannot read. A known reading stands whatever the tag says, a closure parameter reassigned under
 * a tag takes its new value, and a tag the package cannot fully read binds nothing.
 *
 * @mixin Post
 */
final class DeclaredPrecedenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var string|null $picked */
        $picked = $this->resource->id > 0 ? json_decode('"x"') : null;

        /** @var string $pickedStrict */
        $pickedStrict = $this->resource->id > 0 ? json_decode('"x"') : null;

        /** @var int|string $pickedOrZero */
        $pickedOrZero = $this->resource->id > 0 ? json_decode('"x"') : 0;

        /** @var string|null $elvis */
        $elvis = json_decode('"x"') ?: null;

        /** @var string $literal */
        $literal = ['a' => 1];

        /** @var array{a: int} $heading */
        $heading = $this->resource->title;

        /** @var CartTotals $titleAsTotals */
        $titleAsTotals = $this->resource->title;

        /** @var MustVerifyEmail $verifiable */
        $verifiable = $this->resource->author;

        /** @var callable(int):string $callable */
        $callable = $this->resource->getRelationValue('x');

        /** @var array{fn: callable(int): string} $callableShape */
        $callableShape = $this->resource->getRelationValue('x');

        /** @var Closure(int): string $closure */
        $closure = $this->resource->getRelationValue('x');

        /** @var Post&JsonSerializable $intersection */
        $intersection = $this->resource->getRelationValue('x');

        return [
            'picked' => $picked,
            'picked_strict' => $pickedStrict,
            'picked_or_zero' => $pickedOrZero,
            'elvis' => $elvis,
            'literal' => $literal,
            'heading' => $heading,
            'title_as_totals' => $titleAsTotals,
            'verifiable_email' => $verifiable->email,
            'callable' => $callable,
            'callable_shape' => $callableShape,
            'closure' => $closure,
            'intersection' => $intersection,
            'length' => $this->whenHas('title', function ($title) {
                /** @var int $title */
                $title = strlen($title);

                return $title;
            }),
            'transformed_length' => $this->transform($this->resource->title, function ($title) {
                /** @var int $title */
                $title = strlen($title);

                return $title;
            }),
            'author_name' => $this->whenLoaded('author', function (User $author) {
                /** @var string $author */
                $author = $author->name;

                return $author;
            }),
            'first_comment' => $this->whenLoaded('author', function (User $author) {
                /** @var Comment|null $author */
                $author = $author->comments->first();

                return $author;
            }),
        ];
    }
}

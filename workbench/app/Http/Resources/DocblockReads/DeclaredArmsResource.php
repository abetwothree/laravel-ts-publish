<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DocblockReads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;
use Workbench\App\ValueObjects\DocblockReads\CategoryBranch;
use Workbench\App\ValueObjects\DocblockReads\HasLabel;
use Workbench\App\ValueObjects\DocblockReads\MaybeLabel;
use Workbench\App\ValueObjects\DocblockReads\TextLabel;

/**
 * An outer declaration over a value, an inline `@var`, a `toArray()` `@return` key or a closure `@return`: it replaces
 * each arm only a declaration produced, and keeps each arm the body proves (GH-134; plan task T5).
 *
 * `featured_image_url` is a nullable string column, `title` a NOT NULL one, and a comment's `parent_id` a nullable key.
 *
 * @property-read Post $resource
 */
class DeclaredArmsResource extends JsonResource
{
    /**
     * @return array{
     *     decl_closure_nullable: list<Post>,
     *     decl_closure_array: list<array{a: int}>,
     *     decl_two_array_nullsafe: list<string>,
     *     decl_param_nullsafe: list<string>,
     *     decl_nullable_return: list<string>,
     *     decl_hedged_map: list<string>,
     *     decl_first_kid_name: string,
     *     decl_column: string,
     *     decl_ternary: string,
     *     decl_coalesce_null: string,
     *     decl_nullable_column: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $labels = Opaque::value([new TextLabel('a')]);
        $branch = new CategoryBranch;
        $post = $this->resource->id > 1 ? $this->resource : null;

        /** @var list<Post> $closureNullable */
        $closureNullable = collect([1])->map(fn ($i): ?Post => Opaque::value($this->resource))->all();
        /** @var list<Post> $closureModel */
        $closureModel = collect([1])->map(fn ($i): ?Model => Opaque::value($this->resource))->all();
        /** @var list<Post> $closureModelNarrowed */
        $closureModelNarrowed = collect([1])->map(fn ($i): Model => Opaque::value($this->resource))->all();
        /** @var list<Post> $closureFiltered */
        $closureFiltered = collect([1])->map(fn ($i): ?Post => Opaque::value($this->resource))->filter()->values()->all();
        /** @var list<string> $twoArrayNullsafe */
        $twoArrayNullsafe = array_map(
            fn (Comment $c, ?Post $p) => $p?->title,
            $this->resource->comments->all(),
            Opaque::value([$this->resource]),
        );
        /** @var list<string> $paramNullsafe */
        $paramNullsafe = collect($labels)->map(/** @param HasLabel|null $label */ fn ($label) => $label?->label())->values()->all();
        /** @var list<string> $nullableReturn */
        $nullableReturn = collect($labels)->map(/** @param MaybeLabel $label */ fn ($label) => $label->label())->values()->all();
        /** @var list<string> $hedgedMap */
        $hedgedMap = array_map(/** @param MaybeLabel $label */ fn ($label) => $label->label(), $labels);
        /** @var string $firstKidName */
        $firstKidName = $branch->ownNamedFirstKidName();
        /** @var list<int> $parentIds */
        $parentIds = $this->resource->comments->map(fn ($comment) => $comment->parent_id)->values()->all();
        /** @var string $column */
        $column = $this->resource->featured_image_url;
        /** @var list<string> $mappedColumn */
        $mappedColumn = collect([$this->resource])->map(fn (Post $p) => $p->featured_image_url)->all();
        /** @var list<array{content: string, other: int}> $zipped */
        $zipped = array_map(
            fn (Comment $c, $p) => ['content' => $c->content, 'other' => $p],
            $this->resource->comments->all(),
            Opaque::value([1]),
        );
        /** @var list<true> $flags */
        $flags = collect([1])->map(fn (int $i): bool => $i > 0)->all();
        /** @var string $ternary */
        $ternary = $this->resource->id > 1 ? $this->resource->title : null;
        /** @var string $coalesceNull */
        $coalesceNull = $this->resource->featured_image_url ?? null;
        /** @var string $coalesced */
        $coalesced = $this->resource->featured_image_url ?? 'none';
        /** @var array{a: int} $uncovered */
        $uncovered = ['a' => 1, 'b' => $this->resource->featured_image_url];
        /** @var mixed $mixed */
        $mixed = $this->resource->featured_image_url;
        /** @var string|null $nullableColumn */
        $nullableColumn = $this->resource->featured_image_url;
        /** @var string $provenNullsafe */
        $provenNullsafe = $post?->title;
        /** @var list<string> $mappedCoalesceNull */
        $mappedCoalesceNull = collect([$this->resource])->map(fn (Post $p) => $p->featured_image_url ?? null)->all();

        return [
            'var_closure_nullable' => $closureNullable,
            'var_closure_model' => $closureModel,
            'var_closure_model_narrowed' => $closureModelNarrowed,
            'var_closure_filtered' => $closureFiltered,
            'var_two_array_nullsafe' => $twoArrayNullsafe,
            'var_param_nullsafe' => $paramNullsafe,
            'var_nullable_return' => $nullableReturn,
            'var_hedged_map' => $hedgedMap,
            'var_first_kid_name' => $firstKidName,
            'var_parent_ids' => $parentIds,
            'var_column' => $column,
            'var_mapped_column' => $mappedColumn,
            'var_zipped' => $zipped,
            'var_flags' => $flags,
            'decl_closure_nullable' => collect([1])->map(fn ($i): ?Post => Opaque::value($this->resource))->all(),
            'decl_closure_array' => collect([1])->map(fn ($i): ?array => Opaque::value(['a' => $i]))->all(),
            'decl_two_array_nullsafe' => array_map(
                fn (Comment $c, ?Post $p) => $p?->title,
                $this->resource->comments->all(),
                Opaque::value([$this->resource]),
            ),
            'decl_param_nullsafe' => collect($labels)->map(/** @param HasLabel|null $label */ fn ($label) => $label?->label())->values()->all(),
            'decl_nullable_return' => collect($labels)->map(/** @param MaybeLabel $label */ fn ($label) => $label->label())->values()->all(),
            'decl_hedged_map' => array_map(/** @param MaybeLabel $label */ fn ($label) => $label->label(), $labels),
            'decl_first_kid_name' => $branch->ownNamedFirstKidName(),
            'decl_column' => $this->resource->featured_image_url,
            'when_closure_nullable' => $this->when(true, /** @return list<Post> */ fn () => collect([1])->map(fn ($i): ?Post => Opaque::value($this->resource))->all()),
            'when_closure_array' => $this->when(true, /** @return list<array{a: int}> */ fn () => collect([1])->map(fn ($i): ?array => Opaque::value(['a' => $i]))->all()),
            'when_column' => $this->when(true, /** @return string */ fn () => $this->resource->featured_image_url),
            'when_first_kid_name' => $this->when(true, /** @return string */ fn () => $branch->ownNamedFirstKidName()),
            'when_flags' => $this->when(true, /** @return list<true> */ fn () => collect([1])->map(fn (int $i): bool => $i > 0)->all()),
            'bare_closure_nullable' => collect([1])->map(fn ($i): ?Post => Opaque::value($this->resource))->all(),
            'bare_param_nullsafe' => collect($labels)->map(/** @param HasLabel|null $label */ fn ($label) => $label?->label())->values()->all(),
            'bare_hedged_map' => array_map(/** @param MaybeLabel $label */ fn ($label) => $label->label(), $labels),
            'bare_first_kid_name' => $branch->ownNamedFirstKidName(),
            'bare_column' => $this->resource->featured_image_url,
            'bare_zipped' => array_map(
                fn (Comment $c, $p) => ['content' => $c->content, 'other' => $p],
                $this->resource->comments->all(),
                Opaque::value([1]),
            ),
            'var_ternary' => $ternary,
            'var_coalesce_null' => $coalesceNull,
            'var_coalesced' => $coalesced,
            'var_uncovered' => $uncovered,
            'var_mixed' => $mixed,
            'var_nullable_column' => $nullableColumn,
            'var_proven_nullsafe' => $provenNullsafe,
            'var_mapped_coalesce_null' => $mappedCoalesceNull,
            'decl_ternary' => $this->resource->id > 1 ? $this->resource->title : null,
            'decl_coalesce_null' => $this->resource->featured_image_url ?? null,
            'decl_nullable_column' => $this->resource->featured_image_url,
            'when_ternary' => $this->when(true, /** @return string */ fn () => $this->resource->id > 1 ? $this->resource->title : null),
            'when_nullable_column' => $this->when(true, /** @return string|null */ fn () => $this->resource->featured_image_url),
        ];
    }
}

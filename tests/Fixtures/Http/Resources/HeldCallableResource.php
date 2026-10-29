<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * First-class callables held in locals, passed where Laravel calls the value or a default, beside one passed straight
 * to a merge.
 *
 * @mixin Post
 */
final class HeldCallableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $label = $this->label(...);
        $upper = $this->upper(...);
        $authorName = $this->authorName(...);
        $fields = $this->heldFields(...);
        $defaultFields = $this->defaultFields(...);

        return [
            'label' => $this->when($request->has('label'), $label),
            'fallback' => $this->when($request->has('none'), 1, $label),
            'not_null' => $this->whenNotNull($label),
            'author' => $this->whenLoaded('author', $authorName),
            'shouted' => $this->transform($this->title, $upper),
            'has_title' => $this->whenHas('title', $upper),
            $this->mergeWhen($request->has('held'), $fields),
            $this->mergeUnless($request->has('plain'), [], $defaultFields),
            $this->mergeWhen($request->has('merged'), $this->mergedFields(...)),
        ];
    }

    /** The post's title, upper-cased. */
    public function label(): string
    {
        return strtoupper((string) $this->title);
    }

    /** A value, upper-cased. */
    public function upper(string $value): string
    {
        return strtoupper($value);
    }

    /** An author's name. */
    public function authorName(User $author): string
    {
        return $author->name;
    }

    /**
     * The fields a held callable merges.
     *
     * @return array{held: int}
     */
    public function heldFields(): array
    {
        return ['held' => 1];
    }

    /**
     * The fields a held callable merges as a merge's default.
     *
     * @return array{merged_default: string}
     */
    public function defaultFields(): array
    {
        return ['merged_default' => 'x'];
    }

    /**
     * The fields a callable passed straight to a merge merges.
     *
     * @return array{merged: bool}
     */
    public function mergedFields(): array
    {
        return ['merged' => true];
    }
}

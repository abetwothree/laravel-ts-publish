<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Interpolated keys whose pattern another spread method also fills, through a named key or its own
 * interpolated key, so each signature's value must cover every key it matches.
 *
 * @mixin Post
 */
final class SamePatternKeysResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->opaqueTags(),
            ...$this->priceTag(),
            ...$this->literalNotes(),
            ...$this->countNote(),
            ...$this->literalCodes(),
            ...$this->opaqueCodes(),
            ...$this->literalMarks(),
            ...$this->numericMarks(),
            'id' => $this->id,
        ];
    }

    /**
     * Only the docblock types these values.
     *
     * @return array<string, string>
     */
    public function opaqueTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** A named key the `_tag` pattern also matches. */
    public function priceTag(): array
    {
        return ['price_tag' => 5];
    }

    /** The body types these values. */
    public function literalNotes(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_note"] = 'Note';
        }

        return $data;
    }

    /** A named key the `_note` pattern also matches. */
    public function countNote(): array
    {
        return ['count_note' => 5];
    }

    /** The body types these values. */
    public function literalCodes(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_code"] = 'Code';
        }

        return $data;
    }

    /**
     * The same pattern again, with values only the docblock types.
     *
     * @return array<string, int>
     */
    public function opaqueCodes(): array
    {
        $data = [];

        foreach (['north', 'south'] as $name) {
            $data["{$name}_code"] = $this->opaque();
        }

        return $data;
    }

    /** The body types these values. */
    public function literalMarks(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_mark"] = 'Mark';
        }

        return $data;
    }

    /** The same pattern again, with a different body-typed value. */
    public function numericMarks(): array
    {
        $data = [];

        foreach (['north', 'south'] as $name) {
            $data["{$name}_mark"] = 5;
        }

        return $data;
    }

    /** Deliberately untyped so only a docblock can type what it returns. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * Interpolated keys written in the returned array and inside merges beside same-pattern keys, so each signature's
 * value covers every key it matches, and merges of a helper's keys and of the model itself.
 *
 * @mixin Tag
 */
final class MergedSignatureKeysResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->colorNotes(),
            "{$this->id}_note" => $this->id,
            $this->merge(fn () => $this->labelFields()),
            $this->mergeWhen($this->id > 0, fn () => ["{$this->slug}_label" => $this->id]),
            $this->mergeWhen($this->color !== null, fn () => $this->resource),
        ];
    }

    /** `_note` keys the body types. */
    public function colorNotes(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_note"] = 'Note';
        }

        return $data;
    }

    /** A named key the `_label` pattern also matches. */
    public function labelFields(): array
    {
        return ['main_label' => $this->name];
    }
}

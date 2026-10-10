<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\PostResource;

/** One shape per method: keys a returned array or a merge sets that are not plain literal keys. */
final class MergedValueReadsResource extends JsonResource
{
    /** A concatenated key, and an interpolated key in a nested array. */
    public function toArray(Request $request): array
    {
        return [
            'tag_'.$this->id => true,
            'box' => ["{$this->id}_inner" => 5, 'x' => 1],
        ];
    }

    /** A closure returning the resource's own model, behind a condition. */
    public function closureModel(): array
    {
        return ['id' => $this->id, $this->mergeWhen($this->id > 0, fn () => $this->resource)];
    }

    /** A closure returning a helper's keys, merged unconditionally. */
    public function closureMethod(): array
    {
        return ['id' => $this->id, $this->merge(fn () => $this->extras())];
    }

    /** The model and the helper passed as they are, without a closure. */
    public function directValues(): array
    {
        return [$this->mergeWhen($this->id > 0, $this->resource), $this->merge($this->extras())];
    }

    /** A key set before a merge that sets it again. */
    public function keyBeforeMerge(): array
    {
        return ['id' => $this->title, $this->mergeWhen($this->id > 0, ['id' => 5])];
    }

    /** Two `_note` entries of different types beside a resource-typed `main_note`, which no union takes in. */
    public function putBackNotes(): array
    {
        return [...$this->colorNotes(), "{$this->id}_note" => $this->id, 'main_note' => new PostResource($this->resource)];
    }

    /** A `+=` of a `_note` entry onto a variable that already holds `_note` entries of another type. */
    public function plusNotes(): array
    {
        $data = $this->colorNotes();
        $data += ["{$this->id}_note" => $this->id];

        return $data;
    }

    /** The issue's shape: the pinned branch is a literal, the other merges the model behind a condition. */
    public function pinnedModel(): array
    {
        if ($this->is_pinned) {
            return ['id' => $this->id, 'title' => $this->title];
        }

        $data = ['id' => $this->id, $this->mergeWhen($this->word_count > 0, fn () => $this->resource)];

        return $data;
    }

    /** As above, with the model merged unconditionally. */
    public function pinnedMergedModel(): array
    {
        if ($this->is_pinned) {
            return ['id' => $this->id, 'title' => $this->title];
        }

        $data = ['id' => $this->id, $this->merge(fn () => $this->resource)];

        return $data;
    }

    /** A closure returning a static call the analysis does not read. */
    public function unreadClosure(): array
    {
        if ($this->is_pinned) {
            return ['id' => $this->id, 'extra_a' => 2];
        }

        $data = ['id' => $this->id, $this->merge(fn () => MergedFields::all())];

        return $data;
    }

    /** A closure returning a variable whose whole write the walk does not read. */
    public function unreadVariable(): array
    {
        if ($this->is_pinned) {
            return ['id' => $this->id, 'extra_a' => 2];
        }

        $data = ['id' => $this->id, $this->merge(function () {
            $fields = MergedFields::all();

            return $fields;
        })];

        return $data;
    }

    /** A static call passed as it is. */
    public function unreadValue(): array
    {
        if ($this->is_pinned) {
            return ['id' => $this->id, 'extra_a' => 2];
        }

        $data = ['id' => $this->id, $this->merge(MergedFields::all())];

        return $data;
    }

    /** A helper's keys. */
    public function extras(): array
    {
        return ['extra_a' => 1, 'extra_b' => 'x'];
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
}

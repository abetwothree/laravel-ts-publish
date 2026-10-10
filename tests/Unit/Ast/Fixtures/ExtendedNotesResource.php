<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Two `_note` entries of different types under an extends clause, which puts back the union the analysis made.
 *
 * @mixin Post
 */
#[TsExtends('BaseResource', import: '@/types/base')]
final class ExtendedNotesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->colorNotes(), "{$this->id}_note" => $this->id];
    }

    /**
     * `_note` keys the body types.
     *
     * @return array<string, string>
     */
    public function colorNotes(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_note"] = 'Note';
        }

        return $data;
    }
}

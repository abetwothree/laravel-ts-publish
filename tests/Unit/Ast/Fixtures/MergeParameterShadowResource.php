<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Merge closures whose optional parameters shadow an outer local and a loop variable: each holds its default.
 *
 * @mixin Post
 */
final class MergeParameterShadowResource extends JsonResource
{
    /** Reads an outer local and a loop variable, then shadows each with a merge closure's optional parameter. */
    public function toArray(Request $request): array
    {
        $title = $this->title;
        $ids = [];

        foreach ($this->comments as $comment) {
            $ids[] = $comment->id;
        }

        return [
            'outer_title' => $title,
            $this->mergeWhen(true, fn ($title = null) => ['merged_shadow' => $title]),
            $this->merge(fn ($count = 5) => ['merged_default' => $count]),
            $this->mergeWhen(true, fn ($comment = null) => ['merged_loop' => $comment]),
        ];
    }
}

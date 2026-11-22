<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DocblockReads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;
use Workbench\App\ValueObjects\DocblockReads\OptionItem;

/**
 * `@var X[]` over string-keyed locals with controls, and a typed two-array `array_map()` (GH-134; plan tasks T1, T4).
 *
 * @mixin Post
 */
class BracketVarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Comment[] $commentsBySlug */
        $commentsBySlug = Opaque::value($this->resource->comments->keyBy(fn (Comment $c) => 'c'.$c->id)->all());
        /** @var array<string, Comment> $commentRecord */
        $commentRecord = Opaque::value($this->resource->comments->keyBy(fn (Comment $c) => 'c'.$c->id)->all());
        /** @var Comment[] $commentList */
        $commentList = $this->resource->comments->all();
        /** @var OptionItem[] $optionsByName */
        $optionsByName = Opaque::value(['first' => new OptionItem]);
        /** @var array<string, OptionItem> $optionRecord */
        $optionRecord = Opaque::value(['first' => new OptionItem]);
        /** @var OptionItem[] $optionList */
        $optionList = [new OptionItem];

        return [
            'comments_by_slug' => $commentsBySlug,
            'comments_by_slug_nested' => ['inner' => $commentsBySlug],
            'comment_contents_by_slug_mapped' => array_map(fn (Comment $c) => $c->content, $commentsBySlug),
            'comment_contents_by_slug_collected' => collect($commentsBySlug)->map(fn ($c) => $c->content)->all(),
            'comment_record' => $commentRecord,
            'comment_list' => $commentList,
            'options_by_name' => $optionsByName,
            'options_by_name_nested' => ['inner' => $optionsByName],
            'option_ids_by_name_mapped' => array_map(fn (OptionItem $o) => $o->itemId, $optionsByName),
            'option_ids_by_name_collected' => collect($optionsByName)->map(fn (OptionItem $o) => $o->itemId)->all(),
            'option_record' => $optionRecord,
            'option_list' => $optionList,
            'zipped_with_untyped_posts' => array_map(
                fn (Comment $c, Post $p) => ['content' => $c->content, 'title' => $p->title],
                $this->resource->comments->all(),
                Opaque::value([$this->resource]),
            ),
            'titles_from_untyped_posts' => array_map(fn (Post $p) => $p->title, Opaque::value([$this->resource])),
            'zipped_typed_comments' => array_map(
                fn (Comment $c, Comment $same) => ['content' => $c->content, 'id' => $same->id],
                $this->resource->comments->all(),
                $this->resource->comments->all(),
            ),
        ];
    }
}

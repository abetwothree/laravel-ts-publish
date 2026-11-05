<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post's members read through getAttribute() and getAttributeValue() on every receiver, and the spellings whose
 * reading those calls leave alone: a key that is no literal, an unpacked one, and the original-value reads.
 *
 * @extends JsonResource<Post>
 */
final class AttributeReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $local = $this->resource;
        $key = (string) $request->input('key', 'title');
        $keys = ['title'];

        return [
            'attr' => $this->resource->getAttribute('title'),
            'attr_value' => $this->resource->getAttributeValue('title'),
            'attr_proxy' => $this->getAttribute('title'),
            'attr_local' => $local->getAttribute('title'),
            'attr_accessor' => $this->resource->getAttribute('title_display'),
            'attr_enum' => $this->resource->getAttribute('status'),
            'attr_relation' => $this->resource->getAttribute('author'),
            'attr_value_relation' => $this->resource->getAttributeValue('author'),
            'attr_missing' => $this->resource->getAttribute('nope'),
            'attr_rel_one' => $this->resource->author->getAttribute('name'),
            'attr_rel_elem' => $this->resource->comments->first()?->getAttribute('content'),
            'attr_closure' => $this->whenLoaded('author', fn ($author) => $author->getAttribute('name')),
            'attr_new' => (new Post)->getAttribute('title'),
            'attr_new_relation' => (new Post)->getAttribute('author'),
            'prop_new_relation' => (new Post)->author,
            'attr_dynamic' => $this->resource->getAttribute($key),
            'attr_unpacked' => $this->resource->getAttribute(...$keys),
            'raw_original' => $this->resource->getRawOriginal('title'),
            'original' => $this->resource->getOriginal('title'),
        ];
    }
}

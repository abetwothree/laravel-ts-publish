<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The arrays `array_keys()` reads, those its type lists though PHP may key them by strings beside those keyed by ints,
 * and the `??` lefts a null object on the way can turn null.
 *
 * @mixin Post
 */
final class ArrayKeysSourcesResource extends JsonResource
{
    public const array RECORD = ['x' => 1, 'y' => 2];

    /** @var array<string, int> */
    public array $scores = ['x' => 1, 'y' => 2];

    public array $plain = ['x' => 1];

    /** @var array */
    public $docArray = ['x' => 1];

    public array $spreadDefault = [1, ...self::RECORD];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plain' => array_keys($this->plain),
            'doc_array' => array_keys($this->docArray),
            'native_return' => array_keys($this->nativeArray()),
            'doc_return' => array_keys($this->docArrayReturn()),
            'cast' => array_keys((array) $this->resource->only('id')),
            'spread_default' => array_keys($this->spreadDefault),
            'bracket_doc' => array_keys($this->bracketDoc()),
            'generic_doc' => array_keys($this->genericDoc()),
            'collect_plain' => collect($this->plain)->keys()->all(),
            'collect_bracket_doc' => collect($this->bracketDoc())->keys()->all(),
            'list_doc' => array_keys($this->listDoc()),
            'int_keyed_doc' => array_keys($this->intKeyedDoc()),
            'array_key_doc' => array_keys($this->arrayKeyDoc()),
            'author_posts' => $this->author->posts ?? $this->scores,
            'first_replies' => $this->comments->first()->replies ?? $this->scores,
            'last_replies' => $this->comments->last()->replies ?? $this->scores,
            'author_comments' => $this->author->comments ?? ['a' => 1],
            'comments_only' => $this->comments->only([5, 7]) ?? $this->scores,
        ];
    }

    /** Natively an array PHP may key by strings. */
    public function nativeArray(): array
    {
        return json_decode('{"k":1}', true);
    }

    /** @return array */
    public function docArrayReturn()
    {
        return json_decode('{"k":1}', true);
    }

    /** @return string[] */
    public function bracketDoc(): array
    {
        return ['a' => 'x'];
    }

    /** @return array<string> */
    public function genericDoc(): array
    {
        return ['b' => 'y'];
    }

    /** @return list<string> */
    public function listDoc(): array
    {
        return ['a', 'b'];
    }

    /** @return array<int, string> */
    public function intKeyedDoc(): array
    {
        return [3 => 'a', 5 => 'b'];
    }

    /** @return array<array-key, int> */
    public function arrayKeyDoc(): array
    {
        return ['c' => 1];
    }
}

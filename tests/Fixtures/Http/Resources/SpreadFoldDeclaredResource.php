<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource writing keys, then spreading a method's array whose `@return` declares one of them optional.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldDeclaredResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $partial = $this->resource->partial($request->boolean('picked'));

        return [
            'by' => $this->resource->author,
            'n' => 'x',
            ...$this->resource->partial($request->boolean('picked')),
            'row' => ['by' => $this->resource->author, 'n' => 'x', ...$this->resource->partial($request->boolean('picked'))],
            'static' => ['n' => 'x', ...FoldBox::partialOf($request->boolean('picked'))],
            'local' => ['n' => 'x', ...$partial],
        ];
    }
}

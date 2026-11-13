<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource writing a key, then writing it again on every path with a value of another class.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldCertainResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $app = ['u' => $this->resource->author];
        $crm = ['c' => $this->resource->crmStatus];

        return [
            'crm_then_app' => ['u' => $this->resource->crmAuthor, ...$app],
            'crm_then_wrap' => [...$crm, 'c' => EnumResource::make($this->resource->status), 's' => $this->resource->status],
        ];
    }
}

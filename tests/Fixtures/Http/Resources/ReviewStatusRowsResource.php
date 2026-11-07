<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;

/**
 * Sends a subject as its wrapped status or as rows whose cached status only the `@return` docblock types, the same enum
 * bare (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewStatusRowsResource extends JsonResource
{
    /** @return array{subject: Status|list<array{s: Status}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->kind === 'status'
                ? EnumResource::make($this->resource->status)
                : array_map(fn ($r) => ['s' => cache('s')], $this->resource->rows),
        ];
    }
}

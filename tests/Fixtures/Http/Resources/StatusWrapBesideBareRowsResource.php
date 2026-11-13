<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;

/**
 * Sends a subject as its wrapped status, the same status bare, or rows whose cached status only the `@return` types
 * (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class StatusWrapBesideBareRowsResource extends JsonResource
{
    /** @return array{subject: Status|list<array{s: Status}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->kind === 'wrap'
                ? EnumResource::make($this->resource->status)
                : ($this->resource->kind === 'status'
                    ? $this->resource->status
                    : array_map(fn ($r) => ['s' => cache('s')], $this->resource->rows)),
        ];
    }
}

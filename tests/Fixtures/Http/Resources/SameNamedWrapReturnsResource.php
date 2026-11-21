<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;

/**
 * A test-only resource whose returns each send a wrap or a read of the app's or the CRM's `Status`, the app's twice
 * (GH-76).
 *
 * @property-read EnumWrapBox $resource
 */
final class SameNamedWrapReturnsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource->c) {
            return ['wrap' => EnumResource::make($this->resource->status), 'read' => $this->resource->status];
        }

        if ($this->resource->d) {
            return ['wrap' => EnumResource::make($this->resource->status), 'read' => $this->resource->status];
        }

        return ['wrap' => EnumResource::make($this->resource->crmStatus), 'read' => $this->resource->crmStatus];
    }
}

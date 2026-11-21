<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose `except()` leaves out every key that reads the app's `Status`, so the CRM `Status` it
 * keeps is the only enum of that name in the file (GH-160).
 *
 * @mixin Warehouse
 */
final class ExceptAppStatusesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->except(['status', 'review_priority', 'review_priority_typed', 'review_priority_typed_short']);
    }
}

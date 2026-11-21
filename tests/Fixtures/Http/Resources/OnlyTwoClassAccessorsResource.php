<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource that keeps, through its own `only()`, an accessor typed as two enums and one typed as two
 * models that share a name (GH-160).
 *
 * @mixin Warehouse
 */
final class OnlyTwoClassAccessorsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'review_priority', 'last_user_activity_by_typed']);
    }
}

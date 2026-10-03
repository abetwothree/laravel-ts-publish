<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Badge;

/**
 * A test-only resource whose cast imports a type named like the const of the enum another key wraps.
 *
 * @mixin Badge
 */
#[TsCasts(['label' => ['type' => 'ClearanceType', 'import' => '@js/types/clearance']])]
class CustomImportBadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
            'clearance_type' => EnumResource::make($this->clearance_type),
        ];
    }
}

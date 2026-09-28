<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides an accessor's own key with a longer name that contains the one its `#[TsType]` import supplies.
 *
 * @mixin Warehouse
 */
#[TsCasts(['menu_config' => 'MenuSettingsTypeV2 | null'])]
final class LongerNameCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'menu_config' => $this->menu_config,
        ];
    }
}

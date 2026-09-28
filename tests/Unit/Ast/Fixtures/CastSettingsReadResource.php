<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a read of an accessor typed by a `#[TsType(import:)]` class, so its type no longer names that class.
 *
 * @mixin Warehouse
 */
#[TsCasts(['settings' => 'Record<string, unknown> | null'])]
final class CastSettingsReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'settings' => $this->menu_config,
        ];
    }
}

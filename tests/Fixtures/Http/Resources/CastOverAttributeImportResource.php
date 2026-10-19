<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * A test-only resource whose #[TsCasts] imports a type named like the `#[TsType]` import of the attribute its key
 * reads directly.
 *
 * @mixin Stockroom
 */
#[TsCasts(['menu_config' => ['type' => 'MenuSettingsType | null', 'import' => '@js/types/menu']])]
class CastOverAttributeImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'menu_config' => $this->menu_config,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * A test-only resource whose #[TsCasts] imports a type named like the `#[TsType]` import of the attribute its `only()`
 * key reads.
 *
 * @mixin Stockroom
 */
#[TsCasts(['menu_config' => ['type' => 'MenuSettingsType | null', 'import' => '@js/types/menu']])]
class CastOverAttributeImportOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['menu_config']),
        ];
    }
}

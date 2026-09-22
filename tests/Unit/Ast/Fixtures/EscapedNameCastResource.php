<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with the type's name spelled through a `\u{…}` identifier
 * escape, which TypeScript decodes; the values are single-quoted so PHP leaves the escapes as written.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => '\u{55}ser | null',
    'state' => '\u{53}tatusType',
    'settings' => 'Menu\u{53}ettingsType | null',
])]
final class EscapedNameCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'app' => $this->manager,
            'state' => $this->status,
            'settings' => $this->menu_config,
        ];
    }
}

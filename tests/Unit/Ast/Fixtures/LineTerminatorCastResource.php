<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with a line comment TypeScript ends at CR, U+2028 or U+2029,
 * then a template literal, the type and another template literal.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => "// c\r`a\nb` | User | `x`",
    'state' => "// c\u{2028}`a\nb` | StatusType | `x`",
    'settings' => "// c\u{2029}`a\nb` | MenuSettingsType | `x`",
])]
final class LineTerminatorCastResource extends JsonResource
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

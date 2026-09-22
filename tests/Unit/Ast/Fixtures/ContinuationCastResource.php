<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with a quoted string a backslash-newline continues, then
 * quoted arms around the type: TypeScript reads the continued string as one string and the type as a reference.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => "'a\\\nb' | 'x' | User | 'y'",
    'state' => "\"a\\\nb\" | \"x\" | StatusType | \"y\"",
    'settings' => "{ 'a\\\nb': 'x'; c: MenuSettingsType; d: 'y' }",
])]
final class ContinuationCastResource extends JsonResource
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

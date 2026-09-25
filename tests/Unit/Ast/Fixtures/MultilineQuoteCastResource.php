<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with a template literal whose second line holds an
 * apostrophe, and names the type after the literal closes, before a quoted string.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => "`line one\nit's` | User | 'x'",
    'state' => "`a\nit's` | StatusType | 'x'",
    'settings' => "`a\nit's` | MenuSettingsType | 'x'",
])]
final class MultilineQuoteCastResource extends JsonResource
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

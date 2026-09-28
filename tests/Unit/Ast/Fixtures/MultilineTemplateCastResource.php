<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with a template literal spanning two lines, and names the
 * type after the literal closes, before another template literal.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => "`line one\nline two` | User | `x`",
    'state' => "`a\nb` | StatusType | `x`",
    'settings' => "`a\nb` | MenuSettingsType | `x`",
])]
final class MultilineTemplateCastResource extends JsonResource
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

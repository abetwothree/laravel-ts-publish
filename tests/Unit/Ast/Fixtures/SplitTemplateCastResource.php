<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides one key with a template literal that never closes, then a model, an enum and a `#[TsType]` read each with
 * a value that closes it, names the type and opens another: emitted together, TypeScript lexes the pairs as one.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'label' => '`a',
    'app' => 'x` | User | `y`',
    'state' => 'x` | StatusType | `y`',
    'settings' => 'x` | MenuSettingsType | `y`',
])]
final class SplitTemplateCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->id,
            'app' => $this->manager,
            'state' => $this->status,
            'settings' => $this->menu_config,
        ];
    }
}

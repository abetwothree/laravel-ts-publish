<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model, an enum and a `#[TsType]` read each with a tuple whose variadic element names the type.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => '[string, ...User[]]',
    'state' => '[...StatusType[], string]',
    'settings' => 'readonly [...MenuSettingsType[]]',
])]
final class SpreadCastResource extends JsonResource
{
    /**
     * Reads the model, enum and `#[TsType]` attributes the overrides above retype.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'app' => $this->manager,
            'state' => $this->status,
            'settings' => $this->menu_config,
        ];
    }
}

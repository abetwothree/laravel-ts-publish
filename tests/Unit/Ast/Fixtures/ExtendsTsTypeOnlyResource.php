<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * Overrides the only read naming `MenuSettingsType`, selected through `only()`, while an extends clause without an
 * import path still names it.
 *
 * @mixin Stockroom
 */
#[TsExtends('Partial<Record<"s", MenuSettingsType>>')]
#[TsCasts(['menu_config' => 'string'])]
final class ExtendsTsTypeOnlyResource extends JsonResource
{
    /**
     * Filters the model to its id and the `#[TsType]` attribute the override above retypes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'menu_config']);
    }
}

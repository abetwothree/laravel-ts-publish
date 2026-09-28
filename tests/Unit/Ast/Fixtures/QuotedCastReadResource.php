<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides a model read and a `#[TsType]` read with string literals that spell the names they replaced.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'app' => "'User' | 'Admin'",
    'settings' => "'MenuSettingsType' | null",
])]
final class QuotedCastReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'app' => $this->manager,
            'settings' => $this->menu_config,
        ];
    }
}

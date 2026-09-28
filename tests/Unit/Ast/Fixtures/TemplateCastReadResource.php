<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides one key with a template literal holding an apostrophe, before reads that name an enum, a `#[TsType]` class
 * and a model.
 *
 * @mixin Warehouse
 */
#[TsCasts(['label' => '`${string}\'s label`'])]
final class TemplateCastReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->id,
            'state' => $this->status,
            'settings' => $this->menu_config,
            'picked' => $this->manager?->only(['id', 'name']),
        ];
    }
}

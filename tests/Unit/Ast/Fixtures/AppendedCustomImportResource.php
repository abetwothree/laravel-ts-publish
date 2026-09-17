<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Reads an accessor typed by a `#[TsType(import:)]` class through whenAppended(), under a key no accessor shares.
 *
 * @mixin Warehouse
 */
final class AppendedCustomImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'settings' => $this->whenAppended('menu_config'),
        ];
    }
}

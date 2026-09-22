<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Overrides the only read naming `StatusType`, while an extends clause without an import path still names it.
 *
 * @mixin Warehouse
 */
#[TsExtends('Partial<Record<StatusType, unknown>>')]
#[TsCasts(['state' => 'string'])]
final class ExtendsEnumCastResource extends JsonResource
{
    /**
     * Reads the enum attribute the override above retypes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'state' => $this->status,
        ];
    }
}

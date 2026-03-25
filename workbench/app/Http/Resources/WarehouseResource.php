<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\Concerns\ExtendsInterfaces;

/**
 * Resource with no @mixin or TsResource — tests convention-based model guess.
 * Also tests multiple TsExtends in parent class, trait, and locally.
 */
#[TsExtends('BaseResource', import: '@/types/base')]
class WarehouseResource extends RoutableResource
{
    use ExtendsInterfaces;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}

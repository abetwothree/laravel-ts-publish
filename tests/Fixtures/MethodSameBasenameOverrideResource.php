<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose toArray() #[TsCasts] retypes one of two keys that read same-named models.
 *
 * @mixin Warehouse
 */
class MethodSameBasenameOverrideResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['contact' => '{ id: number } | null'])]
    public function toArray(Request $request): array
    {
        return [
            'manager' => $this->manager,
            'contact' => $this->primaryContact,
        ];
    }
}

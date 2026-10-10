<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource that spreads two helpers casting one key: the first without an import, the second with.
 *
 * @mixin Warehouse
 */
class SiblingSpreadCastsReversedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->first(),
            ...$this->second(),
            'crm' => $this->primaryContact,
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['manager' => ['type' => 'User | null', 'import' => '@js/types/user']])]
    protected function second(): array
    {
        return ['manager' => $this->manager];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['manager' => 'User | null'])]
    protected function first(): array
    {
        return ['manager' => $this->manager];
    }
}

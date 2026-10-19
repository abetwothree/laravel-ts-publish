<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose inline arrays, one of them a ternary arm, spread helpers whose #[TsCasts] displace the
 * class one key reads, and spell its name, without an import, on a key with no class behind it.
 *
 * @mixin Warehouse
 */
class InlineCarriedModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [...$this->people()],
            'maybe' => $this->id ? [...$this->states()] : null,
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['owner' => 'string', 'label' => 'User | null'])]
    protected function people(): array
    {
        return ['owner' => $this->manager, 'label' => 'x'];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['state' => 'string', 'tag' => 'StatusType'])]
    protected function states(): array
    {
        return ['state' => $this->status, 'tag' => 'x'];
    }
}

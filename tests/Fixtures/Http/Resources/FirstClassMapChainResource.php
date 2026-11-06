<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * For analysis only: `map(...)` and `pluck(...)` as first-class callables inside a chain, which build a Closure that
 * `->values()` then throws on, so toArray() never runs.
 *
 * @mixin Team
 */
class FirstClassMapChainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mapped' => $this->members->map(...)->values(),
            'plucked' => $this->members->pluck(...)->values(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The bare naming-convention candidate for LedgerCollection, which Laravel takes before LedgerResource, held out
 * of the published set with #[TsExclude] so the gate must reject it.
 */
#[TsExclude]
class Ledger extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
        ];
    }
}

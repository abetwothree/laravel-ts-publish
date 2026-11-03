<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource that wraps the `Record` enum beside a key it types as TypeScript's global `Record<…>`.
 */
class LedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'record' => EnumResource::make(Record::Kept),
            'totals' => $this->totals(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function totals(): array
    {
        return ['kept' => 1];
    }
}

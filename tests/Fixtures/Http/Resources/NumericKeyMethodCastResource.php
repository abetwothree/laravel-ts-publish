<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource whose toArray() cast spells a numeric-keyed shape for one key, which the re-index leaves alone.
 *
 * @mixin Depot
 */
class NumericKeyMethodCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['cast_codes' => '{ "200": string }'])]
    public function toArray(Request $request): array
    {
        return [
            'cast_codes' => [200 => 'OK'],
            'codes' => [200 => 'OK'],
        ];
    }
}

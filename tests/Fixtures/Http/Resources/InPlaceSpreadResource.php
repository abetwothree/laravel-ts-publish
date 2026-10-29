<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose helpers turn the values of the array they are passed into strings in place, one read as a
 * value and one spread into the response.
 */
final class InPlaceSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ref_stringified' => $this->refStringified(['id' => 1, 'n' => 2]),
            ...$this->spreadWalked(['count' => 1]),
        ];
    }

    /**
     * Turns each value into a string through a by-reference foreach, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function refStringified(array $data): array
    {
        foreach ($data as &$value) {
            $value = (string) $value;
        }

        unset($value);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Prefixes each value through array_walk(), then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function spreadWalked(array $data): array
    {
        array_walk($data, function (mixed &$value): void {
            $value = "#{$value}";
        });
        $data['label'] = 'l';

        return $data;
    }
}

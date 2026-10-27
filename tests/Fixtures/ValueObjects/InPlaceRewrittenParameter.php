<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Arr;

/**
 * Helpers that rewrite the values of the array they are passed in place, at the top level and as a string where it held
 * a number, then add a key and return it.
 */
final class InPlaceRewrittenParameter
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'walked' => $this->walked(['id' => 1, 'n' => 2]),
            'walked_recursive' => $this->walkedRecursive(['id' => 1, 'n' => 2]),
            'ref_looped' => $this->refLooped(['id' => 1, 'n' => 2]),
            'arr_set' => $this->arrSet(['id' => 1, 'n' => 2]),
        ];
    }

    /**
     * Turns each value into a string through array_walk(), then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function walked(array $data): array
    {
        array_walk($data, function (mixed &$value): void {
            $value = (string) $value;
        });
        $data['x'] = 1;

        return $data;
    }

    /**
     * Turns each leaf into a string through array_walk_recursive(), then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function walkedRecursive(array $data): array
    {
        array_walk_recursive($data, function (mixed &$value): void {
            $value = (string) $value;
        });
        $data['x'] = 1;

        return $data;
    }

    /**
     * Turns each value into a string through a by-reference foreach, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function refLooped(array $data): array
    {
        foreach ($data as &$value) {
            $value = (string) $value;
        }

        unset($value);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Sets `id` to a string through Arr::set(), then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function arrSet(array $data): array
    {
        Arr::set($data, 'id', 'str');
        $data['x'] = 1;

        return $data;
    }
}

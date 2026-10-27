<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Arr;
use Throwable;

/**
 * Helpers that rewrite the values of the array they are passed in place, as a string where it held a number, at the top
 * level or inside a block, then add a key and return it.
 */
final class InPlaceRewrittenParameter
{
    /** @param  list<int>  $rows */
    public function __construct(public bool $rewrite = false, public array $rows = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'walked' => $this->walked(['id' => 1, 'n' => 2]),
            'walked_recursive' => $this->walkedRecursive(['id' => 1, 'n' => 2]),
            'ref_looped' => $this->refLooped(['id' => 1, 'n' => 2]),
            'arr_set' => $this->arrSet(['id' => 1, 'n' => 2]),
            'if_walked' => $this->ifWalked(['id' => 1]),
            'if_arr_set' => $this->ifArrSet(['id' => 1]),
            'loop_walked' => $this->loopWalked(['id' => 1]),
            'try_walked' => $this->tryWalked(['id' => 1]),
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
     * Turns each value into a string through array_walk() when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ifWalked(array $data): array
    {
        if ($this->rewrite) {
            array_walk($data, function (mixed &$value): void {
                $value = (string) $value;
            });
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Sets `id` to a string through Arr::set() when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ifArrSet(array $data): array
    {
        if ($this->rewrite) {
            Arr::set($data, 'id', 'str');
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Turns each value into a string through array_walk() once per row, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function loopWalked(array $data): array
    {
        foreach ($this->rows as $row) {
            array_walk($data, function (mixed &$value): void {
                $value = (string) $value;
            });
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Turns each value into a string through array_walk() inside a `try`, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function tryWalked(array $data): array
    {
        try {
            array_walk($data, function (mixed &$value): void {
                $value = (string) $value;
            });
        } catch (Throwable) {
        }

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

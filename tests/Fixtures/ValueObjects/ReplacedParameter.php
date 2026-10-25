<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * Helpers that write the array they are passed, whole or by one key, before they add a key, and return it.
 */
final class ReplacedParameter
{
    public bool $clear = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->replaced(['id' => 1]);
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'cleared' => $this->cleared(['id' => 1]),
            'destructured' => $this->destructured(['id' => 1]),
            'sorted' => $this->sorted(['id' => 1]),
            'matched' => $this->matched(['id' => 1]),
            'looped' => $this->looped(['id' => 1]),
        ];
    }

    /** @return array<string, mixed> */
    public function kept(): array
    {
        return [
            'ksorted' => $this->ksorted(['id' => 1]),
            'trimmed' => $this->trimmed(['id' => 1, 'name' => 'n']),
            'maybeCleared' => $this->maybeCleared(['id' => 1]),
        ];
    }

    /**
     * Replaces the array with a decoded one, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function replaced(array $data): array
    {
        $data = json_decode('{"k":1}', true);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Unsets the array, then builds a new one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleared(array $data): array
    {
        unset($data);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Destructures another array into the parameter, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function destructured(array $data): array
    {
        [$data] = [['k' => 1]];
        $data['x'] = 1;

        return $data;
    }

    /**
     * Sorts the array, which renumbers its keys, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sorted(array $data): array
    {
        sort($data);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Lets preg_match() write its matches into the parameter, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function matched(array $data): array
    {
        preg_match('/(?<k>a)/', 'a', $data);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Loops into the parameter, which holds the last row after the loop, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function looped(array $data): array
    {
        foreach ([['k' => 1]] as $data) {
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Sorts the array by key, which keeps every key, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ksorted(array $data): array
    {
        ksort($data);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Unsets one key, then adds another.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function trimmed(array $data): array
    {
        unset($data['name']);
        $data['x'] = 1;

        return $data;
    }

    /**
     * Unsets the array only when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function maybeCleared(array $data): array
    {
        if ($this->clear) {
            unset($data);
        }

        $data['x'] = 1;

        return $data;
    }
}

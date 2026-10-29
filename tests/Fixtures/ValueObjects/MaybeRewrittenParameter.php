<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Arr;

/**
 * Helpers that may rewrite the array they are passed, or a local, as a whole inside an `if`, in a way the walk cannot
 * read, before they add a key and return it.
 */
final class MaybeRewrittenParameter
{
    public bool $rewrite = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'unset' => $this->unsetMaybe(['id' => 1]),
            'sorted' => $this->sortedMaybe(['id' => 1]),
            'looped' => $this->loopedMaybe(['id' => 1]),
            'destructured' => $this->destructuredMaybe(['id' => 1]),
            'matched' => $this->matchedMaybe(['id' => 1]),
            'filtered' => $this->filteredMaybe(['id' => 0, 'name' => 'n']),
            'excepted' => $this->exceptedMaybe(['id' => 1, 'name' => 'n']),
            'local' => $this->localUnsetMaybe(),
            'resorted' => $this->resortedMaybe(['id' => 1]),
            'signed' => $this->signedLocalMaybe('a'),
        ];
    }

    /**
     * Unsets the array when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function unsetMaybe(array $data): array
    {
        if ($this->rewrite) {
            unset($data);
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Sorts the array when asked to, which renumbers its keys, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sortedMaybe(array $data): array
    {
        if ($this->rewrite) {
            sort($data);
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Loops into the parameter when asked to, which holds the last row after the loop, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function loopedMaybe(array $data): array
    {
        if ($this->rewrite) {
            foreach ([['k' => 1]] as $data) {
            }
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Destructures another array into the parameter when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function destructuredMaybe(array $data): array
    {
        if ($this->rewrite) {
            [$data] = [['k' => 1]];
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Lets preg_match() write its matches into the parameter when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function matchedMaybe(array $data): array
    {
        if ($this->rewrite) {
            preg_match('/(?<k>a)/', 'a', $data);
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Drops the array's falsy values when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filteredMaybe(array $data): array
    {
        if ($this->rewrite) {
            $data = array_filter($data);
        }

        $data['url'] = '/x';

        return $data;
    }

    /**
     * Drops the `id` key when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function exceptedMaybe(array $data): array
    {
        if ($this->rewrite) {
            $data = Arr::except($data, 'id');
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Writes a key in a `do` loop whose condition then sorts the array when asked to, else writes the key alone, then
     * adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resortedMaybe(array $data): array
    {
        if ($this->rewrite) {
            do {
                $data['k'] = 1;
            } while (! sort($data));
        } else {
            $data['k'] = 2;
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Builds a local with a key it names from a suffix, sorts it when asked to, then adds a key.
     *
     * @return array<string, mixed>
     */
    private function signedLocalMaybe(string $suffix): array
    {
        $data = ['id' => 1];
        $data["k_{$suffix}"] = 1;

        if ($this->rewrite) {
            sort($data);
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Builds a local, unsets it when asked to, then adds a key.
     *
     * @return array<string, mixed>
     */
    private function localUnsetMaybe(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            unset($data);
        }

        $data['x'] = 1;

        return $data;
    }
}

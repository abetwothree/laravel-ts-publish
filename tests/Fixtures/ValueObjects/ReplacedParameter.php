<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A helper that replaces the array it is passed with one nothing types before it writes a key, and returns it.
 */
final class ReplacedParameter
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->replaced(['id' => 1]);
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
}

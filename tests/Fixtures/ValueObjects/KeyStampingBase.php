<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * Helpers that write one key into the array they are passed and return it, for a subclass to call through `parent::`.
 */
class KeyStampingBase
{
    /**
     * Adds one key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function stamped(array $data): array
    {
        $data['x'] = 1;

        return $data;
    }

    /**
     * Adds one key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extended(array $data): array
    {
        $data['x'] = 1;

        return $data;
    }
}

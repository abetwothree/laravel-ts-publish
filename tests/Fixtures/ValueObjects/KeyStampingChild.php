<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * Overrides that hand the array they are passed to the parent's key-writing helper: one returns the parent's result,
 * the other adds a key to it first.
 */
final class KeyStampingChild extends KeyStampingBase
{
    /**
     * Returns what the parent's helper returns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function stamped(array $data): array
    {
        return parent::stamped($data);
    }

    /**
     * Adds a key to what the parent's helper returns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extended(array $data): array
    {
        $data = parent::extended($data);
        $data['y'] = 2;

        return $data;
    }
}

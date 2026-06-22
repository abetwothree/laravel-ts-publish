<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache\Contracts;

interface CacheRepository
{
    /**
     * Fetch a stored payload by key, or null when absent or invalid.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * Store a payload under a key.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): void;

    /**
     * Remove a single key.
     */
    public function forget(string $key): void;

    /**
     * Remove every key owned by this repository.
     */
    public function flush(): void;
}

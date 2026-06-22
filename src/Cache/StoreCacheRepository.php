<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;
use Illuminate\Contracts\Cache\Repository as IlluminateCache;

class StoreCacheRepository implements CacheRepository
{
    protected string $indexKey;

    public function __construct(
        protected IlluminateCache $store,
        protected string $prefix,
    ) {
        $this->indexKey = $this->prefix.':__index__';
    }

    /**
     * Fetch a prefixed payload from the store, or null when absent/invalid.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $value = $this->store->get($this->prefixed($key));

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Store a prefixed payload forever and track its key in the index.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): void
    {
        $this->store->forever($this->prefixed($key), $value);
        $this->trackKey($key);
    }

    /**
     * Remove a single prefixed key and untrack it from the index.
     */
    public function forget(string $key): void
    {
        $this->store->forget($this->prefixed($key));
        $this->untrackKey($key);
    }

    /**
     * Remove only this repository's tracked keys, never unrelated store entries.
     */
    public function flush(): void
    {
        /** @var list<string> $keys */
        $keys = $this->store->get($this->indexKey, []);

        foreach ($keys as $key) {
            $this->store->forget($this->prefixed($key));
        }

        $this->store->forget($this->indexKey);
    }

    /**
     * Namespace a logical key with this repository's prefix.
     */
    protected function prefixed(string $key): string
    {
        return $this->prefix.':'.$key;
    }

    /**
     * Add a key to the tracked-key index so flush() can find it later.
     */
    protected function trackKey(string $key): void
    {
        /** @var list<string> $keys */
        $keys = $this->store->get($this->indexKey, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->store->forever($this->indexKey, $keys);
        }
    }

    /**
     * Remove a key from the tracked-key index.
     */
    protected function untrackKey(string $key): void
    {
        /** @var list<string> $keys */
        $keys = $this->store->get($this->indexKey, []);
        $keys = array_values(array_filter($keys, fn (string $k) => $k !== $key));

        $this->store->forever($this->indexKey, $keys);
    }
}

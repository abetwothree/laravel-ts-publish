<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;
use Illuminate\Contracts\Cache\Repository as IlluminateCache;

class StoreCacheRepository implements CacheRepository
{
    protected string $indexKey;

    /** @var list<string>|null In-memory key index; null until first loaded from the store. */
    protected ?array $index = null;

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
     * Store a prefixed payload forever and track its key in the in-memory index.
     * The index is persisted once on commit(), not on every put.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): void
    {
        $this->store->forever($this->prefixed($key), $value);
        $this->trackKey($key);
    }

    /**
     * Remove a single prefixed key and untrack it from the in-memory index.
     */
    public function forget(string $key): void
    {
        $this->store->forget($this->prefixed($key));
        $this->untrackKey($key);
    }

    /**
     * Remove only this repository's tracked keys, never unrelated store entries,
     * then reset the in-memory index.
     */
    public function flush(): void
    {
        foreach ($this->loadIndex() as $key) {
            $this->store->forget($this->prefixed($key));
        }

        $this->store->forget($this->indexKey);
        $this->index = [];
    }

    /**
     * Persist the in-memory key index to the store. Call once after a batch of
     * writes so the index is rewritten a single time per run instead of per put.
     */
    public function commit(): void
    {
        if ($this->index !== null) {
            $this->store->forever($this->indexKey, $this->index);
        }
    }

    /**
     * Namespace a logical key with this repository's prefix.
     */
    protected function prefixed(string $key): string
    {
        return $this->prefix.':'.$key;
    }

    /**
     * The key index, loaded lazily from the store on first access this run.
     *
     * @return list<string>
     */
    protected function loadIndex(): array
    {
        if ($this->index === null) {
            /** @var list<string> $stored */
            $stored = $this->store->get($this->indexKey, []);
            $this->index = $stored;
        }

        return $this->index;
    }

    /**
     * Add a key to the in-memory index (persisted later by commit()).
     */
    protected function trackKey(string $key): void
    {
        $index = $this->loadIndex();

        if (! in_array($key, $index, true)) {
            $index[] = $key;
            $this->index = $index;
        }
    }

    /**
     * Remove a key from the in-memory index (persisted later by commit()).
     */
    protected function untrackKey(string $key): void
    {
        $this->index = array_values(array_filter($this->loadIndex(), fn (string $k) => $k !== $key));
    }
}

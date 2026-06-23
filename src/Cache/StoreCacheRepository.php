<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Concerns\SignsCachePayloads;
use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;
use Illuminate\Contracts\Cache\Repository as IlluminateCache;

/**
 * Generation-cache backend that persists the manifest in a Laravel cache store.
 *
 * Payloads are HMAC-signed (see SignsCachePayloads) and verified on read, which
 * protects their integrity. The underlying Laravel store, however, deserializes
 * its own values on get() — and by default (`cache.serializable_classes` unset) it
 * does so with PHP classes allowed, BEFORE this layer's HMAC is checked. The signing
 * is therefore defense-in-depth: for a shared or untrusted store, also set Laravel's
 * `cache.serializable_classes` to false (or an allowlist) and/or use a trusted store.
 * The file backend is unaffected — it deserializes with allowed_classes: false.
 */
class StoreCacheRepository implements CacheRepository
{
    use SignsCachePayloads;

    protected string $indexKey;

    /** @var list<string>|null In-memory key index; null until first loaded from the store. */
    protected ?array $index = null;

    public function __construct(
        protected IlluminateCache $store,
        protected string $prefix,
        protected ?string $key = null,
    ) {
        $this->indexKey = $this->prefix.':__index__';
    }

    /**
     * Fetch and verify a prefixed payload from the store, or null when absent,
     * unreadable, or its signature does not match. Entry payloads are HMAC-signed
     * and deserialized with objects disabled, so a tampered store entry can never
     * inject an object into the build.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $value = $this->store->get($this->prefixed($key));

        if (! is_string($value)) {
            return null;
        }

        return $this->readSignedPayload($value, $this->key);
    }

    /**
     * Sign and store a prefixed payload forever and track its key in the
     * in-memory index. The index is persisted once on commit(), not on every put.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): void
    {
        $this->store->forever($this->prefixed($key), $this->signPayload($value, $this->key));
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
     * The index holds only key names and is never deserialized into objects, so
     * it is stored as-is without signing.
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

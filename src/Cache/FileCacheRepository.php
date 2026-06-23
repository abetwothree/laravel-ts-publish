<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;
use Throwable;

class FileCacheRepository implements CacheRepository
{
    public function __construct(
        protected string $directory,
        protected ?string $key,
    ) {
        $this->ensureDirectory();
    }

    /**
     * Read and unserialize a payload, verifying the HMAC signature first when a
     * signing key is configured. Tampered or corrupt files are forgotten.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $file = $this->path($key);

        if (! is_file($file)) {
            return null;
        }

        $payload = $this->unwrap((string) file_get_contents($file), $key);

        if ($payload === null) {
            return null;
        }

        try {
            // allowed_classes: false — manifest payloads are plain arrays; never
            // instantiate objects here (the transformer snapshot is a base64
            // string that BaseRunner deserializes separately). Closes the
            // object-injection surface on the unsigned cache path.
            $data = unserialize($payload, ['allowed_classes' => false]);
        } catch (Throwable) {
            $this->forget($key);

            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $typed = [];

        foreach ($data as $k => $v) {
            if (! is_string($k)) {
                return null;
            }

            $typed[$k] = $v;
        }

        return $typed;
    }

    /**
     * Serialize and write a payload, prepending an HMAC signature when a signing
     * key is configured.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): void
    {
        $this->ensureDirectory();

        $serialized = serialize($value);

        if ($this->key !== null && $this->key !== '') {
            $serialized = hash_hmac('sha256', $serialized, $this->key).':'.$serialized;
        }

        file_put_contents($this->path($key), $serialized);
    }

    /**
     * Delete the cache file for a key, if it exists.
     */
    public function forget(string $key): void
    {
        $file = $this->path($key);

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Delete every `*.cache` file in the cache directory.
     */
    public function flush(): void
    {
        foreach (glob($this->directory.'/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * No-op: the file backend writes each key eagerly, so there is no buffered
     * bookkeeping to persist.
     */
    public function commit(): void
    {
        //
    }

    /**
     * Verify and strip the HMAC signature, returning the raw serialized payload,
     * or null (forgetting the key) when the signature is missing or invalid.
     */
    protected function unwrap(string $content, string $key): ?string
    {
        if ($this->key === null || $this->key === '') {
            return $content;
        }

        if (! str_contains($content, ':')) {
            $this->forget($key);

            return null;
        }

        [$signature, $serialized] = explode(':', $content, 2);

        if (! hash_equals($signature, hash_hmac('sha256', $serialized, $this->key))) {
            $this->forget($key);

            return null;
        }

        return $serialized;
    }

    /**
     * Map a logical key to its on-disk cache file path.
     */
    protected function path(string $key): string
    {
        return $this->directory.'/'.hash('xxh128', $key).'.cache';
    }

    /**
     * Create the cache directory if missing and ensure it self-ignores in git.
     */
    protected function ensureDirectory(): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $gitignore = $this->directory.'/.gitignore';

        if (! is_file($gitignore)) {
            file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }
}

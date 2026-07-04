<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Concerns\SignsCachePayloads;
use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;

class FileCacheRepository implements CacheRepository
{
    use SignsCachePayloads;

    public function __construct(
        protected string $directory,
        protected ?string $key,
    ) {
        $this->ensureDirectory();
    }

    /**
     * Read, verify, and unserialize a payload (objects disabled). Tampered or
     * corrupt files are forgotten so the next run rebuilds cleanly.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $file = $this->path($key);

        if (! is_file($file)) {
            return null;
        }

        $data = $this->readSignedPayload((string) file_get_contents($file), $this->key);

        if ($data === null) {
            $this->forget($key);

            return null;
        }

        return $data;
    }

    /**
     * Sign and write a payload to disk.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): void
    {
        $this->ensureDirectory();

        file_put_contents($this->path($key), $this->signPayload($value, $this->key));
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

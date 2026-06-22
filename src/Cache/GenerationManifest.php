<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use AbeTwoThree\LaravelTsPublish\Cache\Contracts\CacheRepository;

/**
 * @phpstan-type Entry = array{fingerprint: string, filename: string, deps: list<string>, outputs: list<string>, snapshot: string}
 */
class GenerationManifest
{
    public const META_KEY = '__meta__';

    /**
     * @param  array<string, Entry>  $entries
     * @param  array<string, true>  $seen
     */
    private function __construct(
        protected CacheRepository $repository,
        protected string $version,
        protected string $configHash,
        protected array $entries = [],
        protected array $seen = [],
    ) {}

    /**
     * Load the manifest from the repository, busting everything when the stored
     * header no longer matches the current package version or config hash.
     */
    public static function load(CacheRepository $repository, string $version, string $configHash): self
    {
        $meta = $repository->get(self::META_KEY);

        $valid = is_array($meta)
            && ($meta['version'] ?? null) === $version
            && ($meta['config_hash'] ?? null) === $configHash;

        if (! $valid) {
            $repository->flush();

            return new self($repository, $version, $configHash);
        }

        /** @var list<string> $classes */
        $classes = $meta['classes'] ?? [];
        $entries = [];

        foreach ($classes as $fqcn) {
            $entry = $repository->get(self::entryKey($fqcn));

            if (is_array($entry) && isset($entry['fingerprint'], $entry['filename'], $entry['deps'], $entry['outputs'], $entry['snapshot'])) {
                /** @var Entry $entry */
                $entries[$fqcn] = $entry;
            }
        }

        return new self($repository, $version, $configHash, $entries);
    }

    /**
     * Determine whether a class can be served from cache: its fingerprint must
     * match AND every output file it previously produced must still exist on
     * disk (guards against a manually deleted output directory).
     */
    public function hit(string $fqcn, string $fingerprint): bool
    {
        $entry = $this->entries[$fqcn] ?? null;

        if ($entry === null || $entry['fingerprint'] !== $fingerprint) {
            return false;
        }

        foreach ($entry['outputs'] as $path) {
            if (! is_file($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The dependency file paths recorded for a class on its last full build.
     *
     * @return list<string>
     */
    public function deps(string $fqcn): array
    {
        return $this->entries[$fqcn]['deps'] ?? [];
    }

    /**
     * The base64-encoded serialized transformer snapshot for a class, if cached.
     */
    public function snapshot(string $fqcn): ?string
    {
        return $this->entries[$fqcn]['snapshot'] ?? null;
    }

    /**
     * The cached output filename (without extension) for a class, if cached.
     */
    public function filename(string $fqcn): ?string
    {
        return $this->entries[$fqcn]['filename'] ?? null;
    }

    /**
     * Record a freshly built class: its fingerprint, output filename, the
     * dependency files and output files it touched, and its transformer snapshot.
     *
     * @param  list<string>  $deps
     * @param  list<string>  $outputs
     */
    public function record(string $fqcn, string $fingerprint, string $filename, array $deps, array $outputs, string $snapshot): void
    {
        $this->entries[$fqcn] = [
            'fingerprint' => $fingerprint,
            'filename' => $filename,
            'deps' => $deps,
            'outputs' => $outputs,
            'snapshot' => $snapshot,
        ];

        $this->markSeen($fqcn);
    }

    /**
     * Mark a class as seen this run so it survives the prune on save().
     */
    public function markSeen(string $fqcn): void
    {
        $this->seen[$fqcn] = true;
    }

    /**
     * Persist all seen entries and the header, pruning any class not seen this
     * run (i.e. removed from the source tree).
     */
    public function save(): void
    {
        foreach (array_keys($this->entries) as $fqcn) {
            if (! isset($this->seen[$fqcn])) {
                $this->repository->forget(self::entryKey($fqcn));
                unset($this->entries[$fqcn]);
            }
        }

        // Rewrites every surviving entry each run. Cheap at expected class
        // counts (one write per class, same order as the per-class file writes
        // the run already performs); revisit with dirty-tracking if it bites.
        foreach ($this->entries as $fqcn => $entry) {
            $this->repository->put(self::entryKey($fqcn), $entry);
        }

        $this->repository->put(self::META_KEY, [
            'version' => $this->version,
            'config_hash' => $this->configHash,
            'classes' => array_keys($this->entries),
        ]);
    }

    /**
     * Build the repository key for a class entry.
     */
    protected static function entryKey(string $fqcn): string
    {
        return 'class:'.$fqcn;
    }
}

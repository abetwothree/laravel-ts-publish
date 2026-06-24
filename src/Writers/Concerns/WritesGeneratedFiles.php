<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers\Concerns;

use AbeTwoThree\LaravelTsPublish\Cache\OutputRecorder;
use Illuminate\Filesystem\Filesystem;

/**
 * @property Filesystem $filesystem
 */
trait WritesGeneratedFiles
{
    /**
     * Write only when the file is missing or its content differs, mirroring
     * Wayfinder's content-skip so unchanged files keep their mtime (no spurious
     * Vite reloads). Always records the path for the generation cache.
     */
    protected function putIfChanged(string $path, string $content): void
    {
        if (! $this->filesystem->exists($path) || $this->filesystem->get($path) !== $content) {
            $this->filesystem->put($path, $content);
        }

        OutputRecorder::record($path);
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers\Concerns;

use RuntimeException;

trait EnsuresDirectoryExists
{
    /**
     * Create the output directory if missing, tolerating concurrent writers
     * (e.g. parallel Vite builds targeting the same shared output tree)
     * racing to create it.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create output directory [{$path}].");
        }
    }
}

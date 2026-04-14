<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use Illuminate\Filesystem\Filesystem;

/**
 * Reads a configured source file, .env, or .env.example for VITE_-prefixed variables
 * and writes a vite-env.d.ts declaration file that augments Vite's ImportMetaEnv.
 *
 * All variables are typed as string since Vite always provides strings at runtime.
 */
class ViteEnvWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * Parse the env source file, render the template, and optionally write to disk.
     *
     * @return string The rendered vite-env.d.ts content, or empty string when disabled.
     */
    public function write(): string
    {
        if (! config()->boolean('ts-publish.vite_env.enabled')) {
            return '';
        }

        $variables = $this->parseViteVariables();

        if ($variables === []) {
            return '';
        }

        $content = view('laravel-ts-publish::vite-env', [
            'variables' => $variables,
        ])->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeToDisk($content);
        }

        return $content;
    }

    /**
     * Extract VITE_-prefixed variable names from the env source file.
     *
     * @return list<string> Sorted list of VITE_ variable names.
     */
    protected function parseViteVariables(): array
    {
        $sourcePath = $this->resolveSourcePath();

        if (! $this->filesystem->exists($sourcePath)) {
            return [];
        }

        $contents = $this->filesystem->get($sourcePath);
        $lines = explode("\n", $contents);

        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Extract variable name (before = sign)
            $equalsPos = strpos($line, '=');

            if ($equalsPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $equalsPos));

            if (str_starts_with($name, 'VITE_')) {
                $variables[] = $name;
            }
        }

        sort($variables);

        return array_values(array_unique($variables));
    }

    /**
     * Resolve the absolute path to the env source file.
     *
     * Tries .env first, then falls back to .env.example when no source file is configured.
     *
     * @return string Absolute path to the env source file.
     */
    protected function resolveSourcePath(): string
    {
        $configured = config('ts-publish.vite_env.source_file');

        if (is_string($configured) && $configured !== '') {
            return str_starts_with($configured, '/') ? $configured : base_path($configured);
        }

        $envPath = base_path('.env');

        if ($this->filesystem->exists($envPath)) {
            return $envPath;
        }

        return base_path('.env.example');
    }

    /**
     * Write the rendered content to the output directory.
     *
     * @param  string  $content  The rendered vite-env.d.ts content.
     */
    protected function writeToDisk(string $content): void
    {
        $outputPath = config('ts-publish.vite_env.output_path');
        $outputDir = is_string($outputPath) && $outputPath !== ''
            ? $outputPath
            : config()->string('ts-publish.output_directory');

        $filename = config()->string('ts-publish.vite_env.filename', 'vite-env.d.ts');

        $this->filesystem->ensureDirectoryExists($outputDir);
        $this->filesystem->put("$outputDir/$filename", $content);
    }
}

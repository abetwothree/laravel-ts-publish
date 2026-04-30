<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

/**
 * Writes the inertia module augmentation file.
 *
 * @phpstan-import-type SharedDataResult from InertiaSharedDataAnalyzer
 */
class InertiaConfigWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * Render and optionally write the inertia augmentation file.
     *
     * @param  SharedDataResult  $sharedData
     */
    public function write(array $sharedData): string
    {
        $content = view('laravel-ts-publish::inertia-config', $sharedData)->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeFile($content);
        }

        return $content;
    }

    /**
     * Write the inertia augmentation file to disk.
     */
    protected function writeFile(string $content): void
    {
        $outputPath = $this->resolveOutputPath();
        $filename = Config::string('ts-publish.inertia.augmentation_filename');

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("{$outputPath}/{$filename}", $content);
    }

    /**
     * Resolve the output directory for the inertia augmentation file
     */
    protected function resolveOutputPath(): string
    {
        $inertiaOutputPath = config('ts-publish.inertia.output_path');

        if (is_string($inertiaOutputPath)) {
            return $inertiaOutputPath;
        }

        $routesOutputPath = config('ts-publish.routes.output_path');

        if (is_string($routesOutputPath)) {
            return $routesOutputPath;
        }

        return config()->string('ts-publish.output_directory');
    }
}

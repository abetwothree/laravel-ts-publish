<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

/**
 * Generate a barrel list of export files for a .d.ts or .ts file that re-exports all generated types and enums.
 */
class BarrelWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * @param  Collection<int, CoreGenerator<mixed>>  $transformers
     */
    public function write(Collection $transformers, string $filename, string $outputDirectory): string
    {
        $content = $transformers
            ->map(fn (CoreGenerator $transformer) => $transformer->filename())
            ->unique()
            ->sort()
            ->map(fn (string $file) => "export * from './{$file}';")
            ->implode("\n");

        if (config()->boolean('ts-publish.output_to_files')) {
            $outputPath = config()->string('ts-publish.output_directory')."/$outputDirectory";
            $this->filesystem->ensureDirectoryExists($outputPath);
            $this->filesystem->put("$outputPath/$filename.ts", $content);
        }

        return $content;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
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
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $transformers
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

    /**
     * Write per-namespace barrel files for modular publishing.
     *
     * Groups generators by their transformer's namespacePath and writes
     * an index.ts barrel file for each unique namespace directory.
     *
     * @param  Collection<int, EnumGenerator>|Collection<int, ModelGenerator>|Collection<int, ResourceGenerator>  $generators
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function writeModular(Collection $generators): array
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = [];

        foreach ($generators as $generator) {
            $namespacePath = $generator->transformer->namespacePath;
            $filename = $generator->filename();
            $grouped[$namespacePath][] = $filename;
        }

        /** @var array<string, string> $results */
        $results = [];

        foreach ($grouped as $namespacePath => $filenames) {
            $content = collect($filenames)
                ->unique()
                ->sort()
                ->map(fn (string $file) => "export * from './{$file}';")
                ->implode("\n");

            if (config()->boolean('ts-publish.output_to_files')) {
                $outputPath = config()->string('ts-publish.output_directory').'/'.$namespacePath;
                $this->filesystem->ensureDirectoryExists($outputPath);
                $this->filesystem->put("$outputPath/index.ts", $content);
            }

            $results[$namespacePath] = $content;
        }

        ksort($results);

        return $results;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Generate a barrel list of export files for a .d.ts or .ts file that re-exports all generated types and enums.
 */
class BarrelWriter
{
    use WritesGeneratedFiles;

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

        if (Config::boolean('ts-publish.output_to_files')) {
            $outputPath = Config::string('ts-publish.output_directory')."/$outputDirectory";
            $this->filesystem->ensureDirectoryExists($outputPath);
            $this->putIfChanged("$outputPath/$filename.ts", $content);
        }

        return $content;
    }

    /**
     * Write per-namespace barrel files for modular publishing.
     *
     * Groups generators by their transformer's namespacePath and writes
     * an index.ts barrel file for each unique namespace directory.
     *
     * @param  Collection<int, BroadcastEventGenerator>|Collection<int, EnumGenerator>|Collection<int, FormRequestGenerator>|Collection<int, ModelGenerator>|Collection<int, ResourceGenerator>  $generators
     * @param  string|null  $outputBase  Base output directory for the barrel files. Falls back to the global output_directory when null/empty. This must match the directory the corresponding per-file writer targets so the modular export structure stays intact.
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function writeModular(Collection $generators, ?string $outputBase = null): array
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = [];

        foreach ($generators as $generator) {
            $namespacePath = $generator->transformer->namespacePath;
            $filename = $generator->filename();
            $grouped[$namespacePath][] = $filename;
        }

        $base = is_string($outputBase) && $outputBase !== ''
            ? $outputBase
            : Config::string('ts-publish.output_directory');

        /** @var array<string, string> $results */
        $results = [];

        foreach ($grouped as $namespacePath => $filenames) {
            $content = collect($filenames)
                ->unique()
                ->sort()
                ->map(fn (string $file) => "export * from './{$file}';")
                ->implode("\n");

            if (Config::boolean('ts-publish.output_to_files')) {
                $outputPath = $base.'/'.$namespacePath;
                $this->filesystem->ensureDirectoryExists($outputPath);
                $this->putIfChanged("$outputPath/index.ts", $content);
            }

            $results[$namespacePath] = $content;
        }

        ksort($results);

        return $results;
    }
}

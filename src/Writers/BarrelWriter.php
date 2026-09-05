<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\EnsuresDirectoryExists;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use AbeTwoThree\LaravelTsPublish\Writers\Contracts\MergesModularBarrels;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Generate a barrel list of export files for a .d.ts or .ts file that re-exports all generated types and enums.
 */
class BarrelWriter
{
    use EnsuresDirectoryExists;
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
            $this->ensureDirectoryExists($outputPath);
            $this->putIfChanged("$outputPath/$filename.ts", $content);
        }

        return $content;
    }

    /**
     * Write per-namespace barrel files for modular publishing.
     *
     * Groups generators by namespace path and writes an index.ts barrel file
     * for each unique namespace directory.
     *
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $generators
     * @param  string|null  $outputBase  Base output directory for the barrel files. Falls back to the global output_directory when null/empty. This must match the directory the corresponding per-file writer targets so the modular export structure stays intact.
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function writeModular(Collection $generators, ?string $outputBase = null): array
    {
        return $this->writeModularBarrels($generators, $outputBase, false);
    }

    /**
     * Merge generated exports into existing per-namespace barrel files.
     *
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $generators
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function mergeModular(Collection $generators, ?string $outputBase = null): array
    {
        return $this->writeModularBarrels($generators, $outputBase, true);
    }

    /**
     * Determine whether partial runs can use the writer's modular merge behavior.
     */
    public function supportsModularMerging(): bool
    {
        return static::class === self::class || $this instanceof MergesModularBarrels;
    }

    /**
     * Write grouped modular barrels using replace or merge semantics.
     *
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $generators
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    private function writeModularBarrels(Collection $generators, ?string $outputBase, bool $merge): array
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = [];

        foreach ($generators as $generator) {
            $grouped[$generator->namespacePath()][] = $generator->filename();
        }

        $base = is_string($outputBase) && $outputBase !== ''
            ? $outputBase
            : Config::string('ts-publish.output_directory');

        $outputToFiles = Config::boolean('ts-publish.output_to_files');

        /** @var array<string, string> $results */
        $results = [];

        foreach ($grouped as $namespacePath => $filenames) {
            $outputPath = $base.'/'.$namespacePath;
            $barrelPath = "$outputPath/index.ts";
            $exports = collect($filenames)
                ->map(fn (string $file) => "export * from './{$file}';");

            if ($merge && $this->filesystem->exists($barrelPath)) {
                $existingExports = preg_split('/\R/', $this->filesystem->get($barrelPath)) ?: [];
                $exports = $exports->merge($existingExports);
            }

            $content = $exports
                ->filter()
                ->unique()
                ->sort()
                ->implode("\n");

            if ($outputToFiles) {
                $this->ensureDirectoryExists($outputPath);
                $this->putIfChanged($barrelPath, $content);
            }

            $results[$namespacePath] = $content;
        }

        ksort($results);

        return $results;
    }
}

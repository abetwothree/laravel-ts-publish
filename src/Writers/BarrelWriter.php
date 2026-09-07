<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\EnsuresDirectoryExists;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Closure;
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

    /** The one line shape this writer emits, tolerating the quote style a formatter may have applied. */
    private const string EXPORT_LINE = '#^export \* from [\'"]\./(?<file>[^\'"]+)[\'"];?$#';

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
     * Groups generators by namespace path and rewrites an index.ts barrel file for each unique namespace directory.
     *
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $generators
     * @param  string|null  $outputBase  Base output directory for the barrel files. Falls back to the global output_directory when null/empty. This must match the directory the corresponding per-file writer targets so the modular export structure stays intact.
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function writeModular(Collection $generators, ?string $outputBase = null): array
    {
        return $this->writeModularBarrels($generators, $outputBase, null);
    }

    /**
     * Write per-namespace barrels, carrying over the existing exports $keepExisting approves.
     *
     * Only namespaces that received a generator this run are written; an untouched namespace keeps its file.
     *
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $generators
     * @param  Closure(string): bool  $keepExisting  Receives an existing export's filename, e.g. 'user_meta'.
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function writeModularPreserving(Collection $generators, Closure $keepExisting, ?string $outputBase = null): array
    {
        return $this->writeModularBarrels($generators, $outputBase, $keepExisting);
    }

    /**
     * Group generators by namespace and write each barrel, optionally keeping approved existing exports.
     *
     * Protected so a subclass that changes the barrel format can reuse the merge instead of reimplementing it.
     *
     * @template T of CoreGenerator
     *
     * @param  Collection<int, T>  $generators
     * @param  (Closure(string): bool)|null  $keepExisting
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    protected function writeModularBarrels(Collection $generators, ?string $outputBase, ?Closure $keepExisting): array
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

            if ($keepExisting !== null) {
                $filenames = [...$filenames, ...array_filter($this->existingExports($barrelPath), $keepExisting)];
            }

            $content = collect($filenames)
                ->unique()
                ->sort()
                ->map(fn (string $file) => "export * from './{$file}';")
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

    /**
     * Filenames an existing barrel exports. Anything that is not an export line is not carried over.
     *
     * @return list<string>
     */
    protected function existingExports(string $barrelPath): array
    {
        if (! $this->filesystem->exists($barrelPath)) {
            return [];
        }

        $files = [];

        foreach (preg_split('/\R/', $this->filesystem->get($barrelPath)) ?: [] as $line) {
            if (preg_match(self::EXPORT_LINE, trim($line), $matches) === 1) {
                $files[] = $matches['file'];
            }
        }

        return $files;
    }
}

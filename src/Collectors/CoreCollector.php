<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Collectors\Concerns\ValidatesCollectorFiles;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Support\Collection;
use ReflectionClass;

/**
 * @template TFindable
 */
abstract class CoreCollector
{
    use ValidatesCollectorFiles;

    abstract protected function defaultDirectory(): string;

    /** @param ReflectionClass<object> $reflection */
    abstract protected function classFilter(ReflectionClass $reflection): bool;

    /**
     * @return array{
     *  included: list<string>,
     *  excluded: list<string>,
     *  additional_directories: list<string>,
     * }
     */
    abstract protected function finderSettings(): array;

    /** @return Collection<int, class-string<TFindable>> */
    public function collect(): Collection
    {
        $settings = $this->finderSettings();

        $additionalDirs = collect($settings['additional_directories'])
            ->filter(fn (string $dir) => is_dir($dir))
            ->values();

        $additionalClasses = collect($settings['additional_directories'])
            ->filter(fn (string $dir) => class_exists($dir))
            ->values();

        $included = $settings['included'];
        $excluded = $settings['excluded'];

        $includedDirs = collect($included)
            ->filter(fn (string $entry) => is_dir($entry))
            ->values();

        $defaultDir = $this->defaultDirectory();

        /** @var Collection<int, class-string<TFindable>> */
        return $additionalDirs
            ->merge($includedDirs)
            ->when(is_dir($defaultDir), fn (Collection $dirs) => $dirs->add($defaultDir))
            ->unique()
            ->flatMap(ClassMapGenerator::createMap(...))
            ->sortKeys()
            ->flip()
            ->merge($additionalClasses) // @phpstan-ignore argument.type
            ->filter(function (string $class) {
                if (! class_exists($class)) {
                    return false;
                }

                $reflection = new ReflectionClass($class);

                return $this->classFilter($reflection) && $reflection->getAttributes(TsExclude::class) === [];
            })
            ->when($included, function (Collection $collection) use ($included) {
                $resolved = $this->resolveClassesAndDirectories($included);

                return $collection->filter(fn (string $class) => in_array($class, $resolved));
            })
            ->when($excluded, function (Collection $collection) use ($excluded) {
                $resolved = $this->resolveClassesAndDirectories($excluded);

                return $collection->filter(fn (string $class) => ! in_array($class, $resolved));
            })
            ->unique()
            ->values();
    }

    /**
     * Determine whether an explicitly supplied class passes the configured include and exclude filters.
     *
     * @param  class-string  $class
     */
    public function allows(string $class): bool
    {
        $settings = $this->finderSettings();

        return ! $this->matchesEntry($class, $settings['excluded'])
            && ($settings['included'] === [] || $this->matchesEntry($class, $settings['included']));
    }

    /**
     * Determine whether a class matches configured class names or directories.
     *
     * @param  class-string  $class
     * @param  list<string>  $entries
     */
    private function matchesEntry(string $class, array $entries): bool
    {
        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === $class) {
                return true;
            }

            if (is_dir($entry)) {
                $directories[] = $entry;
            }
        }

        foreach ($directories as $directory) {
            if (array_key_exists($class, ClassMapGenerator::createMap($directory))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a mixed list of class names and directory paths into a flat list of class names.
     *
     * @param  list<string>  $entries
     * @return array<int, string>
     */
    private function resolveClassesAndDirectories(array $entries): array
    {
        return collect($entries)
            ->flatMap(function (string $entry) {
                if (is_dir($entry)) {
                    return array_keys(ClassMapGenerator::createMap($entry));
                }

                return [$entry];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<mixed>  $setting
     * @return list<string>
     */
    protected function sanitizeAllowSetting(array $setting): array
    {
        return array_values(array_filter($setting, 'is_string'));
    }
}

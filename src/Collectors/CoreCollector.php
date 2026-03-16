<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Support\Collection;
use ReflectionClass;

/**
 * @template TFindable
 */
abstract class CoreCollector
{
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
     * Resolve a mixed list of fully-qualified class names and directory paths
     * into a flat list of class names.
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
}

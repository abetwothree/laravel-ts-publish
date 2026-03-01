<?php

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Support\Collection;
use ReflectionClass;

/**
 * @template TFindable
 */
abstract class CoreCollector
{
    abstract protected function defaultDirectory(): string;

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

        $additionalDirs = collect($settings['additional_directories'] ?? [])
            ->filter(fn ($dir) => is_string($dir) && is_dir($dir))
            ->values();

        $additionalClasses = collect($settings['additional_directories'] ?? [])
            ->filter(fn ($dir) => is_string($dir) && class_exists($dir))
            ->values();

        $defaultDir = $this->defaultDirectory();

        return $additionalDirs
            ->when(is_dir($defaultDir), fn ($dirs) => $dirs->add($defaultDir))
            ->flatMap(ClassMapGenerator::createMap(...))
            ->flip()
            ->merge($additionalClasses)
            ->filter(fn ($file) => class_exists($file) && $this->classFilter(new ReflectionClass($file)))
            ->when($settings['included'] ?? null, fn ($collection, $included) => $collection->filter(fn ($class) => in_array($class, $included)))
            ->when($settings['excluded'] ?? null, fn ($collection, $excluded) => $collection->filter(fn ($class) => ! in_array($class, $excluded)))
            ->values();
    }
}

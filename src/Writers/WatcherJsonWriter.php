<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use ReflectionEnum;
use UnitEnum;

class WatcherJsonWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    public function write(): string
    {
        if (! config()->boolean('ts-publish.watcher.enabled')) {
            return '';
        }

        $paths = [
            ...$this->collectEnumPaths(),
            ...$this->collectModelPaths(),
            ...$this->collectResourcePaths(),
            ...$this->collectRoutePaths(),
        ];

        sort($paths, SORT_STRING);

        $content = (string) json_encode($paths, JSON_PRETTY_PRINT);

        if (config()->boolean('ts-publish.output_to_files')) {
            $watcherDir = config('ts-publish.watcher.output_directory');
            $outputPath = is_string($watcherDir) ? $watcherDir : config()->string('ts-publish.output_directory');
            $filename = config()->string('ts-publish.watcher.filename');

            $this->filesystem->ensureDirectoryExists($outputPath);
            $this->filesystem->put("$outputPath/$filename", $content);
        }

        return $content;
    }

    /**
     * @return list<string>
     */
    protected function collectEnumPaths(): array
    {
        if (! config()->boolean('ts-publish.enums.enabled')) {
            return [];
        }

        /** @var EnumsCollector $collector */
        $collector = resolve(config()->string('ts-publish.enums.collector_class'));

        return array_values(
            $collector->collect()
                ->map(function (string $fqcn): string {
                    /** @var class-string<UnitEnum|BackedEnum> $fqcn */
                    $reflection = new ReflectionEnum($fqcn);

                    return LaravelTsPublish::resolveRelativePath((string) $reflection->getFileName());
                })
                ->all()
        );
    }

    /**
     * @return list<string>
     */
    protected function collectModelPaths(): array
    {
        if (! config()->boolean('ts-publish.models.enabled')) {
            return [];
        }

        /** @var ModelsCollector $collector */
        $collector = resolve(config()->string('ts-publish.models.collector_class'));

        return array_values(
            $collector->collect()
                ->map(function (string $fqcn): string {
                    /** @var class-string<Model> $fqcn */
                    $reflection = new ReflectionClass($fqcn);

                    return LaravelTsPublish::resolveRelativePath((string) $reflection->getFileName());
                })
                ->all()
        );
    }

    /**
     * @return list<string>
     */
    protected function collectResourcePaths(): array
    {
        if (! config()->boolean('ts-publish.resources.enabled')) {
            return [];
        }

        /** @var ResourcesCollector $collector */
        $collector = resolve(config()->string('ts-publish.resources.collector_class'));

        return array_values(
            $collector->collect()
                ->map(function (string $fqcn): string {
                    $reflection = new ReflectionClass($fqcn);

                    return LaravelTsPublish::resolveRelativePath((string) $reflection->getFileName());
                })
                ->all()
        );
    }

    /**
     * @return list<string>
     */
    protected function collectRoutePaths(): array
    {
        if (! config()->boolean('ts-publish.routes.enabled')) {
            return [];
        }

        /** @var RoutesCollector $collector */
        $collector = resolve(config()->string('ts-publish.routes.collector_class'));

        return array_values(
            $collector->collect()
                ->map(function (string $fqcn): string {
                    $reflection = new ReflectionClass($fqcn);

                    return LaravelTsPublish::resolveRelativePath((string) $reflection->getFileName());
                })
                ->all()
        );
    }
}

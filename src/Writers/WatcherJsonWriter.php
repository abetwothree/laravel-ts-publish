<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastEventsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\FormRequestsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
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
        if (! Config::boolean('ts-publish.watcher.enabled')) {
            return '';
        }

        $paths = [
            ...$this->collectEnumPaths(),
            ...$this->collectModelPaths(),
            ...$this->collectResourcePaths(),
            ...$this->collectRoutePaths(),
            ...$this->collectFormRequestPaths(),
            ...$this->collectBroadcastEventPaths(),
        ];

        sort($paths, SORT_STRING);

        $content = (string) json_encode($paths, JSON_PRETTY_PRINT);

        if (Config::boolean('ts-publish.output_to_files')) {
            $watcherDir = Config::string('ts-publish.watcher.output_directory');
            $outputPath = ! empty($watcherDir) ? $watcherDir : Config::string('ts-publish.output_directory');
            $filename = Config::string('ts-publish.watcher.filename');

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
        if (! Config::boolean('ts-publish.enums.enabled')) {
            return [];
        }

        /** @var EnumsCollector $collector */
        $collector = resolve(Config::string('ts-publish.enums.collector_class', EnumsCollector::class));

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
        if (! Config::boolean('ts-publish.models.enabled')) {
            return [];
        }

        /** @var ModelsCollector $collector */
        $collector = resolve(Config::string('ts-publish.models.collector_class', ModelsCollector::class));

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
        if (! Config::boolean('ts-publish.resources.enabled')) {
            return [];
        }

        /** @var ResourcesCollector $collector */
        $collector = resolve(Config::string('ts-publish.resources.collector_class', ResourcesCollector::class));

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
        if (! Config::boolean('ts-publish.routes.enabled')) {
            return [];
        }

        /** @var RoutesCollector $collector */
        $collector = resolve(Config::string('ts-publish.routes.collector_class', RoutesCollector::class));

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
    protected function collectFormRequestPaths(): array
    {
        if (! Config::boolean('ts-publish.form_requests.enabled')) {
            return [];
        }

        /** @var FormRequestsCollector $collector */
        $collector = resolve(Config::string('ts-publish.form_requests.collector_class', FormRequestsCollector::class));

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
    protected function collectBroadcastEventPaths(): array
    {
        if (! Config::boolean('ts-publish.broadcast_events.enabled')) {
            return [];
        }

        /** @var BroadcastEventsCollector $collector */
        $collector = resolve(Config::string('ts-publish.broadcast_events.collector_class', BroadcastEventsCollector::class));

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

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Support\Collection;
use Override;

/**
 * @extends CoreWriter<RouteTransformer>
 */
class RouteWriter extends CoreWriter
{
    /**
     * @param  RouteTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();

        /** @var view-string $template */
        $template = config()->string('ts-publish.route_template');

        $data = $transformer->data();

        $content = view($template, ['data' => $data])->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeRouteFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeRouteFile(string $filename, string $content, string $namespacePath): void
    {
        $routesOutputPath = config('ts-publish.routes.output_path');
        $outputBase = \is_string($routesOutputPath)
            ? $routesOutputPath
            : config()->string('ts-publish.output_directory').'/routes';

        $outputPath = $outputBase.'/'.$namespacePath;

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }

    /**
     * Write per-namespace barrel files for routes.
     *
     * Route barrels export only the default (controller object) export — not `export *` —
     * to avoid naming conflicts across controllers with the same method names.
     *
     * @param  Collection<int, RouteGenerator>  $generators
     * @return array<string, string> Barrel contents keyed by namespace path
     */
    public function writeRouteBarrels(Collection $generators): array
    {
        /** @var array<string, list<array{filename: string, controllerName: string}>> $grouped */
        $grouped = [];

        foreach ($generators as $generator) {
            $namespacePath = $generator->transformer->namespacePath;
            $grouped[$namespacePath][] = [
                'filename' => $generator->filename(),
                'controllerName' => $generator->transformer->controllerName,
            ];
        }

        /** @var array<string, string> $results */
        $results = [];

        foreach ($grouped as $namespacePath => $entries) {
            $content = collect($entries)
                ->sortBy('filename')
                ->map(fn (array $entry) => "export { default as {$entry['controllerName']} } from './{$entry['filename']}';")
                ->implode("\n");

            if (config()->boolean('ts-publish.output_to_files')) {
                $routesOutputPath = config('ts-publish.routes.output_path');
                $outputBase = \is_string($routesOutputPath)
                    ? $routesOutputPath
                    : config()->string('ts-publish.output_directory').'/routes';

                $outputPath = $outputBase.'/'.$namespacePath;
                $this->filesystem->ensureDirectoryExists($outputPath);
                $this->filesystem->put("$outputPath/index.ts", $content);
            }

            $results[$namespacePath] = $content;
        }

        ksort($results);

        return $results;
    }
}

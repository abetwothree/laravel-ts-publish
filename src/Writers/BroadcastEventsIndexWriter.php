<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

/**
 * Writes the broadcast-events.ts index file from all generated broadcast event interfaces.
 *
 * Produces import statements, a BroadcastEvent union type, a flat BroadcastEvents const,
 * and re-export type aliases. Returns "export {};\n" when no generators are provided.
 */
class BroadcastEventsIndexWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * Render the broadcast-events.ts index file from a collection of generators.
     *
     * @param  Collection<int, BroadcastEventGenerator>  $generators
     */
    public function write(Collection $generators): string
    {
        if ($generators->isEmpty()) {
            $content = "export {};\n";
        } else {
            $content = $this->renderContent($generators);
        }

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeFile($content);
        }

        return $content;
    }

    /**
     * Build the template data and render.
     *
     * @param  Collection<int, BroadcastEventGenerator>  $generators
     */
    protected function renderContent(Collection $generators): string
    {
        $events = $generators->map(function (BroadcastEventGenerator $generator): array {
            $transformer = $generator->transformer;
            $dto = $transformer->data();
            $relativePath = './'.$transformer->namespacePath.'/'.$transformer->filename();

            return [
                'eventName' => $dto->eventName,
                'broadcastName' => $dto->broadcastName,
                'constKey' => $this->quoteKey($dto->eventName),
                'importPath' => $relativePath,
            ];
        });

        $imports = $events->map(
            fn ($event) => "import type { {$event['eventName']} } from '{$event['importPath']}';"
        )->values();

        $eventNames = $events->pluck('eventName')->values()->all();

        /** @var view-string $template */
        $template = config()->string('ts-publish.broadcast_events.index_template');

        return view($template, [
            'isEmpty' => false,
            'imports' => $imports->all(),
            'events' => $events->values()->all(),
            'eventNames' => $eventNames,
        ])->render();
    }

    /**
     * Quote an object key if it contains characters that are invalid bare identifiers.
     */
    protected function quoteKey(string $key): string
    {
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key)) {
            return $key;
        }

        return '"'.$key.'"';
    }

    /**
     * Write the rendered content to the configured output path.
     */
    protected function writeFile(string $content): void
    {
        $outputPath = $this->resolveOutputPath();
        $filename = config()->string('ts-publish.broadcast_events.index_filename', 'broadcast-events.ts');

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("{$outputPath}/{$filename}", $content);
    }

    /**
     * Resolve the output directory, falling back to the global output_directory.
     */
    protected function resolveOutputPath(): string
    {
        $outputPath = config('ts-publish.broadcast_events.output_path');

        if (is_string($outputPath)) {
            return $outputPath;
        }

        return config()->string('ts-publish.output_directory');
    }
}

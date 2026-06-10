<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\ResolvesEventNameConflicts;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Writes the broadcast-events.ts index file from all generated broadcast event interfaces.
 *
 * Produces import statements, a BroadcastEvent union type, a flat BroadcastEvents const,
 * and re-export type aliases. Returns "export {};\n" when no generators are provided.
 *
 * @phpstan-type RawIndexEvent = array{eventName: string, broadcastName: string, constKey: string, importPath: string, namespacePath: string}
 * @phpstan-type IndexEvent = array{eventName: string, broadcastName: string, constKey: string, importPath: string, namespacePath: string, importedAs: string, exportedName: string}
 */
class BroadcastEventsIndexWriter
{
    use ResolvesEventNameConflicts;

    /** @var view-string */
    protected string $template;

    public function __construct(
        protected Filesystem $filesystem,
    ) {
        /** @var view-string $template */
        $template = Config::string('ts-publish.broadcast_events.index_template');
        $this->template = $template;
    }

    /**
     * Render the broadcast-events.ts index file from a collection of generators.
     *
     * @param  Collection<int, BroadcastEventGenerator>  $generators
     */
    public function write(Collection $generators): string
    {
        if ($generators->isEmpty()) {
            $content = view($this->template, [
                'isEmpty' => true,
                'imports' => [],
                'events' => [],
                'eventNames' => [],
            ])->render();
        } else {
            $content = $this->renderContent($generators);
        }

        if (Config::boolean('ts-publish.output_to_files')) {
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
        $rawEvents = $generators->map(function (BroadcastEventGenerator $generator): array {
            $transformer = $generator->transformer;
            $dto = $transformer->data();
            $relativePath = './'.$transformer->namespacePath.'/'.$transformer->filename();

            return [
                'eventName' => $dto->eventName,
                'broadcastName' => $dto->broadcastName,
                'constKey' => $this->quoteKey($dto->eventName),
                'importPath' => $relativePath,
                'namespacePath' => $transformer->namespacePath,
            ];
        })->sortBy('eventName')->values();

        /** @var Collection<int, IndexEvent> $events */
        $events = $this->resolveEventNameConflicts($rawEvents); // @phpstan-ignore argument.type

        $imports = $events->map(
            fn ($event) => "import type { {$event['importedAs']} } from '{$event['importPath']}';"
        )->values();

        $eventNames = $events->pluck('exportedName')->values()->all();

        return view($this->template, [
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
     * Inject the updated constKey when a conflict alias is computed.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function extraConflictFields(array $event, string $alias): array
    {
        return ['constKey' => $this->quoteKey($alias)];
    }

    /**
     * Write the rendered content to the configured output path.
     */
    protected function writeFile(string $content): void
    {
        $outputPath = $this->resolveOutputPath();
        $filename = Config::string('ts-publish.broadcast_events.index_filename', 'broadcast-events.ts');

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("{$outputPath}/{$filename}", $content);
    }

    /**
     * Resolve the output directory, falling back to the global output_directory.
     */
    protected function resolveOutputPath(): string
    {
        $outputPath = Config::string('ts-publish.broadcast_events.output_directory');

        if (! empty($outputPath)) {
            return $outputPath;
        }

        return Config::string('ts-publish.output_directory');
    }
}

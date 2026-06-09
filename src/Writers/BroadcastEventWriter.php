<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use Override;

/**
 * Renders a TypeScript interface for a single broadcast event and writes it to disk.
 *
 * The output file is placed at {output_directory}/{namespacePath}/{ClassName}.ts.
 * Used by BroadcastEventGenerator as part of the modular broadcast events pipeline.
 *
 * @extends CoreWriter<BroadcastEventTransformer>
 */
class BroadcastEventWriter extends CoreWriter
{
    /**
     * Render the TypeScript interface for a single broadcast event and optionally write to disk.
     *
     * @param  BroadcastEventTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();
        $data = $transformer->data();

        /** @var view-string $template */
        $template = config()->string('ts-publish.broadcast_events.template');

        $content = view($template, [
            'filename' => $filename,
            'data' => $data,
        ])->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeEventFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    /**
     * Write the rendered event interface to the appropriate namespace directory.
     */
    protected function writeEventFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = config('ts-publish.broadcast_events.output_path');
        if (! is_string($outputBase)) {
            $outputBase = config()->string('ts-publish.output_directory');
        }

        $outputPath = $outputBase.'/'.$namespacePath;

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("{$outputPath}/{$filename}.ts", $content);
    }
}

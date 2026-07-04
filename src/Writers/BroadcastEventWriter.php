<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Support\Facades\Config;
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
    use WritesGeneratedFiles;

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
        $template = Config::string('ts-publish.broadcast_events.template');

        $content = view($template, [
            'filename' => $filename,
            'data' => $data,
        ])->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $this->writeEventFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    /**
     * Write the rendered event interface to the appropriate namespace directory.
     */
    protected function writeEventFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = Config::string('ts-publish.broadcast_events.output_directory');
        if (empty($outputBase)) {
            $outputBase = Config::string('ts-publish.output_directory');
        }

        $outputPath = $outputBase.'/'.$namespacePath;

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->putIfChanged("{$outputPath}/{$filename}.ts", $content);
    }
}

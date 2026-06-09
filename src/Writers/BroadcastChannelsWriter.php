<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastChannelsTransformer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class BroadcastChannelsWriter
{
    public function __construct(
        protected Filesystem $filesystem,
        protected BroadcastChannelsTransformer $transformer,
    ) {}

    /**
     * Transform the channel name collection into TypeScript and optionally write to disk.
     *
     * @param  Collection<int, string>  $channels
     */
    public function write(Collection $channels): string
    {
        $dto = $this->transformer->transform($channels);

        /** @var view-string $template */
        $template = Config::string('ts-publish.broadcast_channels.template');
        $content = view($template, ['data' => $dto])->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $this->writeFile($content);
        }

        return $content;
    }

    /**
     * Write the rendered content to the configured output path.
     */
    protected function writeFile(string $content): void
    {
        $outputPath = $this->resolveOutputPath();
        $filename = Config::string('ts-publish.broadcast_channels.filename', 'broadcast-channels.ts');

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("{$outputPath}/{$filename}", $content);
    }

    /**
     * Resolve the output directory, falling back to the global output_directory.
     */
    protected function resolveOutputPath(): string
    {
        $channelsOutputPath = Config::string('ts-publish.broadcast_channels.output_path');

        if (! empty($channelsOutputPath)) {
            return $channelsOutputPath;
        }

        return Config::string('ts-publish.output_directory');
    }
}

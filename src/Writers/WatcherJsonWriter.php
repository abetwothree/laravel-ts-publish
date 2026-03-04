<?php

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Runner;
use Illuminate\Filesystem\Filesystem;

class WatcherJsonWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    public function write(Runner $runner): string
    {
        if (! config()->boolean('ts-publish.output_collected_files_json')) {
            return '';
        }

        $content = json_encode([
            ...$runner->enumGenerators->map(fn (EnumGenerator $g) => $g->transformer->filePath),
            ...$runner->modelGenerators->map(fn (ModelGenerator $g) => $g->transformer->filePath),
        ], JSON_PRETTY_PRINT);

        if (config()->boolean('ts-publish.output_to_files')) {
            $outputPath = config()->string('ts-publish.collected_files_json_output_directory');
            $filename = config()->string('ts-publish.collected_files_json_filename');

            $this->filesystem->ensureDirectoryExists($outputPath);
            $this->filesystem->put("$outputPath/$filename", $content);
        }

        return $content;
    }
}

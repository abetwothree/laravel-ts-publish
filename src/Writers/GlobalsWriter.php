<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Runner;
use Illuminate\Filesystem\Filesystem;

class GlobalsWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    public function write(Runner $runner): string
    {
        if(!config()->boolean('ts-publish.output_globals_file')){
            return '';
        }

        /** @var view-string $template */
        $template = config()->string('ts-publish.globals_template');

        $content = view(
            $template,
            [
                'enums' => $runner->enumGenerators->map(fn (EnumGenerator $g) => $g->transformer),
                'models' => $runner->modelGenerators->map(fn (ModelGenerator $g) => $g->transformer),
                'modelsNamespace' => config()->string('ts-publish.models_namespace'),
                'enumsNamespace' => config()->string('ts-publish.enums_namespace'),
            ]
        )->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $outputPath = config()->string('ts-publish.global_directory');
            $filename = config()->string('ts-publish.global_filename');

            $this->filesystem->ensureDirectoryExists($outputPath);
            $this->filesystem->put("$outputPath/$filename", $content);
        }

        return $content;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\ClassTypeTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreWriter<ClassTypeTransformer>
 */
class ClassTypeWriter extends CoreWriter
{
    use WritesGeneratedFiles;

    /**
     * Render a composed class's module, and write it into its namespace directory when publishing to files.
     *
     * @param  ClassTypeTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        /** @var view-string $template */
        $template = Config::string('ts-publish.class_types.template', 'laravel-ts-publish::class-type');

        $content = view($template, ['data' => $transformer->data()])->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            // The top-level directory, since every file importing the module computes its relative path from there.
            $outputPath = Config::string('ts-publish.output_directory').'/'.$transformer->namespacePath;

            $this->ensureDirectoryExists($outputPath);
            $this->putIfChanged("$outputPath/{$transformer->filename()}.ts", $content);
        }

        return $content;
    }
}

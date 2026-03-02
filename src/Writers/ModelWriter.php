<?php

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Override;

/**
 * @extends CoreWriter<ModelTransformer>
 */
class ModelWriter extends CoreWriter
{
    /**
     * @param  ModelTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();

        $content = view(
            config()->string('ts-publish.model_template'),
            [
                ...$transformer->data(),
                'filename' => $filename,
            ]
        )->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeModelFile($filename, $content);
        }

        return $content;
    }

    protected function writeModelFile(string $filename, string $content): void
    {
        $outputPath = config()->string('ts-publish.output_directory').'/models';
        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreWriter<ModelMetadataTransformer>
 */
class ModelMetadataWriter extends CoreWriter
{
    use WritesGeneratedFiles;

    /**
     * Render and optionally write model metadata.
     *
     * @param  ModelMetadataTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        /** @var view-string $template */
        $template = Config::string('ts-publish.model_metadata.template');
        $content = view($template, ['data' => $transformer->data()])->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $outputPath = Config::string('ts-publish.output_directory').'/'.$transformer->namespacePath;
            $this->ensureDirectoryExists($outputPath);
            $this->putIfChanged($outputPath.'/'.$transformer->filename().'.ts', $content);
        }

        return $content;
    }
}

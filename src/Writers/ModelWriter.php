<?php

declare(strict_types=1);

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

        /** @var view-string $template */
        $template = config()->string('ts-publish.model_template');

        $data = $transformer->data();

        $content = view(
            $template,
            [
                'filename' => $filename,
                'metadataEnabled' => config()->boolean('ts-publish.enum_metadata_enabled'),
                'usesTolkiPackage' => config()->boolean('ts-publish.enums_use_tolki_package'),
                'data' => $data,
            ]
        )->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeModelFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeModelFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = config()->string('ts-publish.output_directory');
        $outputPath = config()->boolean('ts-publish.modular_publishing')
            ? $outputBase.'/'.$namespacePath
            : $outputBase.'/models';

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

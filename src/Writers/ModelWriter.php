<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Override;

/**
 * @phpstan-import-type ModelData from ModelTransformer
 *
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

        /** @var ModelData $data */
        $data = $transformer->data();

        $content = view(
            $template,
            [
                ...$data,
                'filename' => $filename,
                'useTypeImports' => config()->boolean('ts-publish.use_type_imports'),
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

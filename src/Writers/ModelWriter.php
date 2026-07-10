<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreWriter<ModelTransformer>
 */
class ModelWriter extends CoreWriter
{
    use WritesGeneratedFiles;

    /**
     * @param  ModelTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();

        /** @var view-string $template */
        $template = Config::string('ts-publish.models.template');

        $data = $transformer->data();

        $content = view(
            $template,
            [
                'filename' => $filename,
                'metadataEnabled' => Config::boolean('ts-publish.enums.metadata_enabled'),
                'usesTolkiPackage' => Config::boolean('ts-publish.enums.use_tolki_package'),
                'data' => $data,
            ]
        )->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $this->writeModelFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeModelFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = Config::string('ts-publish.output_directory');
        $outputPath = $outputBase.'/'.$namespacePath;

        $this->ensureDirectoryExists($outputPath);
        $this->putIfChanged("$outputPath/$filename.ts", $content);
    }
}

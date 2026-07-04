<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreWriter<FormRequestTransformer>
 */
class FormRequestWriter extends CoreWriter
{
    use WritesGeneratedFiles;

    /**
     * @param  FormRequestTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();
        $data = $transformer->data();

        /** @var view-string $template */
        $template = Config::string('ts-publish.form_requests.template');

        $content = view(
            $template,
            [
                'filename' => $filename,
                'data' => $data,
            ]
        )->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $this->writeFormRequestFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeFormRequestFile(string $filename, string $content, string $namespacePath): void
    {
        $outputPath = $this->resolveOutputPath($namespacePath);

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->putIfChanged("$outputPath/$filename.ts", $content);
    }

    protected function resolveOutputPath(string $namespacePath): string
    {
        $outputPath = Config::string('ts-publish.form_requests.output_directory');
        $outputBase = ! empty($outputPath)
            ? $outputPath
            : Config::string('ts-publish.output_directory');

        return $outputBase.'/'.$namespacePath;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Override;

/**
 * @extends CoreWriter<EnumTransformer>
 */
class EnumWriter extends CoreWriter
{
    /**
     * @param  EnumTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();

        $data = $transformer->data();

        /** @var view-string $template */
        $template = config()->string('ts-publish.enums.template');

        $content = view(
            $template,
            [
                'filename' => $filename,
                'metadataEnabled' => config()->boolean('ts-publish.enums.metadata_enabled'),
                'usesTolkiPackage' => config()->boolean('ts-publish.enums.use_tolki_package'),
                'data' => $data,
            ]
        )->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeEnumFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeEnumFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = config()->string('ts-publish.output_directory');
        $outputPath = $outputBase.'/'.$namespacePath;

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

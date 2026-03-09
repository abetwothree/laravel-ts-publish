<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Override;

/**
 * @phpstan-import-type EnumData from EnumTransformer
 *
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

        /** @var EnumData $data */
        $data = $transformer->data();

        /** @var view-string $template */
        $template = config()->string('ts-publish.enum_template');

        $content = view(
            $template,
            [
                ...$data,
                'filename' => $filename,
                'metadataEnabled' => config()->boolean('ts-publish.enum_metadata_enabled'),
                'usesTolkiPackage' => config()->boolean('ts-publish.enums_use_tolki_package'),
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
        $outputPath = config()->boolean('ts-publish.modular_publishing')
            ? $outputBase.'/'.$namespacePath
            : $outputBase.'/enums';

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

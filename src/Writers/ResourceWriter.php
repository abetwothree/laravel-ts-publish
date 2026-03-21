<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Override;

/**
 * @extends CoreWriter<ResourceTransformer>
 */
class ResourceWriter extends CoreWriter
{
    /**
     * @param  ResourceTransformer  $transformer
     */
    #[Override]
    public function write(CoreTransformer $transformer): string
    {
        $filename = $transformer->filename();

        /** @var view-string $template */
        $template = config()->string('ts-publish.resource_template');

        $data = $transformer->data();

        $content = view(
            $template,
            [
                'filename' => $filename,
                'usesTolkiPackage' => config()->boolean('ts-publish.enums_use_tolki_package'),
                'data' => $data,
            ]
        )->render();

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeResourceFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeResourceFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = config()->string('ts-publish.output_directory');
        $outputPath = config()->boolean('ts-publish.modular_publishing')
            ? $outputBase.'/'.$namespacePath
            : $outputBase.'/'.config()->string('ts-publish.resources_namespace', 'resources');

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

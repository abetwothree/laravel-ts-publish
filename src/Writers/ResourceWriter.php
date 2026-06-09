<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Support\Facades\Config;
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
        $template = Config::string('ts-publish.resources.template');

        $data = $transformer->data();

        $content = view(
            $template,
            [
                'filename' => $filename,
                'usesTolkiPackage' => Config::boolean('ts-publish.enums.use_tolki_package'),
                'data' => $data,
            ]
        )->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $this->writeResourceFile($filename, $content, $transformer->namespacePath);
        }

        return $content;
    }

    protected function writeResourceFile(string $filename, string $content, string $namespacePath): void
    {
        $outputBase = Config::string('ts-publish.output_directory');
        $outputPath = $outputBase.'/'.$namespacePath;

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

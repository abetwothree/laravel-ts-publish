<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreWriter<ResourceTransformer>
 */
class ResourceWriter extends CoreWriter
{
    use WritesGeneratedFiles;

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

        // The template imports `AsEnum` under this flag, and a cast can write `typeof Status`, needing only the const.
        $usesAsEnum = Config::boolean('ts-publish.enums.use_tolki_package') && TsTypeString::typeNameOccursIn(
            'AsEnum',
            ...array_column($data->properties, 'type'),
            ...$data->tsExtends,
            ...array_filter([$data->typeAlias]),
        );

        $content = view(
            $template,
            [
                'filename' => $filename,
                'usesTolkiPackage' => $usesAsEnum,
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

        $this->ensureDirectoryExists($outputPath);
        $this->putIfChanged("$outputPath/$filename.ts", $content);
    }
}

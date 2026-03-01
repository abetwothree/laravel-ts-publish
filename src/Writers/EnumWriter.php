<?php

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

        $caseKinds = [];
        if ($data['backed']) {
            $caseKinds = array_map(fn ($case) => "'".$case['name']."'", $data['cases']);
        }

        $caseTypes = array_map(function ($case) {
            if (is_string($case['value'])) {
                return "'{$case['value']}'";
            }

            return $case['value'];
        }, $data['cases']);

        $content = view(
            'laravel-ts-publish::enum',
            [
                ...$data,
                'filename' => $filename,
                'caseTypes' => $caseTypes,
                'caseKinds' => $caseKinds,
            ]
        )->render();

        if (config()->boolean('ts-publish.output-to-files')) {
            $this->writeEnumFile($filename, $content);
        }

        return $content;
    }

    protected function writeEnumFile(string $filename, string $content): void
    {
        $outputPath = config()->string('ts-publish.output-directory').'/enums';
        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("$outputPath/$filename.ts", $content);
    }
}

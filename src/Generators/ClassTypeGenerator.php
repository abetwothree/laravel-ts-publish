<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Generators\Concerns\RehydratesFromCache;
use AbeTwoThree\LaravelTsPublish\Transformers\ClassTypeTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ClassTypeWriter;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreGenerator<object>
 */
class ClassTypeGenerator extends CoreGenerator
{
    use RehydratesFromCache;

    public protected(set) ClassTypeTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var ClassTypeTransformer $transformer */
        $transformer = resolve(
            Config::string('ts-publish.class_types.transformer_class', ClassTypeTransformer::class),
            ['findable' => $this->findable],
        );
        $this->transformer = $transformer;

        /** @var ClassTypeWriter $writer */
        $writer = resolve(Config::string('ts-publish.class_types.writer_class', ClassTypeWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->cachedFilename ?? $this->transformer->filename();
    }
}

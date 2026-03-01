<?php

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use BackedEnum;
use Override;
use UnitEnum;

/**
 * @extends CoreGenerator<UnitEnum|BackedEnum>
 */
class EnumGenerator extends CoreGenerator
{
    protected EnumTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        $this->transformer = resolve(EnumTransformer::class, [
            'findable' => $this->findable,
        ]);

        $writer = resolve(EnumWriter::class);

        return $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

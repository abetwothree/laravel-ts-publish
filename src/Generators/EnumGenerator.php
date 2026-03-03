<?php

declare(strict_types=1);

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
    public protected(set) EnumTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var EnumTransformer $transformer */
        $transformer = resolve(config()->string('ts-publish.enum_transformer_class'), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var EnumWriter $writer */
        $writer = resolve(config()->string('ts-publish.enum_writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

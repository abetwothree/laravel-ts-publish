<?php

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @extends CoreGenerator<Model>
 */
class ModelGenerator extends CoreGenerator
{
    protected ModelTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        $this->transformer = resolve(ModelTransformer::class, [
            'findable' => $this->findable,
        ]);

        $writer = resolve(ModelWriter::class);

        return $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

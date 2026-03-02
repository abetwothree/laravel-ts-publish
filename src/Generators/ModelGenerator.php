<?php

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @extends CoreGenerator<Model>
 */
class ModelGenerator extends CoreGenerator
{
    public protected(set) ModelTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        $this->transformer = resolve(config()->string('ts-publish.model_transformer_class'), [
            'findable' => $this->findable,
        ]);

        $writer = resolve(config()->string('ts-publish.model_writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

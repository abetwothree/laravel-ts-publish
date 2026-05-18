<?php

declare(strict_types=1);

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
    public protected(set) ModelTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var ModelTransformer $transformer */
        $transformer = resolve(config()->string('ts-publish.models.transformer_class'), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var ModelWriter $writer */
        $writer = resolve(config()->string('ts-publish.models.writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

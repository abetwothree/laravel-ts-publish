<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @extends CoreGenerator<JsonResource>
 */
class ResourceGenerator extends CoreGenerator
{
    public protected(set) ResourceTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var ResourceTransformer $transformer */
        $transformer = resolve(config()->string('ts-publish.resource_transformer_class'), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var ResourceWriter $writer */
        $writer = resolve(config()->string('ts-publish.resource_writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

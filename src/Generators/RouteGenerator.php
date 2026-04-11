<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
use Override;

/**
 * @extends CoreGenerator<object>
 */
class RouteGenerator extends CoreGenerator
{
    public protected(set) RouteTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var RouteTransformer $transformer */
        $transformer = resolve(config()->string('ts-publish.route_transformer_class'), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var RouteWriter $writer */
        $writer = resolve(config()->string('ts-publish.route_writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

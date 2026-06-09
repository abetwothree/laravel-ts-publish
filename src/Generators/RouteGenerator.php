<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
use Illuminate\Support\Facades\Config;
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
        $transformer = resolve(Config::string('ts-publish.routes.transformer_class', RouteTransformer::class), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var RouteWriter $writer */
        $writer = resolve(Config::string('ts-publish.routes.writer_class', RouteWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

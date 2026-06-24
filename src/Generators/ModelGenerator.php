<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Generators\Concerns\RehydratesFromCache;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreGenerator<Model>
 */
class ModelGenerator extends CoreGenerator
{
    use RehydratesFromCache;

    public protected(set) ModelTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var ModelTransformer $transformer */
        $transformer = resolve(Config::string('ts-publish.models.transformer_class', ModelTransformer::class), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var ModelWriter $writer */
        $writer = resolve(Config::string('ts-publish.models.writer_class', ModelWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->cachedFilename ?? $this->transformer->filename();
    }
}

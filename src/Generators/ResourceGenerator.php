<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Generators\Concerns\RehydratesFromCache;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreGenerator<JsonResource>
 */
class ResourceGenerator extends CoreGenerator
{
    use RehydratesFromCache;

    public protected(set) ResourceTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var ResourceTransformer $transformer */
        $transformer = resolve(Config::string('ts-publish.resources.transformer_class', ResourceTransformer::class), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var ResourceWriter $writer */
        $writer = resolve(Config::string('ts-publish.resources.writer_class', ResourceWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->cachedFilename ?? $this->transformer->filename();
    }
}

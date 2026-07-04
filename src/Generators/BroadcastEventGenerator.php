<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Generators\Concerns\RehydratesFromCache;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventWriter;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreGenerator<ShouldBroadcast>
 */
class BroadcastEventGenerator extends CoreGenerator
{
    use RehydratesFromCache;

    public protected(set) BroadcastEventTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var BroadcastEventTransformer $transformer */
        $transformer = resolve(Config::string('ts-publish.broadcast_events.transformer_class', BroadcastEventTransformer::class), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var BroadcastEventWriter $writer */
        $writer = resolve(Config::string('ts-publish.broadcast_events.writer_class', BroadcastEventWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->cachedFilename ?? $this->transformer->filename();
    }
}

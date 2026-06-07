<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventWriter;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Override;

/**
 * @extends CoreGenerator<ShouldBroadcast>
 */
class BroadcastEventGenerator extends CoreGenerator
{
    public protected(set) BroadcastEventTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var BroadcastEventTransformer $transformer */
        $transformer = resolve(config()->string('ts-publish.broadcast_events.transformer_class'), [
            'findable' => $this->findable,
        ]);
        $this->transformer = $transformer;

        /** @var BroadcastEventWriter $writer */
        $writer = resolve(config()->string('ts-publish.broadcast_events.writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}

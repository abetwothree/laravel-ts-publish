<?php

namespace AbeTwoThree\LaravelTsPublish\Transformers;

/**
 * @template TTransformable
 */
abstract class CoreTransformer
{
    /**
     * @param  class-string<TTransformable>  $findable
     */
    public function __construct(
        protected string $findable,
    ) {
        $this->transform();
    }

    abstract public function transform(): self;

    abstract public function filename(): string;

    abstract public function data(): array;
}

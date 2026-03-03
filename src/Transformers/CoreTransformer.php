<?php

declare(strict_types=1);

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

    /** @return static */
    abstract public function transform(): self;

    abstract public function filename(): string;

    /** @return array<string, mixed> */
    abstract public function data(): array;
}

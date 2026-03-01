<?php

namespace AbeTwoThree\LaravelTsPublish\Generators;

/**
 * @template TGeneratable
 */
abstract class CoreGenerator
{
    /**
     * @param  class-string<TGeneratable>  $findable
     */
    public function __construct(
        protected string $findable,
    ) {
        $this->generate();
    }

    abstract public function generate(): string;

    abstract public function filename(): string;
}

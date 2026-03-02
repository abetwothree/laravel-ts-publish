<?php

namespace AbeTwoThree\LaravelTsPublish\Generators;

/**
 * @template TGeneratable
 */
abstract class CoreGenerator
{
    public protected(set) string $content;

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

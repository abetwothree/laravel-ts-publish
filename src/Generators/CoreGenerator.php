<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

/**
 * @template TGeneratable
 */
abstract class CoreGenerator
{
    public protected(set) string $content;

    protected ?string $cachedFilename = null;

    /**
     * @param  class-string<TGeneratable>  $findable
     */
    public function __construct(
        public protected(set) string $findable,
    ) {
        $this->generate();
    }

    abstract public function generate(): string;

    abstract public function filename(): string;
}

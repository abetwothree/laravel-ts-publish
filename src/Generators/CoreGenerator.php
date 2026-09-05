<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use LogicException;

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

    /**
     * Get the namespace path containing the generated file.
     */
    public function namespacePath(): string
    {
        if (! isset($this->transformer) || ! $this->transformer instanceof CoreTransformer) {
            throw new LogicException('The generator must have a core transformer to provide a namespace path.');
        }

        return $this->transformer->namespacePath;
    }
}

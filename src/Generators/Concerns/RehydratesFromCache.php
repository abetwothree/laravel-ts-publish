<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators\Concerns;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use ReflectionClass;

trait RehydratesFromCache
{
    /**
     * Build a generator from a cached transformer + filename WITHOUT running
     * generate() — no transform, no analysis, no file write. The per-class
     * output file is already on disk (existence is verified by the manifest).
     *
     * @param  CoreTransformer<covariant mixed>  $transformer
     */
    public static function fromCache(string $findable, CoreTransformer $transformer, string $filename): static
    {
        /** @var static $generator */
        $generator = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        $generator->hydrate($findable, $transformer, $filename);

        return $generator;
    }

    /**
     * Populate a constructor-less generator instance with cached state.
     *
     * @param  CoreTransformer<covariant mixed>  $transformer
     */
    protected function hydrate(string $findable, CoreTransformer $transformer, string $filename): void
    {
        $this->findable = $findable; // @phpstan-ignore assign.propertyType
        $this->transformer = $transformer; // @phpstan-ignore assign.propertyType
        $this->content = '';
        $this->cachedFilename = $filename;
    }
}

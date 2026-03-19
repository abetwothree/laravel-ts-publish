<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;

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

    abstract public function data(): Datable;

    protected function resolveRelativePath(string $absolutePath): string
    {
        return LaravelTsPublish::resolveRelativePath($absolutePath);
    }
}

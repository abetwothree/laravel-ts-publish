<?php

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use Illuminate\Filesystem\Filesystem;

/**
 * @template TTransformer of CoreTransformer
 */
abstract class CoreWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * @param  TTransformer  $transformer
     */
    abstract public function write(CoreTransformer $transformer): string;
}

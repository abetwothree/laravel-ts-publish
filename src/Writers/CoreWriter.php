<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\EnsuresDirectoryExists;
use Illuminate\Filesystem\Filesystem;

/**
 * @template TTransformer of CoreTransformer
 */
abstract class CoreWriter
{
    use EnsuresDirectoryExists;

    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * @param  TTransformer  $transformer
     */
    abstract public function write(CoreTransformer $transformer): string;
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;

/** A custom transformer_class that names its companions with a suffix of its own. */
final class SuffixedModelMetadataTransformer extends ModelMetadataTransformer
{
    public const string FILENAME_SUFFIX = '.meta';
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelMetadataWriter;

final class CommentedModelMetadataWriter extends ModelMetadataWriter
{
    /**
     * Prefix the rendered metadata with a marker comment.
     *
     * @param  CoreTransformer<covariant mixed>  $transformer
     */
    public function write(CoreTransformer $transformer): string
    {
        return "// custom writer\n".parent::write($transformer);
    }
}

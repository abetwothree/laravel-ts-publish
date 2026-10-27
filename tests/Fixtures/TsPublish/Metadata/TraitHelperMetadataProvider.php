<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\Concerns\ProvidesHelperMetadata;

/** A test-only provider whose trait-supplied provide() reaches the helpers declared here. */
final class TraitHelperMetadataProvider implements ModelMetadataProvider
{
    use ProvidesHelperMetadata;

    /** The helper the trait's `self::` reaches. */
    public static function own(): string
    {
        return 'x';
    }

    /** The helper the trait's `$this->` reaches. */
    public function helper(): string
    {
        return 'x';
    }
}

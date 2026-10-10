<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\Concerns\ProvidesTraitModelMetadata;

final class TraitModelMetadataProvider implements ModelMetadataProvider
{
    use ProvidesTraitModelMetadata;
}

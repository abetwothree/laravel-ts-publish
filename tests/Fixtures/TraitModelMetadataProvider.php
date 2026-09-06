<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;

final class TraitModelMetadataProvider implements ModelMetadataProvider
{
    use ProvidesTraitModelMetadata;
}

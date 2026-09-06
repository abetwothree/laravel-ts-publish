<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;

final class TaggedModelMetadataTransformer extends ModelMetadataTransformer
{
    /**
     * Add a property the configured provider never returned, proving the override ran.
     */
    protected function transformProperties(): static
    {
        parent::transformProperties();

        $this->properties['tagged'] = true;
        $this->propertyTypes['tagged'] = 'boolean';

        return $this;
    }
}

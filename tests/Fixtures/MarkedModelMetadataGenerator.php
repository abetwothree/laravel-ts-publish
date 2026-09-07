<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;

final class MarkedModelMetadataGenerator extends ModelMetadataGenerator
{
    /**
     * Append a marker the default generator never emits, proving the override ran.
     */
    public function generate(): string
    {
        return $this->content = parent::generate()."\n// custom generator\n";
    }
}

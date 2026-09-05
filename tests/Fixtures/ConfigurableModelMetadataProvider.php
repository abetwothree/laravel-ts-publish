<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class ConfigurableModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Create a provider returning the configured test value.
     */
    public function __construct(
        private readonly mixed $value,
    ) {}

    /**
     * Provide the configurable runtime metadata value.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['value' => 'unknown'])]
    public function provide(Model $model): array
    {
        return [
            'value' => $this->value,
        ];
    }
}

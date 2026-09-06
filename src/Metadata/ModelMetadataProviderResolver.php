<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final class ModelMetadataProviderResolver
{
    /**
     * Resolve and validate the configured metadata provider through the container.
     */
    public function resolve(): ModelMetadataProvider
    {
        $providerClass = Config::string(
            'ts-publish.model_metadata.provider_class',
            DefaultModelMetadataProvider::class,
        );
        $provider = resolve($providerClass);

        if (! $provider instanceof ModelMetadataProvider) {
            throw new InvalidArgumentException(
                "Configured model metadata provider [{$providerClass}] must implement ".ModelMetadataProvider::class.'.',
            );
        }

        return $provider;
    }
}

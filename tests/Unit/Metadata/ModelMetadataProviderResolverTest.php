<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Metadata\DefaultModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Metadata\ModelMetadataProviderResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidModelMetadataProvider;

test('resolves the default metadata provider', function () {
    expect(resolve(ModelMetadataProviderResolver::class)->resolve())
        ->toBeInstanceOf(DefaultModelMetadataProvider::class);
});

test('honors container substitutions for the configured metadata provider', function () {
    $provider = new CustomModelMetadataProvider;
    app()->instance(InvalidModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);

    expect(resolve(ModelMetadataProviderResolver::class)->resolve())->toBe($provider);
});

test('rejects a resolved object that does not implement the provider contract', function () {
    config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);

    expect(fn () => resolve(ModelMetadataProviderResolver::class)->resolve())
        ->toThrow(InvalidArgumentException::class, 'must implement');
});

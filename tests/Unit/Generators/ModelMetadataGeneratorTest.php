<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ConfigurableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use Workbench\App\Models\User;
use Workbench\App\Providers\AstInferredModelMetadataProvider;

test('generates model metadata content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelMetadataGenerator::class, ['findable' => User::class]);

    expect($generator->content)
        ->toContain('export const UserModelMetadata')
        ->toContain("morphClass: 'Workbench\\\\App\\\\Models\\\\User'");
});

test('exposes its dedicated transformer', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelMetadataGenerator::class, ['findable' => User::class]);

    expect($generator->transformer)->toBeInstanceOf(ModelMetadataTransformer::class)
        ->and($generator->filename())->toBe('user_meta')
        ->and($generator->findable)->toBe(User::class);
});

test('renders metadata types inferred from a provider with a generic array declaration', function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.model_metadata.provider_class', AstInferredModelMetadataProvider::class);

    $generator = resolve(ModelMetadataGenerator::class, ['findable' => User::class]);

    expect($generator->content)
        ->toContain("import type { RoleType } from '../enums';")
        ->toContain('morphClass: string;')
        ->toContain('enabled: boolean;')
        ->toContain('limits: { minimum: number; maximum: null };')
        ->toContain('role: RoleType;');
});

test('cache signature is stable for one payload and changes with it', function () {
    $first = ModelMetadataGenerator::cacheSignature(User::class);

    expect(ModelMetadataGenerator::cacheSignature(User::class))->toBe($first);

    app()->instance(ConfigurableModelMetadataProvider::class, new ConfigurableModelMetadataProvider('changed'));
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(ModelMetadataGenerator::cacheSignature(User::class))->not->toBe($first);
});

test('cache signature never repeats for a payload that cannot be serialized', function () {
    app()->instance(ConfigurableModelMetadataProvider::class, new ConfigurableModelMetadataProvider(fn () => null));
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(ModelMetadataGenerator::cacheSignature(User::class))
        ->not->toBe(ModelMetadataGenerator::cacheSignature(User::class));
});

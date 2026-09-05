<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Metadata\DefaultModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstUnimportableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\BoundModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InheritedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MismatchedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\OptionalModelMetadataProvider;

/**
 * @param  class-string<ModelMetadataProvider>  $providerClass
 * @param  list<string>  $payloadKeys
 */
function analyzeMetadataTypesFor(string $providerClass, array $payloadKeys): ModelMetadataAnalysis
{
    return resolve(ModelMetadataAnalyzer::class)->analyze($providerClass, $payloadKeys);
}

test('binds the Model parameter so its Laravel-typed method calls infer', function () {
    $analysis = analyzeMetadataTypesFor(BoundModelMetadataProvider::class, ['table', 'keyName', 'routeKeyName', 'morphClass']);

    expect($analysis->types)->toBe([
        'table' => 'string',
        'keyName' => 'string',
        'routeKeyName' => 'string',
        'morphClass' => 'string',
    ])->and($analysis->sources)->toBe([
        'table' => 'inferred',
        'keyName' => 'inferred',
        'routeKeyName' => 'inferred',
        'morphClass' => 'inferred',
    ])->and($analysis->requiredKeys)->toBe([]);
});

test('keeps only inferred keys the concrete payload returned', function () {
    $analysis = analyzeMetadataTypesFor(BoundModelMetadataProvider::class, ['table']);

    expect($analysis->types)->toBe(['table' => 'string']);
});

test('an inherited provide() keeps the parameter binding', function () {
    $analysis = analyzeMetadataTypesFor(InheritedModelMetadataProvider::class, ['enabled', 'table']);

    expect($analysis->types)->toBe(['enabled' => 'boolean', 'table' => 'string']);
});

test('drops unknown inferences and reports payload keys no source typed', function () {
    $analysis = analyzeMetadataTypesFor(MismatchedModelMetadataProvider::class, ['table']);

    expect($analysis->types)->toBe(['other' => 'string'])
        ->and($analysis->sources)->toBe(['other' => 'casts'])
        ->and($analysis->undeclaredKeys(['table']))->toBe(['table']);
});

test('reads required and optional keys from the docblock shape and TsCasts', function () {
    $analysis = analyzeMetadataTypesFor(OptionalModelMetadataProvider::class, ['table']);

    expect($analysis->types)->toBe(['table' => 'string', 'exists' => 'ExistsFlag'])
        ->and($analysis->sources)->toBe(['table' => 'docblock', 'exists' => 'casts'])
        ->and($analysis->requiredKeys)->toBe(['table' => true])
        ->and($analysis->missingKeys(['table']))->toBe([])
        ->and($analysis->importPaths)->toBe(['exists' => '@/types/exists-flag']);
});

test('labels TsCasts keys so only import-free keys face the token check', function () {
    $analysis = analyzeMetadataTypesFor(CustomModelMetadataProvider::class, ['table', 'details']);

    expect($analysis->importFreeKeys(['table', 'details']))->toBe(['table']);
});

test('renders a body-inferred enum as its alias and leaves it import-free', function () {
    // The engine spells the enum as RoleType (toTsType); with no import channel the transformer rejects it.
    $analysis = analyzeMetadataTypesFor(AstUnimportableModelMetadataProvider::class, ['role']);

    expect($analysis->types)->toBe(['role' => 'RoleType'])
        ->and($analysis->sources)->toBe(['role' => 'inferred']);
});

test('the default provider still infers its cast morph class', function () {
    $analysis = analyzeMetadataTypesFor(DefaultModelMetadataProvider::class, ['morphClass']);

    expect($analysis->types)->toBe(['morphClass' => 'string'])
        ->and($analysis->sources)->toBe(['morphClass' => 'docblock']);
});

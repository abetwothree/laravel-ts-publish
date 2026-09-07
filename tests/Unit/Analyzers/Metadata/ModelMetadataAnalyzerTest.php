<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Metadata\DefaultModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstUnimportableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\BoundModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ClassNamedKeyMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CollidingProvideDecoyProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DocblockOverridesEnumMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumAndScalarMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InheritedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MismatchedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\OptionalModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TraitModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnionEnumMetadataProvider;

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

test('imports a body-inferred enum relative to the companion file', function () {
    $analysis = resolve(ModelMetadataAnalyzer::class)
        ->analyze(AstUnimportableModelMetadataProvider::class, ['role'], 'workbench/app/models');

    expect($analysis->types)->toBe(['role' => 'RoleType'])
        ->and($analysis->sources)->toBe(['role' => 'inferred'])
        ->and($analysis->typeImports)->toBe(['../enums' => ['RoleType']])
        ->and($analysis->importedNames())->toBe(['RoleType']);
});

test('keys absent from the payload drop their inferred imports', function () {
    $analysis = resolve(ModelMetadataAnalyzer::class)
        ->analyze(EnumAndScalarMetadataProvider::class, ['count'], 'workbench/app/models');

    expect($analysis->types)->toBe(['count' => 'number'])
        ->and($analysis->typeImports)->toBe([]);
});

test('a docblock override removes the inferred import it displaced', function () {
    $analysis = resolve(ModelMetadataAnalyzer::class)
        ->analyze(DocblockOverridesEnumMetadataProvider::class, ['role'], 'workbench/app/models');

    expect($analysis->types)->toBe(['role' => 'string'])
        ->and($analysis->sources)->toBe(['role' => 'docblock'])
        ->and($analysis->typeImports)->toBe([]);
});

test('a TsCasts import is not duplicated by the engine-applied attribute', function () {
    // ResourceAstAnalyzer::applyTsCastsFromMethod() already appended this import to customImports.
    $analysis = resolve(ModelMetadataAnalyzer::class)
        ->analyze(CustomModelMetadataProvider::class, ['table', 'details'], 'workbench/app/models');

    expect($analysis->typeImports)->toBe([])
        ->and($analysis->importPaths)->toBe(['details' => '@/types/model-metadata']);
});

test('a displaced key named after a loadable class still drops its import', function () {
    // 'error' is a real class name, so a class_exists() test would spare its stale channel and the live
    // Crm StatusType would then collide with an App StatusType the user cannot alias away.
    $analysis = resolve(ModelMetadataAnalyzer::class)
        ->analyze(ClassNamedKeyMetadataProvider::class, ['error', 'state'], 'workbench/app/models');

    expect($analysis->types)->toBe(['state' => 'StatusType', 'error' => 'string'])
        ->and($analysis->sources)->toBe(['state' => 'inferred', 'error' => 'docblock'])
        ->and($analysis->typeImports)->toBe(['../../crm/enums' => ['StatusType']]);
});

test('a union of direct enums keeps both imports, which the engine keys by FQCN', function () {
    $analysis = resolve(ModelMetadataAnalyzer::class)
        ->analyze(UnionEnumMetadataProvider::class, ['role'], 'workbench/app/models');

    expect($analysis->types)->toBe(['role' => 'RoleType | StatusType'])
        ->and($analysis->typeImports)->toBe(['../enums' => ['RoleType', 'StatusType']]);
});

test('a trait-supplied provide() in its own file infers its body and keeps its declared sources', function () {
    $analysis = analyzeMetadataTypesFor(TraitModelMetadataProvider::class, ['label', 'table', 'flag']);

    expect($analysis->types)->toBe(['table' => 'string', 'label' => 'string', 'flag' => 'boolean'])
        ->and($analysis->sources)->toBe(['table' => 'inferred', 'label' => 'docblock', 'flag' => 'casts'])
        ->and($analysis->undeclaredKeys(['label', 'table', 'flag']))->toBe([]);
});

test('a decoy provide() earlier in the same file does not displace the real body', function () {
    $analysis = analyzeMetadataTypesFor(CollidingProvideDecoyProvider::class, ['label', 'real']);

    expect($analysis->types)->toBe(['label' => 'string', 'real' => 'number'])
        ->and($analysis->sources)->toBe(['label' => 'inferred', 'real' => 'inferred'])
        ->and($analysis->undeclaredKeys(['label', 'real']))->toBe([]);
});

test('the default provider still infers its cast morph class', function () {
    $analysis = analyzeMetadataTypesFor(DefaultModelMetadataProvider::class, ['morphClass']);

    expect($analysis->types)->toBe(['morphClass' => 'string'])
        ->and($analysis->sources)->toBe(['morphClass' => 'docblock']);
});

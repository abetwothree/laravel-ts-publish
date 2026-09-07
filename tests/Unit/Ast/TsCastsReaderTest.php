<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

it('returns all-empty views for an empty attribute list', function () {
    $result = new TsCastsReader()->unpack([]);

    expect($result['overrides'])->toBe([])
        ->and($result['importPaths'])->toBe([])
        ->and($result['importMap'])->toBe([])
        ->and($result['optionalOverrides'])->toBe([]);
});

it('extracts a plain string override with no import or optional entries', function () {
    $attribute = new TsCasts(['status' => 'active | inactive']);

    $result = new TsCastsReader()->unpack([$attribute]);

    expect($result['overrides'])->toBe(['status' => 'active | inactive'])
        ->and($result['importPaths'])->toBe([])
        ->and($result['importMap'])->toBe([])
        ->and($result['optionalOverrides'])->toBe([]);
});

it('does not throw and produces no import entry when the array value omits the import key', function () {
    // This is the InertiaSharedDataAnalyzer drift fix: its old copy read $value['import']
    // unguarded and would emit a warning/throw here. The unified reader must guard with isset().
    $attribute = new TsCasts(['flag' => ['type' => 'boolean']]);

    $result = new TsCastsReader()->unpack([$attribute]);

    expect($result['overrides'])->toBe(['flag' => 'boolean'])
        ->and($result['importPaths'])->toBe([])
        ->and($result['importMap'])->toBe([]);
});

it('produces column-keyed importPaths and path-keyed importMap from the same input', function () {
    // Two columns share one import path: importPaths has two entries (one per column),
    // importMap has one entry (per path) whose value lists both columns' importable types.
    $attribute = new TsCasts([
        'dimensions' => ['type' => 'ProductDimensions', 'import' => '@js/types/product'],
        'weight' => ['type' => 'ProductWeight', 'import' => '@js/types/product'],
    ]);

    $result = new TsCastsReader()->unpack([$attribute]);

    expect($result['importPaths'])->toBe([
        'dimensions' => '@js/types/product',
        'weight' => '@js/types/product',
    ])->and($result['importMap'])->toBe([
        '@js/types/product' => ['ProductDimensions', 'ProductWeight'],
    ]);

    // Genuinely different shapes, not aliases of one another.
    expect(array_keys($result['importPaths']))->not->toBe(array_keys($result['importMap']));
});

it('lets a later attribute win over an earlier one for the same key', function () {
    $earlier = new TsCasts(['status' => 'draft | published']);
    $later = new TsCasts(['status' => 'archived']);

    $result = new TsCastsReader()->unpack([$earlier, $later]);

    expect($result['overrides'])->toBe(['status' => 'archived']);
});

it('flows optional into optionalOverrides only when the key is present', function () {
    $attribute = new TsCasts([
        'deleted_at' => ['type' => 'string | null', 'optional' => true],
        'created_at' => ['type' => 'string'],
    ]);

    $result = new TsCastsReader()->unpack([$attribute]);

    expect($result['optionalOverrides'])->toBe(['deleted_at' => true])
        ->and($result['optionalOverrides'])->not->toHaveKey('created_at');
});

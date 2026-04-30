<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithClassTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithConflictingImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDocblockReturn;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDuplicateImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithImportPaths;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodOverridesClass;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithoutShareMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTsCastsAndDocblock;
use Laravel\Ranger\Collectors\InertiaSharedData as InertiaSharedDataCollector;
use Laravel\Ranger\Components\InertiaSharedData as SharedDataComponent;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Mockery\MockInterface;

/**
 * Create an analyzer with a mocked collector and an optional middleware class override.
 *
 * When $middlewareClass is provided, the discoverMiddlewareClass() method
 * is overridden to return that class without filesystem discovery.
 *
 * @param  class-string|null  $middlewareClass
 * @return array{analyzer: InertiaSharedDataAnalyzer, collector: MockInterface&InertiaSharedDataCollector}
 */
function createAnalyzerWithMockedCollector(?string $middlewareClass = null): array
{
    $collector = Mockery::mock(InertiaSharedDataCollector::class);

    if ($middlewareClass !== null) {
        $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class, [$collector])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $analyzer->shouldReceive('discoverMiddlewareClass')
            ->andReturn($middlewareClass);
    } else {
        $analyzer = new InertiaSharedDataAnalyzer($collector);
    }

    return ['analyzer' => $analyzer, 'collector' => $collector];
}

// ─── analyze() with mocked collector ─────────────────────────────

test('returns null when collector returns empty collection', function () {
    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect());

    $analyzer = new InertiaSharedDataAnalyzer($mock);

    expect($analyzer->analyze())->toBeNull();
});

test('returns shared data result with props type string', function () {
    $data = new ArrayType([
        'appName' => new StringType,
        'userId' => new IntType,
    ]);

    $component = new SharedDataComponent($data, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, userId: number }')
        ->and($result['withAllErrors'])->toBeFalse()
        ->and($result['importStatements'])->toBe([]);
});

test('returns withAllErrors true when component has it enabled', function () {
    $data = new ArrayType([
        'flash' => new StringType,
    ]);

    $component = new SharedDataComponent($data, true);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['withAllErrors'])->toBeTrue();
});

test('returns Record<string, never> for empty shared data', function () {
    $data = new ArrayType([]);
    $component = new SharedDataComponent($data, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('Record<string, never>');
});

test('uses first component when multiple are collected', function () {
    $data1 = new ArrayType(['first' => new StringType]);
    $data2 = new ArrayType(['second' => new IntType]);

    $component1 = new SharedDataComponent($data1, true);
    $component2 = new SharedDataComponent($data2, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component1, $component2]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ first: string }')
        ->and($result['withAllErrors'])->toBeTrue();
});

test('handles nested array shapes in shared data', function () {
    $data = new ArrayType([
        'auth' => new ArrayType([
            'user' => (new StringType)->nullable(),
        ]),
        'flash' => new ArrayType([
            'success' => (new StringType)->nullable(),
        ]),
    ]);

    $component = new SharedDataComponent($data, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toContain('auth:')
        ->and($result['sharedPageProps'])->toContain('flash:');
});

// ─── TsCasts overrides on middleware ─────────────────────────────

test('applies class-level TsCasts overrides to shared data props', function () {
    $data = new ArrayType([
        'appName' => new MixedType,
        'flash' => new MixedType,
        'userId' => new IntType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithClassTsCasts::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, flash: { success: string | null, error: string | null }, userId: number }')
        ->and($result['importStatements'])->toBe([]);
});

test('applies method-level TsCasts overrides to shared data props', function () {
    $data = new ArrayType([
        'appName' => new MixedType,
        'userId' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithMethodTsCasts::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, userId: number }')
        ->and($result['importStatements'])->toBe([]);
});

test('method-level TsCasts overrides class-level for same key', function () {
    $data = new ArrayType([
        'appName' => new MixedType,
        'flash' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithMethodOverridesClass::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    // Method-level #[TsCasts(['flash' => '{ success: string | null, error: string | null }'])]
    // should override class-level #[TsCasts(['flash' => 'FlashData'])]
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, flash: { success: string | null, error: string | null } }')
        ->and($result['importStatements'])->toBe([]);
});

test('TsCasts with import paths generates import statements', function () {
    $data = new ArrayType([
        'auth' => new MixedType,
        'flash' => new MixedType,
        'appName' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithImportPaths::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: AuthData, flash: FlashData, appName: string }')
        ->and($result['importStatements'])->toBe([
            "import type { AuthData } from '@js/types/auth';",
            "import type { FlashData } from '@js/types/flash';",
        ]);
});

test('TsCasts with duplicate same-path imports deduplicates import statements', function () {
    $data = new ArrayType([
        'auth' => new MixedType,
        'flash' => new MixedType,
        'appName' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithDuplicateImports::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: SharedData, flash: SharedData, appName: string }')
        ->and($result['importStatements'])->toBe([
            "import type { SharedData } from '@js/types/shared';",
        ]);
});

test('TsCasts with conflicting type names aliases later imports', function () {
    $data = new ArrayType([
        'auth' => new MixedType,
        'flash' => new MixedType,
        'appName' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithConflictingImports::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: AuthSharedData, flash: FlashSharedData, appName: string }')
        ->and($result['importStatements'])->toBe([
            "import type { SharedData as AuthSharedData } from '@js/types/auth';",
            "import type { SharedData as FlashSharedData } from '@js/types/flash';",
        ]);
});

test('TsCasts adds keys not present in Surveyor-analyzed props', function () {
    $data = new ArrayType([
        'appName' => new StringType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithClassTsCasts::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    // 'flash' key is in TsCasts but not in Surveyor data — should be added
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, flash: { success: string | null, error: string | null } }');
});

test('no overrides applied when middleware has no TsCasts', function () {
    $data = new ArrayType([
        'appName' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: unknown }')
        ->and($result['importStatements'])->toBe([]);
});

// ─── @return docblock fallback ───────────────────────────────────

test('docblock @return array shape provides type overrides when no TsCasts present', function () {
    $data = new ArrayType([
        'auth' => new MixedType,
        'flash' => new MixedType,
        'appName' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithDocblockReturn::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: { user: { id: number, name: string, email: string } | null }, flash: { success: string | null, error: string | null }, appName: string }')
        ->and($result['importStatements'])->toBe([]);
});

test('TsCasts overrides win over docblock for same key', function () {
    $data = new ArrayType([
        'auth' => new MixedType,
        'flash' => new MixedType,
        'appName' => new MixedType,
    ]);

    $component = new SharedDataComponent($data, false);

    // MiddlewareWithTsCastsAndDocblock has TsCasts(['flash' => 'FlashMessages'])
    // and @return array{..., flash: array{success: string|null, error: string|null}, ...}
    // TsCasts should win for 'flash', docblock should fill 'auth' and 'appName'
    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithTsCastsAndDocblock::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: { user: { id: number, name: string, email: string } | null }, flash: FlashMessages, appName: string }')
        ->and($result['importStatements'])->toBe([]);
});

// ─── parseDocblockFromMiddleware edge cases ───────────────────────

test('parseDocblockFromMiddleware returns empty array when middleware has no share method', function () {
    // MiddlewareWithoutShareMethod exists as a class but has no share() method.
    // This covers the `if (! $reflection->hasMethod('share')) { return []; }` branch.
    $data = new ArrayType([
        'appName' => new StringType,
    ]);

    $component = new SharedDataComponent($data, false);

    ['analyzer' => $analyzer, 'collector' => $collector] = createAnalyzerWithMockedCollector(MiddlewareWithoutShareMethod::class);
    $collector->shouldReceive('collect')->andReturn(collect([$component]));

    $result = $analyzer->analyze();

    // No docblock overrides — Surveyor infers the type directly.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string }')
        ->and($result['importStatements'])->toBe([]);
});

// ─── buildTypeStringWithOverrides raw-value branches ─────────────

test('buildTypeStringWithOverrides renders plain PHP array prop values as nested object type', function () {
    // A plain PHP array (not a Type instance) as a prop value exercises the
    // `is_array($value)` branch → SurveyorTypeMapper::objectToTypeString().
    $data = new ArrayType([
        'meta' => ['page' => 1, 'total' => 100],
    ]);

    $component = new SharedDataComponent($data, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ meta: { page: unknown, total: unknown } }');
});

test('buildTypeStringWithOverrides marks non-Type non-array prop values as unknown', function () {
    // A plain scalar (not a Type or array) as a prop value exercises the
    // `else { $tsType = \'unknown\'; }` branch.
    $data = new ArrayType([
        'version' => '1.0.0',
    ]);

    $component = new SharedDataComponent($data, false);

    $mock = Mockery::mock(InertiaSharedDataCollector::class);
    $mock->shouldReceive('collect')->andReturn(collect([$component]));

    $analyzer = new InertiaSharedDataAnalyzer($mock);
    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ version: unknown }');
});

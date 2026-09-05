<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ArrayMergeShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\InheritedShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithAllErrors;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithClassTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithConflictingImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDocblockReturn;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDuplicateImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithImportPaths;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithInertiaWrappers;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodOverridesClass;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalDocblockKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithoutShareMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTsCastsAndDocblock;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithUnsharedOptionalKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\SpreadShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\StarterKitArrayMergeMiddleware;

/**
 * Analyze one fixture middleware without touching the filesystem discovery pass.
 *
 * @phpstan-import-type SharedDataResult from InertiaSharedDataAnalyzer
 *
 * @param  class-string  $middlewareClass
 * @return SharedDataResult|null
 */
function analyzeSharedDataFor(string $middlewareClass): ?array
{
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn($middlewareClass);

    return $analyzer->analyze();
}

// ─── discovery ───────────────────────────────────────────────────

test('returns null when no Inertia middleware is discovered', function () {
    expect((new InertiaSharedDataAnalyzer)->analyze())->toBeNull();
});

test('discovers the Inertia middleware class from app paths', function () {
    $analyzer = new InertiaSharedDataAnalyzer;
    $analyzer->setAppPaths(__DIR__.'/Fixtures/Discovery');

    $result = $analyzer->analyze();

    // Only DiscoverableMiddleware's #[TsCasts] can produce this override — proves the
    // fixture directory's lone Inertia\Middleware subclass was the one discovered.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: DiscoveredAppName }');
});

// ─── the starter-kit shape ───────────────────────────────────────

test('types the Laravel starter-kit share() shape', function () {
    $analyzer = new InertiaSharedDataAnalyzer;
    $analyzer->setAppPaths(__DIR__.'/Fixtures/StarterKit');

    $result = $analyzer->analyze();

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe(
            '{ name: string, auth: { user: User | null }, ziggy: { location: string }, sidebarOpen: boolean }'
        )
        ->and($result['typeImports'])->toBe(['./workbench/app/models' => ['User']])
        ->and($result['withAllErrors'])->toBeFalse();
});

test('array_merge(parent::share(), [...]) produces the same starter-kit shape', function () {
    $result = analyzeSharedDataFor(StarterKitArrayMergeMiddleware::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe(
            '{ name: string, auth: { user: User | null }, ziggy: { location: string }, sidebarOpen: boolean }'
        )
        ->and($result['typeImports'])->toBe(['./workbench/app/models' => ['User']]);
});

test('errors is left to Inertia core rather than inferred from the framework middleware', function () {
    // Inertia\Middleware::share() really does return an `errors` key; it is dropped on purpose so
    // the weaker inferred type cannot displace @inertiajs/core's own Errors & ErrorBag.
    $result = analyzeSharedDataFor(StarterKitArrayMergeMiddleware::class);

    expect($result['sharedPageProps'])->not->toContain('errors');
});

// ─── parent chains ───────────────────────────────────────────────

test('a parent middleware share() contributes its keys, the child overriding by name', function () {
    $result = analyzeSharedDataFor(InheritedShareMiddleware::class);

    // `locale` can only come from the grandparent spread; `theme` proves the child wins on collision
    // while keeping the parent's position, exactly as PHP's own spread merge does.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ locale: string, theme: number, sidebarOpen: boolean }');
});

test('each spread body derives its own Request variable names', function () {
    // spreadUrl proves the spread method's own `Request $req` is seen; decoy proves share()'s
    // `$request` does not leak in and type a same-named parameter that holds a string.
    $result = analyzeSharedDataFor(SpreadShareMiddleware::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ spreadUrl: string, decoy: unknown, top: string }');
});

test('array_merge and the spread form agree on a parent chain', function () {
    expect(analyzeSharedDataFor(ArrayMergeShareMiddleware::class)['sharedPageProps'])
        ->toBe(analyzeSharedDataFor(InheritedShareMiddleware::class)['sharedPageProps']);
});

// ─── Inertia prop wrappers ───────────────────────────────────────

test('Inertia prop wrappers resolve to the wrapped value, the lazy ones optional', function () {
    $result = analyzeSharedDataFor(MiddlewareWithInertiaWrappers::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe(
            '{ notifications?: { count: number }, permissions?: boolean, locale: string, appName: string }'
        );
});

// ─── withAllErrors ───────────────────────────────────────────────

test('returns withAllErrors true when the middleware enables it', function () {
    expect(analyzeSharedDataFor(MiddlewareWithAllErrors::class)['withAllErrors'])->toBeTrue();
});

test('returns withAllErrors false when the middleware leaves it at the default', function () {
    expect(analyzeSharedDataFor(MiddlewareWithDocblockReturn::class)['withAllErrors'])->toBeFalse();
});

// ─── empty shapes ────────────────────────────────────────────────

test('returns Record<string, never> when nothing is shared and nothing is overridden', function () {
    // MiddlewareWithoutShareMethod has no share() at all, covering parseDocblockFromMiddleware()'s
    // early return as well as the empty-props branch.
    $result = analyzeSharedDataFor(MiddlewareWithoutShareMethod::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('Record<string, never>')
        ->and($result['typeImports'])->toBe([]);
});

// ─── TsCasts overrides on middleware ─────────────────────────────

test('applies class-level TsCasts overrides to shared data props', function () {
    $result = analyzeSharedDataFor(MiddlewareWithClassTsCasts::class);

    // appName infers as number from the fixture body, so `string` proves the attribute won.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, userId: number, flash: { success: string | null, error: string | null } }')
        ->and($result['typeImports'])->toBe([]);
});

test('TsCasts adds keys not present in the inferred props', function () {
    // 'flash' is in TsCasts but never shared — it is appended after the inferred keys.
    expect(analyzeSharedDataFor(MiddlewareWithClassTsCasts::class)['sharedPageProps'])
        ->toEndWith('flash: { success: string | null, error: string | null } }');
});

test('applies method-level TsCasts overrides to shared data props', function () {
    $result = analyzeSharedDataFor(MiddlewareWithMethodTsCasts::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, userId: number }')
        ->and($result['typeImports'])->toBe([]);
});

test('method-level TsCasts overrides class-level for same key', function () {
    $result = analyzeSharedDataFor(MiddlewareWithMethodOverridesClass::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: string, flash: { success: string | null, error: string | null } }')
        ->and($result['typeImports'])->toBe([]);
});

test('TsCasts with import paths collects type imports', function () {
    $result = analyzeSharedDataFor(MiddlewareWithImportPaths::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: AuthData, flash: FlashData, appName: string }')
        ->and($result['typeImports'])->toBe([
            '@js/types/auth' => ['AuthData'],
            '@js/types/flash' => ['FlashData'],
        ]);
});

test('TsCasts with duplicate same-path imports deduplicates type imports', function () {
    $result = analyzeSharedDataFor(MiddlewareWithDuplicateImports::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: SharedData, flash: SharedData, appName: string }')
        ->and($result['typeImports'])->toBe([
            '@js/types/shared' => ['SharedData'],
        ]);
});

test('TsCasts with conflicting type names aliases later imports', function () {
    $result = analyzeSharedDataFor(MiddlewareWithConflictingImports::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: AuthSharedData, flash: FlashSharedData, appName: string }')
        ->and($result['typeImports'])->toBe([
            '@js/types/auth' => ['SharedData as AuthSharedData'],
            '@js/types/flash' => ['SharedData as FlashSharedData'],
        ]);
});

// ─── @return docblock fallback ───────────────────────────────────

test('docblock @return array shape provides type overrides when no TsCasts present', function () {
    $result = analyzeSharedDataFor(MiddlewareWithDocblockReturn::class);

    // Every key infers as number from the fixture body, so each rendered type proves the docblock won.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: { user: { id: number; name: string; email: string } | null }, flash: { success: string | null; error: string | null }, appName: string }')
        ->and($result['typeImports'])->toBe([]);
});

test('docblock optional key is emitted once, with its marker', function () {
    // Regression: the parsed key carries the '?', so matching it against the plain inferred 'filters'
    // used to miss — the prop was emitted from both loops, which TypeScript rejects (TS2300).
    $result = analyzeSharedDataFor(MiddlewareWithOptionalDocblockKey::class);

    // appName is also declared optional in the docblock, but its #[TsCasts] entry wins outright —
    // proving normalization did not let the docblock entry survive as a second key.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: AppName, filters?: Record<string, string> }');
});

test('docblock optional key absent from the shared props keeps its marker', function () {
    $result = analyzeSharedDataFor(MiddlewareWithUnsharedOptionalKey::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName: AppName, filters?: Record<string, string> }');
});

test('TsCasts overrides win over docblock for same key', function () {
    // MiddlewareWithTsCastsAndDocblock has TsCasts(['flash' => 'FlashMessages'])
    // and @return array{..., flash: array{success: string|null, error: string|null}, ...}
    // TsCasts should win for 'flash', docblock should fill 'auth' and 'appName'.
    $result = analyzeSharedDataFor(MiddlewareWithTsCastsAndDocblock::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ auth: { user: { id: number; name: string; email: string } | null }, flash: FlashMessages, appName: string }')
        ->and($result['typeImports'])->toBe([]);
});

// ─── import channels ─────────────────────────────────────────────

test('combines inferred and TsCasts type imports', function () {
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn(StarterKitArrayMergeMiddleware::class);
    $analyzer->shouldReceive('parseTsCastsFromMiddleware')->andReturn([
        'overrides' => ['flash' => 'FlashData'],
        'importPaths' => ['flash' => '@js/types/flash'],
    ]);

    $result = $analyzer->analyze();

    expect($result['typeImports'])->toBe([
        './workbench/app/models' => ['User'],
        '@js/types/flash' => ['FlashData'],
    ]);
});

test('an override drops the type import the displaced type kept alive', function () {
    // StarterKitArrayMergeMiddleware infers `auth: { user: User | null }`; overriding the key must
    // take the User import with it, or the augmentation file imports a token it never spells.
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn(StarterKitArrayMergeMiddleware::class);
    $analyzer->shouldReceive('parseDocblockFromMiddleware')->andReturn(['auth' => '{ user: null }']);

    $result = $analyzer->analyze();

    expect($result['sharedPageProps'])->toContain('auth: { user: null }')
        ->and($result['typeImports'])->toBe([]);
});

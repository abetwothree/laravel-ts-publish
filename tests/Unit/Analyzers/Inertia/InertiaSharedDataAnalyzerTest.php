<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Middleware\OverrideUnionShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Middleware\RenamedResourceShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\AliasedShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ArrayMergeShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ClassCastChildMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ClassCastOverInheritedShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ClassCastUnreadChildMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\InheritedShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithAllErrors;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithAmbiguousCastSpelling;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithCallableHelperArguments;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithCallableReturn;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithCarriedCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithCastsOverOptionalProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithClassTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithClosedDottedHeads;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithConflictingImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDocblockOverEmbeddedModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDocblockReturn;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDocumentedSpreadHelper;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDottedKeys;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDottedSignatureHead;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDuplicateImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithEmbeddedCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithEnumResourceErrors;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithEnumUnions;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithFlaggedClassCastUnderShareCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithImportlessCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithImportPaths;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithInertiaWrappers;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithKeyEdges;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithLosingSpellingImport;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMergingWith;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodOverridesClass;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithModelDocblock;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithModelNamedLiterals;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMultiEnumTernary;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithNullableEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithNumericBodyKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithNumericCastKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalDocblockKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalModelDocblock;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalShareCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithoutShareMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOverriddenUnionDocblock;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithResourceCollections;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithResources;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSameNamedEnumNames;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSameNamedEnumsAroundPrintedKeys;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSignatureCastSpelling;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSpellingsAcrossLocations;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTagSignatureCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTsCastsAndDocblock;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithUndefinedLiteralCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithUnsharedOptionalKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithWrapHelpers;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithWrappedAndBareEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithWrappedEnumBesideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\NestedParentShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\OptionalCastChildMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ShareCastChildMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ShareCastInheritingMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\SpreadShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\StarterKitArrayMergeMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\UnreadParentShareMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Middleware\HandleInertiaRequests;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

afterEach(function () {
    JsonResource::flushState();
});

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

// share()'s own docblock is laid over its keys by the analyzer, but a spread helper's is read only by the refiner.
test('a helper spread into share() fills a member its own @return types where its body leaves it unknown', function () {
    expect(analyzeSharedDataFor(MiddlewareWithDocumentedSpreadHelper::class)['sharedPageProps'] ?? null)
        ->toContain('recipients: { name: string; settings: { digest: boolean } | null }[]');
});

// Inertia calls a callable wherever it sits, so a helper holding one, read as a value or spread, holds what it returns.
test('a first-class callable passed to a shared-data helper reads as what it returns', function () {
    expect(analyzeSharedDataFor(MiddlewareWithCallableHelperArguments::class)['sharedPageProps'] ?? null)
        ->toBe('{ held_value: { held: string }, spread_held: string }');
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

    // appName is also declared optional in the docblock: its #[TsCasts] entry wins the type and, saying nothing about
    // optional, keeps the `?` — once, so normalization did not let the docblock entry survive as a second key.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName?: AppName, filters?: Record<string, string> }');
});

// The class cast says nothing about `optional`, so `appName` keeps the docblock's `?`.
test('docblock optional key absent from the shared props keeps its marker', function () {
    $result = analyzeSharedDataFor(MiddlewareWithUnsharedOptionalKey::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ appName?: AppName, filters?: Record<string, string> }');
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

test('a share() docblock member naming a model or an enum imports it', function () {
    $request = bindRequestUser((new User)->forceFill(['name' => 'Ada']));
    Inertia::share(new MiddlewareWithModelDocblock()->share($request));
    $shared = analyzeSharedDataFor(MiddlewareWithModelDocblock::class);
    $sent = inertiaProps(Inertia::render('Shared', []), $request);

    expect($shared['sharedPageProps'] ?? null)->toBe('{ owner: User, status: StatusType }')
        ->and(array_merge(...array_values($shared['typeImports'] ?? [])))->toEqualCanonicalizing(['StatusType', 'User'])
        ->and([$sent->owner->name, $sent->status])->toBe(['Ada', 0]);
});

test('a union member named like another key\'s override import keeps its own class', function () {
    $request = bindRequestUser((new User)->forceFill(['name' => 'Ada']));
    Inertia::share(new MiddlewareWithOverriddenUnionDocblock()->share($request));
    $shared = analyzeSharedDataFor(MiddlewareWithOverriddenUnionDocblock::class);
    $sent = inertiaProps(Inertia::render('Shared', []), $request);

    expect($shared['sharedPageProps'] ?? null)->toBe('{ person: User, either: User | WorkbenchUser }')
        ->and(array_merge(...array_values($shared['typeImports'] ?? [])))->toEqualCanonicalizing(['User', 'User as WorkbenchUser'])
        ->and($shared['typeImports'] ?? null)->toBe([
            './workbench/app/models' => ['User as WorkbenchUser'],
            '@js/types/ui' => ['User'],
        ])
        ->and([$sent->person, $sent->either->name])->toEqual([(object) ['name' => 'Ada'], 'Ada']);
});

test('a share() docblock union of an override and the model spelled alike keeps each, listed or bare', function () {
    Inertia::share(new OverrideUnionShareMiddleware()->share(request()));
    $shared = analyzeSharedDataFor(OverrideUnionShareMiddleware::class);
    $sent = inertiaProps(Inertia::render('Shared', []));

    expect($shared['sharedPageProps'] ?? null)->toBe('{ listed: (User | WorkbenchUser)[], either: User | WorkbenchUser }')
        ->and($shared['typeImports'] ?? null)->toBe([
            './workbench/app/models' => ['User as WorkbenchUser'],
            '@js/types/ui' => ['User'],
        ])
        ->and($sent)->toEqual((object) [
            'listed' => [(object) ['name' => 'Ada'], (object) ['name' => 'Ada']],
            'either' => (object) ['name' => 'Ada'],
        ]);
});

test('a share() docblock list beside a resource published under its own name keeps the model\'s token on the model', function () {
    Inertia::share(new RenamedResourceShareMiddleware()->share(request()));
    $shared = analyzeSharedDataFor(RenamedResourceShareMiddleware::class);
    $sent = inertiaProps(Inertia::render('Shared', []));

    expect($shared['sharedPageProps'] ?? null)
        ->toBe('{ listed: (AddressResource | User | WorkbenchUser)[], control: (User | WorkbenchUser)[] }')
        ->and($shared['typeImports'] ?? null)->toBe([
            './workbench/app/models' => ['User as WorkbenchUser'],
            '@js/types/ui' => ['User'],
        ])
        ->and($sent)->toEqual((object) ['listed' => [(object) ['name' => 'Ada']], 'control' => [(object) ['name' => 'Ada']]]);
});

test('an optional share() docblock key the value never sets imports the model it names', function () {
    Inertia::share(new MiddlewareWithOptionalModelDocblock()->share(request()));
    $shared = analyzeSharedDataFor(MiddlewareWithOptionalModelDocblock::class);
    $sent = inertiaProps(Inertia::render('Shared', []));

    expect($shared['sharedPageProps'] ?? null)->toBe('{ n: number, owner?: User }')
        ->and($shared['typeImports'] ?? null)->toBe(['./workbench/app/models' => ['User']])
        ->and($sent)->toEqual((object) ['n' => 1]);
});

test('a share() docblock member over a value that embeds its model imports the model the docblock names', function () {
    $request = bindRequestUser((new User)->forceFill(['name' => 'Ada']));
    Inertia::share(new MiddlewareWithDocblockOverEmbeddedModel()->share($request));
    $shared = analyzeSharedDataFor(MiddlewareWithDocblockOverEmbeddedModel::class);
    $sent = inertiaProps(Inertia::render('Shared', []), $request);

    expect($shared['sharedPageProps'] ?? null)->toBe('{ team: User[] }')
        ->and($shared['typeImports'] ?? null)->toBe(['./workbench/app/models' => ['User']])
        ->and(array_column($sent->team, 'name'))->toBe(['Ada']);
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
    $analyzer->shouldReceive('parseDocblockFromMiddleware')->andReturn(['auth' => ['type' => '{ user: null }', 'customImports' => []]]);

    $result = $analyzer->analyze();

    expect($result['sharedPageProps'])->toContain('auth: { user: null }')
        ->and($result['typeImports'])->toBe([]);
});

// ─── EnumResource props ──────────────────────────────────────────

test('an EnumResource shared prop is rewritten to AsEnum and imports the enum const', function () {
    $result = analyzeSharedDataFor(MiddlewareWithEnumResource::class);

    // The type import is gone on purpose: no prop spells the bare RoleType/StatusType any more, so
    // keeping it would emit an import the augmentation file never uses.
    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe(
            '{ role: AsEnum<typeof Role>, status: AsEnum<typeof Status>, nested: { role: AsEnum<typeof Role> } }'
        )
        ->and($result['valueImports'])->toBe(['./workbench/app/enums' => ['Role', 'Status']])
        ->and($result['typeImports'])->toBe([]);
});

test('aliases two same-named enums\' Kind names, and imports a third enum\'s Kind', function () {
    $result = analyzeSharedDataFor(MiddlewareWithSameNamedEnumNames::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe(
            '{ app_name: WorkbenchStatusKind, crm_name: CrmStatusKind, priority_name: PriorityKind }'
        )
        ->and(array_merge(...array_values($result['typeImports'])))->toEqualCanonicalizing([
            'PriorityKind', 'StatusKind as CrmStatusKind', 'StatusKind as WorkbenchStatusKind',
        ])
        ->and(sentAsJson(new MiddlewareWithSameNamedEnumNames()->share(request())))
        ->toEqual((object) ['app_name' => 'Draft', 'crm_name' => 'Lead', 'priority_name' => 'Low']);
});

test('imports the model an import-less cast spells over the key whose value embeds it', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn () => (new User)->forceFill(['name' => 'Ada']));
    $result = analyzeSharedDataFor(MiddlewareWithEmbeddedCast::class);

    expect($result['sharedPageProps'] ?? null)->toBe('{ auth: { user: User | null } }')
        ->and($result['typeImports'] ?? null)->toBe(['./workbench/app/models' => ['User']])
        ->and(sentAsJson(new MiddlewareWithEmbeddedCast()->share($request))->auth->user->name)->toBe('Ada');
});

it('imports a class an import-less shared-data cast spells', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn () => (new User)->forceFill(['name' => 'Ada']));
    Inertia::share(new MiddlewareWithImportlessCast()->share($request));
    $shared = analyzeSharedDataFor(MiddlewareWithImportlessCast::class);

    expect($shared['sharedPageProps'] ?? null)->toContain('owner: User | null')
        ->and(array_merge(...array_values($shared['typeImports'] ?? [])))->toContain('User')
        ->and(inertiaProps(Inertia::render('Shared', []), $request)->owner->name)->toBe('Ada');
});

it('leaves a string literal spelling a model\'s name as written, beside the model\'s aliased token', function () {
    $request = bindRequestUser((new User)->forceFill(['name' => 'Ada']));
    Inertia::share(new MiddlewareWithModelNamedLiterals()->share($request));
    $shared = analyzeSharedDataFor(MiddlewareWithModelNamedLiterals::class);
    $sent = inertiaProps(Inertia::render('Shared', []), $request);

    expect($shared['sharedPageProps'] ?? null)
        ->toBe("{ doc: 'User' | WorkbenchUser | null, cast: 'User' | WorkbenchUser, ui: User }")
        ->and($shared['typeImports'] ?? null)->toBe([
            './workbench/app/models' => ['User as WorkbenchUser'],
            '@js/types/ui' => ['User'],
        ])
        ->and([$sent->doc->name, $sent->cast->name, $sent->ui->name])->toBe(['Ada', 'Ada', 'Ada']);
});

it('spells a wrapped enum\'s const after typeof beside the same-named model, where a cast imports that name', function () {
    Inertia::share(new MiddlewareWithWrappedEnumBesideModel()->share(request()));
    $shared = analyzeSharedDataFor(MiddlewareWithWrappedEnumBesideModel::class);
    $sent = inertiaProps(Inertia::render('Shared', []));

    expect($shared['sharedPageProps'] ?? null)
        ->toBe('{ ui: Grade, grade: WorkbenchGrade, badge: AsEnum<typeof WorkbenchGrade2> }')
        ->and($shared['typeImports'] ?? null)->toBe([
            './workbench/app/models' => ['Grade as WorkbenchGrade'],
            '@js/types/ui' => ['Grade'],
        ])
        ->and($shared['valueImports'] ?? null)->toBe(['./workbench/app/enums' => ['Grade as WorkbenchGrade2']])
        ->and([$sent->ui->subject, $sent->grade->subject, $sent->badge->name, $sent->badge->value])
        ->toBe(['Math', 'Math', 'Pass', 'pass']);
});

it('imports a class one shared-data cast displaced for another import-less cast that spells it', function () {
    $request = bindRequestUser((new User)->forceFill(['name' => 'Ada']));
    Inertia::share(new MiddlewareWithCarriedCast()->share($request));
    $shared = analyzeSharedDataFor(MiddlewareWithCarriedCast::class);
    $sent = inertiaProps(Inertia::render('Shared', []), $request);

    expect($shared['sharedPageProps'] ?? null)->toBe('{ meta: Record<string, unknown>, owner: User | null }')
        ->and($shared['typeImports'] ?? null)->toBe(['./workbench/app/models' => ['User']])
        ->and([$sent->meta->author->name, $sent->owner->name])->toBe(['Ada', 'Ada']);
});

// Inertia runs shared data through the props resolver, so a shared resource is sent as a page prop's is.
test('a shared resource value publishes what Inertia sends', function () {
    $result = analyzeSharedDataFor(MiddlewareWithResources::class);

    expect($result['sharedPageProps'] ?? null)->toBe('{ firstShelf: { data: WarehouseResource }, '
        .'shelfList: AnonymousResourceCollection<WarehouseResource>, named: PostCollection, '
        .'statuses: { data: AsEnum<typeof Status>[] }, role: AsEnum<typeof Role>, user: { data: UserResource } | null }')
        ->and($result['typeImports'] ?? null)->toBe([
            './workbench/app/http/resources' => ['PostCollection', 'UserResource', 'WarehouseResource'],
            '@tolki/types' => ['AnonymousResourceCollection'],
        ]);
});

test('a shared resource value publishes bare after JsonResource::withoutWrapping()', function () {
    JsonResource::withoutWrapping();

    expect(analyzeSharedDataFor(MiddlewareWithResources::class)['sharedPageProps'] ?? null)->toBe('{ firstShelf: WarehouseResource, '
        .'shelfList: WarehouseResource[], named: PostCollection, statuses: AsEnum<typeof Status>[], role: AsEnum<typeof Role>, '
        .'user: UserResource | null }');
});

// Inertia resolves shared data as it does page props, so a shared paginator reaches the collection's response too.
test('a shared resource collection publishes the response Inertia sends, with the pagination its paginator sends', function () {
    $result = analyzeSharedDataFor(MiddlewareWithResourceCollections::class);

    expect($result['sharedPageProps'] ?? null)->toBe(
        '{ anonymous: AnonymousResourceCollection<PostResource>, named: PostCollection, '
        .'simple: PostCollection & ResourceSimplePagination, cursor: JsonResourceCursorPaginator<PostResource>, '
        .'guessed: PostCollection & ResourcePagination }'
    )
        ->and($result['typeImports'] ?? null)->toBe([
            './workbench/app/http/resources' => ['PostCollection', 'PostResource'],
            '@tolki/types' => ['AnonymousResourceCollection', 'JsonResourceCursorPaginator', 'ResourcePagination', 'ResourceSimplePagination'],
        ]);
});

test('an enum read both wrapped and bare keeps its type import alongside the value import', function () {
    // The GC runs the opposite way round from the resource case: only the wrapped prop's own token is
    // substituted, so the prop that still reads the enum bare has to keep RoleType importable.
    $result = analyzeSharedDataFor(MiddlewareWithWrappedAndBareEnum::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ role: AsEnum<typeof Role>, bareRole: RoleType }')
        ->and($result['typeImports'])->toBe(['./workbench/app/enums' => ['RoleType']])
        ->and($result['valueImports'])->toBe(['./workbench/app/enums' => ['Role']]);
});

test('a bare enum case shared prop publishes its enum and imports its type', function () {
    $result = analyzeSharedDataFor(HandleInertiaRequests::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toContain(', role: AsEnum<typeof Role>, defaultStatus: StatusType, ')
        ->and($result['typeImports'])->toBe(['./workbench/app/enums' => ['StatusType']])
        ->and($result['valueImports'])->toBe(['./workbench/app/enums' => ['Role']]);
});

test('the EnumResource rewrite is inert when the tolki package is disabled', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);

    $result = analyzeSharedDataFor(MiddlewareWithEnumResource::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe(
            '{ role: RoleType, status: StatusType, nested: { role: RoleType } }'
        )
        ->and($result['typeImports'])->toBe(['./workbench/app/enums' => ['RoleType', 'StatusType']])
        ->and($result['valueImports'])->toBe([]);
});

test('a two-enum ternary imports both consts and neither enum type it no longer spells', function () {
    $result = analyzeSharedDataFor(MiddlewareWithMultiEnumTernary::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ either: AsEnum<typeof Role> | AsEnum<typeof Status> }')
        ->and($result['typeImports'])->toBe([])
        ->and($result['valueImports'])->toBe(['./workbench/app/enums' => ['Role', 'Status']]);
});

test('each arm of a shared enum-resource ternary types as Inertia sends it', function () {
    [$picked, $other] = array_map(function (int $c): stdClass {
        $request = Request::create('/', 'GET', ['c' => $c]);
        Inertia::share(new MiddlewareWithEnumUnions()->share($request));

        return inertiaProps(Inertia::render('Shared', []), $request);
    }, [1, 0]);
    $result = analyzeSharedDataFor(MiddlewareWithEnumUnions::class);

    expect($result['sharedPageProps'] ?? null)
        ->toBe('{ either: AsEnum<typeof Role> | AsEnum<typeof Status>, mixed: AsEnum<typeof Status> | StatusType }')
        ->and($result['typeImports'] ?? null)->toBe(['./workbench/app/enums' => ['StatusType']])
        ->and($result['valueImports'] ?? null)->toBe(['./workbench/app/enums' => ['Role', 'Status']])
        ->and([$picked->either->name, $picked->mixed->name, $other->either->name, $other->mixed])
        ->toBe([Role::Admin->name, Status::Draft->name, Status::Draft->name, Status::Published->value]);
});

test('an enum-resource ternary publishes each bare enum type when the tolki package is disabled', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);

    $result = analyzeSharedDataFor(MiddlewareWithEnumUnions::class);

    expect($result['sharedPageProps'] ?? null)->toBe('{ either: RoleType | StatusType, mixed: StatusType }')
        ->and($result['typeImports'] ?? null)->toBe(['./workbench/app/enums' => ['RoleType', 'StatusType']])
        ->and($result['valueImports'] ?? null)->toBe([]);
});

test('an EnumResource on a framework-owned key is skipped rather than fataling', function () {
    // collectProps() drops `errors` for @inertiajs/core, but the key keeps its enumResources entry —
    // the rewrite has to tolerate a channel with no surviving prop to index.
    $result = analyzeSharedDataFor(MiddlewareWithEnumResourceErrors::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ ok: string }')
        ->and($result['valueImports'])->toBe([]);
});

test('a key the middleware\'s #[TsCasts] adds joins a docblock-filled signature\'s union', function () {
    expect(analyzeSharedDataFor(MiddlewareWithTagSignatureCast::class)['sharedPageProps'])
        ->toBe('{ [key: `${string}_tag`]: string | number | undefined, extra_tag: number }');
});

test('the union keeps its undefined arm beside a cast that names undefined only in a literal', function () {
    expect(analyzeSharedDataFor(MiddlewareWithUndefinedLiteralCast::class)['sharedPageProps'])
        ->toBe('{ [key: `${string}_tag`]: string | number | \'undefined\' | undefined, price_tag?: number, state_tag: \'undefined\' }');
});

test('a #[TsCasts] key with the backslashes a single-quoted PHP string leaves retypes the escaped signature', function () {
    expect(analyzeSharedDataFor(MiddlewareWithSignatureCastSpelling::class)['sharedPageProps'])
        ->toBe('{ [key: `${string}\\\\_cast`]: number }');
});

test('a #[TsCasts] key that spells more than one index signature warns', function () {
    AnalysisWarnings::reset();

    analyzeSharedDataFor(MiddlewareWithAmbiguousCastSpelling::class);

    expect(AnalysisWarnings::all())->toBe([[
        'subject' => MiddlewareWithAmbiguousCastSpelling::class,
        'message' => 'The #[TsCasts] key "[key: `${string}\\\\r`]" spells more than one index signature, so it retypes none; cast each by its exact name.',
    ]]);

    AnalysisWarnings::reset();
});

// ─── #[TsCasts] key edges ────────────────────────────────────────

// PHP stores '42' as an int; the optional-suffix check used to receive it under strict types and throw a TypeError.
// PropsResolver::resolve() array_merge()s shared data with the page's props, which renumbers its int keys from 0.
test('a numeric cast key lands on the key Laravel renumbers it to instead of stopping the run, and warns', function () {
    expect(analyzeSharedDataFor(MiddlewareWithNumericCastKey::class)['sharedPageProps'])
        ->toBe('{ appName: string, "0": boolean }')
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => MiddlewareWithNumericCastKey::class,
            'message' => 'The key "42" is renumbered to "0" by Laravel at the top level of shared data; use a string key.',
        ]]);
});

test('binds each same-named enum token to its own class on either side of a key the printer renumbers or marks optional', function () {
    Inertia::share(new MiddlewareWithSameNamedEnumsAroundPrintedKeys()->share(request()));
    $result = analyzeSharedDataFor(MiddlewareWithSameNamedEnumsAroundPrintedKeys::class);

    expect($result['sharedPageProps'] ?? null)
        ->toBe('{ a: WorkbenchStatusType, "0": string, note?: string, b: CrmStatusType }')
        ->and($result['typeImports'] ?? null)->toBe([
            './workbench/app/enums' => ['StatusType as WorkbenchStatusType'],
            './workbench/crm/enums' => ['StatusType as CrmStatusType'],
        ])
        ->and(inertiaProps(Inertia::render('Shared', [])))->toEqual((object) ['a' => 0, '0' => 'seven', 'note' => 'x', 'b' => 'lead']);
});

test('a top-level int key of shared data publishes on the key Laravel renumbers it to, and a nested one keeps its own', function () {
    expect(analyzeSharedDataFor(MiddlewareWithNumericBodyKey::class)['sharedPageProps'])
        ->toBe('{ appName: string, "0": boolean, codes: { "7": string } }')
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => MiddlewareWithNumericBodyKey::class,
            'message' => 'The key "42" is renumbered to "0" by Laravel at the top level of shared data; use a string key.',
        ]]);
});

// PropsResolver::resolve() unpacks shared data's dotted keys with the page's, writing each into its path by Arr::set().
test('a dotted key of shared data publishes nested, as Inertia unpacks it', function () {
    Inertia::share(new MiddlewareWithDottedKeys()->share(request()));

    expect(analyzeSharedDataFor(MiddlewareWithDottedKeys::class)['sharedPageProps'] ?? null)
        ->toBe('{ auth: { user: string }, "1": { "5": string } }')
        ->and(inertiaPropsJson(Inertia::render('Shared', [])))->toBe('{"auth":{"user":"x"},"1":{"5":"y"}}');
});

test('a signature of shared data that covers a dotted key\'s head takes the head\'s type', function () {
    Inertia::share(new MiddlewareWithDottedSignatureHead()->share(request()));

    expect(analyzeSharedDataFor(MiddlewareWithDottedSignatureHead::class)['sharedPageProps'] ?? null)
        ->toBe('{ [key: `${string}_tag`]: string | undefined | { b: number }, x_tag: { b: number } }')
        ->and(inertiaPropsJson(Inertia::render('Shared', [])))->toBe('{"east_tag":"v","west_tag":"v","x_tag":{"b":1}}');
});

// ensurePathIsTraversable() opens only an array, a Closure or an Arrayable, so Arr::set() replaces the rest with `[]`.
test('a dotted key of shared data writes over a resource or an Inertia prop object it is written under', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = function (int $c) use ($user): string {
        $request = bindRequestUser($user);
        $request->query->set('c', (string) $c);
        Inertia::flushShared();
        Inertia::share(new MiddlewareWithClosedDottedHeads()->share($request));

        return inertiaPropsJson(Inertia::render('Shared', []));
    };

    expect(analyzeSharedDataFor(MiddlewareWithClosedDottedHeads::class)['sharedPageProps'] ?? null)
        ->toBe('{ card: { can?: boolean } | { data: UserCardResource }, always: { b?: number } | { a: number } }')
        ->and([$sent(1), $sent(0)])->toBe([
            '{"card":{"can":true},"always":{"b":2}}',
            '{"card":{"data":{"id":'.$user->id.',"name":"Ada"}},"always":{"a":1}}',
        ]);
});

test('share()\'s spelling of a signature outranks the class\'s exact name', function () {
    expect(analyzeSharedDataFor(MiddlewareWithSpellingsAcrossLocations::class)['sharedPageProps'])
        ->toBe('{ id: number, [key: `${string}\\\\_x`]: number }');
});

test('a losing cast spelling brings no import', function () {
    $result = analyzeSharedDataFor(MiddlewareWithLosingSpellingImport::class);

    expect($result['sharedPageProps'])->toBe('{ id: number, [key: `${string}\\\\_cast`]: number }')
        ->and($result['typeImports'])->toBe([]);
});

it('quotes a shared-data key that is not an identifier', function () {
    expect(analyzeSharedDataFor(MiddlewareWithKeyEdges::class)['sharedPageProps'])
        ->toBe('{ "can-edit": boolean, ok: number }');
});

test('the shared-data type prints no ? after a signature, whatever flag reaches it', function () {
    $analyzer = new class extends InertiaSharedDataAnalyzer
    {
        /**
         * The type string joined from the members the builder prints.
         *
         * @param  array<string, array{type: string, optional: bool}>  $props
         * @param  array<string, array{type: string, optional: bool}>  $overrides
         */
        public function build(array $props, array $overrides): string
        {
            return FileImportNames::objectMember(new MethodAnalysis, $this->buildMembersWithOverrides($props, $overrides))['type'];
        }
    };

    expect($analyzer->build(
        ['[key: `${string}_flag`]' => ['type' => 'boolean', 'optional' => true], 'id' => ['type' => 'number', 'optional' => true]],
        ['[key: number]' => ['type' => 'string', 'optional' => true]],
    ))->toBe('{ [key: `${string}_flag`]: boolean, id?: number, [key: number]: string }');
});

// Applied once: a second pass by the engine would leave `filters?` a key of its own beside `filters` (TS2300).
test('a share() cast marks a key optional by its `?` suffix or its optional flag, once', function () {
    expect(analyzeSharedDataFor(MiddlewareWithOptionalShareCast::class)['sharedPageProps'])
        ->toBe("{ filters?: Record<string, string>, locale?: 'en' | 'es', id: number }");
});

test('the engine leaves share()\'s own casts to the shared-data analyzer', function () {
    $analysis = resolve(AstEngine::class)->analyzeMethod(MiddlewareWithOptionalShareCast::class, 'share');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'filters' => 'unknown[]',
        'locale' => 'string',
        'id' => 'number',
    ]);
});

test('a cast that says nothing about optional keeps the docblock\'s flag, else the prop\'s own', function () {
    expect(analyzeSharedDataFor(MiddlewareWithCastsOverOptionalProps::class)['sharedPageProps'])
        ->toBe('{ flash?: Flash, notice: Notice, banner: Banner, held?: HeldShare, id: number, [key: `${string}_note`]: number | undefined }');
});

// The parent whose share() the subclass spreads is a cast location of its own, which the engine leaves alone.
test('a parent middleware\'s share() cast reaches a subclass that spreads parent::share()', function () {
    expect(analyzeSharedDataFor(ShareCastChildMiddleware::class)['sharedPageProps'])
        ->toBe("{ locale: 'en' | 'es', id: number }");
});

test('a subclass that inherits share() whole gets its casts once', function () {
    expect(analyzeSharedDataFor(ShareCastInheritingMiddleware::class)['sharedPageProps'])
        ->toBe("{ filters?: Record<string, string>, locale?: 'en' | 'es', id: number }");
});

test('a share() cast that says nothing about optional keeps the class cast\'s flag', function () {
    expect(analyzeSharedDataFor(MiddlewareWithFlaggedClassCastUnderShareCast::class)['sharedPageProps'])
        ->toBe('{ held?: HeldShare, id: number }');
});

// The parent's body is read as Inertia sends it too, so the callable it shares types as the call Inertia makes.
test('a parent share() cast reached through parent::share() keeps its ? suffix', function () {
    $request = Request::create('/');
    Inertia::share(new OptionalCastChildMiddleware()->share($request));
    $sent = (array) inertiaProps(Inertia::render('Shared', []), $request);

    // The cast is the user's own type for `filters`, laid as written over the null the parent sends.
    expect(sharedProps(OptionalCastChildMiddleware::class))
        ->toBe(['filters?' => 'string[]', 'app' => 'string', 'stamp' => 'string', 'child' => 'number'])
        ->and(AnalysisWarnings::all())->toBe([])
        ->and(array_keys($sent))->toBe(['filters', 'app', 'stamp', 'child'])
        ->and([$sent['filters'], $sent['app'], $sent['child']])->toBe([null, 'x', 1])
        ->and($sent['stamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

// PHP never gives a subclass its parent's attributes, so the parent whose share() is read is a location of its own.
test('a parent class-level cast reaches a child that spreads parent::share()', function () {
    $request = Request::create('/');
    Inertia::share(new ClassCastChildMiddleware()->share($request));

    expect(sharedProps(ClassCastChildMiddleware::class))->toBe(['flag' => 'boolean'])
        ->and((array) inertiaProps(Inertia::render('Shared', []), $request))->toBe(['flag' => 1]);
});

// The share() the subclass inherits is its parent's location, so the subclass's own class comes after it.
test('a subclass\'s class-level cast outranks the share() cast it inherits, keeping that cast\'s optional flag', function () {
    $request = Request::create('/');
    Inertia::share(new ClassCastOverInheritedShareMiddleware()->share($request));

    expect(sharedProps(ClassCastOverInheritedShareMiddleware::class))
        ->toBe(['filters?' => 'Record<string, string>', 'locale?' => "'en'", 'id' => 'number'])
        ->and((array) inertiaProps(Inertia::render('Shared', []), $request))->toBe(['filters' => [], 'locale' => 'en', 'id' => 1]);
});

test('a parent whose share() the child never reads adds no cast key', function () {
    $request = Request::create('/');
    Inertia::share(new UnreadParentShareMiddleware()->share($request));

    expect(sharedProps(UnreadParentShareMiddleware::class))->toBe(['own' => 'number'])
        ->and((array) inertiaProps(Inertia::render('Shared', []), $request))->toBe(['own' => 1]);
});

test('a parent whose share() the child never reads adds none of its class-level cast keys', function () {
    $request = Request::create('/');
    Inertia::share(new ClassCastUnreadChildMiddleware()->share($request));

    expect(sharedProps(ClassCastUnreadChildMiddleware::class))->toBe(['own' => 'number'])
        ->and((array) inertiaProps(Inertia::render('Shared', []), $request))->toBe(['own' => 1]);
});

test('a parent share() nested under a key adds no top-level cast key', function () {
    $request = Request::create('/');
    Inertia::share(new NestedParentShareMiddleware()->share($request));
    $sent = json_decode((string) json_encode(inertiaProps(Inertia::render('Shared', []), $request)), true);

    expect(sharedProps(NestedParentShareMiddleware::class))
        ->toBe(['nested' => '{ filters: null; app: string; stamp: string }', 'child' => 'number'])
        ->and(array_keys($sent['nested']))->toBe(['filters', 'app', 'stamp'])
        ->and([$sent['nested']['filters'], $sent['nested']['app'], $sent['child']])->toBe([null, 'x', 1])
        ->and($sent['nested']['stamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

// Read as the shared-data analyzer reads it, for Inertia; read for anything else the callable stays the Closure.
test('the engine leaves a parent share()\'s casts to the shared-data analyzer too', function () {
    $types = fn (Reading $reading): array => array_column(
        resolve(AstEngine::class)->analyzeMethod(OptionalCastChildMiddleware::class, 'share', reading: $reading)->properties,
        'type',
        'name',
    );

    expect($types(Reading::Inertia))->toBe(['filters' => 'null', 'app' => 'string', 'stamp' => 'string', 'child' => 'number'])
        ->and($types(Reading::Encoded)['stamp'])->toBe('Record<string, never>');
});

// Read here as Inertia sends shared data, whose wrap around the null tryFrom() may return sends `[]`; an API resource
// reading the same helper keeps both arms.
test('a share middleware types the short ternary its own helper returns over an enum resource as that resource alone', function () {
    [$picked, $absent] = array_map(function (array $query): array {
        $request = Request::create('/', 'GET', $query);
        Inertia::share(new MiddlewareWithWrapHelpers()->share($request));

        return json_decode((string) json_encode(inertiaProps(Inertia::render('Shared', []), $request)), true);
    }, [['s' => 1], []]);

    expect(sharedProps(MiddlewareWithWrapHelpers::class))->toBe(['status' => 'AsEnum<typeof Status> | never[]'])
        ->and([$picked['status']['name'], $absent['status']])->toBe([Status::Published->name, []]);
});

// No JsonResource::filter() runs over shared data, nor over a response's with() and additional(), so Inertia sends
// each wrap around null as the `[]` resolve() returns.
test('a share middleware admits the empty list Inertia sends for an EnumResource over a null enum', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);
    $sent = function (): array {
        $request = Request::create('/');
        Inertia::flushShared();
        Inertia::share(new MiddlewareWithNullableEnumResource()->share($request));
        $props = json_decode((string) json_encode(inertiaProps(Inertia::render('Shared', []), $request)), true);

        return [$props['visibility'], $props['card']['meta'], $props['card']['extra']];
    };
    $hidden = $sent();
    $post->update(['visibility' => Visibility::Public]);
    $wrap = 'AsEnum<typeof Visibility> | never[]';

    expect(sharedProps(MiddlewareWithNullableEnumResource::class))->toBe([
        'visibility' => $wrap,
        'card' => "{ data: PostVisibilitiesResource; meta: {$wrap}; extra: {$wrap} }",
    ])
        ->and($hidden)->toBe([[], [], []])
        ->and(array_column($sent(), 'name'))->toBe(array_fill(0, 3, Visibility::Public->name));
});

test('a share() a trait supplies under an alias publishes the keys the trait method returns', function () {
    $request = Request::create('/');
    Inertia::share(new AliasedShareMiddleware()->share($request));

    expect(sharedProps(AliasedShareMiddleware::class))->toBe(['traitKey' => 'number', 'traitName' => 'string'])
        ->and((array) inertiaProps(Inertia::render('Shared', []), $request))->toBe(['traitKey' => 1, 'traitName' => 'x']);
});

// Inertia calls the shared closure once and sends the callable it returns as `{}`, which `number` rejects, as at base:
// a `??` there reads as it always has (the closure known gap).
test('a share middleware reads ?? over a held callable a called closure returns as before', function () {
    $request = Request::create('/');
    Inertia::share(new MiddlewareWithCallableReturn()->share($request));
    $sent = inertiaProps(Inertia::render('Shared', []), $request);

    expect(sharedProps(MiddlewareWithCallableReturn::class))->toBe(['a' => 'number'])
        ->and(json_encode($sent))->toBe('{"a":{}}');
});

// A shared resource's `with()` is sent unfiltered as on a page, so its MergeValues merge no key there either.
test('a share middleware publishes no key a merge() in a shared resource\'s with() would merge', function () {
    $author = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $author->id]);
    $request = Request::create('/');
    Inertia::flushShared();
    Inertia::share(new MiddlewareWithMergingWith()->share($request));
    $sent = json_decode((string) json_encode(inertiaProps(Inertia::render('Shared', []), $request)), true);

    expect(sharedProps(MiddlewareWithMergingWith::class))
        ->toBe(['withMerge' => '{ data: MergingWithResource; a: number; listed: unknown[] }'])
        ->and($sent['withMerge'])->toBe([
            'data' => ['id' => $post->id], 'a' => 1, 0 => [], 1 => ['data' => ['m' => 3]], 'listed' => [['data' => ['n' => 4]]],
        ]);
});

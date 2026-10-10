<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ArrayMergeShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\InheritedShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithAllErrors;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithCastsOverOptionalProps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithClassTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithConflictingImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDocblockReturn;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithDuplicateImports;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithEnumResourceErrors;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithFlaggedClassCastUnderShareCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithImportPaths;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithInertiaWrappers;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithKeyEdges;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithLosingSpellingImport;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodOverridesClass;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMethodTsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithMultiEnumTernary;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithNumericCastKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalDocblockKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalShareCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithoutShareMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSignatureCastSpelling;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithSpellingsAcrossLocations;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTagSignatureCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTsCastsAndDocblock;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithUndefinedLiteralCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithUnsharedOptionalKey;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithWrappedAndBareEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ShareCastChildMiddleware;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ShareCastInheritingMiddleware;
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

test('an enum read both wrapped and bare keeps its type import alongside the value import', function () {
    // The GC runs the opposite way round from the resource case: only the wrapped prop's own token is
    // substituted, so the prop that still reads the enum bare has to keep RoleType importable.
    $result = analyzeSharedDataFor(MiddlewareWithWrappedAndBareEnum::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ role: AsEnum<typeof Role>, bareRole: RoleType }')
        ->and($result['typeImports'])->toBe(['./workbench/app/enums' => ['RoleType']])
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

test('a value import whose const name the props type never spells is dropped', function () {
    // A two-enum ternary lands in multiEnumResourceFqcns, which the AsEnum rewrite does not key off, so
    // the props type keeps both bare names. AnalysisImports still offers Role and Status as value
    // imports; unfiltered they would be emitted unused, which a consumer's noUnusedLocals rejects.
    $result = analyzeSharedDataFor(MiddlewareWithMultiEnumTernary::class);

    expect($result)->not->toBeNull()
        ->and($result['sharedPageProps'])->toBe('{ either: RoleType | StatusType }')
        ->and($result['valueImports'])->toBe([]);
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

// ─── #[TsCasts] key edges ────────────────────────────────────────

// PHP stores '42' as an int; the optional-suffix check used to receive it under strict types and throw a TypeError.
test('a numeric cast key lands on its own key instead of stopping the run', function () {
    expect(analyzeSharedDataFor(MiddlewareWithNumericCastKey::class)['sharedPageProps'])
        ->toBe('{ appName: string, "42": boolean }');
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
         * The type string the builder prints.
         *
         * @param  array<string, array{type: string, optional: bool}>  $props
         * @param  array<string, array{type: string, optional: bool}>  $overrides
         */
        public function build(array $props, array $overrides): string
        {
            return $this->buildTypeStringWithOverrides($props, $overrides);
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

// The shared-data analyzer reads only the subclass's share(), so the engine still applies the parent's own cast.
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

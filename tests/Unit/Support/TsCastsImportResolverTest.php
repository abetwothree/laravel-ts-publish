<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;

describe('resolve', function () {
    test('returns unchanged overrides when no import paths are provided', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'auth' => 'AuthData',
            'appName' => 'string',
        ], []);

        expect($result['overrides'])->toBe([
            'auth' => 'AuthData',
            'appName' => 'string',
        ])->and($result['typeImports'])->toBe([])
            ->and($result)->not->toHaveKey('importStatements');
    });

    test('collects one imported type under its path', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'auth' => 'AuthData',
            'appName' => 'string',
        ], [
            'auth' => '@js/types/auth',
        ]);

        expect($result['overrides'])->toBe([
            'auth' => 'AuthData',
            'appName' => 'string',
        ])->and($result['typeImports'])->toBe([
            '@js/types/auth' => ['AuthData'],
        ]);
    });

    test('deduplicates imports for same type and same path', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'auth' => 'SharedData',
            'flash' => 'SharedData',
            'appName' => 'string',
        ], [
            'auth' => '@js/types/shared',
            'flash' => '@js/types/shared',
        ]);

        expect($result['overrides'])->toBe([
            'auth' => 'SharedData',
            'flash' => 'SharedData',
            'appName' => 'string',
        ])->and($result['typeImports'])->toBe([
            '@js/types/shared' => ['SharedData'],
        ]);
    });

    test('aliases conflicting type names imported from different paths', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'auth' => 'SharedData',
            'flash' => 'SharedData',
        ], [
            'auth' => '@js/types/auth',
            'flash' => '@js/types/flash',
        ]);

        expect($result['overrides'])->toBe([
            'auth' => 'AuthSharedData',
            'flash' => 'FlashSharedData',
        ])->and($result['typeImports'])->toBe([
            '@js/types/auth' => ['SharedData as AuthSharedData'],
            '@js/types/flash' => ['SharedData as FlashSharedData'],
        ]);
    });

    test('creates path-prefixed aliases for three conflicting imports', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'auth' => 'SharedData',
            'flash' => 'SharedData',
            'meta' => 'SharedData',
        ], [
            'auth' => '@js/types/auth',
            'flash' => '@js/types/flash',
            'meta' => '@js/types/meta',
        ]);

        expect($result['overrides'])->toBe([
            'auth' => 'AuthSharedData',
            'flash' => 'FlashSharedData',
            'meta' => 'MetaSharedData',
        ])->and($result['typeImports'])->toBe([
            '@js/types/auth' => ['SharedData as AuthSharedData'],
            '@js/types/flash' => ['SharedData as FlashSharedData'],
            '@js/types/meta' => ['SharedData as MetaSharedData'],
        ]);
    });

    test('ignores import paths for keys that are not present in overrides', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'appName' => 'string',
        ], [
            'unknown' => '@js/types/unknown',
        ]);

        expect($result['overrides'])->toBe([
            'appName' => 'string',
        ])->and($result['typeImports'])->toBe([]);
    });

    test('uses more path segments when conflicting types share the same basename', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'user' => 'UserType',
            'profile' => 'UserType',
        ], [
            'user' => '@types/models/user',
            'profile' => '@js/types/user',
        ]);

        expect($result['overrides'])->toBe([
            'user' => 'ModelsUserUserType',
            'profile' => 'TypesUserUserType',
        ])->and($result['typeImports'])->toBe([
            '@js/types/user' => ['UserType as TypesUserUserType'],
            '@types/models/user' => ['UserType as ModelsUserUserType'],
        ]);
    });

    test('strips all extensions including .d.ts when deriving alias prefix', function () {
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'auth' => 'SharedData',
            'flash' => 'SharedData',
        ], [
            'auth' => '@types/auth.d.ts',
            'flash' => '@types/flash.d.ts',
        ]);

        expect($result['overrides'])->toBe([
            'auth' => 'AuthSharedData',
            'flash' => 'FlashSharedData',
        ])->and($result['typeImports'])->toBe([
            '@types/auth.d.ts' => ['SharedData as AuthSharedData'],
            '@types/flash.d.ts' => ['SharedData as FlashSharedData'],
        ]);
    });

    test('falls back to numeric suffix when paths are identical after extension stripping at every depth', function () {
        // '@types/auth.ts' and '@types/auth.d.ts' collide at every depth: 'auth' at 1, 'TypesAuth' at 2 (max),
        // so no depth disambiguates and the numeric-suffix fallback has to guarantee unique TS identifiers.
        $resolver = new TsCastsImportResolver;

        $result = $resolver->resolve([
            'primary' => 'AuthData',
            'secondary' => 'AuthData',
        ], [
            'primary' => '@types/auth.ts',
            'secondary' => '@types/auth.d.ts',
        ]);

        expect($result['overrides'])->toBe([
            'primary' => 'TypesAuth1AuthData',
            'secondary' => 'TypesAuth2AuthData',
        ])->and($result['typeImports'])->toBe([
            '@types/auth.d.ts' => ['AuthData as TypesAuth2AuthData'],
            '@types/auth.ts' => ['AuthData as TypesAuth1AuthData'],
        ]);
    });
});

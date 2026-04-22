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
        ])->and($result['importStatements'])->toBe([]);
    });

    test('builds a single import statement for one imported type', function () {
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
        ])->and($result['importStatements'])->toBe([
            "import type { AuthData } from '@js/types/auth';",
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
        ])->and($result['importStatements'])->toBe([
            "import type { SharedData } from '@js/types/shared';",
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
        ])->and($result['importStatements'])->toBe([
            "import type { SharedData as AuthSharedData } from '@js/types/auth';",
            "import type { SharedData as FlashSharedData } from '@js/types/flash';",
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
        ])->and($result['importStatements'])->toBe([
            "import type { SharedData as AuthSharedData } from '@js/types/auth';",
            "import type { SharedData as FlashSharedData } from '@js/types/flash';",
            "import type { SharedData as MetaSharedData } from '@js/types/meta';",
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
        ])->and($result['importStatements'])->toBe([]);
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
        ])->and($result['importStatements'])->toBe([
            "import type { UserType as ModelsUserUserType } from '@types/models/user';",
            "import type { UserType as TypesUserUserType } from '@js/types/user';",
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
        ])->and($result['importStatements'])->toBe([
            "import type { SharedData as AuthSharedData } from '@types/auth.d.ts';",
            "import type { SharedData as FlashSharedData } from '@types/flash.d.ts';",
        ]);
    });
});

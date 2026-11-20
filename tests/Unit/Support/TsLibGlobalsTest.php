<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TsLibGlobals;

it('equals what the generator reads from the installed TypeScript', function () {
    $root = dirname(__DIR__, 3);
    $libs = [
        ...glob($root.'/node_modules/@typescript/typescript-*/lib/lib.es5.d.ts') ?: [],
        ...glob($root.'/node_modules/typescript/lib/lib.es5.d.ts') ?: [],
    ];

    if ($libs === []) {
        $this->markTestSkipped('TypeScript is not installed');
    }

    $script = $root.'/.github/scripts/generate-ts-lib-globals.php';
    $generated = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' --stdout');

    expect($generated)->toBe(file_get_contents($root.'/src/Support/TypeStrings/TsLibGlobals.php'));
});

it('holds the global types of TypeScript and the DOM, and no name only a module declares', function () {
    expect(TsLibGlobals::TYPES)
        ->toHaveKeys(['Date', 'Partial', 'PropertyKey', 'Response', 'HTMLElement'])
        ->not->toHaveKeys(['fetch', 'Image', 'Audio', 'Option', 'Proxy', 'NaN', 'Intl', 'Temporal', 'globalThis'])
        ->not->toHaveKey('Meta')
        ->not->toHaveKey('IteratorObjectConstructor');
});

it('holds apart each global the libs declare only as a value, and globalThis, which no lib declares', function () {
    expect(TsLibGlobals::VALUES)
        ->toHaveKeys(['fetch', 'Image', 'Audio', 'Option', 'Proxy', 'NaN', 'Infinity', 'globalThis'])
        ->not->toHaveKeys(['Date', 'HTMLElement', 'Intl', 'Meta'])
        ->and(array_intersect_key(TsLibGlobals::VALUES, TsLibGlobals::TYPES))->toBe([]);
});

it('holds apart each namespace no type of the libs shares a name with', function () {
    expect(array_keys(TsLibGlobals::NAMESPACES))->toBe(['CSS', 'Intl', 'Reflect', 'Temporal', 'WebAssembly'])
        ->and(array_intersect_key(TsLibGlobals::NAMESPACES, TsLibGlobals::TYPES + TsLibGlobals::VALUES))->toBe([]);
});

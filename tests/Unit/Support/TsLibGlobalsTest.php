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
    expect(TsLibGlobals::NAMES)
        ->toHaveKeys(['Date', 'Partial', 'PropertyKey', 'Response', 'HTMLElement', 'Intl', 'fetch'])
        ->not->toHaveKey('Meta')
        ->not->toHaveKey('IteratorObjectConstructor');
});

<?php

declare(strict_types=1);

it('registers every version-guarded Laravel class reference', function () {
    $srcDirectory = __DIR__.'/../../src';
    $registry = (string) file_get_contents(__DIR__.'/../../docs/laravel-version-guards.md');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    $guardedByFile = [];

    foreach ($iterator as $fileInfo) {
        if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($fileInfo->getPathname());
        $relativePath = 'src'.substr($fileInfo->getPathname(), strlen($srcDirectory));

        $guarded = [];

        // Direct: class_exists('Illuminate\Some\Fqcn') or class_exists("Illuminate\Some\Fqcn").
        preg_match_all("/class_exists\(\s*(['\"])(Illuminate\\\\{1,2}[^'\"]+)\\1/", $contents, $direct);
        array_push($guarded, ...$direct[2]);

        // Indirect: $var = 'Illuminate\Some\Fqcn'; ... class_exists($var).
        preg_match_all(
            "/\\\$(\w+)\s*=\s*(['\"])(Illuminate\\\\{1,2}[^'\"]+)\\2\s*;/",
            $contents,
            $assignments,
            PREG_SET_ORDER,
        );

        foreach ($assignments as $assignment) {
            [, $variable, , $fqcn] = $assignment;

            if (preg_match("/class_exists\(\s*\\\$".preg_quote($variable, '/')."\s*\)/", $contents) === 1) {
                $guarded[] = $fqcn;
            }
        }

        if ($guarded === []) {
            continue;
        }

        $guardedByFile[$relativePath] = array_values(array_unique(array_map(
            fn (string $fqcn): string => str_replace('\\\\', '\\', $fqcn),
            $guarded,
        )));
    }

    // A walk that reaches zero files (or the wrong ones) would pass vacuously below. Proving this
    // known, deeply-nested reference was found is proof the recursive walk actually ran.
    expect($guardedByFile)->toHaveKey('src/Analyzers/Concerns/InspectsResourceCalls.php')
        ->and($guardedByFile['src/Analyzers/Concerns/InspectsResourceCalls.php'])
        ->toContain('Illuminate\Http\Resources\Attributes\Collects');

    $allGuarded = array_unique(array_merge(...array_values($guardedByFile)));

    $unregistered = array_values(array_filter(
        $allGuarded,
        fn (string $fqcn): bool => ! str_contains($registry, $fqcn),
    ));

    expect($unregistered)->toBe([]);
});

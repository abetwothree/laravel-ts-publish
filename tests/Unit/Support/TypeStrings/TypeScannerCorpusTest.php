<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ScanDialect;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;

/**
 * The scanner corpus by source: golden types, hand-written cases and generated ones. Each row holds its type `t` and
 * the `union` and `intersection` parts and literal `spans` TypeScript's parser reads.
 *
 * Each row's answers are what TypeScript 5.9's parser reads, so a row edited by hand would no longer be one.
 *
 * @return array<string, array{list<array<string, mixed>>}>
 */
function typeScannerCorpus(): array
{
    /** @var list<array<string, mixed>> $rows */
    $rows = json_decode((string) file_get_contents(__DIR__.'/Fixtures/scanner-corpus.json'), true, flags: JSON_THROW_ON_ERROR);
    $sources = ['a' => 'golden types', 'b' => 'hand-written cases', 'c' => 'generated cases'];
    $bySource = [];

    foreach ($rows as $row) {
        $bySource[$sources[$row['src']]][0][] = $row;
    }

    return $bySource;
}

test('splits and reads literals where TypeScript\'s parser does', function (array $rows) {
    $mismatches = [];

    foreach ($rows as $row) {
        $read = [
            'union' => TypeScanner::split($row['t'], ['|']),
            'intersection' => TypeScanner::split($row['t'], ['&']),
            'spans' => TypeScanner::literalSpans($row['t']),
        ];

        foreach ($read as $answer => $parts) {
            if ($parts !== $row[$answer]) {
                $mismatches[] = [$answer => $row['t'], 'oracle' => $row[$answer], 'scanner' => $parts];
            }
        }
    }

    expect($rows)->not->toBeEmpty()
        ->and($mismatches)->toBe([]);
})->with(typeScannerCorpus());

test('reads a type with no backtick and no arrow alike in both dialects', function (array $rows) {
    $mismatches = [];
    $compared = 0;

    foreach ($rows as $row) {
        if (strpbrk($row['t'], '`=') !== false) {
            continue;
        }

        $compared++;

        foreach ([TypeScanner::depths(...), TypeScanner::literalSpans(...)] as $read) {
            if ($read($row['t'], ScanDialect::PhpDoc) !== $read($row['t'])) {
                $mismatches[] = $row['t'];
            }
        }
    }

    expect($compared)->toBeGreaterThan(0)
        ->and($mismatches)->toBe([]);
})->with(typeScannerCorpus());

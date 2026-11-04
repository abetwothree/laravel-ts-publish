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

test('reads a type with no backtick, no arrow and no quote left open on its line alike in both dialects', function (array $rows) {
    $mismatches = [];
    $compared = 0;

    // TypeScript reads a quote with no closer to the type's end, and one across a line break whole, where PHPDoc reads
    // plain text; a span left open moves when one more byte follows the type.
    $leavesQuoteOpen = static function (string $type): bool {
        $spans = TypeScanner::literalSpans($type);

        return $spans !== TypeScanner::literalSpans($type.' ')
            || array_any($spans, static fn (array $span): bool => strpbrk(substr($type, $span[0], $span[1] - $span[0]), "\r\n") !== false);
    };

    foreach ($rows as $row) {
        if (strpbrk($row['t'], '`=') !== false || $leavesQuoteOpen($row['t'])) {
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

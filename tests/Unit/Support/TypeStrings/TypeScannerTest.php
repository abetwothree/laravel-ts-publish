<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ScanDialect;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;

/**
 * Each offset's depth as one character, a `.` for an offset inside literal text, so a row reads beside its type.
 */
function typeScannerDepthMap(string $type, ScanDialect $dialect = ScanDialect::TypeScript): string
{
    $depths = TypeScanner::depths($type, $dialect);
    $map = '';

    for ($offset = 0; $offset < strlen($type); $offset++) {
        $map .= isset($depths[$offset]) ? (string) $depths[$offset] : '.';
    }

    return $map;
}

test('splits a TypeScript type at its top level only', function (string $type, array $separators, array $parts) {
    expect(TypeScanner::split($type, $separators))->toBe($parts);
})->with([
    ['`a|b` | null', ['|'], ['`a|b`', 'null']],
    ['`a{b` | null', ['|'], ['`a{b`', 'null']],
    ['`a<b` | null', ['|'], ['`a<b`', 'null']],
    ["`it's` | null", ['|'], ["`it's`", 'null']],
    ['`${`a|b`}` | null', ['|'], ['`${`a|b`}`', 'null']],
    ["`\${'`'}` | 'Post'", ['|'], ["`\${'`'}`", "'Post'"]],
    ["'it\\'s | undefined | x' | null", ['|'], ["'it\\'s | undefined | x'", 'null']],
    ['"q\\" | undefined | r" | null', ['|'], ['"q\\" | undefined | r"', 'null']],
    ['`a\\` | undefined | b` | null', ['|'], ['`a\\` | undefined | b`', 'null']],
    ["'a\\\\' | 'b'", ['|'], ["'a\\\\'", "'b'"]],
    ['(a: string) => B | null', ['|'], ['(a: string) => B | null']],
    ['{ fn: () => void | null } | null', ['|'], ['{ fn: () => void | null }', 'null']],
    ['Foo<() => A | B> | null', ['|'], ['Foo<() => A | B>', 'null']],
    ['a) | b', ['|'], ['a)', 'b']],
    ['`${A | B},x`, V', [','], ['`${A | B},x`', 'V']],
    ['k: `a;b`; n: number', [';'], ['k: `a;b`', 'n: number']],
]);

test('splits a PHPDoc type with quotes and no template literals', function (string $type, array $separators, array $parts) {
    expect(TypeScanner::split($type, $separators, ScanDialect::PhpDoc))->toBe($parts);
})->with([
    ["'a|b'|'c'", ['|'], ["'a|b'", "'c'"]],
    ["A&'x&y'", ['&'], ['A', "'x&y'"]],
    ["'it\\'s'|null", ['|'], ["'it\\'s'", 'null']],
    ['`a|b`', ['|'], ['`a', 'b`']],
    ["'x'|a => b|c", ['|'], ["'x'", 'a => b', 'c']],
]);

test('lists and masks literal text, keeping interpolations as code', function () {
    expect(TypeScanner::literalSpans("`a\${B}c` | 'd'"))->toBe([[0, 4], [5, 8], [11, 14]])
        ->and(TypeScanner::maskLiterals("`a\${User}c` | 'Post'"))->toBe('    User    |       ');
});

test('splits at any of several separators and keeps no empty part', function (string $type, array $separators, array $parts) {
    expect(TypeScanner::split($type, $separators))->toBe($parts);
})->with([
    'an intersection inside a union arm' => ['A & B | C', ['|', '&'], ['A', 'B', 'C']],
    'a leading pipe' => ['| A | B', ['|'], ['A', 'B']],
    'no separator' => ['Record<string, A | B>', ['|'], ['Record<string, A | B>']],
    'an empty type' => ['', ['|'], []],
    'only spaces' => ['  ', ['|'], []],
]);

test('reads each offset at its bracket depth, literal text at none', function (string $type, ScanDialect $dialect, string $map) {
    expect(typeScannerDepthMap($type, $dialect))->toBe($map);
})->with([
    'a string literal' => ["'a' | b", ScanDialect::TypeScript, '...0000'],
    'an escaped quote' => ["'it\\'s' | b", ScanDialect::TypeScript, '.......0000'],
    'an interpolation one level in' => ['`a${B}c` | x', ScanDialect::TypeScript, '....1...0000'],
    'a stray closer kept inside its interpolation' => ['`${a)}`|b', ScanDialect::TypeScript, '...11..00'],
    'an arrow owning the union after it' => ['() => A | B', ScanDialect::TypeScript, '00000000100'],
    'an arrow ending with its parentheses' => ['(() => A) | B', ScanDialect::TypeScript, '0111111100000'],
    'an arrow ending at a comma' => ['() => A, B | C', ScanDialect::TypeScript, '00000000000000'],
    'a stray closer ending an arrow' => ['() => A) | B', ScanDialect::TypeScript, '000000000000'],
    'a PHPDoc backtick as plain text' => ['`{`', ScanDialect::PhpDoc, '001'],
    'no arrow rule in PHPDoc' => ['a => b|c', ScanDialect::PhpDoc, '00000000'],
]);

test('reads an unterminated literal to the end of the type', function () {
    expect(TypeScanner::literalSpans("x | 'ab"))->toBe([[4, 7]])
        ->and(TypeScanner::literalSpans('`a${B'))->toBe([[0, 4]])
        ->and(typeScannerDepthMap('`a${B'))->toBe('....1');
});

test('lists a literal nested in an interpolation between its template segments', function () {
    expect(TypeScanner::literalSpans("`\${'x'}`"))->toBe([[0, 3], [3, 6], [6, 8]])
        ->and(TypeScanner::literalSpans('`a\\`b`'))->toBe([[0, 6]])
        ->and(TypeScanner::maskLiterals("`\${'x'}`"))->toBe('        ');
});

// PHPStan's lexer ends no literal past its line, so a PHPDoc quote with no closer before a line break is plain text.
test('reads a PHPDoc quote with no closer on its line as plain text', function (string $type, string $map) {
    expect(typeScannerDepthMap($type, ScanDialect::PhpDoc))->toBe($map);
})->with([
    'never closed' => ["'a|{", '0000'],
    'closed only past a line break' => ["'a\n'{", '00000'],
    'closed past an escaped line break' => ["'a\\\n'{", '000000'],
    'closed on its line, after a lone quote' => ["'a\n'b'{", '000...0'],
]);

test('reads an unclosed TypeScript quote to the end of the type, past a line break too', function () {
    expect(typeScannerDepthMap("'a\n'{"))->toBe('....0')
        ->and(typeScannerDepthMap("'a|{"))->toBe('....');
});

test('reads only quotes as literals in PHPDoc', function () {
    expect(TypeScanner::literalSpans("`a` | 'b'", ScanDialect::PhpDoc))->toBe([[6, 9]])
        ->and(TypeScanner::maskLiterals("array{'a|b': `c`}", ScanDialect::PhpDoc))->toBe('array{     : `c`}')
        ->and(TypeScanner::maskLiterals('Record<string, number>'))->toBe('Record<string, number>');
});

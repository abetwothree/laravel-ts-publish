<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Seeded keys over the characters a printer escapes or quotes: quotes, backslashes, slashes, control characters,
 * line separators, digits, signs and non-ASCII letters, 0 to 8 a key.
 *
 * @return list<string>
 */
function printedKeyCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $alphabet = [
        'a', 'Z', '_', '$', '0', '1', '9', '-', '.', ' ', '?', ':', '[', ']', '{', '}', "'", '"', '\\', '/', '`',
        "\n", "\r", "\t", "\x00", "\x01", "\x1f", "\x7f", 'é', '😀', "\u{2028}", "\u{200D}",
    ];
    $keys = [];

    for ($case = 0; $case < $cases; $case++) {
        $length = $random->getInt(0, 8);
        $keys[] = implode('', array_map(
            static fn (): string => $alphabet[$random->getInt(0, count($alphabet) - 1)],
            $length === 0 ? [] : range(1, $length),
        ));
    }

    return $keys;
}

describe('decode', function () {
    test('decodes a printed key', function (string $printed, ?string $key) {
        expect(PrintedKey::decode($printed))->toBe($key);
    })->with([
        ['name', 'name'], ['name?', 'name'], ['42', '42'], ['"a\/b"', 'a/b'], ['"é"', 'é'],
        ["'it\\'s'", "it's"], ['"a\": b"', 'a": b'], ['"x y"?', 'x y'], ['[key: string]', null],
    ]);

    test('reads a bare key only where TypeScript reads it as written', function (string $printed, ?string $key) {
        expect(PrintedKey::decode($printed))->toBe($key);
    })->with([
        'a dollar sign' => ['$', '$'],
        'a non-ASCII identifier' => ['é', 'é'],
        'zero' => ['0', '0'],
        'a space before the optional marker' => ['a ?', 'a'],
        'a leading zero, an octal literal' => ['01', null],
        'a negative int, which is no property name' => ['-1', null],
        'an int past PHP_INT_MAX' => ['9223372036854775808', null],
        'a decimal fraction' => ['1.5', '1.5'],
        'an optional decimal fraction' => ['1.5?', '1.5'],
        'a decimal fraction below one' => ['0.25', '0.25'],
        'a decimal with a leading zero, which TypeScript rejects' => ['01.5', null],
        'a decimal with a trailing zero, which names 1.5' => ['1.50', null],
        'a whole decimal, which names 1' => ['1.0', null],
        'an exponent, which names 1000' => ['1e3', null],
        'a negative decimal, which is no property name' => ['-1.5', null],
        'a decimal past what a double holds' => ['0.30000000000000001', null],
        'a dashed name' => ['a-b', null],
        'a name with a newline after it, which TypeScript names without' => ["a\n", null],
        'a modifier' => ['readonly a', null],
        'a number index signature' => ['[key: number]', null],
        'a template index signature' => ['[key: `${string}_tag`]', null],
        'empty' => ['', null],
    ]);

    test('reads a quoted key through the escapes the printers write, and nothing past its closing quote', function (string $printed, ?string $key) {
        expect(PrintedKey::decode($printed))->toBe($key);
    })->with([
        'a JSON unicode escape' => ['"é"', 'é'],
        'a surrogate pair' => ['"😀"', '😀'],
        'a single-quoted unicode escape' => ["'\\u00e9'", 'é'],
        'a single-quoted double quote, bare' => ["'say \"hi\"'", 'say "hi"'],
        'a single-quoted double quote, escaped' => ["'say \\\"hi\\\"'", 'say "hi"'],
        'a double-quoted escaped single quote' => ['"it\\\'s"', "it's"],
        'escaped control characters' => ["'a\\nb\\rc\\td'", "a\nb\rc\td"],
        'an escaped backslash before the closing quote' => ["'a\\\\'", 'a\\'],
        'a raw control character toJsLiteral() leaves' => ["'\x01'", "\x01"],
        'an empty key' => ['""', ''],
        'a quote that closes early' => ["'a'b'", null],
        'two quoted keys' => ['"a" "b"', null],
        'mismatched quotes' => ["'a\"", null],
        'an unterminated key' => ['"a', null],
        'a newline past the closing quote' => ["\"a\"\n", null],
        'an escape JSON does not read' => ['"\x41"', null],
        'a lone surrogate' => ['"\ud83d"', null],
    ]);

    test('reads back every key validJsObjectKey() prints', function (string $key) {
        expect(PrintedKey::decode(JsEmitter::validJsObjectKey($key)))->toBe($key);
    })->with(['a/b', 'é', "it's", 'a": b', '1', 'x y', "line\nbreak", '😀']);

    test('prints a key ending in a newline quoted, and reads it back with and without its marker', function () {
        $printed = JsEmitter::validJsObjectKey("a\n");

        expect($printed)->toBe('"a\\n"')
            ->and(PrintedKey::decode($printed))->toBe("a\n")
            ->and(PrintedKey::decode($printed.'?'))->toBe("a\n");
    });

    test('reads back every generated key validJsObjectKey() and toJsLiteral() print, as an optional key too', function () {
        $escaped = 0;

        foreach (printedKeyCases(98, 500) as $case => $key) {
            foreach ([JsEmitter::validJsObjectKey($key), JsEmitter::toJsLiteral($key)] as $printed) {
                $escaped += (int) str_contains($printed, '\\');

                expect(PrintedKey::decode($printed))->toBe($key, "case {$case}: {$printed}")
                    ->and(PrintedKey::decode($printed.'?'))->toBe($key, "case {$case}: {$printed}?");
            }
        }

        expect($escaped)->toBeGreaterThan(400);
    });
});

test('names() says whether a printed key names a key', function (string $printed, string $key, bool $names) {
    expect(PrintedKey::names($printed, $key))->toBe($names);
})->with([
    'a decoded name' => ["'it\\'s'", "it's", true],
    'the raw spelling' => ["'it\\'s'", "it\\'s", false],
    'an optional key' => ['"a\/b"?', 'a/b', true],
    'another key' => ['a', 'b', false],
    'an index signature, which names no key' => ['[key: string]', '[key: string]', false],
]);

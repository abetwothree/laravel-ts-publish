<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\LiteralArm;

test('reads the scalar a literal arm spells', function (string $arm, string|int|float|bool $value) {
    expect(LiteralArm::value($arm))->toBe($value)
        ->and(LiteralArm::isLiteral($arm))->toBeTrue();
})->with([
    'a single-quoted string' => ["'image'", 'image'],
    'a double-quoted string' => ['"image"', 'image'],
    'an escaped quote' => ["'it\\'s'", "it's"],
    'an escaped slash' => ['"a\/b"', 'a/b'],
    'the empty string' => ["''", ''],
    'zero' => ['0', 0],
    'a negative integer' => ['-1', -1],
    'a fraction' => ['1.5', 1.5],
    'true' => ['true', true],
    'false' => ['false', false],
]);

test('reads no scalar from any other arm', function (string $arm) {
    expect(LiteralArm::value($arm))->toBeNull()
        ->and(LiteralArm::isLiteral($arm))->toBeFalse();
})->with([
    'a keyword' => ['string'],
    'an alias' => ['MediaTypeType'],
    'null' => ['null'],
    'never[]' => ['never[]'],
    'a gap' => ['$gap1'],
    'a template literal' => ['`x${string}`'],
    'a template literal with no placeholder' => ['`x`'],
    'an object' => ['{ a: 1 }'],
    'two string literals' => ["'a' | 'b'"],
    'an unclosed string' => ["'image"],
    'a hexadecimal number' => ['0x10'],
    'a number with a leading zero' => ['01'],
    'a number with a separator' => ['1_000'],
]);

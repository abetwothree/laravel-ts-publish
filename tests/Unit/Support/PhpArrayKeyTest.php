<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;

// PHP itself is the oracle: the key an array literal stores is what storedInt() must answer.
it('answers the int PHP stores a string key as, and null for a key PHP keeps a string', function (string $key, ?int $stored) {
    $phpStores = array_key_first([$key => true]);

    expect(PhpArrayKey::storedInt($key))->toBe($stored)
        ->and(PhpArrayKey::storedInt($key))->toBe(is_int($phpStores) ? $phpStores : null);
})->with([
    'a decimal' => ['6', 6],
    'zero' => ['0', 0],
    'a negative decimal' => ['-1', -1],
    'the largest int' => [(string) PHP_INT_MAX, PHP_INT_MAX],
    'a leading zero' => ['06', null],
    'negative zero' => ['-0', null],
    'a decimal fraction' => ['1.5', null],
    'an exponent' => ['1e3', null],
    'a leading space' => [' 1', null],
    'a plus sign' => ['+1', null],
    'past the largest int' => ['9223372036854775808', null],
    'a name' => ['id', null],
    'empty' => ['', null],
]);

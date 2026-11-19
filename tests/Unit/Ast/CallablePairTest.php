<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallablePair;
use PhpParser\Node\Expr\Array_;
use PhpParser\PrettyPrinter\Standard;

/**
 * CallablePair's reading of an array literal: the pair printed as `holder, method`, or the `false` or `null` it
 * returns.
 */
function callablePairOf(string $array): string|false|null
{
    $value = runtimeClassCall($array);
    assert($value instanceof Array_);
    $pair = CallablePair::of($value);

    if (! $pair instanceof CallablePair) {
        return $pair;
    }

    $printer = new Standard;

    return $printer->prettyPrintExpr($pair->holder).', '.$printer->prettyPrintExpr($pair->method);
}

it('reads a callable pair by its keys 0 and 1, as PHP does', function (string $array, string|false|null $pair) {
    expect(callablePairOf($array))->toBe($pair);
})->with([
    'a list' => ["[\$this, 'm']", "\$this, 'm'"],
    'keys out of order' => ["[1 => 'm', 0 => \$this]", "\$this, 'm'"],
    'a method first' => ["['m', \$this]", "'m', \$this"],
    'keys written as numeric strings' => ["['0' => \$this, '1' => 'm']", "\$this, 'm'"],
    'a key written twice, its last value kept' => ["[\$a, 0 => \$this, 'm']", "\$this, 'm'"],
    'a key past 1' => ["[0 => \$a, 2 => 'm']", null],
    'a third item' => ["[\$a, 'm', 1]", null],
    'an implicit key after 1' => ["[1 => 'm', \$a]", null],
    'a numeric string PHP keeps as a string' => ["['01' => \$a, 1 => 'm']", null],
    'one item' => ['[$this]', null],
    'no items' => ['[]', null],
    'a computed key' => ["[\$k => \$a, 1 => 'm']", false],
    'a bool key, which PHP casts to an int' => ["[0 => \$a, true => 'm']", false],
    'an unpacked item' => ['[...$x]', false],
    'both slots, then an unpacked item' => ["[\$this, 'm', ...\$x]", false],
    'a by-reference item, which PHP calls as it calls a copy' => ["[&\$x, 'm']", "\$x, 'm'"],
    'a key past 1 beside a computed key, which no key removes' => ["[\$k => \$a, 2 => 'm']", null],
    'a string key beside an unpacked item' => ["['mode' => \$a, ...\$x]", null],
]);

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;

/**
 * Parse one PHP expression, names resolved as the analyzer resolves them.
 */
function readPathExpr(string $php): Expr
{
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];
    assert($statement instanceof Expression);

    return $statement->expr;
}

/**
 * The read path one PHP expression spells.
 */
function readPathOf(string $php): ?ReadPath
{
    return ReadPath::of(readPathExpr($php));
}

it('reads a variable or a chain of named property reads as its root and segments', function (
    string $php,
    string $root,
    array $segments,
    array $nullsafe,
) {
    $path = readPathOf($php);

    expect($path?->root)->toBe($root)
        ->and($path?->segments)->toBe($segments)
        ->and($path?->nullsafe)->toBe($nullsafe);
})->with([
    'a variable' => ['$x', 'x', [], []],
    'a property of $this' => ['$this->a', 'this', ['a'], [false]],
    'a chain of two' => ['$this->a->b', 'this', ['a', 'b'], [false, false]],
    'a nullsafe read' => ['$this->a?->b', 'this', ['a', 'b'], [false, true]],
]);

it('reads no path from a call, a variable variable, a computed name or a static property', function (string $php) {
    expect(readPathOf($php))->toBeNull();
})->with([
    'a method call' => ['$this->a()'],
    'a variable variable' => ['$$x'],
    'a computed property name' => ['$this->{$k}'],
    'a static property' => ['A::$b'],
    'a read through a call' => ['$this->a()->b'],
]);

it('is the same read only where root, segments and nullsafe flags all match', function (
    string $left,
    string $right,
    bool $same,
) {
    expect(readPathOf($left)?->same(readPathOf($right)))->toBe($same)
        ->and(ReadPath::sameRead(readPathExpr($left), readPathExpr($right)))->toBe($same);
})->with([
    'one chain twice' => ['$this->a->b', '$this->a->b', true],
    'a nullsafe step against a plain one' => ['$this->a->b', '$this->a?->b', false],
    'another segment' => ['$this->a->b', '$this->a->c', false],
    'another root' => ['$this->a', '$that->a', false],
    'a longer chain' => ['$this->a', '$this->a->b', false],
]);

it('is never the same read as an expression that spells no path', function () {
    expect(ReadPath::sameRead(readPathExpr('$this->a()'), readPathExpr('$this->a()')))->toBeFalse()
        ->and(ReadPath::sameRead(readPathExpr('$$x'), readPathExpr('$$x')))->toBeFalse();
});

it('gives the one member a path reads on another', function (string $path, string $base, ?string $member) {
    expect(readPathOf($path)?->memberOf(readPathOf($base)))->toBe($member);
})->with([
    'a value read' => ['$this->status->value', '$this->status', 'value'],
    'a nullsafe name read' => ['$this->status?->name', '$this->status', 'name'],
    'the base itself' => ['$this->status', '$this->status', null],
    'two members deeper' => ['$this->status->value->x', '$this->status', null],
    'another base' => ['$this->priority->value', '$this->status', null],
]);

it('drops its last segment for its parent, and has none at its root', function () {
    expect(readPathOf('$this->a?->b')?->parent()?->same(readPathOf('$this->a')))->toBeTrue()
        ->and(readPathOf('$this->a')?->parent()?->same(readPathOf('$this')))->toBeTrue()
        ->and(readPathOf('$this')?->parent())->toBeNull();
});

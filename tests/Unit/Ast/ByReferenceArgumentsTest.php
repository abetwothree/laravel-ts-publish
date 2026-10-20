<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceDefaultsParent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceDefaultsSubject;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\PrettyPrinter\Standard;

/**
 * The arguments of() reports for the one call a statement makes, every argument asked about, printed as PHP.
 *
 * @param  ReflectionClass<object>|null  $subject
 * @param  ReflectionClass<object>|null  $declaration
 * @return list<string>
 */
function byReferenceArgumentsOf(string $statement, ?ReflectionClass $subject = null, ?ReflectionClass $declaration = null): array
{
    $stmt = new AstParser()->parseSource('<?php '.$statement)[0] ?? null;
    $call = $stmt instanceof Expression ? $stmt->expr : null;

    if (! $call instanceof CallLike) {
        throw new LogicException("Not a call: {$statement}");
    }

    $passed = $call->isFirstClassCallable() ? [] : $call->getArgs();
    $printer = new Standard;

    return array_map(
        fn ($argument): string => $printer->prettyPrintExpr($argument),
        resolve(ByReferenceArguments::class)->of($call, $passed, $subject, $declaration ?? $subject),
    );
}

it('reports the arguments a call passes to a by-reference parameter of the callee it names', function (string $statement, array $arguments) {
    expect(byReferenceArgumentsOf($statement, new ReflectionClass(ByReferenceDefaultsSubject::class)))->toBe($arguments);
})->with([
    'a positional argument' => ['preg_match("/x/", $s, $m);', ['$m']],
    'a named argument, in any order' => ['preg_match(matches: $m, pattern: "/x/", subject: $s);', ['$m']],
    // PHP collects every surplus positional argument into the trailing variadic.
    'a variadic' => ['sscanf("1 2", "%d %d", $first, $second);', ['$first', '$second']],
    'a pointer function, which only moves the pointer' => ['end($a);', []],
    'a by-value function' => ['strlen($s);', []],
    'a function nobody declares' => ['missing_function($a);', []],
    'a method of $this' => ['$this->fill($a);', ['$a']],
    'a self:: method' => ['self::stash($a);', ['$a']],
    'a static:: method' => ['static::stash($a);', ['$a']],
    'a named class constructor' => ['new '.ByReferenceDefaultsParent::class.'($a);', ['$a']],
    // PHP routes a method it cannot find to __call() or __callStatic(), which take their arguments by value.
    'a method the class does not declare' => ['self::absent($a);', []],
    'a receiver it cannot name' => ['$other->push($a);', []],
    'a first-class callable' => ['preg_match(...);', []],
]);

it('binds parent:: to the declaration\'s parent, not the subject\'s', function () {
    $subject = new ReflectionClass(ByReferenceDefaultsSubject::class);

    expect(byReferenceArgumentsOf('parent::append($a);', $subject))->toBe(['$a'])
        ->and(byReferenceArgumentsOf('parent::append($a);', $subject, new ReflectionClass(ByReferenceDefaultsParent::class)))
        ->toBe([]);
});

it('names no $this, self or static callee without a subject', function (string $statement) {
    expect(byReferenceArgumentsOf($statement))->toBe([]);
})->with([
    '$this' => ['$this->fill($a);'],
    'self' => ['self::stash($a);'],
    'parent' => ['parent::append($a);'],
]);

it('reads only the arguments it is asked about, by their position', function () {
    $call = new AstParser()->parseSource('<?php preg_match("/x/", $s, $m);')[0]->expr;
    $arguments = $call->getArgs();

    expect(resolve(ByReferenceArguments::class)->of($call, [0 => $arguments[0], 1 => $arguments[1]], null, null))->toBe([])
        ->and(resolve(ByReferenceArguments::class)->of($call, [2 => $arguments[2]], null, null))
        ->toHaveCount(1)
        ->sequence(fn ($argument) => $argument->toBeInstanceOf(Variable::class));
});

it('reports what a built-in leaves in the variable it takes by reference', function (string $statement, ByReferenceEffect $effect) {
    $call = new AstParser()->parseSource('<?php namespace App; '.$statement)[0]->stmts[0]->expr;
    $argument = array_find($call->getArgs(), fn ($arg): bool => $arg->value instanceof Variable && $arg->value->name === 'x')->value;

    expect(resolve(ByReferenceArguments::class)->effect($call, $argument))->toBe($effect);
})->with([
    'ksort()' => ['ksort($x);', ByReferenceEffect::KeepsAny],
    'ksort() comparing keys as numbers' => ['ksort($x, SORT_NUMERIC);', ByReferenceEffect::KeepsAny],
    'ksort() comparing keys as strings, which puts 10 before 2' => ['ksort($x, SORT_STRING);', ByReferenceEffect::KeepsObject],
    'ksort() with flags it cannot read' => ['ksort($x, $flags);', ByReferenceEffect::KeepsObject],
    'uksort(), whose comparator can put a list\'s keys out of order' => ['uksort($x, "f");', ByReferenceEffect::KeepsObject],
    'array_pop()' => ['array_pop($x);', ByReferenceEffect::KeepsList],
    'array_shift()' => ['array_shift($x);', ByReferenceEffect::KeepsList],
    'array_splice()' => ['array_splice($x, 1, 1, ["z"]);', ByReferenceEffect::Appends],
    'sort(), named' => ['sort(array: $x);', ByReferenceEffect::KeepsList],
    'asort()' => ['asort($x);', ByReferenceEffect::KeepsObject],
    'str_replace()\'s count' => ['str_replace("a", "b", "c", $x);', ByReferenceEffect::KeepsNumber],
    'preg_replace_callback()\'s count, named' => ['preg_replace_callback("/a/", "f", "c", count: $x);', ByReferenceEffect::KeepsNumber],
    'array_push()' => ['array_push($x, 1);', ByReferenceEffect::Appends],
    'array_walk()' => ['array_walk($x, "f");', ByReferenceEffect::WritesElements],
    'array_walk_recursive()' => ['array_walk_recursive($x, "f");', ByReferenceEffect::WritesLeaves],
    'preg_match()\'s matches' => ['preg_match("/a/", "b", $x);', ByReferenceEffect::Replaces],
    'settype()' => ['settype($x, "int");', ByReferenceEffect::Replaces],
    'a by-value argument of a known built-in' => ['str_replace($x, "b", "c", $n);', ByReferenceEffect::Replaces],
    'a function nobody declares' => ['missing_function($x);', ByReferenceEffect::Replaces],
    'a method' => ['$this->fill($x);', ByReferenceEffect::Replaces],
]);

it('reports what a built-in leaves in a key of a variable it takes by reference', function () {
    $call = new AstParser()->parseSource('<?php sort($x["k"]["j"]);')[0]->expr;

    expect(resolve(ByReferenceArguments::class)->effect($call, $call->getArgs()[0]->value))->toBe(ByReferenceEffect::KeepsList);
});

it('records the class whose declaration says which parameters take a reference', function (string $statement, string $class) {
    DependencyRecorder::start();

    try {
        byReferenceArgumentsOf($statement, new ReflectionClass(ByReferenceDefaultsSubject::class));
        $paths = DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }

    expect($paths)->toContain((string) new ReflectionClass($class)->getFileName());
})->with([
    'a named class constructor' => ['new '.ByReferenceDefaultsParent::class.'($a);', ByReferenceDefaultsParent::class],
    'a method of $this' => ['$this->fill($a);', ByReferenceDefaultsSubject::class],
    'a self:: method' => ['self::stash($a);', ByReferenceDefaultsSubject::class],
]);

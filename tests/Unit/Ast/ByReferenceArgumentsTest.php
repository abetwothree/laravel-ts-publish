<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceDefaultsParent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceSelfBase;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceSelfChild;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\PrettyPrinter\Standard;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The arguments of() reports for the one call a statement makes, every argument asked about, printed as PHP.
 *
 * @param  ReflectionClass<object>|null  $runtime
 * @param  ReflectionClass<object>|null  $declaring  the runtime class when not given
 * @return list<string>
 */
function byReferenceArgumentsOf(string $statement, ?ReflectionClass $runtime = null, ?ReflectionClass $declaring = null): array
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
        resolve(ByReferenceArguments::class)->of($call, $passed, $declaring ?? $runtime, $runtime),
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

it('binds parent:: to the declaring class\'s parent, not the runtime class\'s', function () {
    $subject = new ReflectionClass(ByReferenceDefaultsSubject::class);

    expect(byReferenceArgumentsOf('parent::append($a);', $subject))->toBe(['$a'])
        ->and(byReferenceArgumentsOf('parent::append($a);', $subject, new ReflectionClass(ByReferenceDefaultsParent::class)))
        ->toBe([]);
});

it('binds self in a scanned body to the class that declares it, and to the using class in a trait', function () {
    $child = new ReflectionClass(ByReferenceSelfChild::class);
    $defaults = resolve(SubjectPropertyTypeResolver::class);
    $sent = new ByReferenceSelfChild;
    $sent->run();
    $sent->stashAll();

    expect(byReferenceArgumentsOf('self::stash($a);', $child, new ReflectionClass(ByReferenceSelfBase::class)))->toBe(['$a'])
        ->and(byReferenceArgumentsOf('self::stash($a);', $child))->toBe([])
        ->and(byReferenceArgumentsOf('static::stash($a);', $child, new ReflectionClass(ByReferenceSelfBase::class)))->toBe([])
        ->and($defaults->holdsDefault($child, 'stashed'))->toBeFalse()
        ->and($defaults->holdsDefault($child, 'traitStashed'))->toBeFalse()
        ->and([$sent->stashed, $sent->traitStashed])->toBe([['a', 1], ['a', 1]]);
});

it('reads a $this-> callee on the declaring class\'s own private method, which PHP calls over the runtime class\'s', function () {
    $child = new ReflectionClass(ByReferenceSelfChild::class);
    $sent = new ByReferenceSelfChild;
    $sent->runFill();

    expect(byReferenceArgumentsOf('$this->fill($a);', $child, new ReflectionClass(ByReferenceSelfBase::class)))->toBe(['$a'])
        ->and(byReferenceArgumentsOf('$this->fill($a);', $child))->toBe([])
        ->and(resolve(SubjectPropertyTypeResolver::class)->holdsDefault($child, 'filled'))->toBeFalse()
        ->and($sent->filled)->toBe(['a', 1]);
});

it('names no $this, self or static callee without a class', function (string $statement) {
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
    'preg_match()\'s matches' => ['preg_match("/(a)(b)?/", "b", $x);', ByReferenceEffect::WritesMatches],
    'preg_match()\'s matches, named' => ['preg_match(subject: "b", matches: $x, pattern: "/a/");', ByReferenceEffect::WritesMatches],
    'preg_match()\'s matches past a lookbehind' => ['preg_match("/(?<=a)(?<!c)b/", "ab", $x);', ByReferenceEffect::WritesMatches],
    'preg_match()\'s matches with a flag' => ['preg_match("/a/", "b", $x, PREG_OFFSET_CAPTURE);', ByReferenceEffect::WritesMatches],
    'preg_match()\'s matches with a zero flag and an offset' => ['preg_match("/a/", "b", $x, 0, 1);', ByReferenceEffect::WritesMatches],
    'preg_match()\'s matches over a named group' => ['preg_match("/(?<n>a)/", "b", $x);', ByReferenceEffect::WritesMatches],
    'preg_match()\'s matches with flags it cannot read' => ['preg_match("/a/", "b", $x, $flags);', ByReferenceEffect::Replaces],
    'preg_match()\'s matches over a computed pattern' => ['preg_match($p, "b", $x);', ByReferenceEffect::Replaces],
    'preg_match()\'s matches over a pattern PCRE rejects' => ['preg_match("/(a/", "b", $x);', ByReferenceEffect::Replaces],
    'preg_match_all()\'s matches' => ['preg_match_all("/(a)/", "b", $x);', ByReferenceEffect::WritesMatches],
    'settype()' => ['settype($x, "int");', ByReferenceEffect::Replaces],
    'a by-value argument of a known built-in' => ['str_replace($x, "b", "c", $n);', ByReferenceEffect::Replaces],
    'a function nobody declares' => ['missing_function($x);', ByReferenceEffect::Replaces],
    'a method' => ['$this->fill($x);', ByReferenceEffect::Replaces],
]);

/**
 * The type matchesType() gives the matches of the one call a statement makes.
 */
function pregMatchesType(string $statement): ?string
{
    $call = new AstParser()->parseSource('<?php namespace App; '.$statement)[0]->stmts[0]->expr;

    return resolve(ByReferenceArguments::class)->matchesType($call);
}

// PHP drops trailing groups that did not take part and writes `[]` on no match, unless PREG_UNMATCHED_AS_NULL keeps
// each group as null; preg_match_all() keys every group in pattern order, and lists each match's groups in set order.
it('types preg_match()\'s and preg_match_all()\'s matches from the pattern\'s groups and the flags', function (string $statement, ?string $type) {
    expect(pregMatchesType($statement))->toBe($type);
})->with([
    'no flag' => ['preg_match("/(a)(b)?/", "b", $x);', 'string[]'],
    'no group' => ['preg_match("/a/", "b", $x);', 'string[]'],
    'a zero flag and an offset' => ['preg_match("/(a)/", "b", $x, 0, 1);', 'string[]'],
    'named arguments' => ['preg_match(subject: "b", matches: $x, pattern: "/(a)/", flags: PREG_OFFSET_CAPTURE);', '[string, number][]'],
    'a lookbehind and a lookahead' => ['preg_match("/(?<=a)(?<!c)b(?=d)(?!e)/", "ab", $x);', 'string[]'],
    'a named group' => ['preg_match("/(?<n>a)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a P-named group' => ['preg_match("/(?P<n>a)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a quote-named group' => ['preg_match("/(?\'n\'a)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a named group past an unnamed one' => ['preg_match("/(a)(?<n>b)/", "b", $x);', '{ "0": string; "1"?: string; n?: string; "2"?: string } | never[]'],
    'groups past escapes, a class and a non-capturing group' => [
        'preg_match("/\\\\(\\((?:x)[(?<no>)](?<n>a)/", "b", $x);',
        '{ "0": string; n?: string; "1"?: string } | never[]',
    ],
    'a group PCRE quotes' => ['preg_match("/\\Q(?<q>\\E(?<n>a)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a group the x flag comments out' => ['preg_match("/(?<n>a) # (?<c>b)\\n/x", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'branch reset groups' => ['preg_match("/(?|(?<n>a)|(?<n>b)(c))/", "b", $x);', '{ "0": string; n?: string; "1"?: string; "2"?: string } | never[]'],
    'the n flag, which captures only named groups' => ['preg_match("/(a)(?<n>b)/n", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a leading verb' => ['preg_match("/(*UTF8)(?<n>a)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'bracket delimiters' => ['preg_match("{(?<n>a)}i", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'PREG_OFFSET_CAPTURE' => ['preg_match("/(a)/", "b", $x, PREG_OFFSET_CAPTURE);', '[string, number][]'],
    'PREG_UNMATCHED_AS_NULL' => ['preg_match("/(a)(b)?/", "b", $x, PREG_UNMATCHED_AS_NULL);', '(string | null)[]'],
    'PREG_UNMATCHED_AS_NULL with no group' => ['preg_match("/a/", "b", $x, PREG_UNMATCHED_AS_NULL);', 'string[]'],
    'both flags' => ['preg_match("/(a)/", "b", $x, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);', '[string | null, number][]'],
    'both flags as an int' => ['preg_match("/(a)/", "b", $x, 768);', '[string | null, number][]'],
    'named groups unmatched as null' => [
        'preg_match("/(?<first>B)(?<second>x)?/", "b", $x, PREG_UNMATCHED_AS_NULL);',
        '{ "0": string; first: string | null; "1": string | null; second: string | null; "2": string | null } | never[]',
    ],
    'a named group with offsets' => [
        'preg_match("/(?<first>B)/", "b", $x, PREG_OFFSET_CAPTURE);',
        '{ "0": [string, number]; first?: [string, number]; "1"?: [string, number] } | never[]',
    ],
    'preg_match_all()' => ['preg_match_all("/(B)/", "b", $x);', 'string[][]'],
    'preg_match_all() in pattern order' => ['preg_match_all("/(B)/", "b", $x, PREG_PATTERN_ORDER);', 'string[][]'],
    'preg_match_all() unmatched as null' => ['preg_match_all("/(B)/", "b", $x, PREG_UNMATCHED_AS_NULL);', '(string | null)[][]'],
    'preg_match_all() with offsets' => ['preg_match_all("/(B)/", "b", $x, PREG_OFFSET_CAPTURE);', '[string, number][][]'],
    'preg_match_all() of named groups' => [
        'preg_match_all("/(?<f>B)(x)?/", "b", $x);',
        '{ "0": string[]; f: string[]; "1": string[]; "2": string[] }',
    ],
    'preg_match_all() of named groups unmatched as null' => [
        'preg_match_all("/(?<f>B)/", "b", $x, PREG_PATTERN_ORDER | PREG_UNMATCHED_AS_NULL);',
        '{ "0": string[]; f: (string | null)[]; "1": (string | null)[] }',
    ],
    'preg_match_all() in set order' => ['preg_match_all("/(B)/", "b", $x, PREG_SET_ORDER);', 'string[][]'],
    'preg_match_all() of named groups in set order' => [
        'preg_match_all("/(?<f>B)(x)?/", "b", $x, PREG_SET_ORDER);',
        '{ "0": string; f?: string; "1"?: string; "2"?: string }[]',
    ],
    'a computed pattern' => ['preg_match($p, "b", $x);', null],
    'a pattern PCRE rejects' => ['preg_match("/(a/", "b", $x);', null],
    'an unknown modifier' => ['preg_match("/a/b/", "b", $x);', null],
    'flags in a variable' => ['preg_match("/a/", "b", $x, $flags);', null],
    'a flag preg_match() rejects' => ['preg_match("/a/", "b", $x, PREG_SET_ORDER);', null],
    'both orders' => ['preg_match_all("/a/", "b", $x, PREG_SET_ORDER | PREG_PATTERN_ORDER);', null],
    'another function' => ['preg_replace("/a/", "b", "c", -1, $x);', null],
]);

/**
 * Seeded PCRE patterns with a subject each matches: escapes, classes holding parentheses, groups named three ways,
 * non-capturing groups, lookarounds, `\Q…\E`, branch resets and comments, one `\Q` or comment left open at the end,
 * under random delimiters and flags.
 *
 * @return list<array{pattern: string, subject: string}>
 */
function pregPatternCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $patterns = [];

    while (count($patterns) < $cases) {
        $names = 0;
        $flags = implode('', array_filter(['i', 'x', 'n', 'U', 's'], fn (): bool => $random->getInt(0, 3) === 0));
        $extended = str_contains($flags, 'x');
        $atom = function (int $depth) use (&$atom, $random, &$names, $extended): array {
            $inner = fn (): array => $depth > 2 ? ['a', 'a'] : $atom($depth + 1);

            return match ($random->getInt($depth > 2 ? 0 : 0, $depth > 2 ? 5 : 16)) {
                0 => ['b', 'b'],
                1 => ['\\(', '('],
                2 => ['[()]', ')'],
                3 => ['[^)]', 'q'],
                4 => ['\\Q(?<z>\\E', '(?<z>'],
                5 => [$extended ? " #(?<c>x)\n" : '(?#(x)', ''],
                6 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['('.$p.')', $s];
                })(),
                7 => (function () use ($inner, &$names): array {
                    [$p, $s] = $inner();

                    return ['(?<n'.++$names.'>'.$p.')', $s];
                })(),
                8 => (function () use ($inner, &$names): array {
                    [$p, $s] = $inner();

                    return ['(?P<n'.++$names.'>'.$p.')', $s];
                })(),
                9 => (function () use ($inner, &$names): array {
                    [$p, $s] = $inner();

                    return ["(?'n".++$names."'".$p.')', $s];
                })(),
                10 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['(?:'.$p.')', $s];
                })(),
                11 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['(?='.$p.')'.$p, $s];
                })(),
                12 => ['(?!z)', ''],
                13 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['('.$p.')?', $s];
                })(),
                14 => (function () use ($inner): array {
                    [$p, $s] = $inner();
                    [$q] = $inner();

                    return ['(?|('.$p.')|('.$q.')(c))', $s];
                })(),
                15 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['(?:'.$p.'|(z))', $s];
                })(),
                default => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['('.$p.')+', $s];
                })(),
            };
        };
        $pattern = '';
        $subject = '';

        foreach (range(1, $random->getInt(1, 4)) as $ignored) {
            [$p, $s] = $atom(0);
            $pattern .= $p;
            $subject .= $s;
        }

        // A `\Q` or an `x`-flag comment left open runs to the end of the pattern.
        [$pattern, $subject] = match ($random->getInt(0, 3)) {
            0 => [$pattern.'\\Q(?<o>', $subject.'(?<o>'],
            1 => [$pattern.($extended ? ' #(?<o>x)' : ''), $subject],
            default => [$pattern, $subject],
        };

        [$open, $close] = [['/', '/'], ['~', '~'], ['%', '%'], ['{', '}'], ['@', '@']][$random->getInt(0, 4)];
        $pattern = $open.$pattern.$close.$flags;

        // A branch reset names a group twice only when each branch names it alike, so PCRE rejects the rest.
        if (@preg_match($pattern, $subject) === 1) {
            $patterns[] = ['pattern' => $pattern, 'subject' => $subject];
        }
    }

    return $patterns;
}

/**
 * The keys a published matches type names, in order, or null for a list.
 *
 * @return list<string>|null
 */
function pregMatchesKeys(string $type): ?array
{
    if (! str_starts_with($type, '{')) {
        return null;
    }

    preg_match_all('/(?:\{ |; )("(?:[^"\\\\]|\\\\.)*"|[\w$]+)\??:/', $type, $keys);

    return array_map(fn (string $key): string => (string) (str_starts_with($key, '"') ? json_decode($key) : $key), $keys[1]);
}

// PHP's own preg_match() keys every group of a pattern under PREG_UNMATCHED_AS_NULL, so the published keys are those.
it('names exactly the groups PHP\'s own preg_match() keys, for any pattern', function () {
    foreach (pregPatternCases(8, 300) as $case) {
        preg_match($case['pattern'], $case['subject'], $sent, PREG_UNMATCHED_AS_NULL);
        $source = var_export($case['pattern'], true);
        $nullable = pregMatchesType("preg_match({$source}, \$s, \$m, PREG_UNMATCHED_AS_NULL);");
        $bare = pregMatchesType("preg_match({$source}, \$s, \$m);");
        $keys = array_map(strval(...), array_keys($sent));
        $listed = array_is_list($sent);

        expect(pregMatchesKeys((string) $nullable))->toBe($listed ? null : $keys, $case['pattern'])
            ->and(pregMatchesKeys((string) $bare))->toBe($listed ? null : $keys, $case['pattern'])
            ->and($listed ? [$nullable, $bare] : [])->toBe($listed ? [count($sent) > 1 ? '(string | null)[]' : 'string[]', 'string[]'] : []);
    }
});

it('tells a call that leaves every string key of the variable it takes by reference', function (string $statement, bool $keeps) {
    $call = new AstParser()->parseSource('<?php namespace App; '.$statement)[0]->stmts[0]->expr;
    $argument = array_find($call->getArgs(), fn ($arg): bool => $arg->value instanceof Variable && $arg->value->name === 'x')->value;

    expect(resolve(ByReferenceArguments::class)->keepsStringKeys($call, $argument))->toBe($keeps);
})->with([
    'ksort()' => ['ksort($x);', true],
    'asort()' => ['asort($x);', true],
    'array_push()' => ['array_push($x, 1);', true],
    'array_unshift()' => ['array_unshift($x, 1);', true],
    'array_splice(), which can remove any key' => ['array_splice($x, 1, 1, ["z"]);', false],
    'sort(), which renumbers every key' => ['sort($x);', false],
    'array_walk(), which rewrites each value' => ['array_walk($x, "f");', false],
    'array_walk_recursive(), which rewrites each leaf' => ['array_walk_recursive($x, "f");', false],
    'a method' => ['$this->fill($x);', false],
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

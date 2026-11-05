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
    'a failing verb leading the pattern' => ['preg_match("/(*F)|(B)/", "b", $x);', 'string[]'],
    'a failing verb leading a named pattern' => ['preg_match("/(*FAIL)|(?<n>B)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a raw control byte' => ['preg_match("/[\x01]?(B)/", "b", $x);', 'string[]'],
    'a raw control byte range' => ['preg_match("/[^\x01-\x7F](?<n>B)/", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'every probe delimiter' => ["preg_match(\"\x01[\x02\x03\x04~#%@!;](B)\x01\", \"b\", \$x);", null],
    'a CRLF newline convention with a trailing comment' => ['preg_match("/(*CRLF)(?<n>B)#(?<c>c)/x", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a CR newline convention with a trailing comment' => ['preg_match("/(*CR)(?<n>B)#(?<c>c)/x", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a NUL newline convention with a trailing comment' => ['preg_match("/(*NUL)(?<n>B)#(?<c>c)/x", "b", $x);', '{ "0": string; n?: string; "1"?: string } | never[]'],
    'a CRLF newline convention, unnamed' => ['preg_match("/(*CRLF)(B)#c/x", "b", $x);', 'string[]'],
    'a mark' => ['preg_match("/(*:mk)(B)/", "b", $x);', '{ "0": string; "1"?: string; MARK?: string } | never[]'],
    'a mark with offsets, which stays a string' => [
        'preg_match("/(*:mk)(B)/", "b", $x, PREG_OFFSET_CAPTURE);',
        '{ "0": [string, number]; "1"?: [string, number]; MARK?: string } | never[]',
    ],
    'a MARK verb beside a named group unmatched as null' => [
        'preg_match("/(*MARK:mk)(?<n>B)/", "b", $x, PREG_UNMATCHED_AS_NULL);',
        '{ "0": string; n: string | null; "1": string | null; MARK?: string } | never[]',
    ],
    'a named PRUNE' => ['preg_match("/(*PRUNE:p)B/", "b", $x);', '{ "0": string; MARK?: string } | never[]'],
    'a named THEN' => ['preg_match("/(*THEN:t)B/", "b", $x);', '{ "0": string; MARK?: string } | never[]'],
    'a named SKIP, which only looks a mark up' => ['preg_match("/B(*SKIP:s)/", "b", $x);', 'string[]'],
    'a named FAIL, which never passes its mark' => ['preg_match("/(*F:f)|B/", "b", $x);', 'string[]'],
    'a mark beside a group named MARK, which it overwrites' => ['preg_match("/(?<MARK>B)(*:m)/", "b", $x);', '{ "0": string; MARK?: string; "1"?: string } | never[]'],
    'a mark beside a group named MARK unmatched as null' => [
        'preg_match("/(?<MARK>B)(*:m)/", "b", $x, PREG_UNMATCHED_AS_NULL);',
        '{ "0": string; MARK: string | null; "1": string | null } | never[]',
    ],
    'a mark beside a group named MARK with offsets' => [
        'preg_match("/(?<MARK>B)(*:m)/", "b", $x, PREG_OFFSET_CAPTURE);',
        '{ "0": [string, number]; MARK?: [string, number] | string; "1"?: [string, number] } | never[]',
    ],
    'a group named MARK alone' => ['preg_match("/(?<MARK>B)/", "b", $x);', '{ "0": string; MARK?: string; "1"?: string } | never[]'],
    'an escaped parenthesis spelling a mark' => ['preg_match("/\\\\(*:(B)/", "b", $x);', 'string[]'],
    'a class spelling a mark' => ['preg_match("/[(*:]?(B)/", "b", $x);', 'string[]'],
    'a quoted run spelling a mark' => ['preg_match("/\\\\Q(*:\\\\E(B)/", "b", $x);', 'string[]'],
    'a comment spelling a mark' => ['preg_match("/(?#(*:)(B)/", "b", $x);', 'string[]'],
    'an x-flag comment spelling a mark' => ['preg_match("/(B)#(*:\\n/x", "b", $x);', 'string[]'],
    'a mark beside the text of one' => ['preg_match("/(*MARK:m)\\\\(*:(B)/", "b", $x);', '{ "0": string; "1"?: string; MARK?: string } | never[]'],
    'a mark in a branch' => ['preg_match("/(*:a)x|(B)/", "b", $x);', '{ "0": string; "1"?: string; MARK?: string } | never[]'],
    'a named COMMIT' => ['preg_match("/(*COMMIT:c)(B)/", "b", $x);', '{ "0": string; "1"?: string; MARK?: string } | never[]'],
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
    'preg_match_all() of a mark in set order' => [
        'preg_match_all("/(*:mk)(B)/", "b", $x, PREG_SET_ORDER);',
        '{ "0": string; "1"?: string; MARK?: string }[]',
    ],
    'preg_match_all() of a mark in pattern order, which PHP keys by the matches that passed it' => ['preg_match_all("/(*:mk)(B)/", "b", $x);', null],
    'a computed pattern' => ['preg_match($p, "b", $x);', null],
    'a pattern PCRE rejects' => ['preg_match("/(a/", "b", $x);', null],
    'an unknown modifier' => ['preg_match("/a/b/", "b", $x);', null],
    'flags in a variable' => ['preg_match("/a/", "b", $x, $flags);', null],
    'a flag preg_match() rejects' => ['preg_match("/a/", "b", $x, PREG_SET_ORDER);', null],
    'both orders' => ['preg_match_all("/a/", "b", $x, PREG_SET_ORDER | PREG_PATTERN_ORDER);', null],
    'another function' => ['preg_replace("/a/", "b", "c", -1, $x);', null],
]);

/**
 * Seeded PCRE patterns with a subject each matches: escapes, classes holding parentheses or raw bytes, three spellings
 * of a named group, lookarounds, `\Q…\E`, branch resets, comments, marks the match passes, an open `\Q` or comment at
 * the end, newline-convention verbs and a failing `(*F)|`, under random delimiters and flags.
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
        // A mark off the path the match takes, in a branch it skips or a group it may skip, sends no MARK.
        $atom = function (int $depth, bool $onPath = true) use (&$atom, $random, &$names, $extended): array {
            $inner = fn (bool $within = true): array => $depth > 2 ? ['a', 'a'] : $atom($depth + 1, $onPath && $within);

            return match ($random->getInt(0, $depth > 2 ? 10 : 20)) {
                0 => ['b', 'b'],
                1 => ['\\(', '('],
                2 => ['[()]', ')'],
                3 => ['[^)]', 'q'],
                4 => ['\\Q(?<z>\\E', '(?<z>'],
                5 => [$extended ? " #(?<c>x)\n" : '(?#(x)', ''],
                6 => ["[\x01]?", ''],
                7 => ["[^\x01-\x7F]+", 'é'],
                8 => $onPath ? [$random->getInt(0, 1) === 0 ? '(*:mk)' : '(*MARK:m)', ''] : ['(?!z)', ''],
                9 => ['(?!z)', ''],
                10 => ['(?<=b)', ''],
                11 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['('.$p.')', $s];
                })(),
                12 => (function () use ($inner, &$names): array {
                    [$p, $s] = $inner();

                    return ['(?<n'.++$names.'>'.$p.')', $s];
                })(),
                13 => (function () use ($inner, &$names): array {
                    [$p, $s] = $inner();

                    return ['(?P<n'.++$names.'>'.$p.')', $s];
                })(),
                14 => (function () use ($inner, &$names): array {
                    [$p, $s] = $inner();

                    return ["(?'n".++$names."'".$p.')', $s];
                })(),
                15 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['(?:'.$p.')', $s];
                })(),
                16 => (function () use ($inner): array {
                    [$p, $s] = $inner();

                    return ['(?='.$p.')'.$p, $s];
                })(),
                17 => (function () use ($inner): array {
                    [$p, $s] = $inner(false);

                    return ['('.$p.')?', $s];
                })(),
                18 => (function () use ($inner): array {
                    [$p, $s] = $inner();
                    [$q] = $inner(false);

                    return ['(?|('.$p.')|('.$q.')(c))', $s];
                })(),
                19 => (function () use ($inner): array {
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

        // A newline convention other than LF ends a comment elsewhere; a verb only PCRE reads leads the pattern.
        $verb = ['', '', '(*CR)', '(*CRLF)', '(*NUL)', '(*ANYCRLF)', '(*UTF8)'][$random->getInt(0, 6)];
        $failing = $random->getInt(0, 3) === 0 ? '(*F)|' : '';
        [$open, $close] = [['/', '/'], ['~', '~'], ['%', '%'], ['{', '}'], ['@', '@']][$random->getInt(0, 4)];
        $pattern = $open.$verb.$failing.$pattern.$close.$flags;

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
/**
 * Checks that the matches type of each seeded pattern names exactly the groups PHP's own preg_match() keys under
 * PREG_UNMATCHED_AS_NULL, and a MARK exactly where PHP sends one.
 */
function assertPregMatchesNamePhpKeys(int $seed): void
{
    foreach (pregPatternCases($seed, 300) as $case) {
        preg_match($case['pattern'], $case['subject'], $sent, PREG_UNMATCHED_AS_NULL);
        $source = var_export($case['pattern'], true);
        $nullable = pregMatchesType("preg_match({$source}, \$s, \$m, PREG_UNMATCHED_AS_NULL);");
        $bare = pregMatchesType("preg_match({$source}, \$s, \$m);");
        $groups = array_values(array_filter(array_map(strval(...), array_keys($sent)), fn (string $key): bool => $key !== 'MARK'));
        $marked = isset($sent['MARK']);
        $keyed = $marked || array_any($groups, fn (string $key): bool => ! is_numeric($key));
        $keys = [...$groups, ...($marked ? ['MARK'] : [])];

        expect(pregMatchesKeys((string) $nullable))->toBe($keyed ? $keys : null, $case['pattern'])
            ->and(pregMatchesKeys((string) $bare))->toBe($keyed ? $keys : null, $case['pattern'])
            ->and($keyed ? [] : [$nullable, $bare])->toBe($keyed ? [] : [count($groups) > 1 ? '(string | null)[]' : 'string[]', 'string[]'], $case['pattern']);
    }
}

// Each generated mark lies on the path the match takes, so PHP sends MARK exactly where the pattern holds one.
it('names exactly the groups PHP\'s own preg_match() keys, and the mark it sends, for any pattern', function () {
    assertPregMatchesNamePhpKeys(8);
});

it('names exactly the groups PHP\'s own preg_match() keys with PCRE\'s JIT off', function () {
    $jit = (string) ini_get('pcre.jit');
    ini_set('pcre.jit', '0');

    try {
        assertPregMatchesNamePhpKeys(9);
    } finally {
        ini_set('pcre.jit', $jit);
    }
});

// The JIT lets an empty match past `(*NOTEMPTY)` and ignores a match limit the interpreter enforces, so the probe
// drops both. Each setting reads its own pattern, since the types are cached by pattern.
it('reads a pattern\'s leading options alike with PCRE\'s JIT on and off', function (string $pattern, string $type) {
    $jit = (string) ini_get('pcre.jit');

    try {
        foreach (['1' => 'a', '0' => 'b'] as $setting => $letter) {
            ini_set('pcre.jit', (string) $setting);

            expect(pregMatchesType('preg_match("'.sprintf($pattern, $letter).'", "b", $x);'))->toBe($type, "pcre.jit={$setting}");
        }
    } finally {
        ini_set('pcre.jit', $jit);
    }
})->with([
    '(*NOTEMPTY)' => ['/(*NOTEMPTY)(?<n>%s)/', '{ "0": string; n?: string; "1"?: string } | never[]'],
    '(*NOTEMPTY_ATSTART)' => ['/(*NOTEMPTY_ATSTART)(?<n>%s)/', '{ "0": string; n?: string; "1"?: string } | never[]'],
    '(*LIMIT_MATCH=1)' => ['/(*LIMIT_MATCH=1)(?<n>%s)/', '{ "0": string; n?: string; "1"?: string } | never[]'],
    '(*LIMIT_DEPTH=1) beside (*UTF8)' => ['/(*UTF8)(*LIMIT_DEPTH=1)(?<n>%s)/', '{ "0": string; n?: string; "1"?: string } | never[]'],
    '(*LIMIT_HEAP=0)' => ['/(*LIMIT_HEAP=0)(?<n>%s)/', '{ "0": string; n?: string; "1"?: string } | never[]'],
]);

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

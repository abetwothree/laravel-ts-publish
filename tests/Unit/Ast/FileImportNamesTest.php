<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

test('declaredTokens() gives each own name\'s token the next slot, an unread one\'s too, and another name\'s none', function () {
    $tokens = FileImportNames::declaredTokens("{ kind: 'User'; r: User | User; p: Post }", [null, null, User::class], ['Post']);

    expect(array_map(fn (array $token): array => [$token['token'], $token['slot'], $token['own'], $token['read']], $tokens))->toBe([
        ['User', null, true, false],
        ['User', null, true, true],
        ['User', User::class, true, true],
        ['Post', null, false, true],
    ]);
});

test('declaredTokens() reads no token where the slots hold no class and no other name is asked for', function () {
    expect(FileImportNames::declaredTokens('User | Post', []))->toBe([]);
});

test('declaredArms() gives each top-level arm the slots its own names\' tokens take, in order, and those names', function () {
    expect(FileImportNames::declaredArms('User | User[] | string | null', [null, User::class]))->toBe([
        ['User', [null], ['User']],
        ['User[]', [User::class], ['User']],
        ['string', [], ['User']],
        ['null', [], ['User']],
    ])->and(FileImportNames::declaredArms('{ a: User; p: Post } | User', [null, Post::class, User::class]))->toBe([
        ['{ a: User; p: Post }', [null, Post::class], ['Post', 'User']],
        ['User', [User::class], ['Post', 'User']],
    ])->and(FileImportNames::declaredArms('string | number', []))->toBe([['string', [], []], ['number', [], []]]);
});

test('joinedSlots() gives every arm one slot per token of the names any arm\'s slots hold, a non-own token null', function () {
    expect(FileImportNames::joinedSlots([
        ["{ kind: 'User' }", []],
        ["{ kind: 'User'; p: Post }", [Post::class]],
        ['User[]', [null]],
        ['User', [User::class]],
        ['string', []],
    ]))->toBe([null, null, Post::class, null, User::class])
        ->and(FileImportNames::joinedSlots([['string', []], ['null', []]]))->toBe([]);
});

test('joinedSlots() of a declaration\'s arms gives back its slots', function (string $type, array $slots) {
    expect(FileImportNames::joinedSlots(FileImportNames::declaredArms($type, $slots)))->toBe($slots)
        ->and(FileImportNames::joinedSlots([...FileImportNames::declaredArms($type, $slots), ['null', []]]))->toBe($slots);
})->with([
    'an override before a class in one arm, its model in another' => ['{ a: User; p: Post } | User', [null, Post::class, User::class]],
    'a literal before a class in one arm, its model in another' => ["{ kind: 'User'; p: Post } | User", [null, Post::class, User::class]],
    'two arms, each an override of the other\'s class' => ['{ a: User; p: Post } | { u: User; o: Post }', [null, Post::class, User::class, null]],
    'an override beside its model' => ['User | User[] | string | null', [null, User::class]],
    'a class after an override in a list' => ['(User | Post)[] | User', [null, Post::class, User::class]],
    'no class' => ['string | number', []],
]);

test('classLess() reads a declared type without the classes its tokens name, member by member', function (string $type, array $slots, string $read) {
    expect(FileImportNames::classLess($type, $slots))->toBe($read);
})->with([
    'an override beside its model' => ['User | User', [null, User::class], 'unknown'],
    'the two and a null' => ['User | User | null', [null, User::class], 'unknown'],
    'a list of the two' => ['(User | User)[]', [null, User::class], 'unknown'],
    'the override alone' => ['User', [null], 'User'],
    'a literal beside the model' => ["{ kind: 'User'; r: User }", [null, User::class], "{ kind: 'User'; r: unknown }"],
    'a list of that object' => ["{ kind: 'User'; r: User }[]", [null, User::class], "{ kind: 'User'; r: unknown }[]"],
    'that object or a null' => ["{ kind: 'User'; r: User } | null", [null, User::class], "{ kind: 'User'; r: unknown } | null"],
    'an object of the override and the model' => ['{ a: User; b: User }', [null, User::class], '{ a: User; b: unknown }'],
    'a nested object' => ['{ y: { a: User; p: Post }; n: number }', [Post::class], '{ y: { a: User; p: unknown }; n: number }'],
    'an override nested beside the model in another object' => [
        '{ y: { a: User; p: Post } } | { u: User }',
        [null, Post::class, User::class],
        '{ y: { a: User; p: unknown } } | { u: unknown }',
    ],
    'a record of objects' => ['Record<string, { a: User; b: User }>', [null, User::class], 'Record<string, { a: User; b: unknown }>'],
    'an object arm beside the model' => ['{ a: User; p: Post } | User', [null, Post::class, User::class], 'unknown'],
    'an optional member' => ['{ a?: User; b?: User }', [User::class, null], '{ a?: unknown; b?: User }'],
    'no class' => ['string | null', [], 'string | null'],
]);

test('readsName() reads a name only where a reader binds it to an import', function (array $types, bool $reads) {
    expect(FileImportNames::readsName('User', ...$types))->toBe($reads);
})->with([
    'a token' => [['{ k: unknown; u: User }'], true],
    'in a list' => [['User[]'], true],
    'in the second type' => [['unknown', 'User | null'], true],
    'only in a literal' => [["{ k: unknown; kind: 'User' }"], false],
    'only in a double-quoted literal' => [['"User"'], false],
    'only after typeof' => [['typeof User'], false],
    'only in a longer name' => [['CrmUser | Users'], false],
    'only after a dot' => [['ui.User'], false],
    'no type' => [[], false],
]);

test('classLess() of generated declarations reads each as the oracle says', function () {
    $cases = fileImportNamesCases(98, 500);
    $unknownMembers = 0;

    foreach ($cases as $case => $declarations) {
        foreach ($declarations as ['type' => $type, 'slots' => $slots, 'classLess' => $read]) {
            expect(FileImportNames::classLess($type, $slots))->toBe($read, "case {$case}: {$type} ".json_encode($slots));
            $unknownMembers += substr_count($read, ': unknown');
        }
    }

    expect($unknownMembers)->toBeGreaterThan(50);
});

// Each generated declaration is built with its slots, so the oracle never reads the code under test.
test('joinedSlots() of generated declarations\' arms gives one slot per token of their names, as the oracle says', function () {
    $own = static fn (array $tokens): array => fileImportNamesOwn($tokens);
    $slotsOf = static fn (array $tokens, array $ownNames): array => fileImportNamesSlots($tokens, $ownNames);
    $misreadArms = 0;

    foreach (fileImportNamesCases(98, 500) as $case => $declarations) {
        $joinedArms = [];
        $allTokens = [];

        foreach ($declarations as ['arms' => $arms, 'type' => $type, 'slots' => $slots]) {
            $tokens = array_merge([], ...array_column($arms, 1));
            $cut = FileImportNames::declaredArms($type, $slots);
            $context = "case {$case}: {$type} ".json_encode($slots);

            expect(array_column($cut, 0))->toBe(array_column($arms, 0), $context)
                ->and(array_column($cut, 1))->toBe(array_map(fn (array $arm): array => $slotsOf($arm[1], $own($tokens)), $arms), $context)
                ->and(FileImportNames::joinedSlots($cut))->toBe($slots, $context);

            // The shape a share's own classes misread: a null of a name its arm has no class of, then a class's token.
            foreach ($arms as [, $armTokens]) {
                $foreign = array_diff($own($tokens), $own($armTokens));
                $at = array_find_key($armTokens, static fn (array $t): bool => $t[1] === null && in_array($t[0], $foreign, true));
                $misreadArms += (int) ($at !== null && array_any(array_slice($armTokens, $at), static fn (array $t): bool => $t[1] !== null));
            }

            array_push($joinedArms, ...$cut);
            array_push($allTokens, ...$tokens);
        }

        expect(FileImportNames::joinedSlots($joinedArms))->toBe($slotsOf($allTokens, $own($allTokens)), "case {$case}");
    }

    expect($misreadArms)->toBeGreaterThan(50);
});

/**
 * Generated declarations of 1 to 4 top-level arms, 1 to 3 a case, each beside its arms, its slots and the type it
 * reads class-less: a member whose tokens outside its nested objects name a class is `unknown`.
 *
 * Each arm is [text, tokens, class-less text, whether a token outside its nested objects names a class]; a token is
 * [name, class] for a class's, [name, null] for an override's or a literal's.
 *
 * @return list<list<array{arms: list<list<mixed>>, type: string, slots: list<class-string|null>, classLess: string}>>
 */
function fileImportNamesCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $classes = ['User' => User::class, 'Post' => Post::class, 'Comment' => Comment::class];
    $names = array_keys($classes);

    $token = function () use ($random, $classes, $names): array {
        $name = $names[$random->getInt(0, 2)];

        return match ($random->getInt(0, 2)) {
            0 => [$name, [[$name, $classes[$name]]], $name, true],
            1 => [$name, [[$name, null]], $name, false],
            default => ["{ kind: '{$name}' }", [[$name, null]], "{ kind: '{$name}' }", false],
        };
    };
    $union = null;
    $arm = function (int $depth) use ($random, $token, &$arm, &$union): array {
        $wrap = static fn (string $open, array $members, string $glue, string $close): array => [
            $open.implode($glue, array_column($members, 0)).$close,
            array_merge([], ...array_column($members, 1)),
            $open.implode($glue, array_column($members, 2)).$close,
            array_any(array_column($members, 3), static fn (bool $named): bool => $named),
        ];
        $member = static function (string $key) use ($union, $depth): array {
            [$type, $tokens, $read, $named] = $union($depth + 1);

            return ["{$key}: {$type}", $tokens, "{$key}: ".($named ? 'unknown' : $read), false];
        };

        return match ($depth >= 2 ? $random->getInt(0, 2) : $random->getInt(0, 6)) {
            0, 1 => $token(),
            2 => [$type = ['string', 'number', 'null'][$random->getInt(0, 2)], [], $type, false],
            3 => $wrap('', [$arm($depth + 1)], '', '[]'),
            4 => $wrap('(', [$union($depth + 1)], '', ')[]'),
            5 => $wrap('{ ', array_map($member, array_slice(['a', 'b', 'c'], 0, $random->getInt(1, 3))), '; ', ' }'),
            default => $wrap('Record<string, ', [$union($depth + 1)], '', '>'),
        };
    };
    $union = function (int $depth) use ($random, $arm): array {
        $arms = array_map(static fn (): array => $arm($depth), range(1, $random->getInt(1, 3)));

        return [
            implode(' | ', array_column($arms, 0)),
            array_merge([], ...array_column($arms, 1)),
            implode(' | ', array_column($arms, 2)),
            array_any(array_column($arms, 3), static fn (bool $named): bool => $named),
        ];
    };

    $generated = [];

    for ($case = 0; $case < $cases; $case++) {
        $declarations = [];

        foreach (range(1, $random->getInt(1, 3)) as $ignored) {
            $arms = array_map(static fn (): array => $arm(0), range(1, $random->getInt(1, 4)));
            $tokens = array_merge([], ...array_column($arms, 1));
            $declarations[] = [
                'arms' => $arms,
                'type' => implode(' | ', array_column($arms, 0)),
                'slots' => fileImportNamesSlots($tokens, fileImportNamesOwn($tokens)),
                'classLess' => array_any(array_column($arms, 3), static fn (bool $named): bool => $named)
                    ? 'unknown'
                    : implode(' | ', array_column($arms, 2)),
            ];
        }

        $generated[] = $declarations;
    }

    return $generated;
}

/**
 * The names a generated declaration's tokens take slots for: those its class tokens spell.
 *
 * @param  list<array{string, class-string|null}>  $tokens
 * @return list<string>
 */
function fileImportNamesOwn(array $tokens): array
{
    return array_values(array_unique(array_column(
        array_filter($tokens, static fn (array $token): bool => $token[1] !== null),
        0,
    )));
}

/**
 * The slot each token of an own name takes: its class, or null for an override's or a literal's.
 *
 * @param  list<array{string, class-string|null}>  $tokens
 * @param  list<string>  $ownNames
 * @return list<class-string|null>
 */
function fileImportNamesSlots(array $tokens, array $ownNames): array
{
    return array_values(array_column(
        array_filter($tokens, static fn (array $token): bool => in_array($token[0], $ownNames, true)),
        1,
    ));
}

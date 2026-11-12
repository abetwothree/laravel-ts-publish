<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

test('slottedBranchUnion() joins a declared text\'s arms whole, an override\'s and a model\'s token kept apart', function () {
    expect(BranchJoin::slottedBranchUnion(['User | User', 'string'], false, false, [['User | User', [null, User::class]]]))
        ->toBe(['User | User | string', [null, User::class]])
        ->and(BranchJoin::slottedBranchUnion(['User | User', 'User | User'], false, false, [['User | User', [null, User::class]]]))
        ->toBe(['User | User', [null, User::class]]);
});

// Parentheses add no token, so an arrow arm joined beside its class keeps the slot its own token takes.
test('a join parenthesizes an arrow branch, so the branches after it stay outside its return type', function () {
    expect(BranchJoin::branchUnion(['() => void', 'string | null'], false, false))->toBe('(() => void) | string | null')
        ->and(BranchJoin::branchUnion(['() => A | null', 'string'], false, false))->toBe('(() => A | null) | string')
        ->and(BranchJoin::slottedBranchUnion(['() => User', 'User'], false, false, [['() => User', [User::class]]]))
        ->toBe(['(() => User) | User', [User::class, null]])
        ->and(BranchJoin::slottedBranchUnion(['User | User', '() => void'], false, false, [['User | User', [null, User::class]]]))
        ->toBe(['User | User | (() => void)', [null, User::class]]);
});

test('branchUnion() makes a strict join unknown beside an untypable branch, and a dropping join drops it', function () {
    expect(BranchJoin::branchUnion(['string', 'unknown'], false, false))->toBe('unknown')
        ->and(BranchJoin::branchUnion(['string', 'unknown'], true, false))->toBe('string')
        ->and(BranchJoin::branchUnion(['null', 'unknown'], true, false))->toBe('unknown')
        ->and(BranchJoin::branchUnion(['null', 'unknown'], true, true))->toBe('null')
        ->and(BranchJoin::branchUnion(['string | null', 'number'], false, false))->toBe('string | number | null');
});

test('declaredBranches() reads a declared text unalike where another branch spells its own names outside a literal', function () {
    $fill = ['User | User', [null, User::class]];

    expect(BranchJoin::declaredBranches([$fill, ['User', []]], false))->toBe([[], [$fill]])
        ->and(BranchJoin::declaredBranches([$fill, ["{ kind: 'User' }", []]], false))->toBe([[$fill], []])
        ->and(BranchJoin::declaredBranches([[$fill[0], [User::class, User::class]], ['unknown', []]], true))
        ->toBe([[[$fill[0], [User::class, User::class]]], []]);
});

test('castsEveryBranchAgrees() keeps a cast only where every branch setting its key casts it alike', function () {
    $branch = static fn (array $casts): MethodAnalysis => new MethodAnalysis(
        properties: [
            ['name' => 'a', 'type' => 'string', 'optional' => false, 'description' => ''],
            ['name' => 'b', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
        casts: $casts,
    );
    $cast = ['type' => 'Money', 'import' => '@/money'];

    expect(BranchJoin::castsEveryBranchAgrees([
        $branch(['a' => $cast, 'b' => $cast]),
        $branch(['a' => [...$cast, 'optional' => true], 'b' => ['type' => 'Cents', 'import' => '@/money']]),
    ], ['a', 'b']))->toBe(['a' => [...$cast, 'optional' => true]]);
});

/**
 * One branch holding `k` typed as given, with its model class where it names one.
 *
 * @param  class-string|null  $model
 */
function joinBranch(string $type, ?string $model = null): MethodAnalysis
{
    $branch = new MethodAnalysis;
    $branch->addProperty('k', ['type' => $type, 'optional' => false, ...($model === null ? [] : ['modelFqcn' => $model])]);

    return $branch;
}

test('joinInto() keeps a key the first branch leaves untyped untyped only where told to', function () {
    $kept = new MethodAnalysis;
    BranchJoin::joinInto($kept, [joinBranch('unknown'), joinBranch('string')], dropsUntypedBranches: true, keepsFirstUntyped: true);
    $dropped = new MethodAnalysis;
    BranchJoin::joinInto($dropped, [joinBranch('unknown'), joinBranch('string')], dropsUntypedBranches: true);
    $later = new MethodAnalysis;
    BranchJoin::joinInto($later, [joinBranch('string'), joinBranch('unknown')], dropsUntypedBranches: true, keepsFirstUntyped: true);

    expect(array_column($kept->properties, 'type'))->toBe(['unknown'])
        ->and(array_column($dropped->properties, 'type'))->toBe(['string'])
        ->and(array_column($later->properties, 'type'))->toBe(['string']);
});

test('joinInto() keeps two models one name spells as two arms, each queued, only where told to', function () {
    $apart = new MethodAnalysis;
    BranchJoin::joinInto($apart, [joinBranch('User', User::class), joinBranch('User', CrmUser::class)], keepsClassesApart: true);
    $folded = new MethodAnalysis;
    BranchJoin::joinInto($folded, [joinBranch('User', User::class), joinBranch('User', CrmUser::class)]);

    expect(array_column($apart->properties, 'type'))->toBe(['User | User'])
        ->and($apart->inlineModelFqcns)->toBe(['k' => [User::class, CrmUser::class]])
        ->and(array_column($folded->properties, 'type'))->toBe(['User']);
});

test('a join decides which branches count on their gap-free text, the lone `null` included, and keeps each gap', function () {
    expect(BranchJoin::branchUnion(['null | $gap1', 'unknown'], true, false))->toBe('unknown')
        ->and(BranchJoin::branchUnion(['null | $gap1', 'unknown'], true, true))->toBe('null | $gap1')
        ->and(BranchJoin::branchUnion(['string | $gap1', 'unknown'], true, false))->toBe('string | $gap1')
        ->and(BranchJoin::branchUnion(['null', 'null | $gap1', 'unknown'], true, false))->toBe('unknown')
        ->and(BranchJoin::branchUnion(['string', 'string | $gap1'], false, false))->toBe('string | $gap1');
});

test('declaredBranches() reads two branches that differ only by a gap or the marker as one declared text', function () {
    $fill = ['{ u: User }', [User::class]];
    $gapped = ['{ u: User | $gap1; #$open: unknown }', [User::class]];

    expect(BranchJoin::declaredBranches([$fill, $gapped], false))->toBe([[$fill, $gapped], []])
        ->and(BranchJoin::declaredBranches([$fill, $gapped, ['unknown', []]], true)[1])->toBe([]);
});

test('joinInto() joins a multi-source key whose sources hold gaps as their gap-free texts join', function () {
    $joined = static function (array $types, bool $drops = true): string {
        $target = new MethodAnalysis;
        BranchJoin::joinInto($target, array_map(joinBranch(...), $types), dropsUntypedBranches: $drops);

        return implode(' ', array_column($target->properties, 'type'));
    };

    expect($joined(['string | $gap1', 'unknown']))->toBe('string | $gap1')
        ->and($joined(['null | $gap1', 'unknown']))->toBe('unknown')
        ->and($joined(['string', 'string | $gap1'], false))->toBe('string | $gap1');
});

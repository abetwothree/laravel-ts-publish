<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use Workbench\App\Models\User;

test('slottedBranchUnion() joins a declared text\'s arms whole, an override\'s and a model\'s token kept apart', function () {
    expect(BranchJoin::slottedBranchUnion(['User | User', 'string'], false, false, [['User | User', [null, User::class]]]))
        ->toBe(['User | User | string', [null, User::class]])
        ->and(BranchJoin::slottedBranchUnion(['User | User', 'User | User'], false, false, [['User | User', [null, User::class]]]))
        ->toBe(['User | User', [null, User::class]]);
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

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node\Expr\Variable;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\User;

/**
 * One branch's value of a key, with the declared slots and model queue a declaration gives it where $slots is set.
 *
 * @param  list<class-string|null>|null  $slots
 * @param  array{absent?: true, removed?: true}  $facts
 */
function joinValue(string $type, ?array $slots = null, bool $optional = false, array $facts = []): KeyValue
{
    $analysis = new MethodAnalysis(properties: [['name' => 'k', 'type' => $type, 'optional' => $optional, 'description' => '', ...$facts]]);

    if ($slots !== null) {
        $analysis->declaredTokenFqcns = ['k' => $slots];
        $analysis->inlineModelFqcns = ['k' => [User::class]];
    }

    return KeyValue::of($analysis, $analysis->properties[0]);
}

/**
 * One branch's value of a key read as one model.
 */
function joinModelValue(string $type): KeyValue
{
    $analysis = new MethodAnalysis;
    $analysis->addProperty('k', ['type' => $type, 'optional' => false, 'modelFqcn' => User::class]);

    return KeyValue::of($analysis, $analysis->properties[0]);
}

it('joins one key\'s branch values under each of the join\'s flags', function (array $values, array $flags, string $type) {
    expect(KeyValue::joined($values, ...$flags)->type())->toBe($type);
})->with([
    'a strict join unions the branches' => [fn () => [joinValue('string'), joinValue('number')], [], 'string | number'],
    'a strict join keeps an untyped branch' => [fn () => [joinValue('string'), joinValue('unknown')], [], 'unknown'],
    'a dropping join drops an untyped branch' => [fn () => [joinValue('string'), joinValue('unknown')], ['dropsUntypedBranches' => true], 'string'],
    'a dropping join drops a lone null' => [fn () => [joinValue('null'), joinValue('unknown')], ['dropsUntypedBranches' => true], 'unknown'],
    'a merge keeps a lone null' => [fn () => [joinValue('null'), joinValue('unknown')], ['dropsUntypedBranches' => true, 'keepsLoneNull' => true], 'null'],
    'a dropping join types a key its first source leaves untyped' => [fn () => [joinValue('unknown'), joinValue('string')], ['dropsUntypedBranches' => true], 'string'],
    'a literal\'s key keeps its first untyped source' => [fn () => [joinValue('unknown'), joinValue('string')], ['dropsUntypedBranches' => true, 'keepsFirstUntyped' => true], 'unknown'],
    'a literal\'s key types a later untyped source away' => [fn () => [joinValue('string'), joinValue('unknown')], ['dropsUntypedBranches' => true, 'keepsFirstUntyped' => true], 'string'],
    'two branches spelling a declared name unalike' => [fn () => [joinValue('User | User', [null, User::class]), joinValue('User', [User::class])], [], 'unknown'],
    'a dropping join drops a fill another branch spells unalike' => [fn () => [joinValue('User | User', [null, User::class]), joinModelValue('User')], ['dropsUntypedBranches' => true], 'User'],
]);

it('keeps the branches\' presence and their joined slots', function () {
    $joined = KeyValue::joined([joinValue('User | User', [null, User::class]), joinValue('null', optional: true)]);

    expect($joined->row)->toBe(['type' => 'User | User | null', 'optional' => true, 'description' => ''])
        ->and($joined->channels)->toBe(['inlineModelFqcns' => [User::class], 'declaredTokenFqcns' => [null, User::class]]);
});

it('joins the branches\' presence as if every branch held the key', function (array $values, array $row) {
    expect(KeyValue::joined($values)->row)->toBe($row);
})->with([
    'a key one branch holds absent' => [
        fn () => [joinValue('string', optional: true, facts: ['absent' => true]), joinValue('string')],
        ['type' => 'string', 'optional' => true, 'description' => '', 'absent' => true],
    ],
    'a key one branch may remove' => [
        fn () => [joinValue('string'), joinValue('string', optional: true, facts: ['removed' => true])],
        ['type' => 'string', 'optional' => true, 'description' => '', 'removed' => true],
    ],
    'a key every branch sends' => [
        fn () => [joinValue('string'), joinValue('number')],
        ['type' => 'string | number', 'optional' => false, 'description' => ''],
    ],
]);

it('keeps a cast only where every branch casts the key alike, in one text and import', function (array $casts, ?array $cast) {
    $values = array_map(static function (?array $cast): KeyValue {
        $analysis = new MethodAnalysis(properties: [['name' => 'k', 'type' => 'Money', 'optional' => false, 'description' => '']]);
        $analysis->casts = $cast === null ? [] : ['k' => $cast];

        return KeyValue::of($analysis, $analysis->properties[0]);
    }, $casts);

    expect(KeyValue::joined($values)->channels['casts'] ?? null)->toBe($cast);
})->with([
    'every branch alike' => [[['type' => 'Money', 'import' => '@/money'], ['type' => 'Money', 'import' => '@/money']], ['type' => 'Money', 'import' => '@/money']],
    'one branch optional' => [[['type' => 'Money', 'import' => '@/money'], ['type' => 'Money', 'import' => '@/money', 'optional' => true]], ['type' => 'Money', 'import' => '@/money', 'optional' => true]],
    'another text' => [[['type' => 'Money', 'import' => '@/money'], ['type' => 'Cents', 'import' => '@/money']], null],
    'another import' => [[['type' => 'Money', 'import' => '@/money'], ['type' => 'Money', 'import' => '@/cents']], null],
    'a branch with no cast' => [[['type' => 'Money', 'import' => '@/money'], null], null],
]);

it('keeps no class behind a literal\'s key its first source leaves untyped', function () {
    $joined = KeyValue::joined([joinValue('unknown'), joinModelValue('User')], dropsUntypedBranches: true, keepsFirstUntyped: true);
    $typed = KeyValue::joined([joinValue('unknown'), joinModelValue('User')], dropsUntypedBranches: true);

    expect($joined->type())->toBe('unknown')
        ->and($joined->channels)->toBe([])
        ->and($typed->type())->toBe('User')
        ->and($typed->channels)->toBe(['modelFqcns' => User::class]);
});

it('leaves a gap for each method return a branch spells unalike in the gap mode, under a provisional read', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $branches = array_map(function (array $branch): ResourceAnalysis {
        $analysis = new ResourceAnalysis(properties: [['name' => 'k', 'type' => $branch[0], 'optional' => false, 'description' => '']]);
        $analysis->declaredTokenFqcns = ['k' => $branch[1]];

        return $analysis;
    }, [['User | User', [null, User::class]], ['User', [User::class]]]);
    $returns = [new Variable('a'), new Variable('b')];
    $join = static fn (): string => KeyValue::joined(array_map(
        static fn (ResourceAnalysis $armed): KeyValue => KeyValue::of($armed, $armed->properties[0]),
        BranchJoin::withUnalikeFillsUntyped($branches, $returns, $scope),
    ), dropsUntypedBranches: true)->type();

    $type = resolve(AnalysisMemo::class)->showingGaps(
        fn (): string => DroppedUnionArms::provisional($join, static fn (string $type): bool => true),
    );

    expect(VisibleGaps::ids($type))->toHaveCount(2)
        ->and(VisibleGaps::stripped($type)[0])->toBe('unknown');
});

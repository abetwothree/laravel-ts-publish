<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node\Expr\Variable;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
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

test('joinInto() keeps a cast only where every branch setting its key casts it alike', function () {
    $branch = static fn (array $casts): MethodAnalysis => new MethodAnalysis(
        properties: [
            ['name' => 'a', 'type' => 'string', 'optional' => false, 'description' => ''],
            ['name' => 'b', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
        casts: $casts,
    );
    $cast = ['type' => 'Money', 'import' => '@/money'];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [
        $branch(['a' => $cast, 'b' => $cast]),
        $branch(['a' => [...$cast, 'optional' => true], 'b' => ['type' => 'Cents', 'import' => '@/money']]),
    ]);

    expect($joined->casts)->toBe(['a' => [...$cast, 'optional' => true]]);
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

test('joinInto() keeps two models one name spells as two arms, each queued', function () {
    $apart = new MethodAnalysis;
    BranchJoin::joinInto($apart, [joinBranch('User', User::class), joinBranch('User', CrmUser::class)]);

    expect(array_column($apart->properties, 'type'))->toBe(['User | User'])
        ->and($apart->inlineModelFqcns)->toBe(['k' => [User::class, CrmUser::class]]);
});

test('joinInto() keeps a model and a resource one name spells as two arms, each token read as its own class', function () {
    $resource = new MethodAnalysis;
    $resource->addProperty('k', ['type' => 'Address', 'optional' => false, 'resourceFqcn' => AddressResource::class]);
    $apart = new MethodAnalysis;
    BranchJoin::joinInto($apart, [$resource, joinBranch('Address', Address::class)]);

    expect(array_column($apart->properties, 'type'))->toBe(['Address | Address'])
        ->and($apart->classFqcns('k'))->toBe([AddressResource::class, Address::class]);
});

/**
 * One branch holding `k` as a read of the enum, or as its wrap.
 *
 * @param  class-string  $enum
 */
function enumJoinBranch(string $enum, bool $wraps): MethodAnalysis
{
    $branch = new MethodAnalysis;
    $branch->addProperty('k', $wraps
        ? ['type' => 'AsEnum<typeof Status>', 'optional' => false, 'embeddedEnumResourceFqcns' => [$enum], 'wrappedEnumFqcns' => [$enum]]
        : ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => $enum]);

    return $branch;
}

// GH-76: two branches reading or wrapping two same-named enums print one text, so the key is read by class.
test('joinInto() keeps two same-named enums as two arms, read or wrapped, each token queued once in arm order', function (bool $wraps, string $type, string $channel) {
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [enumJoinBranch(Status::class, $wraps), enumJoinBranch(Status::class, $wraps), enumJoinBranch(CrmStatus::class, $wraps)]);

    expect(array_column($joined->properties, 'type'))->toBe([$type.' | '.$type])
        ->and($joined->{$channel}['k'])->toBe([Status::class, CrmStatus::class]);
})->with([
    'read' => [false, 'StatusType', 'inlineEnumFqcns'],
    'wrapped' => [true, 'AsEnum<typeof Status>', 'inlineEnumResourceFqcns'],
]);

// A strict join's `unknown` stands for the branch it cannot type, so reading the key by class must not drop that
// branch.
test('joinInto() keeps a strict join\'s unknown beside two models of one name, and a dropping join reads them by class', function () {
    $branches = fn (): array => [joinBranch('User', User::class), joinBranch('unknown'), joinBranch('User', CrmUser::class)];
    $strict = new MethodAnalysis;
    BranchJoin::joinInto($strict, $branches());
    $dropping = new MethodAnalysis;
    BranchJoin::joinInto($dropping, $branches(), dropsUntypedBranches: true);

    expect(array_column($strict->properties, 'type'))->toBe(['unknown'])
        ->and(array_column($dropping->properties, 'type'))->toBe(['User | User'])
        ->and($dropping->inlineModelFqcns)->toBe(['k' => [User::class, CrmUser::class]]);
});

/**
 * One branch holding a signature key filled as given, beside its model class.
 *
 * @param  class-string  $model
 */
function filledSignatureBranch(string $type, string $fill, string $model): MethodAnalysis
{
    $branch = new MethodAnalysis;
    $branch->addProperty('[key: string]', ['type' => $type, 'optional' => false, 'modelFqcn' => $model]);
    $branch->properties[0]['fillType'] = $fill;

    return $branch;
}

// Read by class, the key's fill is the union it publishes, so a later reconcile finds no other fill to put back.
test('joinInto() reads a filled key by class only where each fill is its branch\'s value, the fill then its union', function () {
    $agreeing = new MethodAnalysis;
    BranchJoin::joinInto($agreeing, [
        filledSignatureBranch('User | undefined', 'User | undefined', User::class),
        filledSignatureBranch('User | undefined', 'User | undefined', CrmUser::class),
    ]);
    $apart = new MethodAnalysis;
    BranchJoin::joinInto($apart, [
        filledSignatureBranch('User | undefined', 'string | undefined', User::class),
        filledSignatureBranch('User | undefined', 'User | undefined', CrmUser::class),
    ]);

    expect($agreeing->properties[0]['type'])->toBe('User | undefined | User')
        ->and($agreeing->properties[0]['fillType'] ?? null)->toBe('User | undefined | User')
        ->and($apart->properties[0]['type'])->toBe('User | undefined')
        ->and($apart->properties[0]['fillType'] ?? null)->toBe('string | undefined | User');
});

// Declared slots say whose each token is themselves, an override's null too, so the slotted join stands beside two
// models of another name: read by class, the override's token and the model's would print as one.
test('joinInto() leaves a key whose branches declare slots to the slotted join, its slots lined up with its tokens', function () {
    $declared = joinBranch('Address | Address', Address::class);
    $declared->declaredTokenFqcns['k'] = [null, Address::class];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [$declared, joinBranch('User', User::class), joinBranch('User', CrmUser::class)]);

    expect($joined->properties[0]['type'])->toStartWith('Address | Address | ')
        ->and($joined->declaredTokenFqcns['k'])->toBe([null, Address::class]);
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

test('joinInto() keeps a gap in the mode where a method return\'s fill another branch spells unalike is dropped (PJ8)', function () {
    $fill = joinBranch('User | User');
    $fill->declaredTokenFqcns['k'] = [null, User::class];
    $returns = [new Variable('fill'), new Variable('model')];
    $joined = static function (bool $shows) use ($fill, $returns): string {
        $target = new MethodAnalysis;
        $join = static fn () => BranchJoin::joinInto(
            $target,
            [$fill, joinBranch('User', User::class)],
            dropsUntypedBranches: true,
            returns: $returns,
            scope: new AnalysisScope(new ReflectionClass(stdClass::class)),
        );
        $shows ? resolve(AnalysisMemo::class)->showingGaps($join) : $join();

        return $target->properties[0]['type'];
    };

    expect($joined(false))->toBe('User')
        ->and(VisibleGaps::aside($joined(true)))->toMatchArray([0 => 'User'])
        ->and(VisibleGaps::ids($joined(true)))->toHaveCount(1);
});

test('joinInto() keeps the groups of the one analysis it joins, and none of two', function () {
    $branch = static function (): MethodAnalysis {
        $analysis = new MethodAnalysis(properties: [['name' => 'a', 'type' => 'string', 'optional' => false, 'description' => '']]);
        $analysis->variants = new LiteralVariants([], new LiteralWrites, true, [], []);

        return $analysis;
    };
    $sole = $branch();
    $one = new MethodAnalysis;
    BranchJoin::joinInto($one, [$sole]);
    $two = new MethodAnalysis;
    BranchJoin::joinInto($two, [$branch(), $branch()]);

    expect($one->variants)->toBe($sole->variants)
        ->and($two->variants)->toBeNull();
});

/**
 * One required row of a key, typed as given.
 *
 * @return array{name: string, type: string, optional: false, description: string}
 */
function joinRow(string $name, string $type = 'string'): array
{
    return ['name' => $name, 'type' => $type, 'optional' => false, 'description' => ''];
}

// A merge of the branches leaves each key where a branch first set it, moves an alike record a later branch drops to
// where one sets it again, and loses an entry under a key the join leaves without one.
test('joinInto() lays each channel map in the order a merge of its branches lays it', function () {
    $first = new MethodAnalysis(properties: [joinRow('k'), joinRow('j'), joinRow('m'), joinRow('n')]);
    $first->modelFqcns = ['m' => Post::class, 'k' => User::class];
    $first->alikeClassFqcns = ['k' => [User::class], 'j' => [Post::class]];
    $second = new MethodAnalysis(properties: [joinRow('k')]);
    $second->modelFqcns = ['n' => Address::class, 'k' => User::class];
    $third = new MethodAnalysis(properties: [joinRow('k')]);
    $third->modelFqcns = ['k' => User::class];
    $third->alikeClassFqcns = ['k' => [User::class]];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [$first, $second, $third]);

    expect($joined->modelFqcns)->toBe(['m' => Post::class, 'k' => User::class])
        ->and($joined->alikeClassFqcns)->toBe(['j' => [Post::class], 'k' => [User::class]]);
});

test('joinInto() keys a split key\'s classes by themselves after every key its branches hold', function () {
    $first = new MethodAnalysis(properties: [joinRow('k', 'User'), joinRow('z', 'Address')]);
    $first->modelFqcns = ['k' => User::class, 'z' => Address::class];
    $second = new MethodAnalysis(properties: [joinRow('k', 'Post')]);
    $second->modelFqcns = ['k' => Post::class];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [$first, $second]);

    expect($joined->modelFqcns)->toBe(['z' => Address::class, User::class => User::class, Post::class => Post::class])
        ->and($joined->inlineModelFqcns)->toBe(['k' => [User::class, Post::class]]);
});

test('joinInto() reads a fill another branch spells unalike as untyped, its classes and the imports only it spelled gone', function () {
    $fill = new MethodAnalysis(properties: [joinRow('k', 'User | User'), joinRow('m', 'Post')], customImports: ['@/user' => ['User']]);
    $fill->modelFqcns = ['k' => User::class, 'm' => Post::class];
    $fill->declaredTokenFqcns = ['k' => [null, User::class]];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [$fill, joinBranch('User', CrmUser::class)], dropsUntypedBranches: true);

    expect(array_column($joined->properties, 'type', 'name'))->toBe(['k' => 'User', 'm' => 'Post'])
        ->and($joined->modelFqcns)->toBe(['m' => Post::class, 'k' => CrmUser::class])
        ->and($joined->customImports)->toBe([]);
});

// A write a branch overwrote still spells the key's declared names, so the write it sends reads unalike beside it.
test('joinInto() reads a declared key unalike against every write of it, one its branch overwrote too', function () {
    $fill = new MethodAnalysis(properties: [joinRow('k', 'User | User'), joinRow('k', 'Foo')]);
    $fill->declaredTokenFqcns = ['k' => [null, User::class]];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [$fill, new MethodAnalysis(properties: [joinRow('k', 'Bar')])], dropsUntypedBranches: true);

    expect(array_column($joined->properties, 'type'))->toBe(['Bar']);
});

test('joinInto() merges what branches hold beside their keys: the first type alias, each import once, an unowned queue whole', function () {
    $first = new MethodAnalysis(properties: [joinRow('a')], customImports: ['@/x' => ['X', 'Y']], flatTypeAlias: 'As');
    $first->inlineModelFqcns = ['u' => [User::class]];
    $second = new MethodAnalysis(properties: [joinRow('a')], customImports: ['@/x' => ['Y', 'Z']], flatTypeAlias: 'Bs');
    $second->inlineModelFqcns = ['u' => [Post::class]];
    $joined = new MethodAnalysis;
    BranchJoin::joinInto($joined, [$first, $second]);

    expect($joined->flatTypeAlias)->toBe('As')
        ->and($joined->customImports)->toBe(['@/x' => ['X', 'Y', 'Z']])
        ->and($joined->inlineModelFqcns)->toBe(['u' => [User::class, Post::class]]);
});

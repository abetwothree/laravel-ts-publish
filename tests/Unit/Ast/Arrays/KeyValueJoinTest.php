<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Post;
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

/**
 * One branch's value of a key read as one class of the given kind, a model's or a resource's.
 *
 * @param  class-string  $class
 */
function joinClassValue(string $type, string $class, string $kind = 'modelFqcn'): KeyValue
{
    $analysis = new MethodAnalysis;
    $analysis->addProperty('k', ['type' => $type, 'optional' => false, $kind => $class]);

    return KeyValue::of($analysis, $analysis->properties[0]);
}

/**
 * One branch's value of a key whose text embeds a model, queued and keyed by itself, beside its description.
 */
function joinQueuedValue(string $description = ''): KeyValue
{
    $analysis = new MethodAnalysis(properties: [['name' => 'k', 'type' => '{ u: User }', 'optional' => false, 'description' => $description]]);
    $analysis->inlineModelFqcns = ['k' => [User::class]];
    $analysis->modelFqcns = [User::class => User::class];

    return KeyValue::of($analysis, $analysis->properties[0]);
}

it('queues each class branches type a key with apart, each keyed by itself', function () {
    $joined = KeyValue::joined([joinClassValue('User', User::class), joinClassValue('Post', Post::class)]);

    expect($joined->type())->toBe('User | Post')
        ->and($joined->channels)->toBe(['inlineModelFqcns' => [User::class, Post::class]])
        ->and($joined->embedded)->toBe(['modelFqcns' => [User::class => User::class, Post::class => Post::class]]);
});

it('reads a key two classes print alike by its union, each token queued by its own class', function () {
    $joined = KeyValue::joined([joinClassValue('Address', AddressResource::class, 'resourceFqcn'), joinClassValue('Address', Address::class)]);

    expect($joined->type())->toBe('Address | Address')
        ->and($joined->channels)->toBe([
            'alikeClassFqcns' => [AddressResource::class, Address::class],
            'inlineModelFqcns' => [Address::class],
            'inlineResourceFqcns' => [AddressResource::class],
        ])
        ->and($joined->embedded)->toBe([
            'modelFqcns' => [Address::class => Address::class],
            'nestedResources' => [AddressResource::class => AddressResource::class],
        ]);
});

it('keeps one copy of a queue a later branch only repeats, holding the key alike', function (array $values, array $queue) {
    expect(KeyValue::joined($values)->channels['inlineModelFqcns'] ?? null)->toBe($queue);
})->with([
    'alike' => [fn () => [joinQueuedValue(), joinQueuedValue()], [User::class]],
    'with another description' => [fn () => [joinQueuedValue(), joinQueuedValue('the user')], [User::class, User::class]],
]);

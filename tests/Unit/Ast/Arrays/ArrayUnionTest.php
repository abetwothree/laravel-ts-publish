<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ArrayUnion;

/**
 * An analysis of the given rows, each `[name, type, optional, value facts]`.
 *
 * @param  list<array{string, string, bool, array{absent?: bool, removed?: bool}}>  $rows
 */
function plusOperand(array $rows): ResourceAnalysis
{
    $analysis = new ResourceAnalysis;

    foreach ($rows as [$name, $type, $optional, $facts]) {
        $analysis->addProperty($name, ['type' => $type, 'optional' => $optional, ...$facts]);
    }

    return $analysis;
}

// Each expectation is what InertiaPageAnalyzer's plusProps() gave before the move. A row named for a controller action
// holds the operands its `+` of props handed plusProps(): every pair the suite's run reached.
it('lays the right side\'s keys where the left lacks them, as PHP\'s + does', function (array $left, array $right, array $rows) {
    $union = ArrayUnion::plusAnalyses(plusOperand($left), plusOperand($right), new ResourceAnalysis);

    expect($union->properties)->toBe($rows);
})->with([
    'a key the left always holds keeps its value' => [
        [['a', 'number', false, []]],
        [['a', 'string', false, []], ['k', 'number', false, []]],
        [
            ['name' => 'a', 'type' => 'number', 'optional' => false, 'description' => ''],
            ['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'a key the left may lack joins the right\'s value' => [
        [['a', 'string', true, ['absent' => true]]],
        [['a', 'number', false, []], ['k', 'number', false, []]],
        [
            ['name' => 'a', 'type' => 'string | number', 'optional' => false, 'description' => ''],
            ['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'a key the left may remove stays removed' => [
        [['a', 'string', true, []]],
        [['a', 'number', false, []]],
        [['name' => 'a', 'type' => 'string | number', 'optional' => false, 'description' => '', 'removed' => true]],
    ],
    'the left\'s keys lead, the right\'s new keys follow' => [
        [['a', 'number', false, []], ['b', 'string', true, ['absent' => true]]],
        [['c', 'number', false, []], ['b', 'boolean', false, []]],
        [
            ['name' => 'a', 'type' => 'number', 'optional' => false, 'description' => ''],
            ['name' => 'b', 'type' => 'string | boolean', 'optional' => false, 'description' => ''],
            ['name' => 'c', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'PropsLocalController@plus' => [
        [['a', 'number', false, []]],
        [['k', 'number', false, []]],
        [
            ['name' => 'a', 'type' => 'number', 'optional' => false, 'description' => ''],
            ['name' => 'k', 'type' => 'number', 'optional' => false, 'description' => ''],
        ],
    ],
    'PropsLocalController@plusOptional' => [
        [['a', 'number', true, ['absent' => true]]],
        [['a', 'string', false, []]],
        [['name' => 'a', 'type' => 'number | string', 'optional' => false, 'description' => '']],
    ],
    'InertiaPropsWalkController@plusOperator' => [
        [['heading', 'string', false, []], ['total', 'number', true, ['absent' => true]]],
        [['total', 'string', false, []]],
        [
            ['name' => 'heading', 'type' => 'string', 'optional' => false, 'description' => ''],
            ['name' => 'total', 'type' => 'number | string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusIntKeys' => [
        [['7', 'string', true, ['absent' => true]], ['9', 'string', true, ['absent' => true]]],
        [['8', 'string', false, []]],
        [
            ['name' => '7', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true],
            ['name' => '9', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true],
            ['name' => '8', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusDeferred' => [
        [['7', 'string', true, []]],
        [['8', 'string', false, []]],
        [
            ['name' => '7', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true, 'removed' => true],
            ['name' => '8', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusOptionalInTernary' => [
        [['7', 'string', true, ['absent' => true, 'removed' => true]], ['y', 'number', true, ['absent' => true]]],
        [['8', 'string', false, []]],
        [
            ['name' => '7', 'type' => 'string', 'optional' => true, 'description' => '', 'absent' => true, 'removed' => true],
            ['name' => 'y', 'type' => 'number', 'optional' => true, 'description' => '', 'absent' => true],
            ['name' => '8', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
    ],
    'TopLevelKeysController@plusClosedHead' => [
        [['card', '{ data: UserCardResource }', false, []]],
        [['card.can', 'boolean', false, []]],
        [
            ['name' => 'card', 'type' => '{ data: UserCardResource }', 'optional' => false, 'description' => ''],
            ['name' => 'card.can', 'type' => 'boolean', 'optional' => false, 'description' => ''],
        ],
    ],
]);

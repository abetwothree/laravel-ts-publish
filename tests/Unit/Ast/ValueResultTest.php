<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\OpaqueHandle;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\User as CrmUser;

describe('ValueResult::withAttributeChannels()', function () {
    test('carries each FQCN kind on the channel its count picks', function (array $attribute, array $channels) {
        $result = ['type' => 'T', 'optional' => false];

        expect(ValueResult::withAttributeChannels($result, $attribute))->toBe([...$result, ...$channels]);
    })->with([
        'nothing to carry' => [['enumFqcns' => [], 'classFqcns' => []], []],
        'one enum' => [['enumFqcns' => [Status::class], 'classFqcns' => []], ['directEnumFqcn' => Status::class]],
        'several enums' => [
            ['enumFqcns' => [Status::class, Priority::class], 'classFqcns' => []],
            ['embeddedEnumFqcns' => [Status::class, Priority::class]],
        ],
        'one class' => [['enumFqcns' => [], 'classFqcns' => [Comment::class]], ['modelFqcn' => Comment::class]],
        'several classes' => [
            ['enumFqcns' => [], 'classFqcns' => [Comment::class, User::class]],
            ['embeddedModelFqcns' => [Comment::class, User::class]],
        ],
        'an enum beside a class' => [
            ['enumFqcns' => [Status::class], 'classFqcns' => [User::class]],
            ['directEnumFqcn' => Status::class, 'modelFqcn' => User::class],
        ],
        'a #[TsType] import' => [
            ['enumFqcns' => [], 'classFqcns' => [], 'customImports' => ['@/types/geo' => ['GeoPoint']]],
            ['customImports' => ['@/types/geo' => ['GeoPoint']]],
        ],
        'an empty #[TsType] import map' => [['enumFqcns' => [], 'classFqcns' => [], 'customImports' => []], []],
    ]);
});

describe('ValueResult::namesOnlyExportedClasses()', function () {
    test('a result naming no class passes', function () {
        expect(ValueResult::namesOnlyExportedClasses(['type' => 'string', 'optional' => false]))->toBeTrue();
    });

    test('with no published set every model passes, and a class no file is generated for never does', function () {
        expect(ValueResult::namesOnlyExportedClasses(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeTrue()
            ->and(ValueResult::namesOnlyExportedClasses([
                'type' => 'User | OpaqueHandle', 'optional' => false, 'embeddedModelFqcns' => [User::class, OpaqueHandle::class],
            ]))->toBeFalse();
    });

    test('with a published set a model outside it fails on either channel', function () {
        PublishedModelRegistry::register([User::class]);

        expect(ValueResult::namesOnlyExportedClasses(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeTrue()
            ->and(ValueResult::namesOnlyExportedClasses(['type' => 'Comment', 'optional' => false, 'modelFqcn' => Comment::class]))->toBeFalse()
            ->and(ValueResult::namesOnlyExportedClasses([
                'type' => 'User | Comment', 'optional' => false, 'embeddedModelFqcns' => [User::class, Comment::class],
            ]))->toBeFalse();
    });
});

describe('ValueResult::namesOnlyPublishedModels()', function () {
    test('with no published set a framework or abstract model is still known to have no file', function () {
        expect(ValueResult::namesOnlyPublishedModels(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeTrue()
            ->and(ValueResult::namesOnlyPublishedModels([
                'type' => 'DatabaseNotification', 'optional' => false, 'modelFqcn' => DatabaseNotification::class,
            ]))->toBeFalse()
            ->and(ValueResult::namesOnlyPublishedModels(['type' => 'Model', 'optional' => false, 'modelFqcn' => Model::class]))->toBeFalse();
    });

    test('with a published set the set alone decides, so a published framework model passes', function () {
        PublishedModelRegistry::register([DatabaseNotification::class]);

        expect(ValueResult::namesOnlyPublishedModels([
            'type' => 'DatabaseNotification', 'optional' => false, 'modelFqcn' => DatabaseNotification::class,
        ]))->toBeTrue()
            ->and(ValueResult::namesOnlyPublishedModels(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]))->toBeFalse()
            ->and(ValueResult::namesOnlyPublishedModels(['type' => 'Model', 'optional' => false, 'modelFqcn' => Model::class]))->toBeFalse();
    });
});

describe('ValueResult::isPublishableReading()', function () {
    test('a reading publishes when there is one, it is not vague, and every model it names gets a file', function (?array $reading, bool $publishable) {
        expect(ValueResult::isPublishableReading($reading))->toBe($publishable);
    })->with([
        'a precise type' => [['type' => 'string | null', 'optional' => false], true],
        'a published model' => [['type' => 'User', 'optional' => false, 'modelFqcn' => User::class], true],
        'a shape holding unknown' => [['type' => '{ a: unknown }', 'optional' => false], true],
        'no reading' => [null, false],
        'unknown' => [['type' => 'unknown', 'optional' => false], false],
        'a list of unknown' => [['type' => 'unknown[]', 'optional' => false], false],
        'object' => [['type' => 'object', 'optional' => false], false],
        'a framework model' => [['type' => 'Model', 'optional' => false, 'modelFqcn' => Model::class], false],
    ]);
});

/**
 * An `EnumResource` wrap of an enum as its handler spells it with the tolki package on.
 *
 * @param  class-string  $enum
 * @return array<string, mixed>
 */
function valueResultWrapOf(string $enum, string $type, string $suffix = ''): array
{
    return [
        'type' => "AsEnum<typeof {$type}>{$suffix}",
        'optional' => false,
        'embeddedEnumResourceFqcns' => [$enum],
        'wrappedEnumFqcns' => [$enum],
    ];
}

// Each arm publishes as it does alone, in arm order: two members are one only where their text and classes both match.
describe('ValueResult::unionResults() over enum-resource wraps', function () {
    test('keeps each wrap as its own member', function (array $branches, array $union) {
        expect(ValueResult::unionResults($branches))->toEqual($union);
    })->with([
        'two enums' => [
            [valueResultWrapOf(Status::class, 'Status'), valueResultWrapOf(Priority::class, 'Priority')],
            [
                'type' => 'AsEnum<typeof Status> | AsEnum<typeof Priority>',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class, Priority::class],
                'wrappedEnumFqcns' => [Status::class, Priority::class],
            ],
        ],
        'the app\'s and the CRM\'s Status, printed alike' => [
            [valueResultWrapOf(Status::class, 'Status'), valueResultWrapOf(CrmStatus::class, 'Status')],
            [
                'type' => 'AsEnum<typeof Status> | AsEnum<typeof Status>',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class, CrmStatus::class],
                'wrappedEnumFqcns' => [Status::class, CrmStatus::class],
            ],
        ],
        'one enum wrapped twice, one entry per token' => [
            [valueResultWrapOf(Status::class, 'Status'), valueResultWrapOf(Status::class, 'Status')],
            [
                'type' => 'AsEnum<typeof Status>',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class],
                'wrappedEnumFqcns' => [Status::class],
            ],
        ],
        'a wrap, a bare read of its enum and a literal' => [
            [
                valueResultWrapOf(Status::class, 'Status'),
                ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
                ['type' => "'none'", 'optional' => false],
            ],
            [
                'type' => "AsEnum<typeof Status> | StatusType | 'none'",
                'optional' => false,
                'embeddedEnumFqcns' => [Status::class],
                'embeddedEnumResourceFqcns' => [Status::class],
                'wrappedEnumFqcns' => [Status::class],
            ],
        ],
        'a wrap and null' => [
            [valueResultWrapOf(Status::class, 'Status'), ['type' => 'null', 'optional' => false]],
            [
                'type' => 'AsEnum<typeof Status> | null',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class],
                'wrappedEnumFqcns' => [Status::class],
            ],
        ],
        'rows, then two wraps' => [
            [
                ['type' => '{ n: string }[]', 'optional' => false],
                valueResultWrapOf(Status::class, 'Status'),
                valueResultWrapOf(Priority::class, 'Priority'),
            ],
            [
                'type' => '{ n: string }[] | AsEnum<typeof Status> | AsEnum<typeof Priority>',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class, Priority::class],
                'wrappedEnumFqcns' => [Status::class, Priority::class],
            ],
        ],
        'a nullable wrap beside another, as a when() default leaves it' => [
            [valueResultWrapOf(Status::class, 'Status'), [...valueResultWrapOf(Visibility::class, 'Visibility'), 'type' => 'AsEnum<typeof Visibility> | null']],
            [
                'type' => 'AsEnum<typeof Status> | AsEnum<typeof Visibility> | null',
                'optional' => false,
                'embeddedEnumResourceFqcns' => [Status::class, Visibility::class],
                'wrappedEnumFqcns' => [Status::class, Visibility::class],
            ],
        ],
    ]);

    test('reads two same-named wraps apart from the reads beside them', function () {
        $union = ValueResult::unionResults([
            valueResultWrapOf(Status::class, 'Status'),
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class],
            ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => CrmStatus::class],
        ]);

        expect($union['type'])->toBe('AsEnum<typeof Status> | StatusType | StatusType')
            ->and($union['embeddedEnumFqcns'] ?? null)->toBe([Status::class, CrmStatus::class])
            ->and($union['embeddedEnumResourceFqcns'] ?? null)->toBe([Status::class]);
    });
});

// A Collection reaches JSON with its keys, so a union keeps them only when no arm is an array the filter re-indexes.
describe('ValueResult::unionResults() over arms that keep their keys', function () {
    test('keeps the keys when every arm that is not null keeps them', function () {
        $result = ValueResult::unionResults([
            ['type' => '{ "200": string }', 'optional' => false, 'keepsKeys' => true],
            ['type' => 'null', 'optional' => false],
        ]);

        expect($result)->toBe(['type' => '{ "200": string } | null', 'optional' => false, 'keepsKeys' => true]);
    });

    test('drops the keys when an arm that is not null does not keep them', function () {
        $result = ValueResult::unionResults([
            ['type' => '{ "200": string }', 'optional' => false, 'keepsKeys' => true],
            ['type' => '{ "404": string }', 'optional' => false],
        ]);

        expect($result)->not->toHaveKey('keepsKeys');
    });

    test('keeps no keys for a union of null alone', function () {
        expect(ValueResult::unionResults([['type' => 'null', 'optional' => false]]))->not->toHaveKey('keepsKeys');
    });

    // The re-index leaves a scalar or an empty list as it is, so neither decides whether the union keeps keys.
    test('keeps the keys beside an arm holding no object', function (string $other) {
        $result = ValueResult::unionResults([
            ['type' => '{ "200": string }', 'optional' => false, 'keepsKeys' => true],
            ['type' => $other, 'optional' => false],
        ]);

        expect($result)->toBe(['type' => '{ "200": string } | '.$other, 'optional' => false, 'keepsKeys' => true]);
    })->with(['string', 'never[]', 'Record<string, number>']);

    test('drops the keys beside a list of objects that does not keep them', function () {
        $result = ValueResult::unionResults([
            ['type' => '{ "200": string }', 'optional' => false, 'keepsKeys' => true],
            ['type' => '{ "1": string }[]', 'optional' => false],
        ]);

        expect($result)->not->toHaveKey('keepsKeys');
    });
});

describe('ValueResult::withAttributeChannels() and a per-token class queue', function () {
    test('carries the queue in place of the class list when the attribute has one', function () {
        $result = ValueResult::withAttributeChannels(['type' => 'T', 'optional' => false], [
            'enumFqcns' => [],
            'classFqcns' => [User::class, CrmUser::class],
            'classTokenFqcns' => [User::class, User::class, CrmUser::class],
        ]);

        expect($result['embeddedModelFqcns'])->toBe([User::class, User::class, CrmUser::class]);
    });
});

describe('ValueResult::arrayWrapType()', function () {
    test('lists a type, parenthesizing it only where a top-level union, intersection or arrow would bind the brackets', function (string $type, string $list) {
        expect(ValueResult::arrayWrapType($type))->toBe($list);
    })->with([
        'an arrow function' => ['() => A', '(() => A)[]'],
        'an object holding a union' => ['{ a: A | B }', '{ a: A | B }[]'],
        'a union' => ['A | B', '(A | B)[]'],
        'an intersection' => ['A & { b: B }', '(A & { b: B })[]'],
        'a generic holding a union' => ["Pick<Post, 'id' | 'title'>", "Pick<Post, 'id' | 'title'>[]"],
        'a string literal holding a pipe' => ["'a|b'", "'a|b'[]"],
        'a plain type' => ['User', 'User[]'],
    ]);
});

// GH-76: a keyed list spells its element twice, so each per-token queue reads twice and the Record arm keeps its classes.
describe('ValueResult::keyedList()', function () {
    test('reads each per-token queue twice, a single channel leading each copy, and leaves a single class as it is', function () {
        $pair = ValueResult::keyedList([
            'type' => '{ u: User; v: User; s: StatusType; t: StatusType }[]',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class],
            'directEnumFqcn' => Status::class,
            'embeddedEnumFqcns' => [CrmStatus::class],
            'embeddedEnumResourceFqcns' => [Status::class],
            'wrappedEnumFqcns' => [Status::class],
        ]);
        $single = ValueResult::keyedList(['type' => 'StatusType[]', 'optional' => false, 'directEnumFqcn' => Status::class]);

        expect($pair['type'])->toBe('{ u: User; v: User; s: StatusType; t: StatusType }[] | Record<string, { u: User; v: User; s: StatusType; t: StatusType }>')
            ->and($pair['embeddedModelFqcns'])->toBe([User::class, CrmUser::class, User::class, CrmUser::class])
            ->and($pair['directEnumFqcn'])->toBe(Status::class)
            ->and($pair['embeddedEnumFqcns'])->toBe([CrmStatus::class, Status::class, CrmStatus::class])
            ->and($pair['embeddedEnumResourceFqcns'])->toBe([Status::class, Status::class])
            ->and($pair['wrappedEnumFqcns'])->toBe([Status::class])
            ->and($single)->toBe(['type' => 'StatusType[] | Record<string, StatusType>', 'optional' => false, 'directEnumFqcn' => Status::class]);
    });
});

describe('ValueResult::unionResults() over an arm that can be a MissingValue', function () {
    test('an optional typed arm beside a required null arm makes the union optional', function () {
        expect(ValueResult::unionResults([
            ['type' => 'string', 'optional' => true],
            ['type' => 'null', 'optional' => false],
        ])['optional'])->toBeTrue();
    });

    test('an optional arm with no type alone leaves an optional unknown', function () {
        expect(ValueResult::unionResults([
            ['type' => 'unknown', 'optional' => true],
            ['type' => 'unknown', 'optional' => false],
        ]))->toBe(['type' => 'unknown', 'optional' => true]);
    });

    test('an optional arm with no type still makes a typed union optional', function () {
        expect(ValueResult::unionResults([
            ['type' => 'string', 'optional' => false],
            ['type' => 'unknown', 'optional' => true],
        ]))->toBe(['type' => 'string', 'optional' => true]);
    });

    test('arms that are all required leave the union required', function () {
        expect(ValueResult::unionResults([
            ['type' => 'string', 'optional' => false],
            ['type' => 'number', 'optional' => false],
        ])['optional'])->toBeFalse();
    });
});

describe('ValueResult::unionResults() over an empty array beside a list', function () {
    test('folds never[] into a list it is assignable to, and keeps it beside anything else', function (array $types, string $union) {
        $arms = array_map(fn (string $type): array => ['type' => $type, 'optional' => false], $types);

        expect(ValueResult::unionResults($arms)['type'])->toBe($union);
    })->with([
        'a list' => [['string[]', 'never[]'], 'string[]'],
        'a list of unknown' => [['unknown[]', 'never[]'], 'unknown[]'],
        'an empty array first' => [['never[]', '{ id: number }[]'], '{ id: number }[]'],
        'a nullable list' => [['string[] | null', 'never[]'], 'string[] | null'],
        'an Array<> spelling' => [['Array<string>', 'never[]'], 'Array<string>'],
        'a list of a union' => [['(string | number)[]', 'never[]'], '(string | number)[]'],
        'a list beside null' => [['never[]', 'null', 'number[]'], 'number[] | null'],
        'an empty array alone' => [['never[]'], 'never[]'],
        'an empty array beside null' => [['never[]', 'null'], 'never[] | null'],
        'a tuple' => [['[string, number]', 'never[]'], '[string, number] | never[]'],
        'a record' => [['Record<string, string>', 'never[]'], 'Record<string, string> | never[]'],
        'a named type' => [['UserCollection', 'never[]'], 'UserCollection | never[]'],
        'an empty array nested in a shape' => [['{ a: never[] }', 'string[]'], '{ a: never[] } | string[]'],
        'an empty array beside a list, as two arms' => [['never[]', 'number[]'], 'number[]'],
        'an empty array beside a list in a shape, left to the publisher\'s pass' => [
            ['{ k: never[] | number[] }', 'null'],
            '{ k: never[] | number[] } | null',
        ],
    ]);
});

// A list or Collection built from a value Inertia replaces is one it opens, so the mark holds only for its own type.
it('reads a value as closed to dotted keys only while its type is the one it was marked for', function () {
    $card = ValueResult::closeToDottedKeys(['type' => '{ data: R }', 'optional' => false]);

    expect(ValueResult::closedToDottedKeys($card))->toBeTrue()
        ->and(ValueResult::closedToDottedKeys([...$card, 'type' => '{ data: R } | null']))->toBeTrue()
        ->and(ValueResult::closedToDottedKeys([...$card, 'type' => '{ data: R }[]']))->toBeFalse()
        ->and(ValueResult::closedToDottedKeys(['type' => '{ data: R }', 'optional' => false]))->toBeFalse();
});

// A literal printed one object per variant is read back as keys from its one object, while the union stands.
it('reads a split result as its one-object print, with that print\'s classes, only while its type is the union', function () {
    $union = '{ s: 0; v: string } | { s: 1; v: null }';
    $split = [
        'type' => $union,
        'optional' => true,
        'embeddedModelFqcns' => [User::class],
        'unnamedKeys' => true,
        'unsplit' => ['of' => $union, 'print' => [
            'type' => '{ s: StatusType; v: string | null }',
            'optional' => false,
            'embeddedEnumFqcns' => [Status::class],
        ]],
    ];
    $rewritten = [...$split, 'type' => $union.' | null'];

    expect(ValueResult::unsplit($split))->toBe([
        'type' => '{ s: StatusType; v: string | null }',
        'optional' => true,
        'embeddedEnumFqcns' => [Status::class],
        'unnamedKeys' => true,
    ])
        ->and(ValueResult::unsplitType($split))->toBe('{ s: StatusType; v: string | null }')
        ->and(ValueResult::unsplit($rewritten))->toBe($rewritten)
        ->and(ValueResult::unsplitType($rewritten))->toBe($union.' | null')
        ->and(ValueResult::unsplitType(['type' => $union, 'optional' => false]))->toBe($union)
        ->and(ValueResult::isSplit($split))->toBeTrue()
        ->and(ValueResult::isSplit($rewritten))->toBeFalse();
});

it('joins the facts a result records for its one-object print\'s text to the print\'s own', function () {
    $print = '{ a?: string; b?: number }';
    $split = [
        'type' => '{ k: true; a?: string } | { k: false; a?: never }',
        'optional' => false,
        'keyPresence' => ['{ k: true; a?: string }' => ['a' => ['removed' => true]], $print => ['b' => ['absent' => true]]],
        'unsplit' => ['of' => '{ k: true; a?: string } | { k: false; a?: never }', 'print' => [
            'type' => $print,
            'optional' => false,
            'keyPresence' => [$print => ['a' => ['removed' => true]]],
        ]],
    ];

    expect(ValueResult::unsplit($split)['keyPresence'][$print] ?? null)
        ->toBe(['a' => ['removed' => true], 'b' => ['absent' => true]]);
});

// A map() element is what its callback returns, and one that returns a callable may hold an object.
it('reads the value a held callable returns apart from the callable', function () {
    $number = ['type' => 'number', 'optional' => false];

    expect(ValueResult::calledValue(ValueResult::heldObject(ValueResult::heldObject($number))))
        ->toBe([...$number, 'mayHoldObject' => ['objectNull' => false, 'plainNull' => false]])
        ->and(ValueResult::calledValue(ValueResult::heldObject($number)))->toBe($number);
});

// Inertia resolves a prop object even where a callable it called returns it, and sends a callable there as `{}`; it
// replaces either object, and a union of them or of one and null, before a dotted key writes under it.
it('marks a held object\'s kind, and a callable that may return a callable', function () {
    $closure = ValueResult::heldObject(['type' => 'number', 'optional' => false]);
    $prop = ValueResult::heldObject(['type' => 'number', 'optional' => false], prop: true);
    $returnsClosure = ValueResult::heldObject($closure);
    $returnsProp = ValueResult::heldObject($prop);
    $plain = ['type' => 'null', 'optional' => false];

    expect($closure)->toBe(['type' => 'number', 'optional' => false, 'heldObject' => true])
        ->and($prop)->toBe(['type' => 'number', 'optional' => false, 'heldObject' => true, 'heldProp' => true, 'closedToDottedKeys' => 'number'])
        ->and($returnsClosure)->toBe(['type' => 'number', 'optional' => false, 'heldObject' => true, 'returnsCallable' => true])
        ->and($returnsProp)->toBe(['type' => 'number', 'optional' => false, 'closedToDottedKeys' => 'number', 'heldObject' => true])
        ->and(ValueResult::unionResults([$prop, $prop]))
        ->toBe(['type' => 'number', 'optional' => false, 'closedToDottedKeys' => 'number', 'heldObject' => true, 'heldProp' => true])
        ->and(ValueResult::unionResults([$prop, $returnsProp])['closedToDottedKeys'] ?? null)->toBe('number')
        ->and(ValueResult::unionResults([$prop, $plain])['closedToDottedKeys'] ?? null)->toBe('number | null')
        ->and(ValueResult::unionResults([$prop, ['type' => 'string', 'optional' => false]])['closedToDottedKeys'] ?? null)->toBeNull()
        ->and(ValueResult::unionResults([$plain, $plain])['closedToDottedKeys'] ?? null)->toBeNull()
        ->and(ValueResult::unionResults([$prop, $closure]))->toBe(['type' => 'number', 'optional' => false, 'heldObject' => true])
        ->and(ValueResult::unionResults([$prop, $plain])['heldProp'] ?? null)->toBeTrue()
        ->and(ValueResult::unionResults([$returnsClosure, $plain])['returnsCallable'] ?? null)->toBeTrue()
        ->and(ValueResult::withoutHeldObject([...$returnsClosure, 'heldProp' => true]))->toBe(['type' => 'number', 'optional' => false]);
});

describe('gap arms', function () {
    test('gapTransparent() reads a value with its gap arms set aside and gives the result they make back', function () {
        $read = static fn (array $value): array => [...$value, 'type' => str_replace('[]', '', $value['type'])];

        expect(ValueResult::gapTransparent(['type' => 'string[] | $gap1', 'optional' => false], $read)['type'] ?? null)
            ->toBe('string | $gap1')
            ->and(ValueResult::gapTransparent(['type' => 'string[]', 'optional' => false], $read)['type'] ?? null)->toBe('string')
            ->and(ValueResult::gapTransparent(['type' => 'string[] | $gap1', 'optional' => false], static fn (): null => null))
            ->toBeNull();
    });

    test('reads a list-shaped value, an element\'s model and a value closed to dotted keys past their gap arms', function () {
        $element = ValueResult::elementOf(['type' => '(User | $gap2)[]', 'optional' => false, 'modelFqcn' => User::class], 'User | $gap2');
        $closed = ValueResult::closeToDottedKeys(['type' => '{ a: string }', 'optional' => false]);

        expect(ValueResult::isListShaped('string[] | $gap1'))->toBeTrue()
            ->and($element['modelFqcn'] ?? null)->toBe(User::class)
            ->and(ValueResult::closedToDottedKeys([...$closed, 'type' => '{ a: string } | $gap1']))->toBeTrue();
    });

    test('unions a `null` beside a gap as the null Inertia replaces', function () {
        $closed = ValueResult::closeToDottedKeys(['type' => '{ a: string }', 'optional' => false]);
        $union = ValueResult::unionResults([$closed, ['type' => 'null | $gap1', 'optional' => false]]);

        expect(ValueResult::closedToDottedKeys($union))->toBeTrue();
    });
});

test('keeps two same-named classes apart through a strip: their arms\' gaps rise to the union\'s top', function () {
    $union = ValueResult::unionResults([
        ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class]],
        ['type' => '(User | $gap1)[]', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]],
    ]);
    $plain = ValueResult::unionResults([
        ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class]],
        ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]],
    ]);

    expect(VisibleGaps::stripped($union['type'])[0])->toBe($plain['type'])
        ->and(VisibleGaps::ids($union['type']))->toBe([1]);
});

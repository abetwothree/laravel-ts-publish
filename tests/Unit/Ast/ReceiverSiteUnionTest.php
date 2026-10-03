<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairArchive;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairArchiveResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairHandover;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairThird\User as ThirdUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReceiverPairTransfer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use PhpParser\Node\Expr;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/** Parses one PHP expression the way the engine reads source. */
function receiverPairExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/** The scope a ReceiverPairResource's own body is read in. */
function receiverPairScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ReceiverPairResource::class), ReceiverPairHandover::class);
}

/** The property a `$flag ? new A : new B` receiver reads, answered by the rule that types a property on a class. */
function receiverPairProperty(string $property): ?array
{
    $receiver = '($flag ? new '.ReceiverPairHandover::class.' : new '.ReceiverPairTransfer::class.')->';

    return new ReceiverPropertyFetchHandler()->resolve(receiverPairExpr($receiver.$property), receiverPairScope(), chainHandlersThrowingEngine());
}

describe('a receiver holding two models that share a name', function () {
    test('the method rule publishes one member per class', function () {
        $result = resolve(ReceiverMethodReturnResolver::class)->resolve(new ReceiverType([User::class, CrmUser::class]), 'fresh', receiverPairScope());

        expect($result)->toBe([
            'type' => 'User | User | null',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class],
        ]);
    });

    test('the property rule publishes one member per class', function () {
        expect(receiverPairProperty('sender'))->toBe([
            'type' => 'User | User | null',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class],
        ]);
    });

    test('an accessor publishes both classes, each under its own alias, in source order', function (string $accessor, string $type) {
        $data = (new ModelTransformer(ReceiverPairHandover::class))->data();

        expect($data->mutators[$accessor]['type'])->toBe($type);
    })->with([
        'a ternary over the call on a coalesced pair' => ['replica', 'WorkbenchUser | CrmUser | null'],
        'a ternary over the call on a morph relation' => ['reviewer_copy_wrapped', 'CrmUser | WorkbenchUser | null'],
        'a coalesce over the call on a morph relation' => ['reviewer_copy_or_label', 'CrmUser | WorkbenchUser | string'],
        'the call on a morph relation, with no ternary' => ['reviewer_copy', 'CrmUser | WorkbenchUser | null'],
    ]);

    test('a resource publishes the same unions, read through an accessor and made by the call itself', function () {
        $properties = (new ResourceTransformer(ReceiverPairResource::class))->properties;

        expect($properties['replica']['type'])->toBe('WorkbenchUser | CrmUser | null')
            ->and($properties['reviewer_copy']['type'])->toBe('CrmUser | WorkbenchUser | null')
            ->and($properties['reviewer_copy_wrapped']['type'])->toBe('CrmUser | WorkbenchUser | null')
            ->and($properties['reviewer_copy_or_label']['type'])->toBe('CrmUser | WorkbenchUser | string')
            ->and($properties['copy']['type'])->toBe('CrmUser | WorkbenchUser | null')
            ->and($properties['copy_wrapped']['type'])->toBe('CrmUser | WorkbenchUser | null')
            ->and($properties['replica_direct']['type'])->toBe('WorkbenchUser | CrmUser | null');
    });
});

// Nothing is read twice here, so a merge by text and a merge by class are the same merge.
describe('a receiver holding two models with different names', function () {
    test('the method rule publishes what a merge by text published', function () {
        $result = resolve(ReceiverMethodReturnResolver::class)->resolve(new ReceiverType([User::class, Post::class]), 'fresh', receiverPairScope());

        expect($result)->toBe([
            'type' => 'User | Post | null',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, Post::class],
        ]);
    });

    test('the property rule publishes what a merge by text published', function () {
        expect(receiverPairProperty('item'))->toBe([
            'type' => 'Comment | Post | null',
            'optional' => false,
            'embeddedModelFqcns' => [Comment::class, Post::class],
        ]);
    });
});

/**
 * An arm that renders one token and holds both models that share a name behind it.
 *
 * @return array{type: string, optional: bool, embeddedModelFqcns: list<class-string>}
 */
function receiverPairShortArm(): array
{
    return ['type' => 'User | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]];
}

/**
 * The same pair behind one array token, as a merge by text of an array of each model leaves it.
 *
 * @return array{type: string, optional: bool, embeddedModelFqcns: list<class-string>}
 */
function receiverPairArrayArm(): array
{
    return ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]];
}

/**
 * The type each property of ReceiverPairArchiveResource publishes.
 *
 * @return array<string, string>
 */
function receiverPairArchiveTypes(): array
{
    return array_map(
        static fn (array $property): string => $property['type'],
        (new ResourceTransformer(ReceiverPairArchiveResource::class))->properties,
    );
}

dataset('reads of a member that renders alike', [
    'the property of one receiver' => ['owners_alone', 'WorkbenchUser[] | CrmUser[]'],
    'the property of two receivers that hold the same pair' => ['owners_of_either', 'WorkbenchUser[] | CrmUser[]'],
    'the method of two receivers that hold the same pair' => ['owner_list_of_either', 'WorkbenchUser[] | CrmUser[]'],
    'the property of two receivers, one holding another model' => ['owners_of_mixed', 'WorkbenchUser[] | CrmUser[] | Post[]'],
    'the method of two receivers, one holding another model' => ['owner_list_of_mixed', 'WorkbenchUser[] | CrmUser[] | Post[]'],
]);

dataset('unions over a member that renders alike', [
    'the property of one receiver, behind a ternary' => ['owners_or_null', 'WorkbenchUser[] | CrmUser[] | null'],
    'the method of one receiver, behind a ternary' => ['owner_list_or_null', 'WorkbenchUser[] | CrmUser[] | null'],
    'beside a string' => ['owners_or_label', 'WorkbenchUser[] | CrmUser[] | string'],
    'beside the application watchers' => ['owners_or_watchers', 'WorkbenchUser[] | CrmUser[]'],
    'beside the CRM watchers' => ['owners_or_crm_watchers', 'WorkbenchUser[] | CrmUser[]'],
    'both watchers, then the property, in a ternary of a ternary' => ['both_watchers_or_owners', 'WorkbenchUser[] | CrmUser[]'],
    'the CRM user or the third user' => [
        'owners_or_receiver_or_third',
        'WorkbenchUser[] | CrmUser[] | CrmUser | ReceiverPairThirdUser | null',
    ],
    'the third watchers or the CRM user' => [
        'owners_or_third_watchers_or_receiver',
        'WorkbenchUser[] | CrmUser[] | ReceiverPairThirdUser[] | CrmUser | null',
    ],
    'the CRM watchers or the third watchers' => [
        'owners_or_crm_watchers_or_third_watchers',
        'WorkbenchUser[] | CrmUser[] | ReceiverPairThirdUser[]',
    ],
    'the property or the CRM user, then the third user' => [
        'owners_or_receiver_then_third',
        'WorkbenchUser[] | CrmUser[] | CrmUser | ReceiverPairThirdUser | null',
    ],
    'the property or the application user, then the third user' => [
        'owners_or_sender_then_third',
        'WorkbenchUser[] | CrmUser[] | WorkbenchUser | ReceiverPairThirdUser | null',
    ],
    'the third user, else the CRM user, else the property' => [
        'third_or_else_receiver_or_else_owners',
        'ReceiverPairThirdUser | CrmUser | WorkbenchUser[] | CrmUser[]',
    ],
]);

// ReceiverPairDirectory's members are typed `User[]|CrmUser[]` and `list<User>|list<CrmUser>`, whose arms render
// alike for two models that share a name. Each arm keeps a token of its own.
describe('a member typed by a docblock union whose arms render alike for two models that share a name', function () {
    describe('holds a token for each model', function () {
        test('an accessor over it publishes an array of each model, each under its own alias', function (string $accessor, string $type) {
            expect((new ModelTransformer(ReceiverPairArchive::class))->data()->mutators[$accessor]['type'])->toBe($type);
        })->with('reads of a member that renders alike');

        test('a resource reading those accessors publishes the same line', function (string $property, string $type) {
            expect(receiverPairArchiveTypes()[$property])->toBe($type);
        })->with('reads of a member that renders alike');
    });

    // Each token has its class, so the union is read by class: an arm that repeats a member's text and class adds none.
    describe('in a union, which is read by class', function () {
        test('an accessor publishes each class the union holds, each under its own alias', function (string $accessor, string $type) {
            expect((new ModelTransformer(ReceiverPairArchive::class))->data()->mutators[$accessor]['type'])->toBe($type);
        })->with('unions over a member that renders alike');

        test('a resource reading those accessors publishes the same line', function (string $property, string $type) {
            expect(receiverPairArchiveTypes()[$property])->toBe($type);
        })->with('unions over a member that renders alike');

        test('a resource that makes the union itself publishes it the same way', function (string $property, string $type) {
            expect(receiverPairArchiveTypes()[$property])->toBe($type);
        })->with([
            'the property, then the CRM watchers' => ['direct_owners_or_crm_watchers', 'WorkbenchUser[] | CrmUser[]'],
            'both watchers, then the property' => ['direct_both_watchers_or_owners', 'WorkbenchUser[] | CrmUser[]'],
            'the watchers, then the property or the application user' => [
                'direct_watchers_then_owners_or_sender',
                'WorkbenchUser[] | CrmUser[] | WorkbenchUser | null',
            ],
            'the CRM watchers, then the property or the application user' => [
                'direct_crm_watchers_then_owners_or_sender',
                'CrmUser[] | WorkbenchUser[] | WorkbenchUser | null',
            ],
            'the watchers, then a `when()` over the property and the application user' => [
                'direct_watchers_then_when_owners_or_sender',
                'WorkbenchUser[] | CrmUser[] | WorkbenchUser | null',
            ],
        ]);
    });

    describe('under a key of an inline array', function () {
        test('the key after it keeps its own class', function (string $property, string $type) {
            expect(receiverPairArchiveTypes()[$property])->toBe($type);
        })->with([
            'beside the application user' => [
                'keyed_owners_or_sender',
                '{ either: WorkbenchUser[] | CrmUser[] | WorkbenchUser | null; first: WorkbenchUser | null }',
            ],
            'beside the CRM watchers' => [
                'keyed_owners_or_crm_watchers',
                '{ either: WorkbenchUser[] | CrmUser[]; first: WorkbenchUser | null }',
            ],
            'after the watchers, beside the application user, before a key that reads the CRM user' => [
                'keyed_watchers_then_owners_or_sender',
                '{ either: WorkbenchUser[] | CrmUser[] | WorkbenchUser | null; second: CrmUser | null }',
            ],
        ]);
    });
});

// An arm that queues one name for two classes and spells it once cannot say which class its token names, and nothing
// beside it can. ClassTokenQueue::outrunsItsTokens() finds such an arm, and a union over it stays the merge by text.
describe('an arm that holds two models of one name under a single token', function () {
    test('a union of it stays the merge by text, every class queued, whatever sits beside it', function (array $arms, array $merged) {
        expect(ValueResult::unionResults($arms))->toBe($merged);
    })->with(fn () => [
        'beside a string' => [
            [receiverPairShortArm(), ['type' => 'string', 'optional' => false]],
            ['type' => 'User | string | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
        ],
        'beside the same model' => [
            [receiverPairShortArm(), ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]],
            ['type' => 'User | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, User::class]],
        ],
        'beside the other model' => [
            [receiverPairShortArm(), ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class]],
            ['type' => 'User | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class]],
        ],
        'as an array, beside the other model under another text' => [
            [receiverPairArrayArm(), ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class]],
            ['type' => 'User[] | User', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class]],
        ],
        'as an array, after the other model under another text' => [
            [['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class], receiverPairArrayArm()],
            ['type' => 'User | User[]', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class, User::class, CrmUser::class]],
        ],
        'as an array, beside an array of the other model' => [
            [receiverPairArrayArm(), ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class]],
            ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class]],
        ],
        'as an array, beside the same pair held the other way round' => [
            [receiverPairArrayArm(), ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class, User::class]]],
            ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class, User::class]],
        ],
        'as an array, beside an array of the other model and an array of a third' => [
            [
                receiverPairArrayArm(),
                ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class],
                ['type' => 'User[]', 'optional' => false, 'modelFqcn' => ThirdUser::class],
            ],
            ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class, ThirdUser::class]],
        ],
        'in an arm that spells another name as well, beside an array of the other model' => [
            [
                ['type' => 'User[] | Post', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, Post::class]],
                ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            ['type' => 'User[] | Post', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, Post::class, CrmUser::class]],
        ],
        'two resources that share a name, beside a string' => [
            [
                ['type' => 'UserResource | null', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
                ['type' => 'string', 'optional' => false],
            ],
            ['type' => 'UserResource | string | null', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
        ],
        'two resources that share a name, beside the other resource' => [
            [
                ['type' => 'UserResource | null', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
                ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
            ],
            ['type' => 'UserResource | null', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
        ],
        'two resources that share a name, in an arm that spells another name as well' => [
            [
                [
                    'type' => 'UserResource[] | PostResource',
                    'optional' => false,
                    'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class, PostResource::class],
                ],
                ['type' => 'UserResource[]', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
            ],
            [
                'type' => 'UserResource[] | PostResource',
                'optional' => false,
                'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class, PostResource::class],
            ],
        ],
    ]);
});

// A union that stayed the merge by text queues its name more often than it spells it, like the arm it was made over,
// even where each of its classes has a token. As an arm of another union it keeps that union the merge by text.
describe('a union that stayed the merge by text, as an arm of another union', function () {
    test('keeps the outer union the merge by text, though each of its classes has a token', function () {
        $arms = [
            ['type' => 'User[] | User | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class]],
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => ThirdUser::class],
        ];

        expect(ValueResult::unionResults($arms))->toBe([
            'type' => 'User[] | User | null',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class, ThirdUser::class],
        ]);
    });
});

// A union read by class queues one class per rendered token, and nothing else.
describe('a union read by class', function () {
    test('gives each class a member when no arm queues a name for two classes more often than it spells it', function (array $arms, array $union) {
        expect(ValueResult::unionResults($arms))->toBe($union);
    })->with([
        'two arms of one text, a model each' => [
            [
                ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User | null', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            ['type' => 'User | User | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
        ],
        'an arm that queues one model twice behind one token, beside the other model' => [
            [
                ['type' => 'User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, User::class]],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            ['type' => 'User[] | User', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
        ],
    ]);

    // `when()` unions its value and its default by class, so as an arm it has a class behind each of its tokens.
    test('reads a `when()` over the two models by class, as an arm of another union', function () {
        expect(receiverPairArchiveTypes()['direct_when_pair_or_receiver'])->toBe('WorkbenchUser | CrmUser | null');
    });

    test('keeps no queue of a kind none of its tokens names', function (array $carrier) {
        expect(ValueResult::unionResults([$carrier, ['type' => 'string', 'optional' => false]]))
            ->toBe(['type' => 'number | string', 'optional' => false]);
    })->with([
        'both models of one name' => [['type' => 'number', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]]],
        'both resources of one name' => [
            ['type' => 'number', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
        ],
    ]);

    test('leaves out a class whose name no token of its arm spells', function (array $arms, array $union) {
        expect(ValueResult::unionResults($arms))->toBe($union);
    })->with([
        'one model' => [
            [
                ['type' => 'number', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            ['type' => 'number | User', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]],
        ],
        'both models of one name' => [
            [
                ['type' => 'number', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
                ['type' => 'User', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            ['type' => 'number | User', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]],
        ],
        'both resources of one name' => [
            [
                ['type' => 'number', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
                ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
            ],
            ['type' => 'number | UserResource', 'optional' => false, 'embeddedResourceFqcns' => [CrmUserResource::class]],
        ],
    ]);

    test('keeps a class under the token of its own arm that names it, whichever member of that arm spells its name', function (array $arms, array $union) {
        expect(ValueResult::unionResults($arms))->toBe($union);
    })->with([
        'two resources under one text' => [
            [['type' => 'UserResource | UserResource', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]]],
            ['type' => 'UserResource | UserResource', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
        ],
        'two models under two texts, beside the second model under the first text' => [
            [
                ['type' => 'User[] | User', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
                ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ],
            ['type' => 'User[] | User | User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class, CrmUser::class]],
        ],
        'two models under two texts, the second a member an earlier arm already gave' => [
            [
                ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class],
                ['type' => 'User[] | User', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class, User::class]],
            ],
            ['type' => 'User | User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
        ],
        'two resources under two texts, beside the second resource under the first text' => [
            [
                ['type' => 'UserResource[] | UserResource', 'optional' => false, 'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class]],
                ['type' => 'UserResource[]', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
            ],
            [
                'type' => 'UserResource[] | UserResource | UserResource[]',
                'optional' => false,
                'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class, CrmUserResource::class],
            ],
        ],
    ]);
});

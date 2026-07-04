<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastEventDto;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use Workbench\App\Events\EnumBroadcastEvent;
use Workbench\App\Events\MixedTypesEvent;
use Workbench\App\Events\MultiModelEvent;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Events\PostPublishedEvent;
use Workbench\App\Events\PureEnumEvent;
use Workbench\App\Events\ServerCreated;
use Workbench\App\Events\TeamMessageSent;
use Workbench\App\Events\UserNotification;
use Workbench\App\Events\UserRegisteredEvent;
use Workbench\Crm\Events\StatusSynced;
use Workbench\Crm\Events\UserSynced as CrmUserSynced;

describe('BroadcastEventTransformer', function () {
    describe('OrderShipped (default broadcastAs, public props)', function () {
        it('sets the event short name', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->eventName)->toBe('OrderShipped');
        });

        it('sets the broadcast name as dot-prefixed FQCN', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->broadcastName)->toBe('.Workbench.App.Events.OrderShipped');
        });

        it('resolves all public properties with TsCasts overrides applied', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties)->toMatchArray([
                'orderId' => ['type' => 'number', 'optional' => false],
                'trackingNumber' => ['type' => '`${string}-${string}-${string}`', 'optional' => false],
                'carrier' => ['type' => 'string', 'optional' => false],
                'metadata' => ['type' => 'Record<string, unknown>', 'optional' => true],
            ]);
        });

        it('applies TsCasts optional override to make metadata optional', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties['metadata']['optional'])->toBeTrue();
        });

        it('sets the filename to the short class name', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->filename())->toBe('OrderShipped');
        });

        it('sets the namespacePath as a lowercased directory path', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->namespacePath)->toContain('events');
        });

        it('returns a TsBroadcastEventDto from data()', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->data())->toBeInstanceOf(TsBroadcastEventDto::class);
        });
    });

    describe('ServerCreated (broadcastAs() override)', function () {
        it('uses broadcastAs() return value as the broadcast name', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            expect($transformer->broadcastName)->toBe('server.created');
        });

        it('still uses the short class name for eventName', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            expect($transformer->eventName)->toBe('ServerCreated');
        });

        it('resolves public constructor properties', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            expect($transformer->properties)->toMatchArray([
                'serverId' => ['type' => 'number', 'optional' => false],
                'serverName' => ['type' => 'string', 'optional' => false],
            ]);
        });
    });

    describe('TeamMessageSent (broadcastWith() override)', function () {
        it('uses broadcastWith() return type for properties', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => TeamMessageSent::class]);
            // Only teamId and content — private $senderToken is excluded
            expect($transformer->properties)->toHaveKeys(['teamId', 'content']);
            expect($transformer->properties)->not->toHaveKey('senderToken');
        });

        it('uses broadcastWith() and not the constructor props for TeamMessageSent', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => TeamMessageSent::class]);
            expect($transformer->properties['teamId']['type'])->toBe('number');
            expect($transformer->properties['content']['type'])->toBe('string');
        });
    });

    describe('UserNotification', function () {
        it('resolves all three public properties', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => UserNotification::class]);
            expect($transformer->properties)->toHaveCount(3);
        });
    });
});

describe('PostPublishedEvent (single model + string)', function () {
    it('resolves Post as Partial<Post>', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PostPublishedEvent::class]);
        expect($transformer->properties)->toMatchArray([
            'post' => ['type' => 'Partial<Post>', 'optional' => false],
            'message' => ['type' => 'string', 'optional' => false],
        ]);
    });

    it('builds typeImports with Post import from ../models path', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PostPublishedEvent::class]);
        $imports = $transformer->typeImports;
        // There should be a relative path import containing 'Post'
        $allTypes = array_merge(...array_values($imports));
        expect($allTypes)->toContain('Post');
    });

    it('the DTO contains typeImports', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PostPublishedEvent::class]);
        $dto = $transformer->data();
        expect($dto->typeImports)->not->toBeEmpty();
    });
});

describe('UserRegisteredEvent (model + int)', function () {
    it('resolves User as Partial<User>', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => UserRegisteredEvent::class]);
        expect($transformer->properties['user']['type'])->toBe('Partial<User>');
        expect($transformer->properties['userId']['type'])->toBe('number');
    });

    it('builds typeImports containing User', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => UserRegisteredEvent::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->toContain('User');
    });
});

describe('MultiModelEvent (Post + User, same namespace)', function () {
    it('resolves both models as Partial<Model>', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MultiModelEvent::class]);
        expect($transformer->properties['post']['type'])->toBe('Partial<Post>');
        expect($transformer->properties['user']['type'])->toBe('Partial<User>');
    });

    it('merges both model types into a single import path entry', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MultiModelEvent::class]);
        // Both Post and User are in the same namespace; should be one import path
        expect(count($transformer->typeImports))->toBe(1);
        $types = array_values($transformer->typeImports)[0];
        expect($types)->toContain('Post');
        expect($types)->toContain('User');
    });
});

describe('EnumBroadcastEvent (Status int-backed + Color string-backed)', function () {
    it('resolves Status as StatusType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => EnumBroadcastEvent::class]);
        expect($transformer->properties['status']['type'])->toBe('StatusType');
    });

    it('resolves Color as ColorType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => EnumBroadcastEvent::class]);
        expect($transformer->properties['color']['type'])->toBe('ColorType');
    });

    it('builds typeImports containing StatusType and ColorType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => EnumBroadcastEvent::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->toContain('StatusType');
        expect($allTypes)->toContain('ColorType');
    });
});

describe('MixedTypesEvent (Post model + Status enum + string)', function () {
    it('overrides post type via TsCasts', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        expect($transformer->properties['post']['type'])->toBe('PostSnapshot');
    });

    it('still resolves non-overridden properties normally', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        expect($transformer->properties['status']['type'])->toBe('StatusType');
        expect($transformer->properties['message']['type'])->toBe('string');
    });

    it('does not add a model import for the TsCasts-overridden post property', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->not->toContain('Post');
    });

    it('includes the TsCasts import path for PostSnapshot', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        expect($transformer->typeImports)->toHaveKey('@js/types/snapshots');
        expect($transformer->typeImports['@js/types/snapshots'])->toContain('PostSnapshot');
    });

    it('has two distinct import paths (casts import + enums dir)', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        expect(count($transformer->typeImports))->toBe(2);
    });
});

describe('PureEnumEvent (Role pure enum + Visibility pure enum + string)', function () {
    it('resolves Role as RoleType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PureEnumEvent::class]);
        expect($transformer->properties['role']['type'])->toBe('RoleType');
    });

    it('resolves Visibility as VisibilityType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PureEnumEvent::class]);
        expect($transformer->properties['visibility']['type'])->toBe('VisibilityType');
    });

    it('resolves string action as string', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PureEnumEvent::class]);
        expect($transformer->properties['action']['type'])->toBe('string');
    });

    it('builds typeImports with RoleType and VisibilityType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => PureEnumEvent::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->toContain('RoleType');
        expect($allTypes)->toContain('VisibilityType');
    });
});

describe('CrmUserSynced (two User models from different namespaces — import aliasing)', function () {
    it('aliases App\\Models\\User as AppUser and Crm\\Models\\User as CrmUser', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CrmUserSynced::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->toContain('User as AppUser');
        expect($allTypes)->toContain('User as CrmUser');
    });

    it('rewrites the user property type to Partial<AppUser>', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CrmUserSynced::class]);
        expect($transformer->properties['user']['type'])->toBe('Partial<AppUser>');
    });

    it('rewrites the crmUser property type to Partial<CrmUser>', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CrmUserSynced::class]);
        expect($transformer->properties['crmUser']['type'])->toBe('Partial<CrmUser>');
    });

    it('does not produce duplicate unaliased User entries', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CrmUserSynced::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect(array_filter($allTypes, fn ($t) => $t === 'User'))->toBeEmpty();
    });
});

describe('StatusSynced (two Status enums from different namespaces — import aliasing)', function () {
    it('aliases App\\Enums\\Status as AppStatusType and Crm\\Enums\\Status as CrmStatusType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => StatusSynced::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->toContain('StatusType as AppStatusType');
        expect($allTypes)->toContain('StatusType as CrmStatusType');
    });

    it('rewrites the status property type to AppStatusType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => StatusSynced::class]);
        expect($transformer->properties['status']['type'])->toBe('AppStatusType');
    });

    it('rewrites the crmStatus property type to CrmStatusType', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => StatusSynced::class]);
        expect($transformer->properties['crmStatus']['type'])->toBe('CrmStatusType');
    });

    it('does not produce duplicate unaliased StatusType entries', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => StatusSynced::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect(array_filter($allTypes, fn ($t) => $t === 'StatusType'))->toBeEmpty();
    });
});

describe('TsCasts overrides', function () {
    describe('OrderShipped (simple string overrides)', function () {
        it('applies #[TsCasts] string override for trackingNumber', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties['trackingNumber']['type'])
                ->toBe('`${string}-${string}-${string}`');
        });

        it('applies #[TsCasts] string override for metadata', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties['metadata']['type'])->toBe('Record<string, unknown>');
        });

        it('does not add any typeImports for plain string TsCasts overrides', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->typeImports)->toBeEmpty();
        });

        it('leaves non-overridden properties unchanged', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties['orderId']['type'])->toBe('number');
            expect($transformer->properties['carrier']['type'])->toBe('string');
        });

        it('marks optional state correctly even when overridden', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties['metadata']['optional'])->toBeTrue();
            expect($transformer->properties['trackingNumber']['optional'])->toBeFalse();
        });
    });

    describe('MixedTypesEvent (array override with import path)', function () {
        it('applies #[TsCasts] array override with import for post', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
            expect($transformer->properties['post']['type'])->toBe('PostSnapshot');
        });

        it('adds the declared import path to typeImports', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
            expect($transformer->typeImports)->toHaveKey('@js/types/snapshots');
            expect($transformer->typeImports['@js/types/snapshots'])->toContain('PostSnapshot');
        });

        it('does not include the model import for the overridden post property', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
            $allTypes = array_merge(...array_values($transformer->typeImports));
            expect($allTypes)->not->toContain('Post');
        });

        it('still resolves non-overridden properties via Surveyor', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
            expect($transformer->properties['status']['type'])->toBe('StatusType');
            expect($transformer->properties['message']['type'])->toBe('string');
        });

        it('includes the enum import for StatusType alongside the casts import', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
            $allTypes = array_merge(...array_values($transformer->typeImports));
            expect($allTypes)->toContain('StatusType');
        });
    });
});

describe('TsExtends on BroadcastEventTransformer', function () {
    describe('ServerCreated (direct #[TsExtends] on class)', function () {
        it('stores the extends clause on the transformer', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            expect($transformer->tsExtends)->toBe(['BroadcastableEvent']);
        });

        it('adds the import path from the attribute to typeImports', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            expect($transformer->typeImports)->toHaveKey('@/types/broadcast');
            expect($transformer->typeImports['@/types/broadcast'])->toContain('BroadcastableEvent');
        });

        it('passes tsExtends through to the DTO', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            $dto = $transformer->data();
            expect($dto)->toBeInstanceOf(TsBroadcastEventDto::class);
            expect($dto->tsExtends)->toBe(['BroadcastableEvent']);
        });

        it('renders the extends clause in the blade template output', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            $output = view('laravel-ts-publish::broadcast-event', ['data' => $transformer->data()])->render();
            expect($output)->toContain('export interface ServerCreated extends BroadcastableEvent {');
        });
    });

    describe('UserNotification (trait-based #[TsExtends])', function () {
        it('stores the extends clause propagated from the trait', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => UserNotification::class]);
            expect($transformer->tsExtends)->toBe(['HasTimestamps']);
        });

        it('adds the import path from the trait attribute to typeImports', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => UserNotification::class]);
            expect($transformer->typeImports)->toHaveKey('@/types/common');
            expect($transformer->typeImports['@/types/common'])->toContain('HasTimestamps');
        });

        it('passes tsExtends through to the DTO', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => UserNotification::class]);
            $dto = $transformer->data();
            expect($dto->tsExtends)->toBe(['HasTimestamps']);
        });

        it('renders the extends clause from the trait in the blade output', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => UserNotification::class]);
            $output = view('laravel-ts-publish::broadcast-event', ['data' => $transformer->data()])->render();
            expect($output)->toContain('export interface UserNotification extends HasTimestamps {');
        });
    });

    describe('events without #[TsExtends]', function () {
        it('has an empty tsExtends array', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->tsExtends)->toBeEmpty();
        });

        it('does not render an extends clause in the blade output', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            $output = view('laravel-ts-publish::broadcast-event', ['data' => $transformer->data()])->render();
            expect($output)->toContain('export interface OrderShipped {');
            expect($output)->not->toContain('extends');
        });
    });

    describe('global config ts_extends.broadcast_events', function () {
        it('applies a globally configured extends clause to all broadcast events', function () {
            config(['ts-publish.ts_extends.broadcast_events' => ['GlobalBase']]);
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->tsExtends)->toContain('GlobalBase');
        });

        it('merges global config extends with class-level #[TsExtends]', function () {
            config(['ts-publish.ts_extends.broadcast_events' => ['GlobalBase']]);
            $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
            expect($transformer->tsExtends)->toContain('GlobalBase');
            expect($transformer->tsExtends)->toContain('BroadcastableEvent');
        });
    });
});

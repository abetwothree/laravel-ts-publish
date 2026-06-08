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

        it('resolves all public properties', function () {
            $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
            expect($transformer->properties)->toMatchArray([
                'orderId' => ['type' => 'number', 'optional' => false],
                'trackingNumber' => ['type' => 'string', 'optional' => false],
                'carrier' => ['type' => 'string', 'optional' => false],
            ]);
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
    it('resolves mixed payload correctly', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        expect($transformer->properties['post']['type'])->toBe('Partial<Post>');
        expect($transformer->properties['status']['type'])->toBe('StatusType');
        expect($transformer->properties['message']['type'])->toBe('string');
    });

    it('has imports for both Post model and StatusType enum', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => MixedTypesEvent::class]);
        $allTypes = array_merge(...array_values($transformer->typeImports));
        expect($allTypes)->toContain('Post');
        expect($allTypes)->toContain('StatusType');
    });

    it('has two distinct import paths (models dir + enums dir)', function () {
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

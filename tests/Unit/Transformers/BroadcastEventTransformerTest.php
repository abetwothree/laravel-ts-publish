<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastEventDto;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Events\ServerCreated;
use Workbench\App\Events\TeamMessageSent;
use Workbench\App\Events\UserNotification;

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

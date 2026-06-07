<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastEventsCollector;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Events\ServerCreated;
use Workbench\App\Events\TeamMessageSent;
use Workbench\App\Events\UserNotification;

describe('BroadcastEventsCollector', function () {
    it('discovers all ShouldBroadcast event classes in the workbench Events directory', function () {
        config()->set('ts-publish.broadcast_events.additional_directories', []);
        config()->set('ts-publish.broadcast_events.included', []);
        config()->set('ts-publish.broadcast_events.excluded', []);

        $collector = app(BroadcastEventsCollector::class);
        $results = $collector->collect();

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);

        $classes = $results->all();
        expect($classes)->toContain(OrderShipped::class);
        expect($classes)->toContain(UserNotification::class);
        expect($classes)->toContain(ServerCreated::class);
        expect($classes)->toContain(TeamMessageSent::class);
    });

    it('returns class-strings implementing ShouldBroadcast', function () {
        config()->set('ts-publish.broadcast_events.additional_directories', []);
        config()->set('ts-publish.broadcast_events.included', []);
        config()->set('ts-publish.broadcast_events.excluded', []);

        $collector = app(BroadcastEventsCollector::class);
        $results = $collector->collect();

        foreach ($results as $class) {
            expect(is_a($class, ShouldBroadcast::class, true))->toBeTrue();
        }
    });

    it('excludes a specified class when added to the excluded list', function () {
        config()->set('ts-publish.broadcast_events.additional_directories', []);
        config()->set('ts-publish.broadcast_events.included', []);
        config()->set('ts-publish.broadcast_events.excluded', [OrderShipped::class]);

        $collector = app(BroadcastEventsCollector::class);
        $results = $collector->collect();

        expect($results->all())->not->toContain(OrderShipped::class);
        expect($results->all())->toContain(UserNotification::class);
    });
});

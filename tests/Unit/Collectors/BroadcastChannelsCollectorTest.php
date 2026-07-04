<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\BroadcastChannelsCollector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Workbench\App\Broadcasting\PublicAnnouncementsChannel;

test('collect returns a Collection of strings', function () {
    $collector = resolve(BroadcastChannelsCollector::class);
    $channels = $collector->collect();

    expect($channels)->toBeInstanceOf(Collection::class);
    expect($channels->every(fn ($item) => is_string($item)))->toBeTrue();
});

test('collect returns channel names registered via Broadcast::channel', function () {
    Broadcast::channel('test-orders.{orderId}', fn () => true);
    Broadcast::channel('test-announcements', fn () => true);

    $collector = resolve(BroadcastChannelsCollector::class);
    $channels = $collector->collect();

    expect($channels)->toContain('test-orders.{orderId}')
        ->and($channels)->toContain('test-announcements');
});

test('collect includes workbench channels registered from channels.php', function () {
    $collector = resolve(BroadcastChannelsCollector::class);
    $channels = $collector->collect();

    expect($channels)->toContain('orders.{orderId}')
        ->and($channels)->toContain('user.{userId}.notifications')
        ->and($channels)->toContain('chat.{roomId}.messages')
        ->and($channels)->toContain('public-announcements');
});

test('collect returns channel names for class-based channel registrations', function () {
    // Broadcast::channel accepts either a closure or a channel class string.
    // BroadcastManager stores both under the same channel-name key, so the
    // collector reads them identically via array_keys(getChannels()).
    Broadcast::channel('test-class-based.{id}', PublicAnnouncementsChannel::class);

    $collector = resolve(BroadcastChannelsCollector::class);
    $channels = $collector->collect();

    expect($channels)->toContain('test-class-based.{id}');
});

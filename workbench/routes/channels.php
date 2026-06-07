<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Workbench\App\Broadcasting\PublicAnnouncementsChannel;

Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return true;
});

Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('chat.{roomId}.messages', function ($user, $roomId) {
    return true;
});

// Registered using a channel class instead of a closure.
// The channel name string is still the key — the collector treats it identically.
Broadcast::channel('public-announcements', PublicAnnouncementsChannel::class);

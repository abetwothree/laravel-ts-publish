<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Workbench\App\Broadcasting\ColorThemeChannel;
use Workbench\App\Broadcasting\OrderChannel;
use Workbench\App\Broadcasting\OrderStatusChannel;
use Workbench\App\Broadcasting\PostCommentChannel;
use Workbench\App\Broadcasting\PublicAnnouncementsChannel;
use Workbench\App\Broadcasting\RoleDashboardChannel;
use Workbench\App\Broadcasting\TeamRoomChannel;

// ──────────────────────────────────────────────────────────────────────────────
// Closure-based registrations (original fixture)
// ──────────────────────────────────────────────────────────────────────────────

Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return true;
});

Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('chat.{roomId}.messages', function ($user, $roomId) {
    return true;
});

// ──────────────────────────────────────────────────────────────────────────────
// Class-based registrations — the join() parameter types only affect PHP-side
// authorization. The channel name string drives the TypeScript output in every
// case, so `string | number` is the wildcard type regardless of whether a
// wildcard resolves to a Model, an Enum, or a primitive at runtime.
// ──────────────────────────────────────────────────────────────────────────────

// No types — original class-based example (static channel, no wildcards).
Broadcast::channel('public-announcements', PublicAnnouncementsChannel::class);

// 1 model — join(User $user, Order $order) via route model binding.
Broadcast::channel('order.{orderId}', OrderChannel::class);

// 2 related models — join(User $user, Post $post, Comment $comment).
Broadcast::channel('post.{postId}.comment.{commentId}', PostCommentChannel::class);

// Int-backed enum — join(User $user, Status $status); wildcard coerced to Status.
Broadcast::channel('order-status.{statusId}', OrderStatusChannel::class);

// String-backed enum — join(User $user, Color $color); wildcard coerced to Color.
Broadcast::channel('color-theme.{colorId}', ColorThemeChannel::class);

// Non-backed (pure) enum — join(User $user, Role $role); matched by case name.
Broadcast::channel('role-dashboard.{roleId}', RoleDashboardChannel::class);

// Primitives — join(User $user, int $teamId, string $roomName); no binding.
Broadcast::channel('teams.{teamId}.rooms.{roomName}', TeamRoomChannel::class);

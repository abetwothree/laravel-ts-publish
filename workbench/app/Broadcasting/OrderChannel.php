<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

use Workbench\App\Models\Order;
use Workbench\App\Models\User;

/**
 * Channel class demonstrating 1-model parameter authorization.
 *
 * Laravel resolves the {orderId} wildcard via route model binding,
 * injecting the Order instance into join(). The channel NAME is still
 * the string 'order.{orderId}' — the TypeScript output is identical
 * to a closure-based registration with the same channel name.
 */
class OrderChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  User  $user  The authenticated user.
     * @param  Order  $order  The order resolved via route model binding.
     */
    public function join(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}

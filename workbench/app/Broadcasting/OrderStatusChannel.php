<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

use Workbench\App\Enums\Status;
use Workbench\App\Models\User;

/**
 * Channel class demonstrating an int-backed enum parameter.
 *
 * Laravel coerces the {statusId} wildcard (an integer value) into a Status
 * enum instance before passing it to join(). The TypeScript output treats
 * {statusId} as `string | number` — the backing type is a PHP concern only.
 */
class OrderStatusChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  User  $user  The authenticated user.
     * @param  Status  $status  The order status coerced from the channel wildcard.
     */
    public function join(User $user, Status $status): bool
    {
        return true;
    }
}

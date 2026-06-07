<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

use Workbench\App\Models\User;

/**
 * Channel class demonstrating primitive (int + string) parameters.
 *
 * {teamId} and {roomName} are passed as-is (int and string) without any
 * model binding or enum coercion. The TypeScript output treats both as
 * `string | number` — primitive types are a PHP concern only.
 */
class TeamRoomChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  User  $user  The authenticated user.
     * @param  int  $teamId  The team identifier from the channel wildcard.
     * @param  string  $roomName  The room name from the channel wildcard.
     */
    public function join(User $user, int $teamId, string $roomName): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

/**
 * Channel class for the public-announcements channel.
 *
 * Demonstrates class-based channel authorization (an alternative to
 * inline closures in routes/channels.php). The channel name is still
 * registered in channels.php; only the authorization handler differs.
 */
class PublicAnnouncementsChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     */
    public function join(): bool
    {
        return true;
    }
}

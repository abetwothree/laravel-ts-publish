<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

/**
 * Channel class demonstrating a non-backed (pure) enum parameter.
 *
 * Non-backed enums have no scalar value, so Laravel matches the {roleId}
 * wildcard by case name (e.g. 'Admin', 'Editor'). The TypeScript output
 * still treats {roleId} as `string | number` — enum resolution is PHP-only.
 */
class RoleDashboardChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  User  $user  The authenticated user.
     * @param  Role  $role  The role resolved from the channel wildcard by case name.
     */
    public function join(User $user, Role $role): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

use Workbench\App\Enums\Color;
use Workbench\App\Models\User;

/**
 * Channel class demonstrating a string-backed enum parameter.
 *
 * Laravel coerces the {colorId} wildcard (a string value) into a Color
 * enum instance before passing it to join(). The TypeScript output treats
 * {colorId} as `string | number` — the backing type is a PHP concern only.
 */
class ColorThemeChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  User  $user  The authenticated user.
     * @param  Color  $color  The theme color coerced from the channel wildcard.
     */
    public function join(User $user, Color $color): bool
    {
        return true;
    }
}

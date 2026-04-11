<?php

declare(strict_types=1);

namespace Workbench\Accounting\Http\Controllers;

/**
 * Handles two-factor authentication for the accounting module.
 */
class TwoFactorController
{
    /** Set up 2FA for the current user. */
    public function setup(): void {}

    /** Verify a 2FA code. */
    public function verify(): void {}
}

<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

enum Role
{
    case Admin;
    case User;
    case Guest;

    /** Should NOT be published — no TsEnumMethod attribute */
    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    /** Should NOT be published — no TsEnumStaticMethod attribute */
    public static function privilegedRoles(): array
    {
        return [self::Admin];
    }
}

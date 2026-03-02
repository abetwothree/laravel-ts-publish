<?php

namespace Workbench\App\Enums;

/**
 * Pure unit enum with no attributes and no methods.
 * Tests the absolute minimal enum path.
 */
enum MembershipLevel
{
    case Free;
    case Basic;
    case Premium;
    case Enterprise;

    /** Should NOT be published — no TsEnumMethod attribute */
    public function monthlyPriceCents(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Basic => 999,
            self::Premium => 2999,
            self::Enterprise => 9999,
        };
    }

    /** Should NOT be published — no TsEnumStaticMethod attribute */
    public static function paidLevels(): array
    {
        return [self::Basic, self::Premium, self::Enterprise];
    }
}

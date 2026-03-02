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
}

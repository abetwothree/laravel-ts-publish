<?php

declare(strict_types=1);

namespace Workbench\Crm\Enums;

/**
 * Publishes the const `ClearanceType`, the name App's Clearance enum publishes its type under.
 */
enum ClearanceType: string
{
    case Temporary = 'temporary';
    case Permanent = 'permanent';
}

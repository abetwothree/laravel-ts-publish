<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

/**
 * Publishes the type `ClearanceType`, the name Crm's ClearanceType enum publishes its const under.
 */
enum Clearance: string
{
    case Open = 'open';
    case Restricted = 'restricted';
}

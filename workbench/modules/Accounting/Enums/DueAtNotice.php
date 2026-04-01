<?php

declare(strict_types=1);

namespace Workbench\Accounting\Enums;

enum DueAtNotice: string
{
    case ComingUp = 'Payment due date is coming up';
    case DueToday = 'Payment is due today';
    case PastDue = 'Payment is past due';
}

<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

/**
 * Shares its name with the Grade model, whose own file imports this enum's const.
 */
enum Grade: string
{
    case Pass = 'pass';
    case Fail = 'fail';
}

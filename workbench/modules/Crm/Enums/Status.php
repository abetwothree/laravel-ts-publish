<?php

declare(strict_types=1);

namespace Workbench\Crm\Enums;

enum Status: string
{
    case Lead = 'lead';
    case Prospect = 'prospect';
    case Active = 'active';
    case Churned = 'churned';
}

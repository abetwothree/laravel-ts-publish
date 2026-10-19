<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only class whose member is an array of either of two models that share a name, under any keys. Each arm of
 * its docblock union renders two tokens, `User[] | Record<string, User>`, with one class behind them.
 */
class HandoverKeyedRoster
{
    /** @var array<array-key, User>|array<array-key, CrmUser> */
    public array $members = [];
}

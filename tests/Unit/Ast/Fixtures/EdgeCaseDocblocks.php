<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\User;

final class EdgeCaseDocblocks
{
    /** @var array{op: '>'} The operator used. */
    public array $comparison = [];

    /** @var |User */
    public ?object $owner = null;
}

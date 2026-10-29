<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

/**
 * A plain class that publishes the override of the interface it implements.
 */
final class Badge implements HasIcon
{
    public function __construct(public string $name = 'star') {}
}

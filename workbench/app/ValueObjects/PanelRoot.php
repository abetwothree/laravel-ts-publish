<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: the root of the panel payload chain, whose static `root()` a subclass body reaches through `parent::`.
 */
abstract class PanelRoot
{
    public static function root(): int
    {
        return 1;
    }
}

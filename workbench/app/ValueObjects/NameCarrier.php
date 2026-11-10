<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * A class only a write's `@var` names, whose `name` has no type (GH-154).
 */
final class NameCarrier
{
    public $name;
}

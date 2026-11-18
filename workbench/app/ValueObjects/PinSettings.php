<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Settings a resource holds beside its model, whose flag a resource may read through `?->` and write through `->`.
 */
final class PinSettings
{
    public bool $enabled = true;
}

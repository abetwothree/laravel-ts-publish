<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * A manifest with only protected and private properties, which json_encode() writes as `{}`.
 */
final class SealedManifest
{
    protected string $seal = 'wax';

    private int $weight = 12;
}

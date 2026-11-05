<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;

/** An interface declaring properties and a method, which an intersection names beside a model that declares none. */
interface HasCode
{
    public string $code { get; }

    public HeldStats $stats { get; }

    /** The stats the code was read from. */
    public function codeStats(): HeldStats;
}

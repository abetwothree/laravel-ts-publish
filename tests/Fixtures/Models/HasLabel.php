<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** An interface a model implements, which an intersection names beside the model. */
interface HasLabel
{
    /** A display label. */
    public function label(): string;
}

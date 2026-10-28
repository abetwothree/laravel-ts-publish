<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

/**
 * Declares a class whose inherited override already holds null as a nullable property and a nullable method return.
 */
final class MaybeHolder
{
    public ?MaybeChild $child = null;

    /**
     * The child, when there is one.
     */
    public function child(): ?MaybeChild
    {
        return $this->child;
    }
}

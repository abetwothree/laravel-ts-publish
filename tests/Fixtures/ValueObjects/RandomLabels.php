<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Picks one of its labels, or several by count, under a return type its argument decides. */
final class RandomLabels
{
    /**
     * @param  list<string>  $labels
     */
    public function __construct(public array $labels) {}

    /**
     * Picks one label, or a list of the first ones.
     *
     * @return ($number is null ? string : list<string>)
     */
    public function pick(?int $number = null)
    {
        return $number === null ? $this->labels[0] : array_slice($this->labels, 0, $number);
    }
}

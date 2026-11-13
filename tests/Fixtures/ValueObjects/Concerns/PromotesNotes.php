<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** Promotes a property that only this trait's constructor `@param` types. */
trait PromotesNotes
{
    /**
     * Hold the given notes.
     *
     * @param  list<string>  $notes
     */
    public function __construct(public array $notes = []) {}
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A model-less subject whose methods write most of its untyped defaults, so only `label` keeps its default's type. */
class ReassignedDefaultsSubject
{
    protected $note = '';

    protected $tags = ['news', 'guide'];

    protected $views = 0;

    protected $label = 'draft';

    /** Replaces the note's default with a value that may be null. */
    public function __construct(?string $note = null)
    {
        $this->note = $note;
    }

    /** Appends an element the default's element type does not describe. */
    public function tag(): void
    {
        $this->tags[] = 5;
    }

    /** Counts a view. */
    public function view(): void
    {
        $this->views++;
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * A sheet of readings whose own declarations type less than a `toArray()` `@return` docblock does (GH-154): a
 * docblock list with a `mixed` member, an array with no element type, and a note with no type at all.
 */
final class ReadingSheet
{
    /** @var list<array{label: mixed}> */
    public array $entries = [];

    public array $raw = [];

    public $note;
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read;

/**
 * The forms of a whole array a method returns or builds a variable from, as BaseForms::classify() reads them.
 *
 * @internal
 */
enum BaseForm
{
    /** An array literal. */
    case Literal;

    /** A local whose read sees one write, read as the base that write holds. */
    case Local;

    /** A `+` of two bases. */
    case Plus;

    /** An `array_merge()` of literals and `parent::` calls, which the host reads as one literal. */
    case Merge;

    /** A base only the host reads: a `parent::` call, a model filter, a `$this->` helper. */
    case Host;
}

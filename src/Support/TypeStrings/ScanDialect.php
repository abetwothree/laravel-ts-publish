<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

/**
 * The grammar a type string is written in, which decides what the type scanner reads as literal text.
 *
 * @internal
 */
enum ScanDialect
{
    /** A TypeScript type: quotes and template literals are literal text, and an arrow owns the union after it. */
    case TypeScript;

    /** A PHPDoc type: only quotes are literal text, and neither a backtick nor `=>` means anything. */
    case PhpDoc;
}

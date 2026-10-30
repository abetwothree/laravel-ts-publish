<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

/**
 * A key an element write reaches without spelling it, as ArrayArm applies the write.
 *
 * @internal
 */
enum ElementKey
{
    /** `$a[] = …`: the next int key. */
    case Next;

    /** `$a[$k] = …` or `unset($a[$k])`: any key, an int or a string. */
    case Any;

    /** A by-reference `foreach`: every key the array holds, none added. */
    case Every;
}

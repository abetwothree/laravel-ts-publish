<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

/**
 * Why an array may hold keys no name the engine reads names.
 *
 * @internal
 */
enum OpaqueKind
{
    /** A spread of a value the engine cannot read as a closed shape. */
    case UnreadSpread;

    /** A spread of a map whose value the engine cannot type. */
    case UntypedMap;

    /** A spread of a value that may hold keys beside the ones it names. */
    case OpenValue;

    /** A whole write the walk cannot read. */
    case UnreadWholeWrite;

    /** A key write whose key the walk cannot name. */
    case UnnamedKeyWrite;

    /** A merge of a value no rule reads. */
    case UnreadMerge;

    /** A by-reference call that appends under keys the walk never names, as array_push() does. */
    case ByReferenceAppend;
}

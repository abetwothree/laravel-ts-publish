<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;

/**
 * One key's value: its row less its name, which the entry holding it keeps, and each channel map's entry under that
 * name, as MethodAnalysis::sliceOf() takes them. The row keeps the presence it was read with, so a key whose presence
 * no operation changed lowers to the row it was; the entry's presence decides.
 *
 * @phpstan-import-type CastInForce from MethodAnalysis
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type HeldNulls from ExpressionHandler
 *
 * @phpstan-type KeyRow = array{
 *     type: string,
 *     optional: bool,
 *     description: string,
 *     bodyType?: string,
 *     fillType?: string,
 *     keepsKeys?: bool,
 *     splitOf?: string,
 *     absent?: true,
 *     removed?: true,
 * }
 * @phpstan-type KeyChannels = array{
 *     nestedResources?: class-string,
 *     directEnumFqcns?: class-string,
 *     modelFqcns?: class-string,
 *     inlineEnumFqcns?: list<class-string>,
 *     inlineModelFqcns?: list<class-string>,
 *     inlineResourceFqcns?: list<class-string>,
 *     inlineEnumResourceFqcns?: list<class-string>,
 *     wrappedEnums?: list<class-string>,
 *     casts?: CastInForce,
 *     declaredTokenFqcns?: Slots,
 *     alikeClassFqcns?: list<class-string>,
 *     heldObjectKeys?: HeldNulls,
 *     closedToDottedKeys?: true,
 * }
 *
 * @internal
 */
final readonly class KeyValue
{
    /**
     * @param  KeyRow  $row  the row's fields but its name, in the order the row holds them
     * @param  KeyChannels  $channels  each channel map's entry under the key's name, where it holds one
     */
    public function __construct(
        public array $row,
        public array $channels = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;

/**
 * The values of a discriminant whose dependents all take the same arms, beside what each dependent sends for them.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type DiscriminantValueList from DiscriminantValue
 *
 * @internal
 */
final readonly class Variant
{
    /**
     * Values beside their dependents' arms.
     *
     * @param  DiscriminantValueList  $values  in the order the discriminant sends them
     * @param  array<string, ValueExpressionResult|null>  $dependents  each dependent's arm, null where it is removed
     */
    public function __construct(
        public array $values,
        public array $dependents,
    ) {}

    /**
     * The discriminant's type in this variant: each value's literal, both booleans together as `boolean`.
     */
    public function literal(): string
    {
        $sent = array_column($this->values, 'value');
        $boolean = in_array(true, $sent, true) && in_array(false, $sent, true);

        return implode(' | ', array_unique(array_map(
            static fn (DiscriminantValue $value): string => $boolean && is_bool($value->value) ? 'boolean' : $value->literal,
            $this->values,
        )));
    }
}

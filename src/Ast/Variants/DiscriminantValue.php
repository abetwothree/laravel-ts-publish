<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use BackedEnum;
use UnitEnum;

/**
 * One value a key can send, as PHP holds it, beside the TypeScript literal its published type spells it with. An enum
 * case is held as the case, whose literal is the scalar its key's alias spells.
 *
 * @phpstan-type DiscriminantValueList = list<DiscriminantValue>
 *
 * @internal
 */
final readonly class DiscriminantValue
{
    /**
     * A value beside the literal it prints as.
     */
    public function __construct(
        public bool|int|float|string|UnitEnum|null $value,
        public string $literal,
    ) {}

    /**
     * What a case's `->value` or `->name` holds, under this value's literal; null for a scalar, a pure case's `->value`
     * or any other member.
     */
    public function member(string $name): ?self
    {
        $case = $this->value;

        return match (true) {
            ! $case instanceof UnitEnum => null,
            $name === 'name' => new self($case->name, $this->literal),
            $name === 'value' && $case instanceof BackedEnum => new self($case->value, $this->literal),
            default => null,
        };
    }
}

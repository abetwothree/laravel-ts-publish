<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use ArrayObject;

/**
 * A test-only payload whose helpers overwrite a key by spreading a function, a parent method or a chained call, also
 * inside a nested array, an argument or a helper read as a value.
 */
class OverwrittenSpreadBase extends OverwrittenSpreadGrand
{
    /** @return array<string, mixed> */
    protected function m(): array
    {
        return ['a' => 1, ...array_merge(['a' => 'x'], [])];
    }

    /** @return array<string, mixed> */
    protected function viaParent(): array
    {
        return ['a' => 1, ...parent::g()];
    }

    /** @return array<string, mixed> */
    protected function chained(): array
    {
        return ['a' => 1, ...new ArrayObject(['a' => 'x'])->getArrayCopy()];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return [...parent::toArray(), 'e' => 1];
    }

    /** @return array<string, mixed> */
    protected function nestedLiteral(): array
    {
        return ['v' => ['k' => 1, ...array_merge(['k' => 'x'], [])], 'b' => true];
    }

    /** @return array<string, mixed> */
    protected function deeperLiteral(): array
    {
        return ['v' => ['w' => ['k' => 1, ...array_merge(['k' => 'x'], [])]], 'b' => true];
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    protected function withArgument(array $v): array
    {
        return ['v' => $v, 'b' => true];
    }

    /** @return array<string, mixed> */
    protected function nestedHelper(): array
    {
        return ['w' => $this->m(), 'b' => true];
    }
}

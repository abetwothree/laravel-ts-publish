<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

/**
 * One index signature entry of an array shape, such as `[key: string]`: one more runtime key it covers, never optional.
 *
 * Its value is held as the row prints it, `| undefined` included: a printed value cannot be read back raw, since the
 * print adds no `undefined` where the value already held one.
 *
 * @internal
 */
final readonly class ShapeSignature
{
    /**
     * Hold a signature's pattern beside its value.
     */
    public function __construct(
        public string $pattern,
        public KeyValue $value,
    ) {}

    /**
     * The name the entry is written under: the signature's pattern.
     */
    public function writtenName(): string
    {
        return $this->pattern;
    }
}

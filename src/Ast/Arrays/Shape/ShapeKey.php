<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

/**
 * One named key of an array shape: its name as PHP stores it, its value and whether it is sent.
 *
 * @internal
 */
final readonly class ShapeKey
{
    /**
     * @param  bool  $armOnly  whether only a spread its literal prints as an arm writes it
     */
    public function __construct(
        public string $name,
        public KeyValue $value,
        public KeyPresence $presence,
        public bool $armOnly = false,
    ) {}
}

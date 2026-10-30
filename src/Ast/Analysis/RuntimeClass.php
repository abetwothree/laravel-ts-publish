<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use ReflectionClass;

/**
 * The class `$this` and `static` are when a body runs, and whether the analysis knows it or only assumes it, since the
 * object may be a subclass; which is which: docs/components/ast-engine.md § Known or assumed runtime class.
 *
 * @internal
 */
final readonly class RuntimeClass
{
    /**
     * @param  ReflectionClass<object>  $class
     */
    private function __construct(
        public ReflectionClass $class,
        public bool $known,
    ) {}

    /**
     * A runtime class the analysis knows, as the published class's own scope does.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function known(ReflectionClass $class): self
    {
        return new self($class, true);
    }

    /**
     * A runtime class the analysis only assumes, since the object may be a subclass of it: a receiver's declared class,
     * the model `$this->resource` forwards to, or the object a method macro's callable holds.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function assumed(ReflectionClass $class): self
    {
        return new self($class, false);
    }

    /**
     * The runtime class of an object the code holds as an instance of a class: assumed, since it may be a subclass.
     *
     * @param  class-string  $class
     */
    public static function held(string $class): self
    {
        return self::assumed(new ReflectionClass($class));
    }

    /**
     * The runtime class of an object the container fills: known only for a final class, since the container may bind
     * a subclass in place of any other.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function injected(ReflectionClass $class): self
    {
        return new self($class, $class->isFinal());
    }

    /**
     * Another class, known or assumed as this one is.
     *
     * @param  ReflectionClass<object>  $class
     */
    public function withClass(ReflectionClass $class): self
    {
        return new self($class, $this->known);
    }

    /**
     * The memo key suffix that tells a body's reads for this runtime class apart: none for the class read itself, since
     * that read is the plain one, and `@assumed` where the runtime class is only assumed.
     */
    public function memoKey(string $classRead): string
    {
        $name = $this->class->getName();

        return ($name === $classRead ? '' : '@runtime:'.$name).($this->known ? '' : '@assumed');
    }
}

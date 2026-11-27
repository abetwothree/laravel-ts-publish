<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Support;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use LogicException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Lifts each analysis it sees into an ArrayShape, lowers it into a fresh analysis of the same class, and throws on the
 * first field that differs, so the test whose read made the analysis fails with it. It compares every instance field
 * the analysis holds, so a field the lift does not carry fails too.
 */
final class RoundTripShapeObserver implements ShapeObserver
{
    /** @var array<class-string, list<ReflectionProperty>> */
    private static array $fields = [];

    /** The first failure, kept because a read that catches every throwable would swallow the exception. */
    private ?string $failure = null;

    /**
     * Each instance field an analysis class holds, its parents' private ones included.
     *
     * @param  class-string<MethodAnalysis>  $class
     * @return list<ReflectionProperty>
     */
    public static function fields(string $class): array
    {
        if (isset(self::$fields[$class])) {
            return self::$fields[$class];
        }

        $fields = [];

        for ($reflection = new ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (! $property->isStatic() && $property->getDeclaringClass()->getName() === $reflection->getName()) {
                    $fields[] = $property;
                }
            }
        }

        return self::$fields[$class] = $fields;
    }

    /**
     * Throw where two analyses hold a field apart, naming the first and showing both values.
     */
    public static function assertSameFields(MethodAnalysis $analysis, MethodAnalysis $rebuilt): void
    {
        $message = self::difference($analysis, $rebuilt);

        if ($message !== null) {
            throw new LogicException($message);
        }
    }

    /**
     * Lift, lower and compare one analysis, throwing on the first field that differs.
     */
    public function observed(MethodAnalysis $analysis): void
    {
        $rebuilt = new ($analysis::class);
        ShapeLowering::into($rebuilt, ArrayShapes::ofAnalysis($analysis));
        $message = self::difference($analysis, $rebuilt);

        if ($message !== null) {
            $this->failure ??= $message;

            throw new LogicException($message);
        }
    }

    /**
     * Throw the first failure again where a read caught it, so the test that made the analysis still fails.
     */
    public function rethrowCaught(): void
    {
        if ($this->failure !== null) {
            throw new LogicException($this->failure);
        }
    }

    /**
     * The first field two analyses hold apart, compared with `===`, order included, with both values; null where none.
     */
    private static function difference(MethodAnalysis $analysis, MethodAnalysis $rebuilt): ?string
    {
        if ($analysis::class !== $rebuilt::class) {
            return 'The shape round trip changed the class: `'.$analysis::class.'` became `'.$rebuilt::class.'`.';
        }

        foreach (self::fields($analysis::class) as $field) {
            $held = $field->getValue($analysis);
            $lowered = $field->getValue($rebuilt);

            if ($lowered !== $held) {
                return sprintf(
                    "The shape round trip changed `%s`:\n%s\nbecame\n%s",
                    $field->getName(),
                    var_export($held, true),
                    var_export($lowered, true),
                );
            }
        }

        return null;
    }
}

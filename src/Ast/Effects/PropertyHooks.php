<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use PropertyHookType;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Reads what a PHP 8.4 property hook does to a read or a write of a `$this` path, on the class each step reaches: the
 * class `$this` declares the first step on, else, on an API resource, the classes `$this->resource` holds, which an
 * undeclared first step and the step after `resource` read; a later step the one class the step before declares.
 *
 * @phpstan-type HookRun = array{ReflectionClass<object>|null, string}
 * @phpstan-type PathStep = array{string, list<ReflectionClass<object>>, bool}
 *
 * @internal
 */
final readonly class PropertyHooks
{
    /**
     * Hooks read for a `$this` that is an instance of $runtime.
     *
     * @param  ReflectionClass<object>  $runtime
     * @param  list<class-string>  $held  the classes `$this->resource` holds, where an API resource forwards to it
     */
    public function __construct(private ReflectionClass $runtime, private bool $proxied, private array $held) {}

    /**
     * Hooks read in a scope: its runtime class, and on an API resource the backing class and the other classes it
     * declares `$this->resource` to hold.
     */
    public static function of(AnalysisScope $scope): self
    {
        return new self(
            $scope->runtime->class,
            $scope->hasResourceProxy(),
            array_values(array_unique(array_filter([$scope->forwardsUndeclaredMembersTo, ...$scope->declaredHeld ?? []]))),
        );
    }

    /**
     * Whether the class declares or inherits the property with a get hook, which runs on every read of it.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function runsGetHook(ReflectionClass $class, string $property): bool
    {
        return $class->hasProperty($property) && $class->getProperty($property)->hasHook(PropertyHookType::Get);
    }

    /**
     * Whether the class declares or inherits the property with a set hook, which runs on every write of it.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function runsSetHook(ReflectionClass $class, string $property): bool
    {
        return $class->hasProperty($property) && $class->getProperty($property)->hasHook(PropertyHookType::Set);
    }

    /**
     * What the hooks read here hang on besides the path, for a memo key.
     */
    public function key(): string
    {
        return $this->runtime->getName().($this->proxied ? '@proxied' : '').'@'.implode('|', $this->held);
    }

    /**
     * The get hooks reading a `$this` path runs, at any step: each as its property's class where it runs on `$this`,
     * else null, since its body runs with another `$this`, beside its property's name.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is read
     * @return list<HookRun>
     */
    public function getHooks(ReadPath $path, ReflectionClass $declaring): array
    {
        $runs = [];

        foreach ($this->steps($path, $declaring, true) as [$property, $classes, $onThis]) {
            foreach ($classes as $class) {
                if (self::runsGetHook($class, $property)) {
                    $runs[] = [$onThis ? $class : null, $property];
                }
            }
        }

        return $runs;
    }

    /**
     * The set hook writing a `$this` path runs on its last step, as getHooks() lists one.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is written
     * @return list<HookRun>
     */
    public function setHooks(ReadPath $path, ReflectionClass $declaring): array
    {
        $steps = $this->steps($path, $declaring, false);
        [$property, $classes, $onThis] = $steps[count($steps) - 1] ?? ['', [], false];

        return array_values(array_map(
            static fn (ReflectionClass $class): array => [$onThis ? $class : null, $property],
            array_filter($classes, static fn (ReflectionClass $class): bool => self::runsSetHook($class, $property)),
        ));
    }

    /**
     * The classes a property holds as each of the classes declares it: the one class its native type names.
     *
     * @param  list<ReflectionClass<object>>  $classes
     * @return list<ReflectionClass<object>>
     */
    private static function declaredClasses(array $classes, string $property): array
    {
        $held = [];

        foreach ($classes as $class) {
            $type = $class->hasProperty($property) ? $class->getProperty($property)->getType() : null;

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && class_exists($type->getName())) {
                $held[] = new ReflectionClass($type->getName());
            }
        }

        return $held;
    }

    /**
     * Each step of a `$this` path in order, as its property, the classes it reads that on and whether that is `$this`;
     * no class past a step whose class the engine cannot name, and no step for any other root.
     *
     * @param  ReflectionClass<object>  $declaring
     * @param  bool  $reads  whether the last step is read, as a first step `$this` lacks is through the resource's
     *                       `__get()`, which no `__set()` matches for a write
     * @return list<PathStep>
     */
    private function steps(ReadPath $path, ReflectionClass $declaring, bool $reads): array
    {
        $first = $path->segments[0] ?? null;

        if ($path->root !== 'this' || $first === null) {
            return [];
        }

        $own = AnalysisScope::memberClassOf($declaring, $this->runtime, $first, false);
        $forwarded = $this->proxied && ! $own->hasProperty($first) && ($reads || count($path->segments) > 1);
        $classes = $forwarded ? $this->heldClasses() : [$own];
        $steps = [];

        foreach ($path->segments as $index => $property) {
            $steps[] = [$property, $classes, $index === 0 && ! $forwarded];
            $classes = $index === 0 && $this->proxied && $property === 'resource'
                ? $this->heldClasses()
                : self::declaredClasses($classes, $property);
        }

        return $steps;
    }

    /**
     * The classes `$this->resource` holds.
     *
     * @return list<ReflectionClass<object>>
     */
    private function heldClasses(): array
    {
        return array_map(static fn (string $class): ReflectionClass => new ReflectionClass($class), $this->held);
    }
}

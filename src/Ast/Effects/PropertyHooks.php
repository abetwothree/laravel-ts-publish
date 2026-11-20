<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PropertyHookType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Reads what a PHP 8.4 property hook, or an Eloquent model's set mutator, does to a read or a write of a `$this` path,
 * on the classes each step reaches: the class `$this` declares the first step on, else, on an API resource, the classes
 * `$this->resource` holds, which an undeclared first step and the step after `resource` read; a later step the classes
 * the type of the step before names.
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
        $held = array_filter([$scope->forwardsUndeclaredMembersTo, ...$scope->declaredHeld ?? []]);

        return new self($scope->runtime->class, $scope->hasResourceProxy(), array_values(array_unique($held)));
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
     * The get hooks a class declares that reading a `$this` path runs, at any step: each as its property's class where
     * it runs on `$this`, else null, since its body runs with another `$this`, beside its property's name.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is read
     * @return list<HookRun>
     */
    public function getHooks(ReadPath $path, ReflectionClass $declaring): array
    {
        $runs = [];

        foreach ($this->steps($path, $declaring, true) as [$property, $classes, $onThis]) {
            foreach ($classes as $class) {
                if (! $class->isInterface() && self::runsGetHook($class, $property)) {
                    $runs[] = [$onThis ? $class : null, $property];
                }
            }
        }

        return $runs;
    }

    /**
     * Whether reading a `$this` path may run a get hook at any step: on `$this`, the class published, one it declares;
     * past it, one the object's own class may add, since a child may hook a property its parent leaves unsealed.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is read
     */
    public function mayRunGetHook(ReadPath $path, ReflectionClass $declaring): bool
    {
        foreach ($this->steps($path, $declaring, true) as [$property, $classes, $onThis]) {
            foreach ($classes as $class) {
                if ($onThis ? self::runsGetHook($class, $property) : self::mayHook($class, $property)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The set hook writing a `$this` path runs on its last step, as getHooks() lists one, or the set mutator a model
     * runs for an attribute its `__set()` sets, or looks one up through, which runs with the model as `$this`.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is written
     * @return list<HookRun>
     */
    public function setHooks(ReadPath $path, ReflectionClass $declaring): array
    {
        $steps = $this->steps($path, $declaring, false);
        [$property, $classes, $onThis] = $steps[count($steps) - 1] ?? ['', [], false];
        $runs = [];

        foreach ($classes as $class) {
            if ($class->isInterface()) {
                continue;
            }

            if (self::runsSetHook($class, $property)) {
                $runs[] = [$onThis ? $class : null, $property];
            } elseif ($class->isSubclassOf(Model::class) && ! $class->hasProperty($property)
                && self::runsSetMutator($class, $property)
            ) {
                $runs[] = [null, $property];
            }
        }

        return $runs;
    }

    /**
     * Whether the property a `$this` read starts at, past `resource` on an API resource, is one its class declares,
     * which a by-reference parameter binds in place, rather than one read through `__get()`, which hands back a copy
     * unless it returns by reference; a bare local binds in place.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is read
     */
    public function bindsInPlace(ReadPath $path, ReflectionClass $declaring): bool
    {
        if ($path->root !== 'this' && $path->segments === []) {
            return true;
        }

        $index = $this->proxied && ($path->segments[0] ?? null) === 'resource' ? 1 : 0;
        [$property, $classes, $onThis] = $this->steps($path, $declaring, true)[$index] ?? ['', [], false];
        $forwarded = $index === 0 && ! $onThis;

        if ($forwarded && ! ($this->runtime->hasMethod('__get') && $this->runtime->getMethod('__get')->returnsReference())) {
            return false;
        }

        return array_any($classes, static fn (ReflectionClass $class): bool => $class->hasProperty($property));
    }

    /**
     * The classes `$this->resource` holds.
     *
     * @return list<ReflectionClass<object>>
     */
    public function heldClasses(): array
    {
        return array_map(static fn (string $class): ReflectionClass => new ReflectionClass($class), $this->held);
    }

    /**
     * The classes the object a `$this` path reads may be, as its steps name them; none past a step whose class the
     * engine cannot name, and none for any other root.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the path is read
     * @return list<ReflectionClass<object>>
     */
    public function classesOf(ReadPath $path, ReflectionClass $declaring): array
    {
        return $this->walk($path, $declaring, true)[1];
    }

    /**
     * Whether a write of an attribute runs the model's code, as Eloquent's setAttribute() looks for a mutator: a
     * `setXAttribute()` method, or the method named for it returning an `Attribute`, which it runs to read the setter.
     *
     * @param  ReflectionClass<Model>  $model
     */
    private static function runsSetMutator(ReflectionClass $model, string $attribute): bool
    {
        $method = Str::camel($attribute);

        if ($model->hasMethod('set'.Str::studly($attribute).'Attribute')) {
            return true;
        }

        $type = $model->hasMethod($method) ? $model->getMethod($method)->getReturnType() : null;

        return $type instanceof ReflectionNamedType && $type->getName() === Attribute::class
            && self::attributeMayWrite($model, $method);
    }

    /**
     * Whether running a method returning an `Attribute` may write the model or give it a setter: a body that does more
     * than return one value, or cannot be read, may; one that returns a value may as building it may (buildsSetter()).
     *
     * @param  ReflectionClass<Model>  $model
     */
    private static function attributeMayWrite(ReflectionClass $model, string $method): bool
    {
        $body = resolve(MethodLocator::class)->locate($model->getName(), $method)?->method->stmts;
        $returned = $body === null ? null : StatementBlocks::soleReturn($body);

        return $returned === null || self::buildsSetter($returned);
    }

    /**
     * Whether building a returned value may run code or give it a setter: anything but one the builders of Eloquent's
     * own Attribute make with no setter or a `null` one, from arguments that run nothing, with each method called on it
     * named and handed nothing; an unpack it cannot read may hold a setter.
     */
    private static function buildsSetter(Expr $built): bool
    {
        while ($built instanceof MethodCall) {
            if (! $built->name instanceof Identifier || $built->getRawArgs() !== []) {
                return true;
            }

            $built = $built->var;
        }

        if ($built instanceof StaticCall && $built->class instanceof Name && $built->name instanceof Identifier
            && self::namesAttribute($built->class)
            && in_array($built->name->toLowerString(), ['make', 'get', 'set'], true)
        ) {
            $arguments = CallArguments::for($built, new ReflectionMethod(Attribute::class, $built->name->toString()));
        } elseif ($built instanceof New_ && $built->class instanceof Name && self::namesAttribute($built->class)) {
            $arguments = CallArguments::for($built, new ReflectionMethod(Attribute::class, '__construct'));
        } else {
            return true;
        }

        $setter = $arguments->named('set')?->value;

        return $arguments->hasUnpack()
            || ($setter !== null && ! ($setter instanceof ConstFetch && $setter->name->toLowerString() === 'null'))
            || ! array_all($arguments->bound(), static fn (Arg $argument): bool => self::runsNothing($argument->value));
    }

    /**
     * Whether a name is Eloquent's own `Attribute`, whose builders only store what they are handed; a subclass may
     * override one, or the constructor they all run, to call it.
     */
    private static function namesAttribute(Name $class): bool
    {
        return strcasecmp(ltrim($class->toString(), '\\'), Attribute::class) === 0;
    }

    /**
     * Whether passing a value runs no code: a closure or an arrow function written there, a constant, or a first-class
     * callable of a function, of a method of `$this` or of a named class, whose receiver PHP reads with no code run.
     */
    private static function runsNothing(Expr $value): bool
    {
        if (ClosureReach::isClosure($value) || $value instanceof ConstFetch) {
            return true;
        }

        return $value instanceof CallLike && $value->isFirstClassCallable() && match (true) {
            $value instanceof FuncCall => $value->name instanceof Name,
            $value instanceof MethodCall, $value instanceof NullsafeMethodCall => ReadPath::isThis($value->var)
                && $value->name instanceof Identifier,
            $value instanceof StaticCall => $value->class instanceof Name && $value->name instanceof Identifier,
            default => false,
        };
    }

    /**
     * Whether an object of a class may run a get hook on a read of a property: an interface's implementer may, and so
     * may the class itself, or a child of it where neither the class nor the public property is final or readonly.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function mayHook(ReflectionClass $class, string $property): bool
    {
        if ($class->isInterface() || self::runsGetHook($class, $property)) {
            return true;
        }

        $declared = $class->hasProperty($property) ? $class->getProperty($property) : null;

        // A read from outside reaches a property that is not public through `__get()`, as Eloquent's $visible.
        return ! $class->isFinal() && $declared !== null && $declared->isPublic()
            && ! $declared->isFinal() && ! $declared->isReadOnly();
    }

    /**
     * The classes and interfaces a property holds as each of the classes declares it, as its native type names them,
     * else its docblock.
     *
     * @param  list<ReflectionClass<object>>  $classes
     * @return list<ReflectionClass<object>>
     */
    private static function declaredClasses(array $classes, string $property): array
    {
        $held = [];

        foreach ($classes as $class) {
            $named = $class->hasProperty($property)
                ? DeclaredClasses::propertyClasses($class->getProperty($property), $class, $class->getName())
                : null;

            foreach ($named ?? [] as $name) {
                $held[] = new ReflectionClass($name);
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
        return $this->walk($path, $declaring, $reads)[0];
    }

    /**
     * A `$this` path's steps, as steps() lists them, beside the classes the object its last step reads may be.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return array{list<PathStep>, list<ReflectionClass<object>>}
     */
    private function walk(ReadPath $path, ReflectionClass $declaring, bool $reads): array
    {
        $first = $path->segments[0] ?? null;

        if ($path->root !== 'this' || $first === null) {
            return [[], []];
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

        return [$steps, $classes];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second, an untyped default no method rewrites third — and answer whether the class declares it at all.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class SubjectPropertyTypeResolver
{
    /**
     * Framework base classes whose own property names a subclass never owns, however it redeclares them; a new
     * subject kind adds its base here. `Model` is here because its property names are the shared model engine's, and
     * the two resource bases because `declaresOwnProperty()` is the one caller consulting all three.
     */
    private const array FRAMEWORK_BASES = [JsonResource::class, ResourceCollection::class, Model::class];

    /** By-reference functions that only move an array's internal pointer, so the value keeps its default's type. */
    private const array POINTER_FUNCTIONS = ['end', 'next', 'prev', 'reset'];

    /**
     * Resolve a declared property's TypeScript type and FQCN channels, or null when no source
     * yields a type whose tokens can be imported.
     *
     * @param  ReflectionClass<object>  $subject
     * @return ValueExpressionResult|null
     */
    public function resolve(ReflectionClass $subject, string $name): ?array
    {
        if (! $subject->hasProperty($name)) {
            return null;
        }

        $property = $subject->getProperty($name);

        return resolve(PropertyDocblockTypeReader::class)->read($property)
            ?? resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::propertyTypes($subject, $name))
            ?? $this->defaultValueType($subject, $property);
    }

    /**
     * Whether the subject declares the property itself, so PHP reads it before `__get()` forwards anywhere.
     *
     * A framework name stays excluded however the subject redeclares it: `$this->resource` holds the model,
     * and answering it from the declaration would break every read through it.
     *
     * @param  ReflectionClass<object>  $subject
     */
    public function declaresOwnProperty(ReflectionClass $subject, string $name): bool
    {
        if (! $subject->hasProperty($name)) {
            return false;
        }

        $property = $subject->getProperty($name);

        if ($property->isStatic() || str_starts_with($property->getDeclaringClass()->getName(), 'Illuminate\\')) {
            return false;
        }

        foreach (self::FRAMEWORK_BASES as $base) {
            if ($subject->isSubclassOf($base) && property_exists($base, $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The type of an untyped property's default, typed as a parameter default's value is, while no method the class
     * runs writes the property, so a read still holds the default.
     *
     * @param  ReflectionClass<object>  $subject  the class the property is read through
     * @return ValueExpressionResult|null
     */
    private function defaultValueType(ReflectionClass $subject, ReflectionProperty $property): ?array
    {
        if ($property->getType() !== null || ! $property->hasDefaultValue()) {
            return null;
        }

        try {
            $value = $property->getDefaultValue();
        } catch (Throwable) {
            // A default naming a constant this process never defines, such as an absent extension's, throws here.
            return null;
        }

        // A public property left null is a slot that code outside the class fills.
        if ($value === null && $property->isPublic()) {
            return null;
        }

        $result = resolve(ValueResolver::class)->resolveConstantValue($value);

        return $result === null || $this->writesProperty($subject, $property->getName()) ? null : $result;
    }

    /**
     * Whether any method the class runs, an ancestor's or a trait's included, writes `$this->{$name}`, or application
     * code writes a property whose name it computes.
     *
     * Laravel's own computed writes, such as `increment()`'s and `touch()`'s `$this->{$column}`, name a column.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function writesProperty(ReflectionClass $class, string $name): bool
    {
        $finder = new NodeFinder;

        foreach ($this->declarations($class) as $declaration) {
            $file = $declaration->getFileName();

            // An internal class's code cannot name a property its user-land subclass declares.
            if ($file === false) {
                continue;
            }

            $computedNames = ! str_starts_with($declaration->getName(), 'Illuminate\\');
            $node = $finder->findFirst(
                resolve(AstParser::class)->parseFile($file),
                fn (Node $node): bool => $node instanceof ClassLike
                    && $node->namespacedName?->toString() === $declaration->getName(),
            );
            $writer = $node instanceof ClassLike
                ? $finder->findFirst(
                    $node->stmts,
                    fn (Node $node): bool => $this->writesTo($node, $name, $computedNames, $class, $declaration),
                )
                : null;

            // A class whose declaration cannot be read may write anything.
            if (! $node instanceof ClassLike || $writer !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * The class, then each ancestor, each followed by every trait it uses, transitively.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    private function declarations(ReflectionClass $class): array
    {
        $declarations = [];
        $pending = [$class];

        while ($pending !== []) {
            $declaration = array_shift($pending);
            $declarations[] = $declaration;
            $parent = $declaration->getParentClass();
            $pending = [...array_values($declaration->getTraits()), ...$pending, ...($parent === false ? [] : [$parent])];
        }

        return $declarations;
    }

    /**
     * Whether one node writes `$this->{$name}`, or with $computedNames any `$this->{$expr}`: assigns, mutates, unsets,
     * iterates into, takes a reference to or passes it to a by-reference parameter.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>  $declaration  the class or trait whose body holds the node
     */
    private function writesTo(
        Node $node,
        string $name,
        bool $computedNames,
        ReflectionClass $subject,
        ReflectionClass $declaration,
    ): bool {
        $targets = match (true) {
            $node instanceof Assign, $node instanceof AssignOp, $node instanceof PreInc, $node instanceof PreDec,
            $node instanceof PostInc, $node instanceof PostDec => [$node->var],
            $node instanceof AssignRef => [$node->var, $node->expr],
            $node instanceof Foreach_ => [$node->valueVar, $node->keyVar, $node->byRef ? $node->expr : null],
            $node instanceof Unset_ => $node->vars,
            $node instanceof CallLike => $this->referenceArguments($node, $name, $computedNames, $subject, $declaration),
            default => [],
        };

        return array_any(
            $targets,
            fn (?Expr $target): bool => $target !== null && $this->targetsProperty($target, $name, $computedNames),
        );
    }

    /**
     * The arguments naming the property that a call passes to a by-reference parameter of a callee it names: a
     * function, a method of `$this`, `self`, `static`, `parent` or a named class, or a named class's constructor.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>  $declaration
     * @return list<Expr>
     */
    private function referenceArguments(
        CallLike $call,
        string $name,
        bool $computedNames,
        ReflectionClass $subject,
        ReflectionClass $declaration,
    ): array {
        // A first-class callable passes nothing; only a call passing the property pays for reflecting its callee.
        $passed = $call->isFirstClassCallable() ? [] : array_filter(
            $call->getArgs(),
            fn (Arg $arg): bool => $this->targetsProperty($arg->value, $name, $computedNames),
        );
        $callee = $passed === [] ? null : $this->callee($call, $subject, $declaration);

        if ($callee === null
            || ($callee instanceof ReflectionFunction && in_array($callee->getName(), self::POINTER_FUNCTIONS, true))
        ) {
            return [];
        }

        $parameters = $callee->getParameters();
        $last = $parameters === [] ? null : $parameters[array_key_last($parameters)];
        $arguments = [];

        foreach ($passed as $index => $arg) {
            $named = $arg->name?->toString();
            $parameter = $named === null
                ? $parameters[$index] ?? null
                : array_find($parameters, fn (ReflectionParameter $known): bool => $known->getName() === $named);

            // PHP collects a surplus positional or unknown named argument into a trailing variadic.
            $parameter ??= $last?->isVariadic() ? $last : null;

            if ($parameter?->isPassedByReference()) {
                $arguments[] = $arg->value;
            }
        }

        return $arguments;
    }

    /**
     * The function or method a call names, or null when its receiver or name is computed or cannot be loaded.
     *
     * A method PHP cannot find goes to `__call()` or `__callStatic()`, which take their arguments by value.
     *
     * @param  ReflectionClass<object>  $subject  `$this`, `self` and `static` bind to it
     * @param  ReflectionClass<object>  $declaration  `parent` binds to its parent
     */
    private function callee(
        CallLike $call,
        ReflectionClass $subject,
        ReflectionClass $declaration,
    ): ?ReflectionFunctionAbstract {
        try {
            if ($call instanceof FuncCall && $call->name instanceof Name) {
                return $this->namedFunction($call->name);
            }

            if ($call instanceof New_ && $call->class instanceof Name) {
                return $this->namedClass($call->class, $subject, $declaration)?->getConstructor();
            }

            if ($call instanceof MethodCall && $call->var instanceof Variable && $call->var->name === 'this'
                && $call->name instanceof Identifier
            ) {
                return $subject->getMethod($call->name->toString());
            }

            if ($call instanceof StaticCall && $call->class instanceof Name && $call->name instanceof Identifier) {
                return $this->namedClass($call->class, $subject, $declaration)?->getMethod($call->name->toString());
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }

    /**
     * The function a name calls, tried in its namespace first, then globally, as PHP does.
     */
    private function namedFunction(Name $name): ?ReflectionFunction
    {
        $namespaced = $name->getAttribute('namespacedName');

        foreach ([$namespaced instanceof Name ? $namespaced->toString() : null, $name->toString()] as $candidate) {
            if ($candidate !== null && function_exists($candidate)) {
                return new ReflectionFunction($candidate);
            }
        }

        return null;
    }

    /**
     * The class a static call or `new` names, or null when it cannot be loaded.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>  $declaration
     * @return ReflectionClass<object>|null
     */
    private function namedClass(Name $name, ReflectionClass $subject, ReflectionClass $declaration): ?ReflectionClass
    {
        $parent = $declaration->getParentClass();
        $class = match (strtolower($name->toString())) {
            'self', 'static' => $subject->getName(),
            'parent' => $parent === false ? null : $parent->getName(),
            default => $name->toString(),
        };

        return $class !== null && class_exists($class) ? new ReflectionClass($class) : null;
    }

    /**
     * Whether a write target is `$this->{$name}`, an element of it, or with $computedNames a `$this->{$expr}`, directly
     * or inside a destructuring list.
     */
    private function targetsProperty(Expr $target, string $name, bool $computedNames): bool
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        if ($target instanceof List_ || $target instanceof Array_) {
            return array_any(
                $target->items,
                fn (?ArrayItem $item): bool => $item !== null && $this->targetsProperty($item->value, $name, $computedNames),
            );
        }

        return $target instanceof PropertyFetch
            && $target->var instanceof Variable
            && $target->var->name === 'this'
            && ($target->name instanceof Identifier ? $target->name->toString() === $name : $computedNames);
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second, an untyped default literal no method rewrites third — and answer whether the class declares it at all.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class SubjectPropertyTypeResolver
{
    /**
     * Framework base classes whose own property names a subclass never owns, however it redeclares them.
     *
     * This list is the extension point a new subject kind edits: add the base whose declared properties
     * that subject inherits but must not answer `$this->prop` from. `Model` earns its place here because
     * a model's own property names are the shared model engine's knowledge, which every layer reads; the
     * two resource bases are the analyzer's, and sit here only because `declaresOwnProperty()` is the one
     * caller that has to consult all three at once.
     */
    private const array FRAMEWORK_BASES = [JsonResource::class, ResourceCollection::class, Model::class];

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
     * The type of an untyped property's literal default, when every element agrees on one scalar type and no method
     * the class runs writes the property, so a read still holds the default.
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

        $scalar = fn (mixed $v): ?string => match (true) {
            is_string($v) => 'string',
            is_int($v), is_float($v) => 'number',
            is_bool($v) => 'boolean',
            default => null,
        };
        $types = is_array($value) ? array_values(array_unique(array_map($scalar, $value))) : [];

        $type = match (true) {
            ! is_array($value) => $scalar($value),
            array_is_list($value) && count($types) === 1 && $types[0] !== null => $types[0].'[]',
            default => null,
        };

        return $type === null || $this->writesProperty($subject, $property->getName())
            ? null
            : [...ValueResult::unknown(), 'type' => $type];
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
                ? $finder->findFirst($node->stmts, fn (Node $node): bool => $this->writesTo($node, $name, $computedNames))
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
     * iterates into or takes a reference to it.
     */
    private function writesTo(Node $node, string $name, bool $computedNames): bool
    {
        $targets = match (true) {
            $node instanceof Assign, $node instanceof AssignOp, $node instanceof PreInc, $node instanceof PreDec,
            $node instanceof PostInc, $node instanceof PostDec => [$node->var],
            $node instanceof AssignRef => [$node->var, $node->expr],
            $node instanceof Foreach_ => [$node->valueVar, $node->keyVar, $node->byRef ? $node->expr : null],
            $node instanceof Unset_ => $node->vars,
            default => [],
        };

        return array_any(
            $targets,
            fn (?Expr $target): bool => $target !== null && $this->targetsProperty($target, $name, $computedNames),
        );
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

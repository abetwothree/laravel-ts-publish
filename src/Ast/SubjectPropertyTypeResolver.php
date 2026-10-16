<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
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
use WeakMap;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second, an untyped default no method rewrites third — and answer whether the class declares it at all, and
 * whether a read still holds its default.
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

    /** @var array<string, string> each framework file spellsName() read, by path; vendor code holds still in a run */
    private static array $frameworkSources = [];

    /** @var WeakMap<ClassLike, list<Node>>|null each parsed class body's write sites, dropped with its AST */
    private static ?WeakMap $writeSites = null;

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
     * Whether a read of the property still holds its declared default: it has one, and no method the class runs
     * writes it.
     *
     * @param  ReflectionClass<object>  $subject
     */
    public function holdsDefault(ReflectionClass $subject, string $name): bool
    {
        return $subject->hasProperty($name)
            && $subject->getProperty($name)->hasDefaultValue()
            && ! $this->writesProperty($subject, $name);
    }

    /**
     * The nodes of a class body that writesTo() can count, in the order a walk meets them.
     *
     * Every property a class is asked about would otherwise walk the whole body again, and a framework base is asked
     * about once per subject.
     *
     * @return list<Node>
     */
    private static function writeSites(ClassLike $class): array
    {
        self::$writeSites ??= new WeakMap;

        if (! isset(self::$writeSites[$class])) {
            self::$writeSites[$class] = array_values(new NodeFinder()->find(
                $class->stmts,
                fn (Node $node): bool => $node instanceof Assign || $node instanceof AssignOp
                    || $node instanceof AssignRef || $node instanceof PreInc || $node instanceof PreDec
                    || $node instanceof PostInc || $node instanceof PostDec || $node instanceof Foreach_
                    || $node instanceof Unset_ || $node instanceof CallLike,
            ));
        }

        return self::$writeSites[$class];
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

            // A framework body counts only a write that spells the name, so a source that never does is not walked.
            if (! $computedNames && ! $this->spellsName($file, $name)) {
                continue;
            }

            $node = $finder->findFirst(
                resolve(AstParser::class)->parseFile($file),
                fn (Node $node): bool => $node instanceof ClassLike
                    && $node->namespacedName?->toString() === $declaration->getName(),
            );

            // A class whose declaration cannot be read may write anything.
            if (! $node instanceof ClassLike) {
                return true;
            }

            foreach (self::writeSites($node) as $site) {
                if ($this->writesTo($site, $name, $computedNames, $class, $declaration)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a framework file's source spells a name; the file is recorded as a dependency on every read, as
     * AstParser::parseFile() records one it serves from its cache.
     */
    private function spellsName(string $file, string $name): bool
    {
        DependencyRecorder::record($file);

        return str_contains(self::$frameworkSources[$file] ??= (string) file_get_contents($file), $name);
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
     * The arguments naming the property that a call passes to a by-reference parameter of a callee it names.
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

        return $passed === [] ? [] : resolve(ByReferenceArguments::class)->of($call, $passed, $subject, $declaration);
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

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use ReflectionMethod;
use WeakMap;

/**
 * Where a class writes one of its properties: each node of a method the class runs, an ancestor's or a trait's
 * included, that writes `$this->{$name}`. SubjectPropertyTypeResolver reads what each write gives the property.
 *
 * @phpstan-type PropertyWrite array{Node, ReflectionClass<object>, string, ClassMethod|null}
 * @phpstan-type PropertyWrites list<PropertyWrite>
 * @phpstan-type Declaration array{ReflectionClass<object>, ReflectionClass<object>}
 * @phpstan-type TargetsReader Closure(Node): list<Expr|Arg>
 *
 * @internal
 */
final class PropertyWriteSites
{
    /** @var array<string, string> each framework file spellsName() read, by path; vendor code holds still in a run */
    private static array $frameworkSources = [];

    /** @var WeakMap<ClassLike, list<array{Node, list<Expr|Arg>}>>|null each parsed class body's write sites */
    private static ?WeakMap $writeSites = null;

    /**
     * Whether a write target is `$this->{$name}`, an element of it, or with $computedNames a `$this->{$expr}`, directly
     * or inside a destructuring list.
     */
    public static function targetsProperty(Expr $target, string $name, bool $computedNames): bool
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        if ($target instanceof List_ || $target instanceof Array_) {
            return array_any(
                $target->items,
                fn (?ArrayItem $item): bool => $item !== null && self::targetsProperty($item->value, $name, $computedNames),
            );
        }

        return $target instanceof PropertyFetch
            && $target->var instanceof Variable
            && $target->var->name === 'this'
            && ($target->name instanceof Identifier ? $target->name->toString() === $name : $computedNames);
    }

    /**
     * Each node where a method the class runs, an ancestor's or a trait's included, writes `$this->{$name}`, or where
     * application code writes a property whose name it computes, with the class `self` names there, its file and the
     * method holding it as the class runs it; null when a declaration cannot be read, since it may write anything.
     *
     * Laravel's own computed writes, such as `increment()`'s and `touch()`'s `$this->{$column}`, name a column. Found
     * once a run per class and property.
     *
     * @param  ReflectionClass<object>  $class
     * @return PropertyWrites|null
     */
    public function of(ReflectionClass $class, string $name): ?array
    {
        return resolve(AnalysisMemo::class)->remember(
            'property-writes:'.$class->getName().'::'.$name,
            fn (): ?array => $this->find($class, $name),
        );
    }

    /**
     * The nodes of a class body that write something, each with the targets it writes, in the order a walk meets them;
     * none inside a nested class, a named function or a static closure, whose `$this` is never the class's object.
     *
     * Every property a class is asked about would otherwise walk the whole body again, and a framework base is asked
     * about once per subject.
     *
     * @return list<array{Node, list<Expr|Arg>}>
     */
    private static function writeSites(ClassLike $class): array
    {
        self::$writeSites ??= new WeakMap;

        if (! isset(self::$writeSites[$class])) {
            $visitor = new
            /** @phpstan-import-type TargetsReader from PropertyWriteSites */
            class(self::writeTargets(...)) extends NodeVisitorAbstract
            {
                /** @var list<array{Node, list<Expr|Arg>}> */
                public array $sites = [];

                /**
                 * Keep the reader of the targets a node writes.
                 *
                 * @param  TargetsReader  $targets
                 */
                public function __construct(private readonly Closure $targets) {}

                /** Record a node that writes, leaving unread a body whose `$this` is never the class's object. */
                public function enterNode(Node $node): ?int
                {
                    $targets = ($this->targets)($node);

                    if ($targets !== []) {
                        $this->sites[] = [$node, $targets];
                    }

                    return $node instanceof ClassLike || $node instanceof Function_
                        || (($node instanceof ClosureExpr || $node instanceof ArrowFunction) && $node->static)
                        ? NodeVisitor::DONT_TRAVERSE_CHILDREN
                        : null;
                }
            };

            new NodeTraverser($visitor)->traverse($class->stmts);
            self::$writeSites[$class] = $visitor->sites;
        }

        return self::$writeSites[$class];
    }

    /**
     * The expressions a node writes, or for a call the arguments it might pass by reference; empty for any other node.
     *
     * @return list<Expr|Arg>
     */
    private static function writeTargets(Node $node): array
    {
        $targets = match (true) {
            $node instanceof Assign, $node instanceof AssignOp, $node instanceof PreInc, $node instanceof PreDec,
            $node instanceof PostInc, $node instanceof PostDec => [$node->var],
            $node instanceof AssignRef => [$node->var, $node->expr],
            $node instanceof Foreach_ => [$node->valueVar, $node->keyVar, $node->byRef ? $node->expr : null],
            $node instanceof Unset_ => $node->vars,
            $node instanceof CallLike => $node->isFirstClassCallable() ? [] : $node->getArgs(),
            default => [],
        };

        return array_values(array_filter($targets, fn (Expr|Arg|null $target): bool => $target !== null));
    }

    /**
     * Find the writes of() answers, reading each declaration's body.
     *
     * @param  ReflectionClass<object>  $class
     * @return PropertyWrites|null
     */
    private function find(ReflectionClass $class, string $name): ?array
    {
        $finder = new NodeFinder;
        $writes = [];

        foreach ($this->declarations($class) as [$declaration, $self]) {
            $file = $declaration->getFileName();

            // An internal class's code cannot name a property its user-land subclass declares.
            if ($file === false) {
                continue;
            }

            $computedNames = ! FrameworkClasses::is($declaration->getName());

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
                return null;
            }

            foreach (self::writeSites($node) as [$site, $targets]) {
                if (! $this->writesTo($site, $targets, $name, $computedNames, $class, $self)) {
                    continue;
                }

                $method = $this->enclosingMethod($node, $site);
                $running = $method === null ? null : $this->runningMethod($self, $node, $method);

                if ($method === null || $running !== null) {
                    $writes[] = [$site, $self, $file, $running];
                }
            }
        }

        return $writes;
    }

    /**
     * The class, then each ancestor, each followed by every trait it uses, transitively, each with the class `self`
     * names in its body: a class itself, a trait the class that uses it.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<Declaration>
     */
    private function declarations(ReflectionClass $class): array
    {
        $declarations = [];
        $pending = [[$class, $class]];

        while ($pending !== []) {
            [$declaration, $self] = array_shift($pending);
            $declarations[] = [$declaration, $self];
            $parent = $declaration->getParentClass();
            $traits = array_map(fn (ReflectionClass $trait): array => [$trait, $self], array_values($declaration->getTraits()));
            $pending = [...$traits, ...$pending, ...($parent === false ? [] : [[$parent, $parent]])];
        }

        return $declarations;
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
     * Whether one write site writes `$this->{$name}`, or with $computedNames any `$this->{$expr}`: assigns, mutates,
     * unsets, iterates into, takes a reference to or passes it to a by-reference parameter.
     *
     * @param  list<Expr|Arg>  $targets  what writeTargets() returned for the node
     * @param  ReflectionClass<object>  $class  the class read through, which `$this` and `static` name
     * @param  ReflectionClass<object>  $self  the class `self` names in the body that holds the node
     */
    private function writesTo(
        Node $node,
        array $targets,
        string $name,
        bool $computedNames,
        ReflectionClass $class,
        ReflectionClass $self,
    ): bool {
        $hits = array_filter(
            $targets,
            fn (Expr|Arg $target): bool => self::targetsProperty($target instanceof Arg ? $target->value : $target, $name, $computedNames),
        );

        if ($hits === [] || ! $node instanceof CallLike) {
            return $hits !== [];
        }

        // Only a call passing the property pays for reflecting its callee.
        /** @var list<Arg> $hits */
        return resolve(ByReferenceArguments::class)->of($node, $hits, $self, $class) !== [];
    }

    /**
     * The method of a class body that holds a node, or null where none does.
     */
    private function enclosingMethod(ClassLike $class, Node $site): ?ClassMethod
    {
        return array_find($class->getMethods(), fn (ClassMethod $method): bool => StatementBlocks::encloses($method, $site));
    }

    /**
     * A method body as the class runs it: under the alias it runs a trait method it overrides by, if any; null where it
     * never runs it.
     *
     * @param  ReflectionClass<object>  $self  the class `self` names in the body
     */
    private function runningMethod(ReflectionClass $self, ClassLike $class, ClassMethod $method): ?ClassMethod
    {
        $running = $this->runningName($self, $class, $method);

        if ($running === null) {
            return null;
        }

        if ($running === $method->name->toString()) {
            return $method;
        }

        // PHP copies an aliased trait method under the alias, the name its parameters are reflected by.
        $alias = clone $method;
        $alias->name = new Identifier($running);

        return $alias;
    }

    /**
     * The name the class runs a method body under, an alias for a trait method it overrides; null where it never does.
     *
     * @param  ReflectionClass<object>  $self  the class `self` names in the body
     */
    private function runningName(ReflectionClass $self, ClassLike $class, ClassMethod $method): ?string
    {
        $name = $method->name->toString();
        $trait = $class instanceof Trait_ ? $class->namespacedName?->toString() : null;

        // A trait method reflection cannot reach is kept, since the class may still run it.
        if ($trait === null || ! method_exists($trait, $name)) {
            return $name;
        }

        $declared = new ReflectionMethod($trait, $name);
        $runs = fn (ReflectionMethod $running): bool => $running->getDeclaringClass()->getName() === $self->getName()
            && $running->getFileName() === $declared->getFileName()
            && $running->getStartLine() === $declared->getStartLine();

        if ($self->hasMethod($name) && $runs($self->getMethod($name))) {
            return $name;
        }

        // The class's own alias or one a trait it uses declares, as `use T { m as n; }` in a trait does.
        return array_find($self->getMethods(), $runs)?->getName();
    }
}

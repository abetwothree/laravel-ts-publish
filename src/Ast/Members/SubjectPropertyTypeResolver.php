<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrites;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\ConstExprEvaluationException;
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
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Expr\BinaryOp\Plus;
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
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
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
use ReflectionProperty;
use Throwable;
use WeakMap;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second, an untyped default joined with the values its writes assign third — and answer whether the class
 * declares it at all, and whether a read still holds its default.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ArrayWrite from ArrayArm
 *
 * @phpstan-type PropertyWrite array{Node, ReflectionClass<object>, ClassLike, string}
 * @phpstan-type PropertyWrites list<PropertyWrite>
 * @phpstan-type ElementWrite array{path: list<string|null>, value: Expr|null, unsets: bool}
 * @phpstan-type ComputedWrite array{PropertyWrite, ElementWrite}
 * @phpstan-type ComputedWrites list<ComputedWrite>
 * @phpstan-type Declaration array{ReflectionClass<object>, ReflectionClass<object>}
 * @phpstan-type TargetsReader Closure(Node): list<Expr|Arg>
 * @phpstan-type WriteValues array<int, ValueExpressionResult|null>
 * @phpstan-type LiftedWrites array{type: ValueExpressionResult|null, kept: bool}
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

    /** The guard the outermost property whose writes read a helper again holds, so one typed inside reads it cut. */
    private const string REENTRY_GUARD = 'reading-property-writes';

    /** @var array<string, string> each framework file spellsName() read, by path; vendor code holds still in a run */
    private static array $frameworkSources = [];

    /** @var WeakMap<ClassLike, list<array{Node, list<Expr|Arg>}>>|null each parsed class body's write sites */
    private static ?WeakMap $writeSites = null;

    /** @var array<string, ValueExpressionResult> what each property guard answers, by runtime class, in a check */
    private static array $seeds = [];

    /**
     * Resolve a declared property's TypeScript type and FQCN channels, or null when no source
     * yields a type whose tokens can be imported.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime  the class a write's `static` names, where the runtime class
     *                                                      is known; null where it is only assumed, which declines such
     *                                                      a write
     * @return ValueExpressionResult|null
     */
    public function resolve(ReflectionClass $subject, string $name, ?ReflectionClass $knownRuntime = null): ?array
    {
        if (! $subject->hasProperty($name)) {
            return null;
        }

        $property = $subject->getProperty($name);

        return resolve(PropertyDocblockTypeReader::class)->read($property)
            ?? resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::propertyTypes($subject, $name))
            ?? $this->defaultValueType($subject, $property, $knownRuntime);
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

        if ($property->isStatic() || FrameworkClasses::is($property->getDeclaringClass()->getName())) {
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
            /** @phpstan-import-type TargetsReader from SubjectPropertyTypeResolver */
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
     * The type of an untyped property's default, typed as a parameter default's value is, joined with the value each
     * write a method the class runs assigns; null when a write assigns a value the engine cannot type, or writes the
     * property in any other way, since a read may then hold anything.
     *
     * @param  ReflectionClass<object>  $subject  the class the property is read through
     * @param  ReflectionClass<object>|null  $knownRuntime  the class a write's `static` names, if known
     * @return ValueExpressionResult|null
     */
    private function defaultValueType(ReflectionClass $subject, ReflectionProperty $property, ?ReflectionClass $knownRuntime): ?array
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
        $writes = $result === null ? null : $this->propertyWrites($subject, $property->getName());

        if ($result === null || $writes === null) {
            return null;
        }

        $written = [];
        $computed = [];

        foreach ($writes as $write) {
            $assigned = $this->assignedConstant($write[0], $property->getName(), $knownRuntime, $write[1]);
            $elements = $assigned === null ? $this->elementWrites($write, $property->getName(), $knownRuntime ?? $subject) : [];

            if ($elements === null) {
                return null;
            }

            if ($assigned !== null) {
                $written[] = $assigned;
            }

            foreach ($elements as $element) {
                $computed[] = [$write, $element];
            }
        }

        $known = $written === [] ? $result : ValueResult::unionResults([$result, ...$written]);

        if ($computed === []) {
            return $known;
        }

        $key = 'property-type:'.$subject->getName().'::'.$property->getName()
            .AnalysisMemo::runtimeKey($subject->getName(), $knownRuntime, $knownRuntime === null);

        return resolve(AnalysisMemo::class)->remember(
            $key,
            fn (): ?array => $this->withComputedWrites($known, $computed, $subject, $property, $knownRuntime),
        );
    }

    /**
     * The known type joined with the value each computed write assigns, and each element write applied to its arms; the
     * known type alone while the property is already being typed, and null where a write's value is one the engine
     * cannot type, or an element write reaches no array the type names.
     *
     * A write calling a helper already being read reads it again, or cut where the type that joins is no fixpoint; only
     * the outermost property typed reads one again, so its check and fallback each add one read per call and property
     * its writes reach to the cut read's work, and a property typed inside it adds none.
     *
     * @param  ValueExpressionResult  $known  the default's type joined with each constant write's
     * @param  ComputedWrites  $computed  the whole, compound and element writes no constant evaluation types
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime
     * @return ValueExpressionResult|null
     */
    private function withComputedWrites(
        array $known,
        array $computed,
        ReflectionClass $subject,
        ReflectionProperty $property,
        ?ReflectionClass $knownRuntime,
    ): ?array {
        $memo = resolve(AnalysisMemo::class);
        $guard = $this->propertyGuard($property);

        // A write reading the property it writes, as `$this->count + 1` does, reads what the property held before it.
        if (! $memo->enter($guard)) {
            return self::$seeds[$this->seedKey($guard, $subject, $knownRuntime)] ?? $known;
        }

        try {
            // A property typed inside another's re-entry reads its helpers cut, as before it, so no re-entry nests.
            if (! $memo->enter(self::REENTRY_GUARD)) {
                return $memo->cut(fn (): ?array => $this->cutWrites($known, $computed, $subject, $knownRuntime));
            }

            try {
                $lifted = DroppedUnionArms::provisional(
                    fn (): array => $this->liftedWrites($known, $computed, $subject, $property, $knownRuntime),
                    fn (array $lifted): bool => $lifted['kept'],
                );

                // A write that joins a new type each run, as one nesting what its helper returns does, reads it cut.
                return $lifted['kept']
                    ? $lifted['type']
                    : $this->cutWrites($known, $computed, $subject, $knownRuntime);
            } finally {
                $memo->leave(self::REENTRY_GUARD);
            }
        } finally {
            $memo->leave($guard);
        }
    }

    /**
     * The type the computed writes join, each read with the analyses under way lifted, and whether it stands: no write
     * entered one again, it is a fixpoint, or a write that entered none declines it anyway.
     *
     * @param  ValueExpressionResult  $known
     * @param  ComputedWrites  $computed
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime
     * @return LiftedWrites
     */
    private function liftedWrites(
        array $known,
        array $computed,
        ReflectionClass $subject,
        ReflectionProperty $property,
        ?ReflectionClass $knownRuntime,
    ): array {
        $memo = resolve(AnalysisMemo::class);
        $guard = $this->propertyGuard($property);
        $read = fn (int $index): ?array => $memo->reenterOnce(
            $guard,
            fn (): ?array => $this->writeValue($computed[$index], $subject, $knownRuntime),
        );
        $values = [];
        $reentered = [];

        foreach ($this->valuedWrites($computed) as $index) {
            $reentries = $memo->reentries();
            $values[$index] = $read($index);

            if ($memo->reentries() !== $reentries) {
                $reentered[] = $index;
            } elseif ($values[$index] === null) {
                // Reading a helper again cannot type a write that reads none, so the property declines however it ends.
                return ['type' => null, 'kept' => true];
            }
        }

        $joined = $this->joinedWrites($known, $computed, $values, $knownRuntime ?? $subject);

        if ($reentered === [] || $joined === null) {
            return ['type' => $joined, 'kept' => $reentered === []];
        }

        $again = function () use ($known, $computed, $values, $reentered, $read, $subject, $knownRuntime): ?array {
            foreach ($reentered as $index) {
                $values[$index] = $read($index);
            }

            return $this->joinedWrites($known, $computed, $values, $knownRuntime ?? $subject);
        };
        $seed = $this->seedKey($guard, $subject, $knownRuntime);

        return ['type' => $joined, 'kept' => $this->reachesFixpoint($guard, $seed, $joined, $again)];
    }

    /**
     * Whether a joined type is a fixpoint: the join read again with the property's guard answering that type holds no
     * arm it lacks. The check's own reads leave no dropped arm, warning or stored answer behind.
     *
     * @param  ValueExpressionResult  $joined
     * @param  Closure(): (ValueExpressionResult|null)  $again  the join with each write that entered one read again
     */
    private function reachesFixpoint(string $guard, string $seed, array $joined, Closure $again): bool
    {
        $memo = resolve(AnalysisMemo::class);
        self::$seeds[$seed] = $joined;

        try {
            $rejoined = DroppedUnionArms::provisional(
                fn (): ?array => $memo->seeding($guard, $again),
                fn (): bool => false,
            );
        } finally {
            unset(self::$seeds[$seed]);
        }

        $arms = TsTypeShape::splitTopLevel($joined['type'], ['|']);

        return $rejoined !== null && array_diff(TsTypeShape::splitTopLevel($rejoined['type'], ['|']), $arms) === [];
    }

    /**
     * The known type joined with each computed write's value read with no helper read again, as before any re-entry;
     * null where a write's value is one the engine cannot type.
     *
     * @param  ValueExpressionResult  $known
     * @param  ComputedWrites  $computed
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime
     * @return ValueExpressionResult|null
     */
    private function cutWrites(
        array $known,
        array $computed,
        ReflectionClass $subject,
        ?ReflectionClass $knownRuntime,
    ): ?array {
        $values = [];

        foreach ($this->valuedWrites($computed) as $index) {
            $values[$index] = $this->writeValue($computed[$index], $subject, $knownRuntime);

            if ($values[$index] === null) {
                return null;
            }
        }

        return $this->joinedWrites($known, $computed, $values, $knownRuntime ?? $subject);
    }

    /**
     * The index of each computed write that writes a value, which an element `unset()` does not.
     *
     * @param  ComputedWrites  $computed
     * @return list<int>
     */
    private function valuedWrites(array $computed): array
    {
        return array_keys(array_filter($computed, fn (array $write): bool => $write[1]['value'] !== null));
    }

    /**
     * The guard held while a property is typed, one per declaring class and name.
     */
    private function propertyGuard(ReflectionProperty $property): string
    {
        return 'typing-property:'.$property->getDeclaringClass()->getName().'::'.$property->getName();
    }

    /**
     * The key a property's seed is kept under: its guard and the class it is read for, so another class's read of the
     * same declaration takes its own known type.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime
     */
    private function seedKey(string $guard, ReflectionClass $subject, ?ReflectionClass $knownRuntime): string
    {
        return $guard.'@'.($knownRuntime ?? $subject)->getName();
    }

    /**
     * The value a computed write gives the property whole, or the element it writes, typed in its method's scope; null
     * where the engine cannot type it.
     *
     * @param  ComputedWrite  $write
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime
     * @return ValueExpressionResult|null
     */
    private function writeValue(array $write, ReflectionClass $subject, ?ReflectionClass $knownRuntime): ?array
    {
        $expr = $write[1]['value'];
        $value = $expr === null ? null : $this->computedWrite($write[0], $expr, $subject, $knownRuntime);

        return $value === null || $value['type'] === 'unknown' ? null : $value;
    }

    /**
     * The known type joined with each whole write's value, with each element write then applied to its arms; null where
     * a value is missing, or an element write reaches no array the type names.
     *
     * @param  ValueExpressionResult  $known
     * @param  ComputedWrites  $computed
     * @param  WriteValues  $values  each write's value, by its index in $computed
     * @param  ReflectionClass<object>  $runtime  the class the property is read through, whose serializer sends it
     * @return ValueExpressionResult|null
     */
    private function joinedWrites(array $known, array $computed, array $values, ReflectionClass $runtime): ?array
    {
        $typed = [];
        $elements = [];
        $writes = [];

        foreach ($computed as $index => [$write, $element]) {
            $value = $values[$index] ?? null;

            // Only an unset() writes no value; a write whose value the engine cannot type may leave anything.
            if ($value === null && ! $element['unsets']) {
                return null;
            }

            if ($value !== null && $element['path'] === []) {
                $typed[] = $value;

                continue;
            }

            $written = $this->arrayWrite($write[0], $element, $value['type'] ?? null);

            if ($written === null) {
                return null;
            }

            $writes[] = $written;

            if ($value !== null) {
                $elements[] = $value;
            }
        }

        $whole = ValueResult::unionResults([$known, ...$typed]);

        if ($writes === []) {
            return $whole;
        }

        $type = ArrayArm::written($whole['type'], $writes, ResourceCollections::reindexesNumericKeys($runtime));

        return $type === null ? null : [...ValueResult::unionResults([$whole, ...$elements]), 'type' => $type];
    }

    /**
     * The value a write gives `$this->{$name}` or an element of it, typed in the scope of the method that holds it,
     * under the name the class runs it by, and of each closure inside it that holds the write; null where no method
     * holds it, the value names `static` and the runtime class is only assumed, or a closure's read drops a union arm.
     *
     * @param  PropertyWrite  $write
     * @param  ReflectionClass<object>  $subject
     * @param  ReflectionClass<object>|null  $knownRuntime
     * @return ValueExpressionResult|null
     */
    private function computedWrite(array $write, Expr $value, ReflectionClass $subject, ?ReflectionClass $knownRuntime): ?array
    {
        [$site, $self, $class, $file] = $write;
        $method = $this->enclosingMethod($class, $site);
        $running = $method === null ? null : $this->runningName($self, $class, $method);

        if ($method === null || $running === null || ($knownRuntime === null && $this->namesStatic($value))) {
            return null;
        }

        $closures = $this->enclosingClosures($method, $site);

        // PHP copies an aliased trait method under the alias, the name its parameters are reflected by.
        if ($running !== $method->name->toString()) {
            $method = clone $method;
            $method->name = new Identifier($running);
        }

        $dropped = DroppedUnionArms::dropped();
        $written = resolve(AstEngine::class)->analyzeExpressionValue(
            new MethodContext($self, $method, resolve(AstParser::class)->parseFile($file)),
            $value,
            $knownRuntime ?? $subject,
            assumesRuntime: $knownRuntime === null,
            within: $closures,
        );

        // A closure's untypable name, such as a by-reference import, read through `??` would leave only the fallback.
        return $closures !== [] && DroppedUnionArms::dropped() !== $dropped ? null : $written;
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

    /**
     * The method of a class body that holds a node, or null where none does.
     */
    private function enclosingMethod(ClassLike $class, Node $site): ?ClassMethod
    {
        return array_find($class->getMethods(), fn (ClassMethod $method): bool => StatementBlocks::encloses($method, $site));
    }

    /**
     * The writes one site makes to `$this->{$name}`, each as the key path it reaches the property through, empty for
     * the property whole: a whole or compound write, an element write, an element `unset()`, or a by-reference
     * `foreach` with each value its body gives the element; null for any other write, a whole `unset()` included.
     *
     * @param  PropertyWrite  $write
     * @param  ReflectionClass<object>  $runtime  the class `$this` names, for a call in a `foreach` body
     * @return list<ElementWrite>|null
     */
    private function elementWrites(array $write, string $name, ReflectionClass $runtime): ?array
    {
        $site = $write[0];

        if ($site instanceof Unset_) {
            $unsets = [];

            foreach ($site->vars as $var) {
                $path = $this->elementPath($var, $name);

                // After a whole unset() a read goes through `__get()`, which may answer anything.
                if ($path === [] || ($path === null && $this->targetsProperty($var, $name, true))) {
                    return null;
                }

                if ($path !== null) {
                    $unsets[] = ['path' => $path, 'value' => null, 'unsets' => true];
                }
            }

            return $unsets;
        }

        if ($site instanceof Foreach_) {
            $path = $site->byRef ? $this->elementPath($site->expr, $name) : null;
            $loopWrites = array_any(
                array_filter([$site->valueVar, $site->keyVar]),
                fn (Expr $target): bool => $this->targetsProperty($target, $name, true),
            );
            $values = $path === null || $loopWrites ? null : $this->foreachValues($write, $site, $runtime);

            return $path === null || $values === null ? null : array_map(
                fn (Expr $value): array => ['path' => [...$path, null], 'value' => $value, 'unsets' => false],
                $values,
            );
        }

        $whole = $this->writtenValue($site, $name);

        if ($whole !== null || ! $site instanceof Assign) {
            return $whole === null ? null : [['path' => [], 'value' => $whole, 'unsets' => false]];
        }

        $path = $this->elementPath($site->var, $name);

        return $path === null || $path === [] ? null : [['path' => $path, 'value' => $site->expr, 'unsets' => false]];
    }

    /**
     * The keys a target reaches into `$this->{$name}` through, outermost first, each null where no literal spells it;
     * empty for the property itself, and null where the target is neither.
     *
     * @return list<string|null>|null
     */
    private function elementPath(Expr $target, string $name): ?array
    {
        $path = [];

        while ($target instanceof ArrayDimFetch) {
            $key = $target->dim;
            array_unshift($path, $key instanceof String_ || $key instanceof Int_ ? (string) $key->value : null);
            $target = $target->var;
        }

        return $target instanceof PropertyFetch && $target->var instanceof Variable && $target->var->name === 'this'
            && $target->name instanceof Identifier && $target->name->toString() === $name ? $path : null;
    }

    /**
     * The values a by-reference `foreach` puts in the elements it iterates, read in the function that holds it; null
     * where its value variable is no plain name, or a write to it is one the scan cannot type.
     *
     * @param  PropertyWrite  $write
     * @param  ReflectionClass<object>  $runtime
     * @return list<Expr>|null
     */
    private function foreachValues(array $write, Foreach_ $loop, ReflectionClass $runtime): ?array
    {
        [, $self, $class] = $write;
        $method = $this->enclosingMethod($class, $loop);

        if ($method === null) {
            return null;
        }

        $closures = array_values(array_filter(
            $this->enclosingClosures($method, $loop),
            fn (ArrowFunction|ClosureExpr $function): bool => $function instanceof ClosureExpr,
        ));
        $stmts = $closures === [] ? $method->stmts ?? [] : $closures[count($closures) - 1]->stmts;
        $scope = new AnalysisScope($self);
        $scope->runtimeReflection = $runtime;

        return VariableWrites::foreachElementValues($loop, VariableWrites::of($stmts, $scope), $stmts);
    }

    /**
     * The write an element write makes to the array its key path reaches, the key it leaves unnamed last read from the
     * site: a by-reference `foreach` rewrites every element and `[]` appends; null where an earlier key is unnamed.
     *
     * @param  ElementWrite  $element
     * @param  string|null  $value  the type of the value it writes; null for an unset()
     * @return ArrayWrite|null
     */
    private function arrayWrite(Node $site, array $element, ?string $value): ?array
    {
        $path = $element['path'];
        $key = array_pop($path);
        $named = array_values(array_filter($path, is_string(...)));

        if ($element['path'] === [] || count($named) !== count($path)) {
            return null;
        }

        $key ??= match (true) {
            $site instanceof Foreach_ => ElementKey::Every,
            $site instanceof Assign && $site->var instanceof ArrayDimFetch && $site->var->dim === null => ElementKey::Next,
            default => ElementKey::Any,
        };

        return ['path' => $named, 'key' => $key, 'value' => $element['unsets'] ? null : $value];
    }

    /**
     * The closures and arrow functions inside a method that hold a node, outermost first.
     *
     * @return list<ArrowFunction|ClosureExpr>
     */
    private function enclosingClosures(ClassMethod $method, Node $site): array
    {
        /** @var list<ArrowFunction|ClosureExpr> */
        return new NodeFinder()->find(
            $method->stmts ?? [],
            fn (Node $node): bool => ($node instanceof ClosureExpr || $node instanceof ArrowFunction)
                && StatementBlocks::encloses($node, $site),
        );
    }

    /**
     * The value a whole or compound write gives `$this->{$name}`: a compound one as the operation it abbreviates, and
     * an increment or a decrement as its `+ 1` or `- 1`; null for any other write.
     */
    private function writtenValue(Node $site, string $name): ?Expr
    {
        $target = match (true) {
            $site instanceof Assign, $site instanceof AssignOp, $site instanceof PreInc, $site instanceof PreDec,
            $site instanceof PostInc, $site instanceof PostDec => $site->var,
            default => null,
        };

        if (! $target instanceof PropertyFetch || ! $target->name instanceof Identifier || $target->name->toString() !== $name) {
            return null;
        }

        $operation = BinaryOp::class.'\\'.class_basename($site);

        return match (true) {
            $site instanceof Assign => $site->expr,
            $site instanceof AssignOp => is_subclass_of($operation, BinaryOp::class)
                ? new $operation($target, $site->expr, $site->getAttributes())
                : null,
            $site instanceof PreInc, $site instanceof PostInc => new Plus($target, new Int_(1), $site->getAttributes()),
            default => new Minus($target, new Int_(1), $site->getAttributes()),
        };
    }

    /**
     * Whether any method the class runs, an ancestor's or a trait's included, writes `$this->{$name}`.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function writesProperty(ReflectionClass $class, string $name): bool
    {
        return $this->propertyWrites($class, $name) !== [];
    }

    /**
     * Each node where a method the class runs, an ancestor's or a trait's included, writes `$this->{$name}`, or where
     * application code writes a property whose name it computes, with the class `self` names there, the class body
     * holding it and its file; null when a declaration cannot be read, since it may write anything.
     *
     * Laravel's own computed writes, such as `increment()`'s and `touch()`'s `$this->{$column}`, name a column.
     *
     * @param  ReflectionClass<object>  $class
     * @return PropertyWrites|null
     */
    private function propertyWrites(ReflectionClass $class, string $name): ?array
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

                if ($method === null || $this->runningName($self, $node, $method) !== null) {
                    $writes[] = [$site, $self, $node, $file];
                }
            }
        }

        return $writes;
    }

    /**
     * The value a write assigns `$this->{$name}` whole, evaluated as a constant expression in the body that holds it;
     * null for a value no constant evaluation types, and for any other write: a compound or element write, a computed
     * name, an unset, an iteration, a reference or a call that may take it by reference.
     *
     * @param  ReflectionClass<object>|null  $knownRuntime  the class the object is, which `static` names; null
     *                                                      declines a value naming `static`, since a subclass may give
     *                                                      it another type
     * @param  ReflectionClass<object>  $self  the class `self` names in the body that holds the write
     * @return ValueExpressionResult|null
     */
    private function assignedConstant(Node $site, string $name, ?ReflectionClass $knownRuntime, ReflectionClass $self): ?array
    {
        if (! $site instanceof Assign
            || ! $site->var instanceof PropertyFetch
            || ! $site->var->name instanceof Identifier
            || $site->var->name->toString() !== $name
        ) {
            return null;
        }

        if ($knownRuntime === null && $this->namesStatic($site->expr)) {
            return null;
        }

        $scope = new AnalysisScope($self);
        $scope->runtimeReflection = $knownRuntime ?? $self;
        $values = resolve(ValueResolver::class);

        try {
            return $values->resolveConstantValue($values->evaluateConstantExpression($site->expr, $scope));
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * Whether an expression names `static`, which a subclass may give another type, anywhere within it.
     */
    private function namesStatic(Expr $expr): bool
    {
        return new NodeFinder()->findFirst(
            $expr,
            fn (Node $node): bool => $node instanceof Name && $node->toLowerString() === 'static',
        ) !== null;
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
     * Whether one write site writes `$this->{$name}`, or with $computedNames any `$this->{$expr}`: assigns, mutates,
     * unsets, iterates into, takes a reference to or passes it to a by-reference parameter.
     *
     * @param  list<Expr|Arg>  $targets  what writeTargets() returned for the node
     * @param  ReflectionClass<object>  $runtime  the class read through, which `$this` and `static` name
     * @param  ReflectionClass<object>  $self  the class `self` names in the body that holds the node
     */
    private function writesTo(
        Node $node,
        array $targets,
        string $name,
        bool $computedNames,
        ReflectionClass $runtime,
        ReflectionClass $self,
    ): bool {
        $hits = array_filter(
            $targets,
            fn (Expr|Arg $target): bool => $this->targetsProperty($target instanceof Arg ? $target->value : $target, $name, $computedNames),
        );

        if ($hits === [] || ! $node instanceof CallLike) {
            return $hits !== [];
        }

        // Only a call passing the property pays for reflecting its callee.
        /** @var list<Arg> $hits */
        return resolve(ByReferenceArguments::class)->of($node, $hits, $runtime, $self) !== [];
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

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrites;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second, an untyped default joined with the values its writes assign third — and answer whether the class
 * declares it at all, and whether a read still holds its default.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ArrayWrite from ArrayArm
 * @phpstan-import-type PropertyWrite from PropertyWriteSites
 *
 * @phpstan-type ElementWrite array{path: list<string|null>, value: Expr|null, unsets: bool}
 * @phpstan-type ComputedWrite array{PropertyWrite, ElementWrite}
 * @phpstan-type ComputedWrites list<ComputedWrite>
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

    /** @var array<string, ValueExpressionResult> what each property guard answers, by runtime class, in a check */
    private static array $seeds = [];

    /**
     * Resolve a declared property's TypeScript type and FQCN channels, or null when no source
     * yields a type whose tokens can be imported.
     *
     * @param  ReflectionClass<object>  $subject
     * @param  RuntimeClass  $runtime  the class the property is read for: a known one is the class a write's `static`
     *                                 names, and an assumed one declines such a write
     * @return ValueExpressionResult|null
     */
    public function resolve(ReflectionClass $subject, string $name, RuntimeClass $runtime): ?array
    {
        if (! $subject->hasProperty($name)) {
            return null;
        }

        $property = $subject->getProperty($name);

        // An assumed runtime class may be any subclass, so the property is read for the subject itself.
        $readFor = $runtime->known ? $runtime : RuntimeClass::assumed($subject);

        return $this->declaredType($subject, $property) ?? $this->defaultValueType($subject, $property, $readFor);
    }

    /**
     * A subject's property's declared type, its `@var` docblock else its native type, with no default or write read:
     * what holds for a subclass that redeclares it too.
     *
     * @param  ReflectionClass<object>  $subject
     * @return ValueExpressionResult|null
     */
    public function declaredType(ReflectionClass $subject, ReflectionProperty $property): ?array
    {
        return resolve(PropertyDocblockTypeReader::class)->read($property)
            ?? resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::propertyTypes($subject, $property->getName()));
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
     * The plain names these nodes spell where each is a variable, leaving out a variable variable.
     *
     * @param  array<Node>  $nodes
     * @return list<string>
     */
    private static function variableNames(array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if ($node instanceof Variable && is_string($node->name)) {
                $names[] = $node->name;
            }
        }

        return $names;
    }

    /**
     * Whether a value reads an import that a closure holding the write, or one the import passes through on its way in,
     * assigns anywhere in its body: that closure's scope reads the name as the value the body assigns wherever the read
     * sits, which a read before the assignment never holds, so the write declines in either order.
     *
     * @param  list<ArrowFunction|ClosureExpr>  $closures  outermost first
     */
    private static function readsReassignedImport(Expr $value, array $closures): bool
    {
        if ($closures === []) {
            return false;
        }

        $names = array_diff(self::variableNames(new NodeFinder()->findInstanceOf($value, Variable::class)), ['this']);

        foreach (array_reverse($closures) as $closure) {
            // A closure takes only the names it imports; an arrow function every name but its parameters.
            $names = $closure instanceof ClosureExpr
                ? array_intersect($names, self::variableNames(array_map(fn (ClosureUse $use): Expr => $use->var, $closure->uses)))
                : array_diff($names, self::variableNames(array_map(fn (Param $param): Expr => $param->var, $closure->params)));
            $body = $closure instanceof ClosureExpr ? $closure->stmts : [$closure->expr];

            if (array_intersect($names, array_column(VariableWrites::of($body), 0)) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * The type of an untyped property's default, typed as a parameter default's value is, joined with the value each
     * write a method the class runs assigns; null when a write assigns a value the engine cannot type, or writes the
     * property in any other way, since a read may then hold anything.
     *
     * @param  ReflectionClass<object>  $subject  the class the property is read through
     * @param  RuntimeClass  $runtime  the class the property is read for, known, or the subject, assumed
     * @return ValueExpressionResult|null
     */
    private function defaultValueType(ReflectionClass $subject, ReflectionProperty $property, RuntimeClass $runtime): ?array
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
        $writes = $result === null ? null : resolve(PropertyWriteSites::class)->of($subject, $property->getName());

        if ($result === null || $writes === null) {
            return null;
        }

        $written = [];
        $computed = [];

        foreach ($writes as $write) {
            $assigned = $this->assignedConstant($write[0], $property->getName(), $runtime, $write[1]);
            $elements = $assigned === null ? $this->elementWrites($write, $property->getName(), $runtime) : [];

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

        $key = 'property-type:'.$subject->getName().'::'.$property->getName().$runtime->memoKey($subject->getName());

        return resolve(AnalysisMemo::class)->remember(
            $key,
            fn (): ?array => $this->withComputedWrites($known, $computed, $property, $runtime),
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
     * @return ValueExpressionResult|null
     */
    private function withComputedWrites(
        array $known,
        array $computed,
        ReflectionProperty $property,
        RuntimeClass $runtime,
    ): ?array {
        $memo = resolve(AnalysisMemo::class);
        $guard = $this->propertyGuard($property);

        // A write reading the property it writes, as `$this->count + 1` does, reads what the property held before it.
        if (! $memo->enter($guard)) {
            return self::$seeds[$this->seedKey($guard, $runtime)] ?? $known;
        }

        try {
            // A property typed inside another's re-entry reads its helpers cut, as before it, so no re-entry nests.
            if (! $memo->enter(self::REENTRY_GUARD)) {
                return $memo->cut(fn (): ?array => $this->cutWrites($known, $computed, $runtime));
            }

            try {
                $lifted = DroppedUnionArms::provisional(
                    fn (): array => $this->liftedWrites($known, $computed, $property, $runtime),
                    fn (array $lifted): bool => $lifted['kept'],
                );

                // A write that joins a new type each run, as one nesting what its helper returns does, reads it cut.
                return $lifted['kept']
                    ? $lifted['type']
                    : $this->cutWrites($known, $computed, $runtime);
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
     * @return LiftedWrites
     */
    private function liftedWrites(
        array $known,
        array $computed,
        ReflectionProperty $property,
        RuntimeClass $runtime,
    ): array {
        $memo = resolve(AnalysisMemo::class);
        $guard = $this->propertyGuard($property);
        $read = fn (int $index): ?array => $memo->reenterOnce(
            $guard,
            fn (): ?array => $this->writeValue($computed[$index], $runtime),
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

        $joined = $this->joinedWrites($known, $computed, $values, $runtime);

        if ($reentered === [] || $joined === null) {
            return ['type' => $joined, 'kept' => $reentered === []];
        }

        $again = function () use ($known, $computed, $values, $reentered, $read, $runtime): ?array {
            foreach ($reentered as $index) {
                $values[$index] = $read($index);
            }

            return $this->joinedWrites($known, $computed, $values, $runtime);
        };
        $seed = $this->seedKey($guard, $runtime);

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
     * @return ValueExpressionResult|null
     */
    private function cutWrites(array $known, array $computed, RuntimeClass $runtime): ?array
    {
        $values = [];

        foreach ($this->valuedWrites($computed) as $index) {
            $values[$index] = $this->writeValue($computed[$index], $runtime);

            if ($values[$index] === null) {
                return null;
            }
        }

        return $this->joinedWrites($known, $computed, $values, $runtime);
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
     */
    private function seedKey(string $guard, RuntimeClass $runtime): string
    {
        return $guard.'@'.$runtime->class->getName();
    }

    /**
     * The value a computed write gives the property whole, or the element it writes, typed in its method's scope; null
     * where the engine cannot type it.
     *
     * @param  ComputedWrite  $write
     * @return ValueExpressionResult|null
     */
    private function writeValue(array $write, RuntimeClass $runtime): ?array
    {
        $expr = $write[1]['value'];
        $value = $expr === null ? null : $this->computedWrite($write[0], $expr, $runtime);

        return $value === null || $value['type'] === 'unknown' ? null : $value;
    }

    /**
     * The known type joined with each whole write's value, with each element write then applied to its arms; null where
     * a value is missing, or an element write reaches no array the type names.
     *
     * @param  ValueExpressionResult  $known
     * @param  ComputedWrites  $computed
     * @param  WriteValues  $values  each write's value, by its index in $computed
     * @param  RuntimeClass  $runtime  the class the property is read for, whose serializer sends it
     * @return ValueExpressionResult|null
     */
    private function joinedWrites(array $known, array $computed, array $values, RuntimeClass $runtime): ?array
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

        // Order is known only where each arm is the default's or a constant write's; a computed value may print a join.
        $type = ArrayArm::written(
            $whole['type'],
            $writes,
            ResourceCollections::reindexesNumericKeys($runtime->class),
            ordered: $typed === [],
        );

        return $type === null ? null : [...ValueResult::unionResults([$whole, ...$elements]), 'type' => $type];
    }

    /**
     * The value a write gives `$this->{$name}` or an element of it, typed in the scope of the method that holds it,
     * under the name the class runs it by, and of each closure inside it that holds the write; null where no method
     * holds it, the value names `static` and the runtime class is only assumed, or a closure's read drops a union arm.
     *
     * @param  PropertyWrite  $write
     * @return ValueExpressionResult|null
     */
    private function computedWrite(array $write, Expr $value, RuntimeClass $runtime): ?array
    {
        [$site, $self, $file, $method] = $write;

        if ($method === null || (! $runtime->known && $this->namesStatic($value))) {
            return null;
        }

        $closures = $this->enclosingClosures($method, $site);

        if (self::readsReassignedImport($value, $closures)) {
            return null;
        }

        $dropped = DroppedUnionArms::dropped();
        $written = resolve(AstEngine::class)->analyzeExpressionValue(
            new MethodContext($self, $method, resolve(AstParser::class)->parseFile($file)),
            $value,
            $runtime,
            within: $closures,
        );

        // A closure's untypable name, such as a by-reference import, read through `??` would leave only the fallback.
        return $closures !== [] && DroppedUnionArms::dropped() !== $dropped ? null : $written;
    }

    /**
     * The writes one site makes to `$this->{$name}`, each as the key path it reaches the property through, empty for
     * the property whole: a whole or compound write, an element write, an element `unset()`, or a by-reference
     * `foreach` with each value its body gives the element; null for any other write, a whole `unset()` included.
     *
     * @param  PropertyWrite  $write
     * @param  RuntimeClass  $runtime  the class `$this` names, for a call in a `foreach` body
     * @return list<ElementWrite>|null
     */
    private function elementWrites(array $write, string $name, RuntimeClass $runtime): ?array
    {
        $site = $write[0];

        if ($site instanceof Unset_) {
            $unsets = [];

            foreach ($site->vars as $var) {
                $path = $this->elementPath($var, $name);

                // After a whole unset() a read goes through `__get()`, which may answer anything.
                if ($path === [] || ($path === null && PropertyWriteSites::targetsProperty($var, $name, true))) {
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
                fn (Expr $target): bool => PropertyWriteSites::targetsProperty($target, $name, true),
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
     * @return list<Expr>|null
     */
    private function foreachValues(array $write, Foreach_ $loop, RuntimeClass $runtime): ?array
    {
        [, $self, , $method] = $write;

        if ($method === null) {
            return null;
        }

        $closures = array_values(array_filter(
            $this->enclosingClosures($method, $loop),
            fn (ArrowFunction|ClosureExpr $function): bool => $function instanceof ClosureExpr,
        ));
        $stmts = $closures === [] ? $method->stmts ?? [] : $closures[count($closures) - 1]->stmts;
        $writes = VariableWrites::of($stmts, AnalysisScope::detached($self, $runtime));

        return VariableWrites::foreachElementValues($loop, $writes, $stmts);
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
        return resolve(PropertyWriteSites::class)->of($class, $name) !== [];
    }

    /**
     * The value a write assigns `$this->{$name}` whole, evaluated as a constant expression in the body that holds it;
     * null for a value no constant evaluation types, and for any other write: a compound or element write, a computed
     * name, an unset, an iteration, a reference or a call that may take it by reference.
     *
     * @param  RuntimeClass  $runtime  the class the object is, which a known one's `static` names; an assumed one
     *                                 declines a value naming `static`, since a subclass may give it another type
     * @param  ReflectionClass<object>  $self  the class `self` names in the body that holds the write
     * @return ValueExpressionResult|null
     */
    private function assignedConstant(Node $site, string $name, RuntimeClass $runtime, ReflectionClass $self): ?array
    {
        if (! $site instanceof Assign
            || ! $site->var instanceof PropertyFetch
            || ! $site->var->name instanceof Identifier
            || $site->var->name->toString() !== $name
        ) {
            return null;
        }

        if (! $runtime->known && $this->namesStatic($site->expr)) {
            return null;
        }

        $scope = AnalysisScope::detached($self, $runtime);
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
}

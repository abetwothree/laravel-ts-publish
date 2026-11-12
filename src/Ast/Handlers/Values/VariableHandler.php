<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallableForms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Bodies\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ResolvesReturnedValues;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\CollectionSteps;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use ReflectionMethod;

/**
 * Expressions rooted at a bound variable rather than `$this` — `$item->name`, `$items->map(…)`,
 * `$items->pluck('x')`, `$item->method()`, and the bare variable itself resolved through an inline `@var` on its
 * assignment, then the scope's model, collection, closure-parameter and local-assignment binding maps.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type VarDocBinding from AnalysisScope
 * @phpstan-import-type BindingGap from AnalysisScope
 *
 * @phpstan-type DeclaredReplacement array{ValueExpressionResult|null, list<Expr>|null}
 * @phpstan-type LeftMembers array<string, non-empty-list<Expr>>
 * @phpstan-type FilledReading array{ValueExpressionResult, list<Expr>}
 *
 * @internal
 */
final class VariableHandler implements ExpressionHandler, KeepsHeldObjects
{
    use AnalyzesPluckCalls;
    use BindsMapCallbacks;
    use CollectsLocalVarBindings;
    use FiltersAttributeKeys;
    use InspectsAstNodes;
    use ReadsNonNullGuards;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;
    use SpellsKeyedCollections;

    /** The classes whose map() runs over their items: a collection, or a paginator, which forwards it to its page. */
    private const array MAPS_ITEMS = [Enumerable::class, AbstractPaginator::class, AbstractCursorPaginator::class];

    /** What a dropped arm can leave a value or a shape member, which a local's `@var` may fill. */
    private const array PLACEHOLDERS = ['null', 'never[]'];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class, MethodCall::class, Variable::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = $this->resolveRead($expr, $scope, $engine);

        // Only a bare variable is the closure or prop it holds; a read through it, as `$items->map(…)`, is a value.
        return $result === null || $expr instanceof Variable ? $result : ValueResult::withoutHeldObject($result);
    }

    /**
     * Resolve a variable, or a read rooted at one, through the scope's bindings.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveRead(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $peeled = $expr instanceof MethodCall ? $this->peeledValuesOrAll($expr, $scope, $engine) : null;

        if ($peeled !== null) {
            return $peeled;
        }

        // $variable->property — an attribute of the variable's own bound model (whenLoaded param, map param, foreach
        // value var). Anything else, a relation or a narrowed read included, goes on to ReceiverPropertyFetchHandler.
        if ($expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            $receivers = resolve(ReceiverClassResolver::class);
            $boundModel = $receivers->boundModel($expr->var, $scope);
            $attribute = $boundModel === null ? null : $this->analyzeRelatedModelProperty($expr->name->toString(), $scope, $boundModel);

            if ($attribute !== null && $attribute['type'] !== 'unknown') {
                // A parameter bound from a join's element warns of what that join left out, as a receiver's read does.
                $held = isset($scope->leftOutParameters[$expr->var->name]) ? $receivers->resolve($expr->var, $scope) : null;

                if ($held !== null) {
                    $receivers->warnOfLeftOut($held, $attribute, $scope, $engine);
                }

                return $attribute;
            }
        }

        // `$receiver->map(fn (Item $item) => [...])`: the element model comes from the param's type hint, or from the
        // collection the receiver resolver names.
        if ($expr instanceof MethodCall && $this->mappedReceiver($expr) !== null) {
            $mapResult = $this->analyzeReceiverMapCall($expr, $scope, $engine);

            if ($mapResult !== null) {
                return $mapResult;
            }
        }

        // $variable->pluck('field') — an array of the field's type on the element model of the variable's own to-many
        // binding; any other variable goes on to the receiver handlers.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'pluck'
        ) {
            $elementModel = resolve(ReceiverClassResolver::class)->boundElementModel($expr->var, $scope);

            if ($elementModel !== null) {
                return $this->analyzeVariablePluckCall($expr, $scope, $elementModel);
            }
        }

        // $variable->method() — resolve against the variable's own bound model. An only()/except() filter is skipped:
        // the receiver rules own it, and reflecting it here reads Model::except()'s `@return array` as a list or
        // filters a collection by key.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && ! $this->callsAttributeFilter($expr)
        ) {
            $boundModel = resolve(ReceiverClassResolver::class)->boundModel($expr->var, $scope);
            $method = $expr->name->toString();

            // A rule types what reflection reads loosely, and the receiver order reads what it cannot, such as an
            // untyped method's body, so both go on to ReceiverMethodCallHandler.
            if ($boundModel !== null && ! resolve(ReceiverMethodReturnResolver::class)->typesByRule($boundModel, $method)) {
                $result = $this->analyzeRelatedModelMethodCall($method, $boundModel);

                if ($result['type'] !== 'unknown') {
                    return $result;
                }
            }
        }

        if (! $expr instanceof Variable || ! is_string($expr->name)) {
            return null;
        }

        $span = $scope->declaredAt($expr);
        $value = $span === null
            ? $this->boundValue($expr, $expr->name, $scope, $engine)
                ?? $this->keyBuiltValue($expr, $expr->name, $scope, $engine)
            : $this->declaredLocalValue($expr->name, $span, $scope, $engine);

        $matches = $value === null ? $this->writtenMatches($expr, $scope) : null;

        if ($matches !== null) {
            $value = [...ValueResult::unknown(), 'type' => $matches, 'optional' => false, 'absentKeys' => true];
        }

        $kept = $value === null ? null : $this->keptByReference($expr, $expr->name, $value, $scope, $engine);

        return $kept === null ? null : $this->provenNonNull($expr, $kept, $scope);
    }

    /**
     * A trailing argument-less values()/all() on a collection, or a toArray()/jsonSerialize() after a map(), typed from
     * the receiver chain, which alone knows its element: all() hands the array back, keys and all, and values() lists
     * it. On any other class the method is its own, so the receiver's type says nothing about what it returns.
     *
     * A list a method declares under `string` keys keeps the record its declaration reads, as a declared type is
     * trusted.
     *
     * @return ValueExpressionResult|null
     */
    private function peeledValuesOrAll(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ((! $call->var instanceof MethodCall && ! $this->isPlainVariable($call->var))
            || ! $call->name instanceof Identifier
            || ! (in_array($call->name->toString(), ['values', 'all'], true)
                || CollectionSteps::mappedSerializer(CollectionSteps::chain($call)[0]) !== null)
            || $call->isFirstClassCallable()
            || ! CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, $call->name->toString()))->isEmpty()
        ) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $scope);

        if (! $this->holdsOnlyCollections($call->var, $receiver, $scope)) {
            return null;
        }

        $method = $call->name->toString();
        $mapped = fn (bool $arrayed): array => $scope->within(function () use ($scope, $arrayed): void {
            $scope->mapResultArrayed = $scope->mapResultArrayed || $arrayed;
        }, fn (): array => $engine->resolve($call->var));
        $receiverResult = match ($method) {
            'values', 'all' => $mapped($method === 'all'),
            'toArray', 'jsonSerialize' => $this->serializedMapResult($method, $mapped),
            default => null,
        };

        if ($receiverResult === null) {
            return null;
        }

        $type = match (true) {
            $method === 'values' => ArrayArms::valuesList($receiverResult['type']),
            $receiver?->keyed === true && ! $receiver->stringKeyed => $this->keyedAll($receiverResult['type']),
            default => $receiverResult['type'],
        };

        return $type === null || $type === 'unknown' ? null : [...$receiverResult, 'type' => $type];
    }

    /**
     * What all() on a collection whose keys a step broke sends, where its read names only the object: that object, or
     * the `[]` json_encode() writes while it holds nothing, as a `@var Collection<string, X>` local reads.
     */
    private function keyedAll(string $type): string
    {
        return VisibleGaps::carried($type, static function (string $type): string {
            $list = count(TsTypeString::splitTopLevelUnion($type)) === 1 && str_starts_with($type, 'Record<')
                ? ArrayArms::valuesList($type)
                : null;

            return $list === null ? $type : ArrayArms::keyedList($list);
        }) ?? $type;
    }

    /**
     * A bare read's value without its null arm where a guard or a filled-value callback, such as transform()'s, proves
     * the read non-null; a value that is only null keeps it, since nothing else is left to publish.
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult
     */
    private function provenNonNull(Variable $read, array $value, AnalysisScope $scope): array
    {
        return VisibleGaps::aside($value['type'])[0] !== 'null' && $this->isProvenNonNull($read, $scope)
            && TsTypeString::hasNullArm($value['type'])
            ? [...$value, 'type' => TsTypeString::withoutNullArm($value['type'])]
            : $value;
    }

    /**
     * A bare variable's value from what its read sees.
     *
     * Inside a gap, the values its writes assigned, joined with the old binding where that may still hold; else the one
     * write its read sees; else the binding its name came in with, a model as the subclass a guard proves it.
     *
     * @return ValueExpressionResult|null
     */
    private function boundValue(Variable $variable, string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $gap = $scope->gapAt($variable);

        if ($gap !== null) {
            return $this->joinedValue($variable, $name, $gap, $scope, $engine);
        }

        $write = $scope->soleWriteSeenBy($variable);

        if ($write !== null) {
            return $this->boundExpressionValue($name, $write, $scope, $engine);
        }

        if (! $scope->holdsBinding($variable)) {
            return null;
        }

        $narrowed = $this->narrowedModel($variable, $name, $scope);

        return $narrowed === null ? $this->heldValue($name, $scope, $engine) : $this->modelValue($narrowed);
    }

    /**
     * A read's value inside a gap: the values its writes assigned, joined with the old binding where that may still
     * hold. An arm the engine cannot type is left out beside typed ones, as a ternary leaves one out: a parameter's
     * nested write where an earlier value still reaches the read, and an old value beside writes that all type.
     *
     * @param  BindingGap  $gap
     * @return ValueExpressionResult|null
     */
    private function joinedValue(Variable $read, string $name, array $gap, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // The old binding may still hold past a write nested in a block that falls through, so both are read.
        $written = $gap[2] ?? [];
        $values = $scope->beforeGap($name, $gap, fn (): array => array_map(
            fn (Expr $value): ?array => $this->writtenValue($name, $value, $scope, $engine),
            $written,
        ));
        $untyped = fn (?array $arm): bool => $arm === null || $arm['type'] === 'unknown';
        $nested = $gap[5] ?? [];
        // An earlier value still reaches past a parameter's nested write, so a value it wrote the engine cannot type is
        // left out of the join, as fromGap() leaves a receiver.
        $reaches = $gap[3] || array_any($written, fn (Expr $value): bool => ! in_array($value, $nested, true));
        $old = $gap[3] ? $this->heldValue($name, $scope, $engine) : null;
        $arms = [];
        $left = [];
        // A value left out can still be a MissingValue, whose key Laravel drops.
        $optional = false;

        foreach ($values as $index => $arm) {
            if ($reaches && $untyped($arm) && in_array($written[$index], $nested, true)) {
                $left[] = [$written[$index], 'binding-join'];
                $optional = $optional || ($arm['optional'] ?? false);
            } else {
                $arms[] = $arm;
            }
        }

        if ($gap[3] && $untyped($old) && $arms !== [] && ! array_any($arms, $untyped)) {
            $binding = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name]
                ?? $scope->passedValues[$name] ?? $read;
            $left[] = [$binding, 'binding-join'];
            $optional = $optional || ($old['optional'] ?? false);
        } elseif ($gap[3]) {
            array_unshift($arms, $old);
        }

        if ($gap[2] === null || $arms === [] || array_any($arms, $untyped)) {
            return null;
        }

        $typed = array_values(array_filter($arms));
        $union = count($typed) === 1 ? $typed[0] : ValueResult::unionResults($typed);

        return DroppedUnionArms::leftOutOfJoin([...$union, 'optional' => $union['optional'] || $optional], $left, $scope);
    }

    /**
     * A local built by key writes, read where no binding types it, as the array its writes before the read build; null
     * for any other variable, and for one whose walk is already under way.
     *
     * @return ValueExpressionResult|null
     */
    private function keyBuiltValue(Variable $read, string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $stmts = $scope->keyBuiltLocals[$name] ?? null;

        if ($stmts === null || ! $engine instanceof ResolvesReturnedValues) {
            return null;
        }

        return $scope->resolvingName($name, fn (): ?array => $engine->walkedLocal($read, $stmts));
    }

    /**
     * A bare variable's value from the binding its name came into the body with.
     *
     * Where that binds no value, from the body's one write of it, which a value read takes while a receiver keeps the
     * class.
     *
     * @return ValueExpressionResult|null
     */
    private function heldValue(string $name, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Bound to a model class (whenLoaded param, map param, foreach value var) — resolves to the model's own type.
        // Checked before closure-param/local-var expression bindings, which resolve through a *different* expression.
        if (isset($scope->varModelBindings[$name])) {
            return $this->modelValue($scope->varModelBindings[$name]);
        }

        // Bound to a whole relation collection (to-many whenLoaded param) — resolves to the collection type, e.g.
        // `User[]`, never the singular element model.
        if (isset($scope->varCollectionBindings[$name])) {
            $binding = $scope->varCollectionBindings[$name];

            return [
                ...ValueResult::unknown(),
                'type' => $binding['type'],
                'optional' => false,
                'modelFqcn' => $binding['modelFqcn'],
            ];
        }

        // Bound to an already-resolved value — a `collect(...)->map()` closure param, whose element type
        // CollectionPipelineHandler resolved before descending into the body.
        if (isset($scope->varValueBindings[$name])) {
            return $scope->varValueBindings[$name];
        }

        // Bound to a closure parameter's expression or a top-level local assignment; the closure param is the narrower
        // scope, and the re-entrancy guard makes a cyclic binding resolve as unknown.
        $boundExpr = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name] ?? null;

        return $boundExpr === null ? null : $this->boundExpressionValue($name, $boundExpr, $scope, $engine);
    }

    /**
     * The subclass of a variable's bound model a guard proves it to hold, where that is one published model, or null.
     *
     * @return class-string<Model>|null
     */
    private function narrowedModel(Variable $variable, string $name, AnalysisScope $scope): ?string
    {
        $bound = $scope->varModelBindings[$name] ?? null;

        if ($bound === null || $scope->guardAt($variable) === null) {
            return null;
        }

        $held = resolve(ReceiverClassResolver::class)->resolve($variable, $scope)->classes ?? [];
        $model = count($held) === 1 ? $held[0] : null;

        return $model !== null
            && is_subclass_of($model, $bound)
            && ValueResult::namesOnlyPublishedModels([...ValueResult::unknown(), 'modelFqcn' => $model])
                ? $model
                : null;
    }

    /**
     * A model's own value, as a bare variable bound to it reads: the model's published type.
     *
     * @param  class-string<Model>  $model
     * @return ValueExpressionResult
     */
    private function modelValue(string $model): array
    {
        return [...ValueResult::unknown(), 'type' => class_basename($model), 'optional' => false, 'modelFqcn' => $model];
    }

    /**
     * A declared local's value: the engine's reading of its annotated assignment's value, unless that is vague or only
     * the `null` an untypable arm's drop left, as AccessorBodyAnalyzer reads a body. Then the `@var` type, if it is
     * precise enough to publish, optional where the reading was, since Laravel drops a missing conditional value.
     *
     * The tag also fills a `never[]` or a literal's member that a drop left, where it admits what the kept arm sends.
     *
     * A collection is sent as its all() reads, so a record the `@var` keys of either kind keeps its list arm.
     *
     * @param  VarDocBinding  $span
     * @return ValueExpressionResult|null
     */
    private function declaredLocalValue(string $name, array $span, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $value = $this->declaredReadingOrTag($name, $span, $scope, $engine);

        if ($value === null || $this->keyedAll($value['type']) === $value['type']) {
            return $value;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolveDeclared($name, $span, $scope);

        return $receiver !== null && $receiver->keyed && ! $receiver->stringKeyed && $receiver->within([Enumerable::class])
            ? [...$value, 'type' => $this->keyedAll($value['type'])]
            : $value;
    }

    /**
     * A declared local's reading where it stands, else its `@var` type where that can replace it, whole or in each
     * placeholder a drop left; see declaredLocalValue().
     *
     * @param  VarDocBinding  $span
     * @return ValueExpressionResult|null
     */
    private function declaredReadingOrTag(string $name, array $span, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $dropped = DroppedUnionArms::dropped();
        // A placeholder the tag replaces is never published, so the warning its drop raised is not either.
        [$reading, $replacement] = DroppedUnionArms::replaceable(
            function () use ($name, $span, $scope, $engine, $dropped): array {
                $reading = $this->boundExpressionValue($name, $span['expr'], $scope, $engine);

                return [$reading, ...$this->declaredReplacement($span, $reading, $dropped)];
            },
            fn (array $read, ?Expr $arm): bool => $read[1] === null || ($read[2] !== null && ! in_array($arm, $read[2], true)),
        );

        return $replacement ?? $reading;
    }

    /**
     * Whether a declared local's reading stands over its `@var`: not vague, and not the `null` an untypable arm's drop
     * left once the read began at the given count of dropped arms.
     *
     * @param  ValueExpressionResult|null  $reading
     *
     * @phpstan-assert-if-true !null $reading
     */
    private function readingStands(?array $reading, int $dropped): bool
    {
        return $reading !== null
            && ! TsTypeString::isVagueTsType($reading['type'])
            && ! DroppedUnionArms::leftNullSince($reading['type'], $dropped);
    }

    /**
     * The `@var` type in place of a reading that does not stand, or of a `never[]` a drop left that the tag lists,
     * beside null, as it replaces every placeholder; else the reading with each member a drop left filled from the tag,
     * beside the arms whose placeholders it fills (filledMembers()); else nothing.
     *
     * @param  VarDocBinding  $span
     * @param  ValueExpressionResult|null  $reading
     * @return DeclaredReplacement
     */
    private function declaredReplacement(array $span, ?array $reading, int $dropped): array
    {
        $stands = $this->readingStands($reading, $dropped);
        $drops = $stands && DroppedUnionArms::dropped() !== $dropped;
        $listed = $drops && VisibleGaps::aside($reading['type'])[0] === 'never[]';
        $left = $drops && ! $listed ? $this->leftMembers($reading, $span['expr']) : [];

        if ($stands && ! $listed && $left === []) {
            return [null, null];
        }

        $declared = resolve(PropertyDocblockTypeReader::class)->readDeclared($span['type'], $span['context']);

        if (! ValueResult::isPublishableReading($declared)) {
            return [null, null];
        }

        if (! $stands || ($listed && $this->admitsPlaceholder($declared['type'], 'never[]'))) {
            return [[...$declared, 'optional' => $reading['optional'] ?? false], null];
        }

        return $this->filledMembers($reading, $declared, $left) ?? [null, null];
    }

    /**
     * Each member of a shape a literal assigns that a dropped arm inside its own item left only `null` or `never[]`,
     * by its printed key, with the arms whose placeholder warnings the read under way holds there.
     *
     * @param  ValueExpressionResult  $reading
     * @return LeftMembers
     */
    private function leftMembers(array $reading, Expr $assigned): array
    {
        $members = VisibleGaps::members($reading['type'])[0] ?? null;
        $arms = DroppedUnionArms::heldPlaceholderArms();

        if ($members === null || $arms === [] || ! $assigned instanceof Array_) {
            return [];
        }

        $items = $this->itemsByStoredKey(array_values($assigned->items)) ?? [];
        $left = [];

        foreach ($members as [$printed, $type]) {
            $key = in_array(VisibleGaps::aside($type)[0], self::PLACEHOLDERS, true) ? PrintedKey::decode($printed) : null;
            $item = $key === null ? null : $items[$key] ?? null;
            $own = $item === null ? [] : array_values(array_filter(
                $arms,
                fn (Expr $arm): bool => new NodeFinder()->findFirst($item->value, fn (Node $node): bool => $node === $arm) !== null,
            ));

            if ($own !== []) {
                $left[$printed] = $own;
            }
        }

        return $left;
    }

    /**
     * The reading with each member a drop left taken from the `@var` shape's member where that admits the placeholder,
     * and the arms whose placeholders it fills; null where none fills, or the tag's imports cannot follow.
     *
     * @param  ValueExpressionResult  $reading
     * @param  ValueExpressionResult  $declared
     * @param  LeftMembers  $left  see leftMembers()
     * @return FilledReading|null
     */
    private function filledMembers(array $reading, array $declared, array $left): ?array
    {
        $members = TsTypeShape::members($reading['type']) ?? [];
        $filled = [];
        $arms = [];

        foreach ($members as $index => [$printed, $type]) {
            $tagged = isset($left[$printed]) ? $this->taggedMember($declared['type'], (string) PrintedKey::decode($printed)) : null;

            if ($tagged !== null && $this->admitsPlaceholder($tagged, VisibleGaps::aside($type)[0])) {
                $members[$index][1] = $filled[] = $tagged;
                array_push($arms, ...$left[$printed]);
            }
        }

        $type = '{ '.implode('; ', array_map(fn (array $member): string => implode(': ', $member), $members)).' }';
        $result = $filled === [] ? null : $this->withTaggedImports([...$reading, 'type' => $type], $declared, implode(' | ', $filled));

        return $result === null ? null : [$result, $arms];
    }

    /**
     * The type a `@var` gives a member: the union of what each of its arms but `null` gives it, which the literal never
     * is, in one order whatever order the arms are written in; null where an arm lacks the key.
     */
    private function taggedMember(string $declared, string $key): ?string
    {
        $arms = array_diff(TsTypeString::splitTopLevelUnion($declared), ['null']);
        $members = array_values(array_map(fn (string $arm): ?string => TsTypeShape::memberType($arm, $key), $arms));

        if ($members === [] || in_array(null, $members, true)) {
            return null;
        }

        if (count($members) === 1) {
            return $members[0];
        }

        $union = array_values(array_unique(array_merge(...array_map(fn (string $member): array => TsTypeString::splitTopLevelUnion($member), $members))));
        rsort($union, SORT_STRING);

        return TsTypeString::hoistNull($union);
    }

    /**
     * Whether a `@var` type admits the placeholder a drop left, what its kept arm sends, and says more than it does
     * without anything vague: a null arm for a `null`, a list arm for a `never[]`.
     */
    private function admitsPlaceholder(string $tagged, string $placeholder): bool
    {
        if ($tagged === $placeholder || TsTypeString::typeNameOccursIn('unknown', $tagged)) {
            return false;
        }

        return match ($placeholder) {
            'null' => in_array('null', TsTypeString::splitTopLevelUnion($tagged), true),
            'never[]' => TsTypeShape::elementType($tagged) !== null,
            default => false,
        };
    }

    /**
     * A filled reading with the `@var`'s models, enums and custom imports that its filled members spell, each class on
     * its embedded channel as a shape's member carries one; null where such a class shares a name with one the reading
     * holds, since no token could tell them apart.
     *
     * @param  ValueExpressionResult  $filled
     * @param  ValueExpressionResult  $declared
     * @return ValueExpressionResult|null
     */
    private function withTaggedImports(array $filled, array $declared, string $spelled): ?array
    {
        $models = $this->spelledClasses(
            [...(isset($declared['modelFqcn']) ? [$declared['modelFqcn']] : []), ...$declared['embeddedModelFqcns'] ?? []],
            [...(isset($filled['modelFqcn']) ? [$filled['modelFqcn']] : []), ...$filled['embeddedModelFqcns'] ?? []],
            $spelled,
            fn (string $fqcn): array => [class_basename($fqcn)],
        );
        $enums = $this->spelledClasses(
            [...(isset($declared['directEnumFqcn']) ? [$declared['directEnumFqcn']] : []), ...$declared['embeddedEnumFqcns'] ?? []],
            [...(isset($filled['directEnumFqcn']) ? [$filled['directEnumFqcn']] : []), ...$filled['embeddedEnumFqcns'] ?? []],
            $spelled,
            ValueResult::enumNames(...),
        );
        $custom = ImportMaps::spelledIn($declared['customImports'] ?? [], $spelled);

        if ($models === null || $enums === null) {
            return null;
        }

        return [
            ...$filled,
            ...($models === [] ? [] : ['embeddedModelFqcns' => [...$filled['embeddedModelFqcns'] ?? [], ...$models]]),
            ...($enums === [] ? [] : ['embeddedEnumFqcns' => [...$filled['embeddedEnumFqcns'] ?? [], ...$enums]]),
            ...($custom === [] ? [] : ['customImports' => ImportMaps::merge($filled['customImports'] ?? [], $custom)]),
        ];
    }

    /**
     * Each of the tag's classes whose name the filled members spell, once; null where one shares a name with another
     * class the reading holds, or with another the filled members spell.
     *
     * @param  list<class-string>  $tagged
     * @param  list<class-string>  $held
     * @param  Closure(class-string): list<string>  $namesOf
     * @return list<class-string>|null
     */
    private function spelledClasses(array $tagged, array $held, string $spelled, Closure $namesOf): ?array
    {
        $classes = [];

        foreach (array_unique($tagged) as $fqcn) {
            $names = $namesOf($fqcn);

            if (! array_any($names, fn (string $name): bool => TsTypeString::typeNameOccursIn($name, $spelled))) {
                continue;
            }

            if (array_any([...$held, ...$classes], fn (string $other): bool => $other !== $fqcn && array_intersect($namesOf($other), $names) !== [])) {
                return null;
            }

            $classes[] = $fqcn;
        }

        return $classes;
    }

    /**
     * A value a gap's write assigned, as the inline `@var` on that write declares where it has one.
     *
     * @return ValueExpressionResult|null
     */
    private function writtenValue(string $name, Expr $value, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $span = $scope->declaredWrite($name, $value);

        return $span === null
            ? $this->boundExpressionValue($name, $value, $scope, $engine)
            : $this->declaredLocalValue($name, $span, $scope, $engine);
    }

    /**
     * Resolve the expression a variable is bound to, or null while that binding is already mid-resolution.
     *
     * @return ValueExpressionResult|null
     */
    private function boundExpressionValue(string $name, Expr $bound, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        return $scope->resolvingBinding($name, $bound, fn (): array => $engine->resolve($bound));
    }

    /**
     * A variable's bound value past the by-reference calls before the read that keep it only in some forms, or null, as
     * for a variable written twice, when it lacks one of them. A cyclic read, such as `array_push($a, $a)`, keeps
     * nothing, and a read the source does not place, such as one the engine builds, takes every call's form.
     *
     * @param  ValueExpressionResult  $value
     * @return ValueExpressionResult|null
     */
    private function keptByReference(
        Variable $read,
        string $name,
        array $value,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        $forms = $read->getStartFilePos() < 0 ? $scope->byReferenceForms[$name] ?? [] : $scope->formsAt($read);

        if ($forms === []) {
            return $value;
        }

        return $scope->resolvingName($name, function () use ($forms, $value, $scope, $engine): ?array {
            $type = $value['type'];
            $matched = false;

            foreach ($forms as $form) {
                $read = [];
                $written = function (string $element, string $key) use ($form, &$read, $scope, $engine): array {
                    $callback = $form['callback'];
                    $at = $callback === null ? '' : $element."\0".$key;

                    return $read[$at] ??= $callback === null
                        ? array_map(fn (Expr $written): string => $this->writtenType($written, $engine), $form['values'])
                        : $this->writtenByCallback($callback, $form['values'], $element, $key, $scope, $engine);
                };
                $type = $form['effect']->left($type, $written, $form['path'], $scope->reindexesNumericKeys, $form['matches']);

                if ($type === null) {
                    return null;
                }

                $matched = $form['effect'] === ByReferenceEffect::WritesMatches;
            }

            return [...$value, 'type' => $type, ...($matched ? ['absentKeys' => true] : [])];
        });
    }

    /**
     * The type of the matches preg_match() or preg_match_all() writes into a name no other write reaches, for a read
     * past the call no longer holding a binding: only a call that is a statement of the body ends one. A skipped call
     * leaves an unbound name undefined, which Laravel throws on and `??` replaces with its default.
     */
    private function writtenMatches(Variable $read, AnalysisScope $scope): ?string
    {
        if (! is_string($read->name) || ($scope->bindsName($read->name) && $scope->holdsBinding($read))) {
            return null;
        }

        $form = $scope->formsAt($read)[0] ?? null;

        return $form !== null && $form['effect'] === ByReferenceEffect::WritesMatches && $form['sole'] && $form['path'] === []
            ? $form['matches']
            : null;
    }

    /**
     * The types a walk's callback writes into an element of one type under a key of another, each read in the
     * callback's own scope: its first parameter bound to the element and its second to the key, beside what a closure
     * imports, or all an arrow function captures, and the body's own locals. No other outer binding reaches it.
     *
     * @param  list<Expr>  $values
     * @return list<string>
     */
    private function writtenByCallback(
        ArrowFunction|ClosureExpr $callback,
        array $values,
        string $element,
        string $key,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): array {
        return $scope->within(function () use ($callback, $element, $key, $scope, $engine): void {
            $scope->enterClosureBody($callback);

            ParameterBinder::bindCallbackArguments(
                $callback,
                [['type' => $element, 'optional' => false], ['type' => $key, 'optional' => false]],
                $scope,
                $engine,
            );

            if ($callback instanceof ClosureExpr) {
                $this->seedBody($callback->stmts, $scope);
            }
        }, fn (): array => array_map(fn (Expr $written): string => $this->writtenType($written, $engine), $values));
    }

    /**
     * The type a write puts in an element: an increment or a decrement leaves a number, any other value its own type.
     */
    private function writtenType(Expr $written, ExpressionEngine $engine): string
    {
        return $written instanceof PreInc || $written instanceof PostInc || $written instanceof PreDec || $written instanceof PostDec
            ? 'number'
            : $engine->resolve($written)['type'];
    }

    /**
     * Analyze `$receiver->map(fn (Item $item) => [...])`, or any callable form, with the first param bound to its
     * element: the hint's model, else the element model or list element of the collection the resolver names. The body
     * wraps as a list, beside the object arm json_encode() writes where an earlier step broke the receiver's keys.
     *
     * Declines a receiver the resolver names outside MAPS_ITEMS, which runs its own map(), a resource collection's
     * items, an unnamed one but a variable a model hint binds, and a receiver whose element nothing names. The param
     * takes a guarded receiver's guard, and a declared list's where its element names the model.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeReceiverMapCall(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $callback = $this->mapArguments($call)->named('callback')?->value;
        $callback = $callback === null ? null : CallableForms::asOneValueClosure(CallableForms::heldCallable($callback, $scope));

        if ($callback === null) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $scope);
        // The model the first parameter binds over an element nothing names: the one its declaration names.
        $declared = ParameterBinder::declaredClosureModel($callback->params[0] ?? null, $scope, (string) $callback->getDocComment());

        // A resource collection's items are the API resources it built, or what it was given if it collects none.
        if ($receiver === null
            ? ! $this->mayHoldCollection($call->var, $declared, $scope)
            : ! $receiver->within(self::MAPS_ITEMS) || $receiver->collectedItems
        ) {
            return null;
        }

        $elementModel = $receiver?->elementModel;
        $elementValue = null;

        // A collection of plain values, such as a pluck() or an earlier map(), passes each value its list holds.
        if ($declared === null && $elementModel === null) {
            $elementValue = $receiver === null ? null : $this->listElement($engine->resolve($call->var));

            if ($elementValue === null) {
                return null;
            }
        }

        // A model the declaration alone names is the parameter's own, as it is where nothing names the receiver.
        $bodyResult = $this->resolveMapCallback(
            $callback,
            $elementModel,
            $elementValue,
            $scope->mapResultArrayed,
            $scope,
            $engine,
            unread: $receiver?->guarded === true && ! $this->mayHoldCollection($call->var, $declared, $scope),
            guardsElement: $receiver?->guardsItems() === true,
            leftOut: $receiver->leftOut ?? [],
        );

        return $this->mappedList($bodyResult, $receiver?->keyed === true, $receiver?->shortCircuits === true);
    }

    /**
     * The value each element of a one-armed list type holds, with the channels the list carries; else null.
     *
     * @param  ValueExpressionResult  $list
     * @return ValueExpressionResult|null
     */
    private function listElement(array $list): ?array
    {
        $element = ArrayArms::listElement(VisibleGaps::aside($list['type'])[0]);

        return $element === null || $element === 'unknown' || $element === 'never'
            ? null
            : [...$list, 'type' => $element, 'optional' => false];
    }

    /**
     * Whether an unnamed receiver is read as a collection.
     *
     * A variable a model hint binds the element of, unless each write its read sees is an array literal, on which map()
     * throws.
     *
     * @param  class-string<Model>|null  $declared
     */
    private function mayHoldCollection(Expr $receiver, ?string $declared, AnalysisScope $scope): bool
    {
        if ($declared === null || ! $receiver instanceof Variable || ! is_string($receiver->name)
            || $receiver->name === 'this'
        ) {
            return false;
        }

        $writes = $scope->writesSeenBy($receiver);

        return $writes === null || ! array_all($writes, fn (Expr $write): bool => $write instanceof Array_);
    }

    /**
     * A `$variable->map(...)` call's arguments mapped against Collection::map(callable $callback).
     */
    private function mapArguments(MethodCall $call): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'map'));
    }

    /**
     * Whether an expression, which the receiver resolver reads as $receiver, holds only Illuminate collections, whose
     * values() and all() the engine knows.
     */
    private function holdsOnlyCollections(Expr $expr, ?ReceiverType $receiver, AnalysisScope $scope): bool
    {
        // An unnamed receiver passes only as the map arm's `$variable->map(callback)` over an unnamed variable,
        // which that arm already types as a Collection map; values() leaves it one.
        if ($receiver === null) {
            while ($expr instanceof MethodCall
                && $expr->name instanceof Identifier
                && $expr->name->toString() === 'values'
                && ! $expr->isFirstClassCallable()
                && $expr->getArgs() === []
            ) {
                $expr = $expr->var;
            }

            $mapped = $this->mappedReceiver($expr);

            return $mapped instanceof Variable && resolve(ReceiverClassResolver::class)->resolve($mapped, $scope) === null;
        }

        // The receiver rules type values()/all() on a variable whose element the resolver names, keys included, where
        // its bare read may give only its `@var` type.
        if ($expr instanceof Variable && $receiver->elementModel !== null) {
            return false;
        }

        return array_all($receiver->classes, fn (string $class): bool => is_a($class, Enumerable::class, true));
    }

    /**
     * The receiver a `$receiver->map(callback)` call maps over, but a bare `$this`, else null.
     */
    private function mappedReceiver(Expr $expr): ?Expr
    {
        return $expr instanceof MethodCall
            && ! ($expr->var instanceof Variable && $expr->var->name === 'this')
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'map'
            && ! $this->mapArguments($expr)->isEmpty()
                ? $expr->var
                : null;
    }

    /**
     * Whether an expression is a variable other than `$this`.
     */
    private function isPlainVariable(Expr $expr): bool
    {
        return $expr instanceof Variable && is_string($expr->name) && $expr->name !== 'this';
    }
}

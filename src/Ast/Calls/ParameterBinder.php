<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\UnionType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Binds the parameters of a method whose body is read apart from its call, such as a subject's helper: by the types
 * they declare, which hold for every call; and one whose declaration binds nothing, or an argument narrows or passes as
 * a model no row backs yet, by what that argument binds, which a value read's memos key on (CallSite::signature()).
 *
 * A body write that replaces a parameter with another class, or a collection's models with other values, ends its
 * binding there, and the parameter's own `$x = …;` the value it binds: the caller seeds the body (seedBody()) once its
 * parameters are bound.
 *
 * A callback's parameters bind to what its caller passes them: a collection's element (elementBinding()), the value
 * at their position (bindCallbackArgument()), each position in turn (bindCallbackArguments()), or the relation
 * whenLoaded() passes (loadedBinding()).
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type ParameterGuard from AnalysisScope
 * @phpstan-import-type CollectionBinding from AnalysisScope
 * @phpstan-import-type RelationTypeInfo from ModelAttributeResolver
 * @phpstan-import-type KeyKind from ReceiverType
 *
 * @phpstan-type PassedBinding = array{model: class-string<Model>|null, classes: non-empty-list<class-string>|null,
 *     elementModel: class-string<Model>|null, value: ValueExpressionResult|null, unsaved: bool, refines: bool,
 *     declaredOnly: bool, guard: ParameterGuard|null, loaded?: true, key?: KeyKind}
 *
 * @internal
 */
final class ParameterBinder
{
    /**
     * The class a closure parameter's type names, alone or beside `null`, with whether `null` is there: its native
     * type, else the `@param` $docComment gives it where it has none; null for any other type, or a name no class or
     * interface has. A native `self` or `parent` names the class the scope binds it to.
     *
     * @return array{class-string, bool}|null
     */
    public static function declaredClass(Param $param, AnalysisScope $scope, ?string $docComment = null): ?array
    {
        if ($param->type === null) {
            return $docComment === null ? null : self::documentedClass($param, $scope, $docComment);
        }

        [$type, $nullable] = self::classArm($param->type);
        $class = $type === null ? null : self::typeClassName($type, $scope);

        return $class !== null && DeclaredClasses::isValueClass($class) ? [$class, $nullable] : null;
    }

    /**
     * The one concrete model a closure parameter's non-null declaration names, natively or by `@param`; null for any
     * other declaration, or none.
     *
     * The instance declaredModel() reads a method parameter's.
     *
     * @return class-string<Model>|null
     */
    public static function declaredClosureModel(?Param $param, AnalysisScope $scope, ?string $docComment = null): ?string
    {
        $declared = $param === null ? null : self::declaredClass($param, $scope, $docComment);

        return $declared !== null && ! $declared[1] && ReceiverType::isConcreteModel($declared[0]) ? $declared[0] : null;
    }

    /**
     * Whether a closure parameter's type is `self` or `parent`, alone or beside `null`: the object may be any subclass.
     */
    public static function spellsRelativeClass(Param $param): bool
    {
        $type = $param->type === null ? null : self::classArm($param->type)[0];

        return $type !== null && $type->isSpecialClassName();
    }

    /**
     * Bind a closure parameter by the one class its declaration names, and say whether it bound a model, which stands
     * for its value too: a concrete model neither `null` nor an `= null` default lets be null in varModelBindings, and
     * any other class, a Request included, in varDeclaredBindings. A `self` or `parent` marks it in relativeParameters.
     */
    public static function bindClosureClass(Param $param, string $name, AnalysisScope $scope, ?string $docComment = null): bool
    {
        $declared = $param->variadic ? null : self::declaredClass($param, $scope, $docComment);

        if ($declared === null) {
            return false;
        }

        [$class, $nullable] = $declared;

        if (self::spellsRelativeClass($param)) {
            $scope->relativeParameters[$name] = true;
        } else {
            unset($scope->relativeParameters[$name]);
        }

        if (! $nullable && ! self::defaultsToNull($param) && ReceiverType::isConcreteModel($class)) {
            $scope->varModelBindings[$name] = $class;

            return true;
        }

        $scope->varDeclaredBindings[$name] = [$class];

        return false;
    }

    /**
     * How a callback's parameter binds a collection's element: by the element's model, or the concrete model its
     * non-null declaration names where the element names none, or where its native type names another, which PHP
     * enforces; else by the element's value; null for neither. A `@param` gives way to any model the element names.
     *
     * A model a `self` or `parent` spells may be any subclass; the element model a receiver names stays as it is. The
     * caller declines a variadic parameter, which collects the element and the key into one list.
     *
     * A bound model is guarded, as `unread`, whatever declares it where $unread says no reading reaches the callback
     * without the guard; else where $guardsElement says the element is guarded and names the model, not a declaration.
     *
     * @param  class-string<Model>|null  $elementModel
     * @param  ValueExpressionResult|null  $elementValue
     * @param  string|null  $docComment  the callback's, whose `@param` declares a parameter with no native type
     * @return PassedBinding|null
     */
    public static function elementBinding(
        ?string $elementModel,
        ?array $elementValue,
        ?Param $param,
        AnalysisScope $scope,
        bool $unread = false,
        bool $guardsElement = false,
        ?string $docComment = null,
    ): ?array {
        $declaredModel = self::declaredClosureModel($param, $scope, $docComment);
        $documented = $param?->type === null;
        $model = $elementModel !== null && ($declaredModel === null || $documented || is_a($elementModel, $declaredModel, true))
            ? $elementModel
            : $declaredModel;

        if ($model === null && $elementValue === null) {
            return null;
        }

        return [
            'model' => $model,
            'classes' => null,
            'elementModel' => null,
            'value' => $model === null ? $elementValue : null,
            'unsaved' => false,
            'refines' => false,
            'declaredOnly' => $model !== null && $model !== $elementModel && $param !== null && self::spellsRelativeClass($param),
            'guard' => match (true) {
                $model === null => null,
                $unread => 'unread',
                $guardsElement && $model !== $declaredModel => 'guarded',
                default => null,
            },
        ];
    }

    /**
     * Bind a callback's parameter at $position to what its caller passes there: an expression as itself, a typed value
     * as its value, and for a variadic parameter the list it collects. A `$filled` argument is never null.
     *
     * @param  Expr|ValueExpressionResult  $argument
     */
    public static function bindCallbackArgument(
        ArrowFunction|ClosureExpr $callback,
        int $position,
        Expr|array $argument,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        bool $filled = false,
    ): void {
        $param = $callback->params[$position] ?? null;

        if ($param === null || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
            return;
        }

        $name = $param->var->name;

        if ($param->variadic) {
            $element = $argument instanceof Expr ? $engine->resolve($argument) : $argument;
            $element = $filled ? [...$element, 'type' => TsTypeString::withoutNullArm($element['type'])] : $element;

            if ($element['type'] !== 'unknown') {
                $scope->varValueBindings[$name] = [...$element, 'type' => ValueResult::arrayWrapType($element['type']), 'optional' => false];
            }

            return;
        }

        if ($filled) {
            $scope->nonNullReads[$name][] = ['read' => $param->var, 'after' => null, 'before' => null];
        }

        if ($argument instanceof Expr) {
            $scope->closureParamExprBindings[$name] = $argument;
        } elseif ($argument['type'] !== 'unknown') {
            $scope->varValueBindings[$name] = $argument;
        }
    }

    /**
     * Bind a callback's parameters by position to what its caller passes, each as bindCallbackArgument() binds it; or,
     * where $elements says the values are an array's elements, each typed one as elementBinding() binds it, so a model
     * a parameter declares binds, the first with $firstElementModel and the guards. A variadic one collects the rest.
     *
     * @param  list<Expr|ValueExpressionResult>  $passed
     * @param  class-string<Model>|null  $firstElementModel
     */
    public static function bindCallbackArguments(
        ArrowFunction|ClosureExpr $callback,
        array $passed,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?string $firstElementModel = null,
        bool $elements = false,
        bool $unread = false,
        bool $guardsElement = false,
    ): void {
        foreach ($passed as $position => $argument) {
            $param = $callback->params[$position] ?? null;

            if (! $elements || $argument instanceof Expr || $param === null || $param->variadic
                || ! $param->var instanceof Variable || ! is_string($param->var->name)
            ) {
                self::bindCallbackArgument($callback, $position, $argument, $scope, $engine);

                continue;
            }

            // An element nothing types binds nothing, so the parameter's own declaration binds it.
            $value = $argument['type'] === 'unknown' ? null : $argument;
            $docComment = (string) $callback->getDocComment();
            $binding = $position === 0
                ? self::elementBinding($firstElementModel, $value, $param, $scope, $unread, $guardsElement, $docComment)
                : self::elementBinding(null, $value, $param, $scope, docComment: $docComment);

            if ($binding !== null) {
                resolve(self::class)->bindPassed([$param->var->name => $binding], $scope);
            }
        }
    }

    /**
     * How a whenLoaded() callback's first parameter binds the relation Laravel passes it: its one model; its whole
     * collection by the element, which binds no value of its own; or a morphTo's targets, any one of which it may hold.
     *
     * @param  RelationTypeInfo  $relation
     * @return PassedBinding|null
     */
    public static function loadedBinding(array $relation): ?array
    {
        $binding = [
            'model' => null,
            'classes' => null,
            'elementModel' => null,
            'value' => null,
            'unsaved' => false,
            'refines' => false,
            'declaredOnly' => false,
            'guard' => null,
            'loaded' => true,
        ];
        $model = $relation['modelFqcn'];

        return match (true) {
            $model !== null && str_ends_with($relation['type'], '[]') => [
                ...$binding,
                'classes' => [EloquentCollection::class],
                'elementModel' => $model,
                'value' => ['type' => $relation['type'], 'optional' => false],
            ],
            $model !== null => [...$binding, 'model' => $model],
            $relation['morphFqcns'] !== [] => [...$binding, 'classes' => $relation['morphFqcns']],
            default => null,
        };
    }

    /**
     * Bind a method's parameters for a read of its body: by what each declares, then by what its call passes.
     *
     * @param  array<string, PassedBinding>  $passed  each parameter's binding, from passedBinding()
     */
    public function bindCall(ReflectionMethod $method, array $passed, AnalysisScope $scope): void
    {
        $this->bindDeclared($method, $scope);
        $this->bindPassed($passed, $scope);
    }

    /**
     * Bind a closure's parameters for a read of its body by what each declares, as bindDeclared() binds a method's: no
     * call is read, so an untyped parameter binds nothing.
     */
    public function bindClosure(ArrowFunction|ClosureExpr $closure, AnalysisScope $scope): void
    {
        $docComment = (string) $closure->getDocComment();

        foreach ($closure->params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $this->bindClosureParameter($param, $param->var->name, $docComment, $scope);
            }
        }
    }

    /**
     * Bind one closure parameter by what it declares, natively or by the `@param` $docComment gives it: the class
     * bindClosureClass() binds, the element a collection generic names, and its value.
     */
    public function bindClosureParameter(Param $param, string $name, string $docComment, AnalysisScope $scope): void
    {
        if (self::bindClosureClass($param, $name, $scope, $docComment)) {
            return;
        }

        $native = $this->closureTypeInfo($param, $scope);
        $value = $this->heldValue(
            $native === null ? null : resolve(ReflectedTypeAcceptor::class)->accept($native),
            $name,
            $param->variadic,
            $docComment,
            $scope->declaringFileClass,
        );
        $nativeClasses = $param->type === null ? null : $scope->varDeclaredBindings[$name] ?? null;
        $collection = $value === null || $param->variadic || ($param->type !== null && $nativeClasses === null)
            ? null
            : $this->declaredCollection($docComment, $name, $scope->declaringFileClass, $nativeClasses, $value);

        if ($collection !== null) {
            unset($scope->varDeclaredBindings[$name]);
            $scope->varCollectionBindings[$name] = $collection;
        }

        if ($value !== null) {
            $scope->varValueBindings[$name] = $value;
        }
    }

    /**
     * Bind each parameter the scope leaves unbound as its argument binds it: a model in varModelBindings, an Eloquent
     * collection of one model in varCollectionBindings, other classes in varDeclaredBindings, and its value.
     *
     * A model no row may back yet binds as a `new` of it, which the receiver resolver reads again with that mark, in
     * place of a declared concrete model's binding, as a narrower argument replaces a declared abstract class's,
     * interface's or union's. A closure's parameters bind here too, having no method for bindCall().
     *
     * An argument spelled `self`, `static` or `parent` marks the parameter in relativeParameters: it may be a subclass.
     * One a guarded receiver binds keeps its guard in guardedParameters; an `unread` one replaces a declared binding,
     * and a refining one a declared collection binding too.
     *
     * A relation whenLoaded() passes binds a morphTo's targets in varClassBindings, and no value of its own.
     *
     * @param  array<string, PassedBinding>  $passed  each parameter's binding, from passedBinding()
     */
    public function bindPassed(array $passed, AnalysisScope $scope): void
    {
        foreach ($passed as $name => $binding) {
            ['model' => $model, 'classes' => $classes, 'elementModel' => $elementModel, 'value' => $value, 'unsaved' => $unsaved, 'refines' => $refines, 'declaredOnly' => $declaredOnly, 'guard' => $guard] = $binding;
            $loaded = $binding['loaded'] ?? false;
            $declaredModel = $scope->varModelBindings[$name] ?? null;

            if ($refines && (isset($scope->varDeclaredBindings[$name]) || isset($scope->varCollectionBindings[$name]))) {
                unset($scope->varDeclaredBindings[$name], $scope->varCollectionBindings[$name], $scope->varValueBindings[$name]);
            } elseif ($unsaved && $classes !== null && $declaredModel !== null && is_a($classes[0], $declaredModel, true)) {
                // A declared concrete model binds as a saved row, so a passed one no row backs yet replaces it.
                unset($scope->varModelBindings[$name]);
            } elseif ($scope->bindsName($name) && ! ($refines && $guard === 'unread')) {
                continue;
            }

            if ($declaredOnly) {
                $scope->relativeParameters[$name] = true;
            } else {
                unset($scope->relativeParameters[$name]);
            }

            if ($guard !== null) {
                $scope->guardedParameters[$name] = $guard;
            } else {
                unset($scope->guardedParameters[$name]);
            }

            if ($model !== null) {
                $scope->varModelBindings[$name] = $model;

                continue;
            }

            if ($classes !== null && $unsaved) {
                $scope->closureParamExprBindings[$name] = new New_(new FullyQualified($classes[0]), [], [ReceiverClassResolver::HOLDS_ATTRIBUTES => true]);
            } elseif ($elementModel !== null) {
                $scope->varCollectionBindings[$name] = [
                    'type' => $value['type'] ?? ValueResult::arrayWrapType(class_basename($elementModel)),
                    'modelFqcn' => $elementModel,
                    ...($classes === null || $classes === [EloquentCollection::class] ? [] : ['classes' => $classes]),
                    ...(isset($binding['key']) ? ['key' => $binding['key']] : []),
                ];
            } elseif ($classes !== null && $loaded) {
                $scope->varClassBindings[$name] = $classes;
            } elseif ($classes !== null) {
                $scope->varDeclaredBindings[$name] = $classes;
            }

            if ($value !== null && ! $loaded) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * How an argument binds the parameter it is passed: the one concrete model it holds, never null, by its class;
     * other classes and its value otherwise; null when it binds nothing. A `?->` can end it as null: no class binds.
     *
     * A binding that refines a declared class names a class, or is null, since its value alone would lose the class.
     * The receiver's guard, and its element's, ride along.
     *
     * @param  ValueExpressionResult  $value
     * @return PassedBinding|null
     */
    public function passedBinding(array $value, ?ReceiverType $receiver, bool $refines = false): ?array
    {
        $held = $receiver === null || $receiver->shortCircuits ? null : $receiver;
        $unsaved = $held !== null && $held->unsaved && $held->models() !== [];
        // Only a `new` of one class carries a model's unsaved mark into the body: a copy that may be more binds none.
        $classes = $held === null || ($unsaved && count($held->classes) !== 1) ? null : $held->classes;
        $unsaved = $unsaved && $classes !== null;
        $model = $classes !== null && count($classes) === 1 && ReceiverType::isConcreteModel($classes[0]) ? $classes[0] : null;
        // A listed Eloquent collection keeps its items' model, which the receiver rules read for first() and the rest.
        $elementModel = $classes === [EloquentCollection::class] && ! $held->keyed ? $held->elementModel : null;
        $declaredOnly = $classes !== null && $held->declaredOnly;
        $guard = match (true) {
            $classes !== null && $held->guarded => 'guarded',
            $elementModel !== null && $held->guardsElement => 'element',
            default => null,
        };

        // A value that may be null keeps its `null` arm for a bare read, as a nullable declared parameter does.
        if ($model !== null && ! $unsaved && ! TsTypeString::hasNullArm($value['type'])) {
            return ['model' => $model, 'classes' => null, 'elementModel' => null, 'value' => null, 'unsaved' => false, 'refines' => $refines, 'declaredOnly' => $declaredOnly, 'guard' => $guard];
        }

        // The parameter holds the value alone: whether the caller's key may be absent, and `??` fills it, stays there.
        unset($value['absentReadsNull']);
        $value = $value['type'] === 'unknown' ? null : [...$value, 'optional' => false];

        return $classes === null && ($value === null || $refines)
            ? null
            : ['model' => null, 'classes' => $classes, 'elementModel' => $elementModel, 'value' => $value, 'unsaved' => $unsaved, 'refines' => $refines, 'declaredOnly' => $declaredOnly, 'guard' => $guard];
    }

    /**
     * Whether any parameter of a method takes what its call passes, so a read of its body depends on the call: one
     * whose declaration binds nothing, one a narrower argument refines, a declared model, or a `@param` collection,
     * whose element a known argument outranks.
     */
    public function takesPassedValues(ReflectionMethod $method): bool
    {
        return array_any(
            $method->getParameters(),
            fn (ReflectionParameter $parameter): bool => $this->takesPassedValue($parameter, $method)
                || $this->narrowableClasses($parameter) !== null
                || $this->declaredModel($parameter) !== null
                || $this->documentedCollection($parameter, $method) !== null,
        );
    }

    /**
     * The collection a method parameter's `@param` generic declares, as bindDeclared() binds it; null for none.
     *
     * @return CollectionBinding|null
     */
    public function documentedCollection(ReflectionParameter $parameter, ReflectionMethod $method): ?array
    {
        $context = LaravelTsPublish::methodDeclaringFileClass($method);
        $docComment = (string) $method->getDocComment();
        $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter);
        $value = $this->declaredValue($parameter, $docComment, $context);

        return $this->parameterCollection($parameter, $classes, $docComment, $context, $value);
    }

    /**
     * How an argument binds a parameter a `@param` collection generic declares: by the collection it holds, its value
     * and keys, the declaration naming only an element it leaves unknown and the kind a moved key is; null where it
     * names no collection, may end as null, or is guarded and not `unread`, so the declaration binds.
     *
     * @param  CollectionBinding  $declared
     * @param  ValueExpressionResult  $value
     * @return PassedBinding|null
     */
    public function passedCollection(
        ReflectionParameter $parameter,
        array $declared,
        array $value,
        ?ReceiverType $receiver,
        bool $unread = false,
    ): ?array {
        if ($receiver === null
            || $receiver->shortCircuits
            || ($receiver->guarded && ! $unread)
            || ! $receiver->within([Enumerable::class])
        ) {
            return null;
        }

        unset($value['absentReadsNull']);
        $known = TsTypeString::isVagueTsType($value['type']) ? null : [...$value, 'optional' => false];
        $key = match (true) {
            $receiver->stringKeyed => 'string',
            $receiver->keyed => ($declared['key'] ?? null) === 'string' ? 'string' : 'array-key',
            // A collection whose element nothing names, such as collect()'s, may hold any keys its value leaves open.
            $receiver->elementModel !== null || ($known !== null && self::listsOnly($known['type'])) => null,
            default => 'array-key',
        };
        $native = $this->nativeClasses($parameter);
        $element = $receiver->elementModel ?? $declared['modelFqcn'];

        return [
            'model' => null,
            'classes' => $native === null || $receiver->within($native) ? $receiver->classes : $native,
            'elementModel' => $element,
            'value' => $known ?? ($receiver->elementModel === null ? self::listedValue($declared, $element, $key) : null),
            'unsaved' => false,
            'refines' => true,
            'declaredOnly' => $receiver->declaredOnly,
            'guard' => match (true) {
                $unread => 'unread',
                $receiver->guardsElement => 'element',
                default => null,
            },
            ...($key === null ? [] : ['key' => $key]),
        ];
    }

    /**
     * The abstract class, interface or union of classes a parameter declares, which a narrower argument refines; null
     * for any other declaration, such as one concrete class, a Request or a variadic list.
     *
     * @return non-empty-list<class-string>|null
     */
    public function narrowableClasses(ReflectionParameter $parameter): ?array
    {
        $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter);

        if ($classes === null || array_any($classes, fn (string $class): bool => is_a($class, Request::class, true))) {
            return null;
        }

        $reflection = new ReflectionClass($classes[0]);

        return count($classes) > 1 || $reflection->isAbstract() || $reflection->isInterface() ? $classes : null;
    }

    /**
     * The one concrete model a parameter declares, which binds as a row that exists unless its call passes one no row
     * backs yet; null for any other declaration.
     *
     * @return class-string<Model>|null
     */
    public function declaredModel(ReflectionParameter $parameter): ?string
    {
        $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter);

        return $classes !== null && count($classes) === 1 && ReceiverType::isConcreteModel($classes[0]) ? $classes[0] : null;
    }

    /**
     * Whether an argument holds narrower classes than a parameter declares: each within a declared one, and not every
     * declared class within them. A `?->` can end it as null, so it narrows nothing.
     *
     * @param  non-empty-list<class-string>  $declared
     */
    public function narrows(array $declared, ?ReceiverType $receiver): bool
    {
        return $receiver !== null
            && ! $receiver->shortCircuits
            && $receiver->within($declared)
            && ! new ReceiverType($declared)->within($receiver->classes);
    }

    /**
     * Whether a parameter's declaration binds nothing, so only the value its call passes can: no class, no type precise
     * enough to publish, natively or by `@param`. A variadic parameter collects its arguments, never holding one.
     */
    public function takesPassedValue(ReflectionParameter $parameter, ReflectionMethod $method): bool
    {
        return ! $parameter->isVariadic()
            && $this->nativeClasses($parameter) === null
            && $this->declaredValue(
                $parameter,
                (string) $method->getDocComment(),
                LaravelTsPublish::methodDeclaringFileClass($method),
            ) === null;
    }

    /**
     * Whether a parameter's default is `null`, which makes its declared type nullable.
     */
    private static function defaultsToNull(Param $param): bool
    {
        return $param->default instanceof ConstFetch && $param->default->name->toLowerString() === 'null';
    }

    /**
     * Whether every arm of a type but `null` is a list, so the value holds keys 0..n-1.
     */
    private static function listsOnly(string $type): bool
    {
        $arms = array_diff(TsTypeShape::splitTopLevel($type, ['|']), ['null']);

        return $arms !== [] && array_all($arms, fn (string $arm): bool => ArrayArms::listElement($arm) !== null);
    }

    /**
     * The value a collection of a declared element reads as under the given keys, spelled from the declaration's one
     * element type; null where the declaration spells none.
     *
     * @param  CollectionBinding  $declared
     * @param  class-string<Model>  $element
     * @param  KeyKind  $key
     * @return ValueExpressionResult|null
     */
    private static function listedValue(array $declared, string $element, ?string $key): ?array
    {
        $types = array_values(array_unique(array_filter(
            ArrayArms::arms($declared['type'], ArrayArms::elementOf(...)) ?? [],
            fn (string $type): bool => $type !== 'never',
        )));

        if (count($types) !== 1) {
            return null;
        }

        $list = TsTypeShape::listType($types);

        return [...ValueResult::unknown(), 'type' => match ($key) {
            'string' => "Record<string, {$types[0]}>",
            'array-key' => ArrayArms::keyedList($list),
            default => $list,
        }, 'optional' => false, 'modelFqcn' => $element];
    }

    /**
     * The one class name a native type spells, alone or beside `null`, with whether `null` is there; a null name for
     * any other type.
     *
     * @return array{Name|null, bool}
     */
    private static function classArm(Node $type): array
    {
        if ($type instanceof NullableType) {
            return [$type->type instanceof Name ? $type->type : null, true];
        }

        $arms = $type instanceof UnionType ? $type->types : [$type];
        $named = array_values(array_filter(
            $arms,
            fn (Node $arm): bool => ! $arm instanceof Identifier || $arm->toLowerString() !== 'null',
        ));

        return count($named) === 1 && $named[0] instanceof Name ? [$named[0], count($arms) > 1] : [null, false];
    }

    /**
     * The class a closure parameter's `@param` names, alone or beside `null`, read against the scope's declaring file,
     * with whether `null` is there; null for a tag the local gate refuses, any other type, or a `self`, `static`,
     * `parent` or `$this`, which may be a subclass.
     *
     * @return array{class-string, bool}|null
     */
    private static function documentedClass(Param $param, AnalysisScope $scope, string $docComment): ?array
    {
        $name = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : null;
        $context = $scope->declaringFileClass;
        $tag = $name === null ? null : resolve(PropertyDocblockTypeReader::class)->extractParamTag($docComment, $name, $context);

        if ($tag === null || preg_match('/(?<![\\w\\\\$])(?:self|static|parent|\\$this)(?![\\w\\\\])/i', $tag) === 1) {
            return null;
        }

        $arms = DeclaredClasses::docblockArms($tag, $context, $context->getName(), $context->getName());

        return $arms['named'] && count($arms['classes']) === 1 && $arms['intersections'] === []
            ? [$arms['classes'][0], $arms['nullable']]
            : null;
    }

    /**
     * The class a closure parameter's type spells: for `self` and `parent` the class the scope binds it to, none for a
     * `parent` the declaring class lacks, and any other name as written.
     */
    private static function typeClassName(Name $name, AnalysisScope $scope): ?string
    {
        return $name->isSpecialClassName() ? $scope->relativeClass($name)?->getName() : $name->toString();
    }

    /**
     * Bind each parameter by its declaration: one concrete, non-null model in varModelBindings, a collection whose
     * `@param` generic names its element in varCollectionBindings, any other class-only type in varDeclaredBindings,
     * and its value, from the native type, else a `@param` where that is too vague.
     *
     * A Request parameter binds as a request, except in an API resource, whose output was inferred without the request
     * rules; a name the scope already binds is left as it is.
     */
    private function bindDeclared(ReflectionMethod $method, AnalysisScope $scope): void
    {
        $parameters = $method->getParameters();

        if ($parameters === []) {
            return;
        }

        $context = LaravelTsPublish::methodDeclaringFileClass($method);
        $docComment = (string) $method->getDocComment();
        $bindsRequests = ! is_a($scope->subjectReflection->getName(), JsonResource::class, true);

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if ($scope->bindsName($name)) {
                continue;
            }

            $request = $this->requestClass($parameter);

            if ($request !== null) {
                if ($bindsRequests) {
                    $scope->requestVarNames[$name] = $request;
                }

                continue;
            }

            $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter);

            if ($classes !== null && array_any($classes, fn (string $class): bool => is_a($class, Request::class, true))) {
                continue;
            }

            $model = $classes !== null && count($classes) === 1 && ReceiverType::isConcreteModel($classes[0]) ? $classes[0] : null;

            if ($model !== null && ! ($parameter->getType()?->allowsNull() ?? true)) {
                $scope->varModelBindings[$name] = $model;

                continue;
            }

            $value = $this->declaredValue($parameter, $docComment, $context);
            $collection = $this->parameterCollection($parameter, $classes, $docComment, $context, $value);

            if ($collection !== null) {
                $scope->varCollectionBindings[$name] = $collection;
            } elseif ($classes !== null) {
                $scope->varDeclaredBindings[$name] = $classes;
            }

            if ($value !== null) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * The request class a parameter's named type declares, nullable or not, else null.
     *
     * @return class-string<Request>|null
     */
    private function requestClass(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        $class = $type instanceof ReflectionNamedType ? $type->getName() : null;

        return $class !== null && is_a($class, Request::class, true) ? $class : null;
    }

    /**
     * The classes a parameter's native type names when every arm but `null` is a loadable class, `self` and `parent`
     * read against the class that declares it, as PHP 8.4 and a trait's method leave them as written; else null.
     *
     * @return non-empty-list<class-string>|null
     */
    private function nativeClasses(ReflectionParameter $parameter): ?array
    {
        $declaring = $parameter->getDeclaringClass()?->getName() ?? '';

        return DeclaredClasses::nativeClasses($parameter->getType(), $declaring, $declaring);
    }

    /**
     * The collection a method parameter's `@param` generic declares over its native classes and the value it reads as;
     * null for a variadic one, a native type naming no class, or a value that does not read.
     *
     * @param  non-empty-list<class-string>|null  $classes  the classes the native type names
     * @param  ReflectionClass<object>  $context
     * @param  ValueExpressionResult|null  $value
     * @return CollectionBinding|null
     */
    private function parameterCollection(
        ReflectionParameter $parameter,
        ?array $classes,
        string $docComment,
        ReflectionClass $context,
        ?array $value,
    ): ?array {
        return $value === null || $parameter->isVariadic() || ($parameter->hasType() && $classes === null)
            ? null
            : $this->declaredCollection($docComment, $parameter->getName(), $context, $classes, $value);
    }

    /**
     * The collection a parameter's `@param` generic declares, as `Collection<int, Comment>` does: its classes, narrowed
     * to the native ones where those are narrower, its element, a concrete model the run publishes, its kind of key and
     * the value it reads as; null for any other `@param`, or a native type that is no collection of it.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports resolve the `@param`
     * @param  non-empty-list<class-string>|null  $native  the classes the native type names, null where it has none
     * @param  ValueExpressionResult  $value
     * @return CollectionBinding|null
     */
    private function declaredCollection(string $docComment, string $name, ReflectionClass $context, ?array $native, array $value): ?array
    {
        $tag = resolve(PropertyDocblockTypeReader::class)->extractParamTag($docComment, $name, $context);
        $generic = $tag === null ? null : DeclaredClasses::collectionGeneric($tag, $context);
        $documented = $tag === null ? null : DeclaredClasses::docblockClasses($tag, $context, $context->getName(), $context->getName());

        if ($generic === null
            || $generic['element'] === null
            || $documented === null
            || ! ValueResult::everyModelPublished([$generic['element']])
        ) {
            return null;
        }

        $classes = match (true) {
            $native === null || new ReceiverType($documented)->within($native) => $documented,
            new ReceiverType($native)->within($documented) => $native,
            default => null,
        };

        return $classes !== null && new ReceiverType($classes)->within([Enumerable::class])
            ? ['type' => $value['type'], 'modelFqcn' => $generic['element'], 'classes' => $classes, 'key' => $generic['key']]
            : null;
    }

    /**
     * The value a parameter holds by its declaration, a list of it for a variadic one; null when the declaration is
     * too vague to publish or names a model with no published file.
     *
     * @param  ReflectionClass<object>  $context
     * @return ValueExpressionResult|null
     */
    private function declaredValue(ReflectionParameter $parameter, string $docComment, ReflectionClass $context): ?array
    {
        return $this->heldValue(
            resolve(ReflectedTypeAcceptor::class)->accept(
                LaravelTsPublish::resolveReflectionType($parameter->getType(), $parameter->getDeclaringClass()),
            ),
            $parameter->getName(),
            $parameter->isVariadic(),
            $docComment,
            $context,
        );
    }

    /**
     * The value a parameter holds by its native type, else by its `@param` where that is too vague, a list of it for a
     * variadic one; null when neither is precise enough to publish or it names a model with no published file.
     *
     * @param  ValueExpressionResult|null  $native
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports resolve the `@param`
     * @return ValueExpressionResult|null
     */
    private function heldValue(?array $native, string $name, bool $variadic, string $docComment, ReflectionClass $context): ?array
    {
        $value = $native;

        if ($value === null || TsTypeString::isVagueTsType($value['type'])) {
            $reader = resolve(PropertyDocblockTypeReader::class);
            $tag = $reader->extractParamTag($docComment, $name, $context);
            $documented = $tag === null ? null : $reader->readDeclared($tag, $context);
            $value = $documented !== null && TsTypeString::documentedWins($value['type'] ?? 'unknown', $documented['type'])
                ? $documented
                : $value;
        }

        if (! ValueResult::isPublishableReading($value)) {
            return null;
        }

        return $variadic
            ? [...$value, 'type' => ValueResult::arrayWrapType($value['type']), 'optional' => false]
            : [...$value, 'optional' => false];
    }

    /**
     * What a closure parameter's native type names, read as resolveReflectionType() reads a method parameter's, an
     * `= null` default adding `null` as PHP does, `self` and `parent` as the classes the scope binds them to; null for
     * no type, an intersection, or a `parent` the declaring class lacks.
     *
     * @return TypeScriptTypeInfo|null
     */
    private function closureTypeInfo(Param $param, AnalysisScope $scope): ?array
    {
        $type = $param->type;
        $arms = match (true) {
            $type instanceof NullableType => [$type->type],
            $type instanceof UnionType => $type->types,
            $type instanceof Identifier, $type instanceof Name => [$type],
            default => [],
        };
        $infos = [];

        foreach ($arms as $arm) {
            $name = match (true) {
                $arm instanceof Identifier => $arm->toString(),
                $arm instanceof Name => self::typeClassName($arm, $scope),
                default => null,
            };

            if ($name === null) {
                return null;
            }

            $infos[] = LaravelTsPublish::toTsType($name);
        }

        if ($infos === []) {
            return null;
        }

        $info = count($infos) === 1 ? $infos[0] : LaravelTsPublish::mergeTypeScriptInfos($infos);

        return $type instanceof NullableType || self::defaultsToNull($param)
            ? [...$info, 'type' => TsTypeString::withNullArm($info['type'])]
            : $info;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Answers which PHP class(es) an expression holds, so a call on it can be reflected on the right class.
 *
 * Rules (mirrored in docs/components/receiver-types.md):
 * - `$this->resource` holds `modelClass ?? instanceOfWrappedClass`; `$this->prop` not declared on the subject reads that
 *   backing class exactly as `$this->resource->prop` does, so both spellings resolve alike.
 * - `$this->prop` declared on the subject below any `Illuminate\` ancestor: its native class type, else its `@var` classes.
 * - A model member: `resolveAttributeClass()`, else a relation (morph targets or `resolveMorphToBound()`, an Eloquent
 *   collection carrying `elementModel` for to-many, the related model for to-one). A non-model: its declared property.
 * - `$var`: model, collection and Request bindings, then closure-param and local bindings resolved recursively.
 * - `$this->method()`: the subject's own method, else the backing class's when the subject is a JsonResource.
 * - A relation method on a model holds its return class plus `relatedModel`; `getRelated()` holds that model.
 * - `$request->user()` holds the auth model; any other call holds `returnClasses()` for every receiver class.
 * - `X::m()`, `new X`, `resolve(X::class)`, `app(X::class)`, `now()`, `today()`, `collect()`, and ternary/`??` arms.
 *
 * @internal
 */
final class ReceiverClassResolver
{
    /**
     * Resolve the classes an expression holds, or null when any part of it cannot be named.
     */
    public function resolve(Expr $expr, AnalysisScope $scope): ?ReceiverType
    {
        return match (true) {
            $expr instanceof Variable => $this->fromVariable($expr, $scope),
            $expr instanceof PropertyFetch, $expr instanceof NullsafePropertyFetch => $this->fromPropertyFetch($expr, $scope),
            $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall => $this->fromMethodCall($expr, $scope),
            $expr instanceof StaticCall => $this->resolveStaticClass($expr, $scope),
            $expr instanceof New_ => $expr->class instanceof Name && class_exists($expr->class->toString())
                ? ReceiverType::of($expr->class->toString())
                : null,
            $expr instanceof FuncCall => $this->fromFunctionCall($expr),
            $expr instanceof Ternary => $this->fromArms([$expr->if ?? $expr->cond, $expr->else], $scope, $expr->if === null),
            $expr instanceof Coalesce => $this->fromArms([$expr->left, $expr->right], $scope, true),
            default => null,
        };
    }

    /**
     * Resolve the classes a static call returns: `X::m()`, `self::`/`static::`/`parent::`, or `$expr::m()`.
     */
    public function resolveStaticClass(StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        if (! $call->name instanceof Identifier || $call->isFirstClassCallable()) {
            return null;
        }

        $receiver = $call->class instanceof Name
            ? $this->namedClass($call->class, $scope)
            : $this->resolve($call->class, $scope);

        if ($receiver === null) {
            return null;
        }

        $method = $call->name->toString();

        return $this->merge(
            array_map(fn (string $class): ?ReceiverType => $this->typeOf($this->returnClasses($class, $method)), $receiver->classes),
            $receiver->shortCircuits,
        );
    }

    /**
     * The classes a method returns: its native class type, else its `@return` docblock; null when any arm is not a class.
     *
     * @return non-empty-list<class-string>|null
     */
    public function returnClasses(string $class, string $method): ?array
    {
        if (! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);

        if ($reflection->hasReturnType()) {
            return $this->nativeClasses($reflection->getReturnType(), $class);
        }

        $docblock = LaravelTsPublish::extractReturnTypeFromDocblock((string) $reflection->getDocComment());

        return $docblock === null
            ? null
            : $this->docblockClasses($docblock, LaravelTsPublish::methodDeclaringFileClass($reflection), $class);
    }

    /**
     * Resolve a variable through the scope's bindings; an unbound variable declines.
     */
    private function fromVariable(Variable $variable, AnalysisScope $scope): ?ReceiverType
    {
        $name = $variable->name;

        if (! is_string($name) || $name === 'this') {
            return null;
        }

        if (isset($scope->varModelBindings[$name])) {
            return ReceiverType::of($scope->varModelBindings[$name]);
        }

        if (isset($scope->varCollectionBindings[$name])) {
            return new ReceiverType([EloquentCollection::class], elementModel: $scope->varCollectionBindings[$name]['modelFqcn']);
        }

        if (isset($scope->requestVarNames[$name])) {
            return ReceiverType::of($scope->requestVarNames[$name]);
        }

        $bound = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name] ?? null;

        if ($bound === null || isset($scope->resolvingLocalVars[$name])) {
            return null;
        }

        $scope->resolvingLocalVars[$name] = true;

        try {
            return $this->resolve($bound, $scope);
        } finally {
            unset($scope->resolvingLocalVars[$name]);
        }
    }

    /**
     * Resolve `$this->prop`, `<receiver>->prop`, or `<receiver>?->prop`.
     */
    private function fromPropertyFetch(PropertyFetch|NullsafePropertyFetch $fetch, AnalysisScope $scope): ?ReceiverType
    {
        if (! $fetch->name instanceof Identifier) {
            return null;
        }

        $name = $fetch->name->toString();

        if ($fetch->var instanceof Variable && $fetch->var->name === 'this') {
            return $this->fromThisProperty($name, $scope);
        }

        $receiver = $this->resolve($fetch->var, $scope);

        if ($receiver === null) {
            return null;
        }

        return $this->merge(
            array_map(fn (string $class): ?ReceiverType => $this->memberProperty($class, $name), $receiver->classes),
            $receiver->shortCircuits || $fetch instanceof NullsafePropertyFetch,
        );
    }

    /**
     * Resolve `$this->prop`: a subject-declared property first, because PHP reads it before any `__get()` forwarding.
     */
    private function fromThisProperty(string $name, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;
        $backing = $scope->modelClass ?? $scope->instanceOfWrappedClass;

        if (! $subject->hasProperty($name)) {
            return $backing === null ? null : $this->memberProperty($backing, $name);
        }

        $property = $subject->getProperty($name);

        if (str_starts_with($property->getDeclaringClass()->getName(), 'Illuminate\\')) {
            return $name === 'resource' && $backing !== null ? ReceiverType::of($backing) : null;
        }

        return $property->isStatic() ? null : $this->typeOf($this->propertyClasses($property));
    }

    /**
     * Resolve `$this->m()`, `<receiver>->m()`, or `<receiver>?->m()`.
     */
    private function fromMethodCall(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): ?ReceiverType
    {
        if (! $call->name instanceof Identifier || $call->isFirstClassCallable()) {
            return null;
        }

        $method = $call->name->toString();

        if ($call->var instanceof Variable && $call->var->name === 'this') {
            return $this->fromThisMethodCall($method, $scope);
        }

        $receiver = $this->resolve($call->var, $scope);

        if ($receiver === null) {
            return null;
        }

        $shortCircuits = $receiver->shortCircuits || $call instanceof NullsafeMethodCall;

        if ($method === 'getRelated' && $receiver->relatedModel !== null) {
            return ReceiverType::of($receiver->relatedModel, $shortCircuits);
        }

        $authModel = $method === 'user' && $this->holdsOnlyRequests($receiver) ? resolve(AuthUserResolver::class)->model() : null;

        if ($authModel !== null) {
            return ReceiverType::of($authModel, $shortCircuits);
        }

        return $this->merge(
            array_map(fn (string $class): ?ReceiverType => $this->memberMethod($class, $method), $receiver->classes),
            $shortCircuits,
        );
    }

    /**
     * Resolve `$this->m()`: the subject's own method, else the backing class's, since JsonResource forwards `__call()`.
     */
    private function fromThisMethodCall(string $method, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;

        if ($subject->hasMethod($method)) {
            return $this->memberMethod($subject->getName(), $method);
        }

        $backing = $scope->modelClass ?? $scope->instanceOfWrappedClass;

        return $backing !== null && $subject->isSubclassOf(JsonResource::class)
            ? $this->memberMethod($backing, $method)
            : null;
    }

    /**
     * Resolve `now()`, `today()`, `collect()`, `resolve(X::class)` and `app(X::class)`.
     */
    private function fromFunctionCall(FuncCall $call): ?ReceiverType
    {
        if (! $call->name instanceof Name || $call->isFirstClassCallable()) {
            return null;
        }

        return match ($call->name->toLowerString()) {
            'now', 'today' => ReceiverType::of(Carbon::class),
            'collect' => ReceiverType::of(Collection::class),
            'resolve', 'app' => $this->containerClass($call),
            default => null,
        };
    }

    /**
     * Union the non-null arms of a ternary or coalesce; any unresolved non-null arm declines the whole expression.
     *
     * @param  list<Expr>  $arms
     * @param  bool  $firstArmFallsBack  a `??` or `?:` replaces a null first arm, so its short circuit never escapes
     */
    private function fromArms(array $arms, AnalysisScope $scope, bool $firstArmFallsBack): ?ReceiverType
    {
        $types = [];
        $shortCircuits = false;

        foreach ($arms as $index => $arm) {
            if ($arm instanceof ConstFetch && $arm->name->toLowerString() === 'null') {
                continue;
            }

            $type = $this->resolve($arm, $scope);

            if ($type === null) {
                return null;
            }

            $types[] = $type;
            $shortCircuits = $shortCircuits || ($type->shortCircuits && ! ($firstArmFallsBack && $index === 0));
        }

        return $this->merge($types, $shortCircuits);
    }

    /**
     * What a property holds on one class: a model's attribute or relation, or a plain class's declared property.
     */
    private function memberProperty(string $class, string $name): ?ReceiverType
    {
        if (is_a($class, Model::class, true)) {
            return $this->modelMember($class, $name);
        }

        if (! class_exists($class) || ! property_exists($class, $name)) {
            return null;
        }

        $property = new ReflectionProperty($class, $name);

        return $property->isStatic() ? null : $this->typeOf($this->propertyClasses($property));
    }

    /**
     * What a model attribute or relation property holds.
     *
     * @param  class-string<Model>  $model
     */
    private function modelMember(string $model, string $name): ?ReceiverType
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $attributeClass = $resolver->resolveAttributeClass($model, $name);

        if ($attributeClass !== null) {
            return ReceiverType::of($attributeClass);
        }

        $relation = $resolver->resolveRelation($model, $name);

        if ($relation['morphFqcns'] !== []) {
            return new ReceiverType($relation['morphFqcns']);
        }

        if ($relation['modelFqcn'] !== null) {
            return str_ends_with($relation['type'], '[]')
                ? new ReceiverType([EloquentCollection::class], elementModel: $relation['modelFqcn'])
                : ReceiverType::of($relation['modelFqcn']);
        }

        // Only a morphTo is a known relation that names no related model.
        $isMorphTo = $resolver->getRelations($model)?->firstWhere('name', $name) !== null;

        return $isMorphTo ? ReceiverType::of($resolver->resolveMorphToBound($model, $name)) : null;
    }

    /**
     * What a method call returns on one class; a relation method on a model also remembers its related model.
     */
    private function memberMethod(string $class, string $method): ?ReceiverType
    {
        $related = is_a($class, Model::class, true)
            ? resolve(ModelAttributeResolver::class)->resolveRelation($class, $method)['modelFqcn']
            : null;

        if ($related !== null) {
            return new ReceiverType($this->returnClasses($class, $method) ?? [Relation::class], relatedModel: $related);
        }

        return $this->typeOf($this->returnClasses($class, $method));
    }

    /**
     * The class a `self`, `static`, `parent`, or fully-qualified name refers to.
     */
    private function namedClass(Name $name, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;

        $class = match ($name->toLowerString()) {
            'self', 'static' => $subject->getName(),
            'parent' => $subject->getParentClass() === false ? null : $subject->getParentClass()->getName(),
            default => $name->toString(),
        };

        return $class !== null && $this->isClassLike($class) ? ReceiverType::of($class) : null;
    }

    /**
     * The class named by `resolve(X::class)` or `app(X::class)`.
     */
    private function containerClass(FuncCall $call): ?ReceiverType
    {
        $abstract = ($call->getArgs()[0] ?? null)?->value;

        if (! $abstract instanceof ClassConstFetch
            || ! $abstract->class instanceof Name
            || ! $abstract->name instanceof Identifier
            || $abstract->name->toLowerString() !== 'class'
        ) {
            return null;
        }

        $class = $abstract->class->toString();

        return $this->isClassLike($class) ? ReceiverType::of($class) : null;
    }

    /**
     * A property's classes: its native class type, else its `@var` docblock.
     *
     * @return non-empty-list<class-string>|null
     */
    private function propertyClasses(ReflectionProperty $property): ?array
    {
        $declaring = $property->getDeclaringClass();
        $native = $this->nativeClasses($property->getType(), $declaring->getName());

        if ($native !== null) {
            return $native;
        }

        $docComment = $property->getDocComment();

        if ($docComment === false || ! preg_match('/(?<![\w-])@var\s+([^\s*]+)/', $docComment, $m)) {
            return null;
        }

        return $this->docblockClasses($m[1], $declaring, $declaring->getName());
    }

    /**
     * The classes a native type names; null when it is absent, an intersection, or has any builtin arm but `null`.
     *
     * @return non-empty-list<class-string>|null
     */
    private function nativeClasses(?ReflectionType $type, string $selfClass): ?array
    {
        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        $classes = [];

        foreach ($members as $member) {
            if (! $member instanceof ReflectionNamedType) {
                return null;
            }

            $name = $member->getName();

            if ($name === 'null') {
                continue;
            }

            $class = in_array($name, ['static', 'self'], true) ? $selfClass : $name;

            if (($member->isBuiltin() && $name !== 'static') || ! $this->isClassLike($class)) {
                return null;
            }

            $classes[] = $class;
        }

        return $classes === [] ? null : array_values(array_unique($classes));
    }

    /**
     * The classes a docblock union names, resolved against the declaring file's imports; null when any arm is not a class.
     *
     * @param  ReflectionClass<object>  $context
     * @return non-empty-list<class-string>|null
     */
    private function docblockClasses(string $type, ReflectionClass $context, string $selfClass): ?array
    {
        $useMap = LaravelTsPublish::parseFileUseStatements($context);
        $classes = [];

        foreach (LaravelTsPublish::splitPhpDocUnionType($type) as $part) {
            // A generic's arguments never change which class the value is an instance of.
            $name = Str::before(ltrim($part, '?'), '<');

            if (strtolower($name) === 'null') {
                continue;
            }

            $class = in_array($name, ['$this', 'static', 'self'], true)
                ? $selfClass
                : LaravelTsPublish::resolveDocblockTypeName($name, $useMap, $context->getNamespaceName());

            if (! $this->isClassLike($class)) {
                return null;
            }

            $classes[] = $class;
        }

        return $classes === [] ? null : array_values(array_unique($classes));
    }

    /**
     * Union resolved receivers under one short-circuit flag; any null part declines the whole union.
     *
     * An element or related model survives only when every part agrees on it.
     *
     * @param  list<ReceiverType|null>  $types
     */
    private function merge(array $types, bool $shortCircuits): ?ReceiverType
    {
        $classes = [];
        $elementModels = [];
        $relatedModels = [];

        foreach ($types as $type) {
            if ($type === null) {
                return null;
            }

            $classes = [...$classes, ...$type->classes];
            $elementModels[$type->elementModel ?? ''] = $type->elementModel;
            $relatedModels[$type->relatedModel ?? ''] = $type->relatedModel;
        }

        if ($classes === []) {
            return null;
        }

        return new ReceiverType(
            array_values(array_unique($classes)),
            $shortCircuits,
            count($elementModels) === 1 ? reset($elementModels) : null,
            count($relatedModels) === 1 ? reset($relatedModels) : null,
        );
    }

    /**
     * Wrap a class list as a receiver, keeping null as a decline.
     *
     * @param  non-empty-list<class-string>|null  $classes
     */
    private function typeOf(?array $classes): ?ReceiverType
    {
        return $classes === null ? null : new ReceiverType($classes);
    }

    /**
     * Whether every class the receiver holds is an HTTP request.
     */
    private function holdsOnlyRequests(ReceiverType $receiver): bool
    {
        foreach ($receiver->classes as $class) {
            if (! is_a($class, Request::class, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a name is a loadable class, interface, or enum.
     *
     * @phpstan-assert-if-true class-string $name
     */
    private function isClassLike(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name);
    }
}

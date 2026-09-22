<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
 * Answers which PHP class(es) an expression holds; the rules are in docs/components/receiver-types.md § Receiver resolution.
 *
 * @internal
 */
final class ReceiverClassResolver
{
    use ReadsInstanceofChains;

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
            $expr instanceof New_ => $expr->class instanceof Name ? $this->namedClass($expr->class, $scope) : null,
            $expr instanceof FuncCall => $this->fromFunctionCall($expr),
            $expr instanceof Ternary => $this->fromArms([$expr->if ?? $expr->cond, $expr->else], $scope, $expr->if === null, $this->testedClasses($expr)),
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

        $receiver = $this->resolveStaticReceiver($call, $scope);

        if ($receiver === null) {
            return null;
        }

        $method = $call->name->toString();
        $fromInside = $call->class instanceof Name && $call->class->isSpecialClassName();

        return $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => $this->isCallable($class, $method, $fromInside)
                    ? $this->typeOf($this->returnClasses($class, $method))
                    : null,
                $receiver->classes,
            ),
            $receiver->shortCircuits,
        );
    }

    /**
     * Resolve the class a static call is made on, not what it returns: `X::`, `self::`/`static::`/`parent::`, or `$expr::`.
     */
    public function resolveStaticReceiver(StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        return $call->class instanceof Name
            ? $this->namedClass($call->class, $scope)
            : $this->resolve($call->class, $scope);
    }

    /**
     * The classes a method returns: its native class type, else its `@return` docblock; null when any arm is not a class.
     *
     * `static` and `$this` name the receiver class; `self` names the class that declares the method. Visibility is the
     * caller's concern.
     *
     * @return non-empty-list<class-string>|null
     */
    public function returnClasses(string $class, string $method): ?array
    {
        if (! method_exists($class, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($class, $method);
        $declaring = $reflection->getDeclaringClass()->getName();

        if ($reflection->hasReturnType()) {
            return $this->nativeClasses($reflection->getReturnType(), $class, $declaring);
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $reflection->getDocComment());

        return $docblock === null
            ? null
            : $this->docblockClasses($docblock, LaravelTsPublish::methodDeclaringFileClass($reflection), $class, $declaring);
    }

    /**
     * The class a bare `$this->m()` runs on when the subject does not declare `m`: its proxy target.
     *
     * `JsonResource::__call()` forwards an undeclared method to `$this->resource`, so the call is
     * `$this->resource->m()`. Which subjects forward, and to what, is policy the scope carries — reading it
     * here keeps this resolver plain PHP semantics rather than one framework class's magic.
     */
    public function forwardedThisReceiver(string $method, AnalysisScope $scope): ?ReceiverType
    {
        $target = $scope->forwardsUndeclaredMembersTo;

        return $target !== null && ! $scope->subjectReflection->hasMethod($method)
            ? ReceiverType::of($target)
            : null;
    }

    /**
     * The model a bare `$this` is when the subject under analysis is itself a model, as in its own method or accessor.
     */
    public function modelSubject(AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;

        return $subject->isSubclassOf(Model::class) ? ReceiverType::of($subject->getName()) : null;
    }

    /**
     * What a property holds on a receiver other than `$this`: a model's attribute or relation, or a public property.
     *
     * Public so ReceiverPropertyFetchHandler can decide its false-string rule one receiver class at a time. Asking
     * `resolve()` about the whole expression instead answers `null` for a union as soon as one arm holds a builtin.
     */
    public function memberProperty(string $class, string $name): ?ReceiverType
    {
        if (is_a($class, Model::class, true)) {
            return $this->modelMember($class, $name);
        }

        if (! class_exists($class) || ! property_exists($class, $name)) {
            return null;
        }

        $property = new ReflectionProperty($class, $name);

        // A non-public property read from outside goes to __get(), not to the declaration.
        return $property->isPublic() && ! $property->isStatic()
            ? $this->typeOf($this->propertyClasses($property, $class))
            : null;
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

        // First, so a narrowing outranks the localVarBindings fallback a guarded variable normally reaches
        // through; varModelBindings is the same concern for a narrowed closure param or loop variable.
        if (isset($scope->varClassBindings[$name])) {
            return new ReceiverType($scope->varClassBindings[$name]);
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

        if ($property->isStatic()) {
            return null;
        }

        // A subject that redeclares a framework name still names its own class (`/** @var MediaType|null */
        // public $resource`); `@var mixed` on JsonResource::$resource names nothing, so a plain
        // `$this->resource` falls through to the backing model below.
        $declared = str_starts_with($property->getDeclaringClass()->getName(), 'Illuminate\\')
            ? null
            : $this->typeOf($this->propertyClasses($property, $subject->getName()));

        if ($declared !== null || resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty($subject, $name)) {
            return $declared;
        }

        return $name === 'resource' && $scope->forwardsUndeclaredMembersTo !== null
            ? ReceiverType::of($scope->forwardsUndeclaredMembersTo)
            : null;
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
            array_map(fn (string $class): ?ReceiverType => $this->memberMethod($class, $method, false), $receiver->classes),
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
            return $this->memberMethod($subject->getName(), $method, true);
        }

        $forwarded = $this->forwardedThisReceiver($method, $scope);

        return $forwarded === null ? null : $this->memberMethod($forwarded->classes[0], $method, false);
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
     * @param  non-empty-list<class-string>|null  $firstArmTested  what a ternary's condition tests its true arm for
     */
    private function fromArms(array $arms, AnalysisScope $scope, bool $firstArmFallsBack, ?array $firstArmTested = null): ?ReceiverType
    {
        $types = [];
        $shortCircuits = false;

        foreach ($arms as $index => $arm) {
            if ($arm instanceof ConstFetch && $arm->name->toLowerString() === 'null') {
                continue;
            }

            $type = $index === 0 && $firstArmTested !== null
                ? $this->narrowed($this->resolve($arm, $scope), $firstArmTested)
                : $this->resolve($arm, $scope);

            if ($type === null) {
                return null;
            }

            $types[] = $type;
            $shortCircuits = $shortCircuits || ($type->shortCircuits && ! ($firstArmFallsBack && $index === 0));
        }

        return $this->merge($types, $shortCircuits);
    }

    /**
     * The classes a ternary's `instanceof` test, or `||` chain of them, tests its own true arm for.
     *
     * The arm runs when any operand holds, so every operand must test the arm's own read path. A negation proves
     * nothing about the arm; `&&` would prove its `instanceof` operands, but only an `||` chain is read.
     *
     * @return non-empty-list<class-string>|null
     */
    private function testedClasses(Ternary $ternary): ?array
    {
        if ($ternary->if === null) {
            return null;
        }

        $classes = [];

        foreach ($this->orOperands($ternary->cond) as $operand) {
            $test = $this->instanceofTest($operand);

            if ($test === null || ! $this->isSameReadPath($test[0], $ternary->if)) {
                return null;
            }

            $classes[] = $test[1];
        }

        return array_values(array_unique($classes));
    }

    /**
     * Whether two expressions spell the same variable or property read.
     *
     * A method call is never the same read: a second call may return another value than the one the test saw.
     */
    private function isSameReadPath(Expr $left, Expr $right): bool
    {
        if ($left instanceof Variable && $right instanceof Variable) {
            return is_string($left->name) && $left->name === $right->name;
        }

        if (! ($left instanceof PropertyFetch && $right instanceof PropertyFetch)
            && ! ($left instanceof NullsafePropertyFetch && $right instanceof NullsafePropertyFetch)
        ) {
            return false;
        }

        return $left->name instanceof Identifier
            && $right->name instanceof Identifier
            && $left->name->toString() === $right->name->toString()
            && $this->isSameReadPath($left->var, $right->var);
    }

    /**
     * A true arm under its `instanceof` test: narrowed class by class, or the tested classes if it resolves to none.
     *
     * Only the classes change: the value is the one the arm resolved, so its short circuit and models still hold.
     *
     * @param  non-empty-list<class-string>  $tested
     */
    private function narrowed(?ReceiverType $arm, array $tested): ReceiverType
    {
        if ($arm === null) {
            return new ReceiverType($tested);
        }

        $classes = [];

        foreach ($arm->classes as $class) {
            $classes = [...$classes, ...$this->narrowedClass($class, $tested)];
        }

        // No class it resolves to can pass the test, so the arm never runs and any answer holds: keep the one it had.
        return $classes === []
            ? $arm
            : new ReceiverType(array_values(array_unique($classes)), $arm->shortCircuits, $arm->elementModel, $arm->relatedModel);
    }

    /**
     * What one class a true arm resolves to becomes under its tests: itself, the tested subclasses, or nothing.
     *
     * It stays when it passes a test, or when a test unrelated to it has an interface on either side: a subclass of
     * it may pass that test, and no single class names the pair. Otherwise the tested subclasses replace it; with none
     * it is dropped, since every test then names a class unrelated to it by inheritance and no object is both.
     *
     * @param  class-string  $class
     * @param  non-empty-list<class-string>  $tested
     * @return list<class-string>
     */
    private function narrowedClass(string $class, array $tested): array
    {
        $subclasses = [];

        foreach ($tested as $test) {
            if (is_a($class, $test, true)) {
                return [$class];
            }

            if (is_a($test, $class, true)) {
                $subclasses[] = $test;

                continue;
            }

            if (interface_exists($class) || interface_exists($test)) {
                return [$class];
            }
        }

        return $subclasses;
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
     *
     * @param  bool  $fromInside  the call is on `$this`, so a non-public method is reachable
     */
    private function memberMethod(string $class, string $method, bool $fromInside): ?ReceiverType
    {
        if (! $this->isCallable($class, $method, $fromInside)) {
            return null;
        }

        $related = is_a($class, Model::class, true)
            ? resolve(ModelAttributeResolver::class)->resolveRelation($class, $method)['modelFqcn']
            : null;

        if ($related !== null) {
            return new ReceiverType($this->returnClasses($class, $method) ?? [Relation::class], relatedModel: $related);
        }

        return $this->typeOf($this->returnClasses($class, $method));
    }

    /**
     * The class a `self`, `static`, `parent`, or fully-qualified name refers to, from inside the subject.
     */
    private function namedClass(Name $name, AnalysisScope $scope): ?ReceiverType
    {
        $subject = $scope->subjectReflection;
        $parent = $subject->getParentClass();

        // self/parent bind to the class declaring the body, and an inherited body is analyzed under the child;
        // only with no user-land ancestor is that class always the subject.
        $bodyIsSubjects = $parent === false || str_starts_with($parent->getName(), 'Illuminate\\');

        $class = match ($name->toLowerString()) {
            'static' => $subject->getName(),
            'self' => $bodyIsSubjects ? $subject->getName() : null,
            'parent' => $bodyIsSubjects && $parent !== false ? $parent->getName() : null,
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
     * A property's classes: its native class type, else its full `@var` type.
     *
     * @return non-empty-list<class-string>|null
     */
    private function propertyClasses(ReflectionProperty $property, string $receiverClass): ?array
    {
        $declaring = $property->getDeclaringClass();
        $native = $this->nativeClasses($property->getType(), $receiverClass, $declaring->getName());

        if ($native !== null) {
            return $native;
        }

        $docComment = $property->getDocComment();
        $declared = $docComment === false ? null : resolve(PropertyDocblockTypeReader::class)->extractVarType($docComment);

        return $declared === null || $declared === ''
            ? null
            : $this->docblockClasses($declared, $declaring, $receiverClass, $declaring->getName());
    }

    /**
     * The classes a native type names; null when it is absent, an intersection, or has any builtin arm but `null`.
     *
     * @return non-empty-list<class-string>|null
     */
    private function nativeClasses(?ReflectionType $type, string $staticClass, string $selfClass): ?array
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

            $class = match ($name) {
                'static' => $staticClass,
                'self' => $selfClass,
                default => $name,
            };

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
    private function docblockClasses(string $type, ReflectionClass $context, string $staticClass, string $selfClass): ?array
    {
        $useMap = LaravelTsPublish::parseFileUseStatements($context);
        $classes = [];

        foreach (LaravelTsPublish::splitPhpDocUnionType($type) as $part) {
            // Generic arguments never change the class, but text after the closing `>`, such as `[]`, does.
            if (! preg_match('/^\??([\\\\\w$]+)(?:<.*>)?$/s', $part, $match)) {
                return null;
            }

            $name = $match[1];

            if (strtolower($name) === 'null') {
                continue;
            }

            $class = match ($name) {
                '$this', 'static' => $staticClass,
                'self' => $selfClass,
                default => LaravelTsPublish::resolveDocblockTypeName($name, $useMap, $context->getNamespaceName()),
            };

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
     * Whether a method exists and is reachable: any visibility from inside the subject, public from outside.
     *
     * A non-public method called from outside goes to `__call()`, never to the declaration.
     */
    private function isCallable(string $class, string $method, bool $fromInside): bool
    {
        return method_exists($class, $method) && ($fromInside || new ReflectionMethod($class, $method)->isPublic());
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

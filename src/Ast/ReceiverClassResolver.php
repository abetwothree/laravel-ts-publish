<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\NarrowsInstanceofSubjects;
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
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Answers which PHP class(es) an expression holds.
 *
 * The rules are in docs/components/receiver-types.md § Receiver resolution.
 *
 * @phpstan-import-type InstanceofProof from ReadsInstanceofChains
 * @phpstan-import-type VarDocBinding from AnalysisScope
 *
 * @internal
 */
final class ReceiverClassResolver
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;
    use NarrowsInstanceofSubjects;
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
            $expr instanceof Ternary => $this->fromArms(
                [$expr->if ?? $expr->cond, $expr->else],
                $scope,
                $expr->if === null,
                $expr->if === null ? null : $this->instanceofProof($expr->cond),
            ),
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
     * Resolve the class a static call is made on, not what it returns: `X::`, `self::`/`static::`/`parent::`, `$x::`.
     */
    public function resolveStaticReceiver(StaticCall $call, AnalysisScope $scope): ?ReceiverType
    {
        return $call->class instanceof Name
            ? $this->namedClass($call->class, $scope)
            : $this->resolve($call->class, $scope);
    }

    /**
     * The classes a method returns: its native class type, else its `@return` docblock; null for any arm not a class.
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
     * The classes a method's own `return` statements name, read from its body without running it: the class each
     * returned object holds, or the class a returned `X::class` spells; null when any return names none.
     *
     * For a method that answers with an object or its class name alike, such as `Castable::castUsing()`.
     *
     * @return non-empty-list<class-string>|null
     */
    public function bodyReturnClasses(string $class, string $method): ?array
    {
        $context = resolve(MethodLocator::class)->locate($class, $method);

        if ($context === null) {
            return null;
        }

        $scope = resolve(AstEngine::class)->bindingsFor($context);
        $classes = [];

        foreach ($this->collectReturnExpressions($context->method->stmts ?? []) as $returned) {
            $className = $this->classNameFetch($returned);
            $held = $className === null ? $this->resolve($returned, $scope) : $this->namedClass($className, $scope);

            if ($held === null) {
                return null;
            }

            $classes = [...$classes, ...$held->classes];
        }

        return $classes === [] ? null : array_values(array_unique($classes));
    }

    /**
     * The class a bare `$this->m()` runs on when the subject does not declare `m`: its proxy target, since
     * `JsonResource::__call()` forwards it to `$this->resource`. The scope carries which subjects forward, so this
     * resolver keeps to plain PHP semantics rather than one framework class's magic.
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
     * The classes a subject holds once its `instanceof` test passes: its own classes narrowed class by class, so a
     * supertype, interface or sibling test never widens it; the tested classes when it names none.
     *
     * @param  non-empty-list<class-string>  $tested
     * @return non-empty-list<class-string>
     */
    public function narrowedSubject(Expr $subject, array $tested, AnalysisScope $scope): array
    {
        return $this->narrowed($this->resolve($subject, $scope), $tested)->classes;
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

        $guard = $scope->varGuardBindings[$name] ?? null;

        // A guard proves its class only for a read past it: an earlier return still holds whatever the variable does.
        if ($guard !== null && $variable->getStartFilePos() > $guard['after']) {
            return new ReceiverType($guard['classes']);
        }

        $span = $scope->declaredAt($variable);

        if ($span === null) {
            return $this->fromBindings($name, $scope);
        }

        // The annotated assignment replaces what the variable held, so its own value is read; the `@var` fills in only
        // where that names no class the engine can use.
        $assigned = $this->fromBoundExpression($name, $span['expr'], $scope);

        return $assigned !== null && array_any($assigned->classes, $this->isNameable(...))
            ? $assigned
            : $this->typeOf($this->declaredClasses($span, $scope)) ?? $assigned;
    }

    /**
     * The classes an inline `@var` names, read as a property's `@var` is; null when it names a type that is not a
     * loadable class.
     *
     * @param  VarDocBinding  $span
     * @return non-empty-list<class-string>|null
     */
    private function declaredClasses(array $span, AnalysisScope $scope): ?array
    {
        return $this->docblockClasses($span['type'], $span['context'], $scope->subjectReflection->getName(), $span['context']->getName());
    }

    /**
     * Resolve a variable through its model, collection, request, closure-parameter or local-assignment binding.
     */
    private function fromBindings(string $name, AnalysisScope $scope): ?ReceiverType
    {
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

        return $bound === null ? null : $this->fromBoundExpression($name, $bound, $scope);
    }

    /**
     * Resolve the expression a variable is bound to, or null while that variable is already mid-resolution.
     */
    private function fromBoundExpression(string $name, Expr $bound, AnalysisScope $scope): ?ReceiverType
    {
        if (isset($scope->resolvingLocalVars[$name])) {
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
     * Whether a class names a reading the engine can use: any but a model with no published file, such as `Model`.
     */
    private function isNameable(string $class): bool
    {
        return ! is_a($class, Model::class, true)
            || ValueResult::namesOnlyPublishedModels([...ValueResult::unknown(), 'modelFqcn' => $class]);
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

        $loaded = $method === 'getRelation' ? $this->loadedRelation($call, $receiver, $shortCircuits) : null;

        if ($loaded !== null) {
            return $loaded;
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
     * @param  InstanceofProof|null  $proof  what a ternary's `instanceof` condition proves about the arm that runs then
     */
    private function fromArms(array $arms, AnalysisScope $scope, bool $firstArmFallsBack, ?array $proof = null): ?ReceiverType
    {
        $types = [];
        $shortCircuits = false;

        foreach ($arms as $index => $arm) {
            if ($arm instanceof ConstFetch && $arm->name->toLowerString() === 'null') {
                continue;
            }

            $type = $proof !== null && $index === $proof[2]
                ? $this->provenArm($arm, $proof[0], $proof[1], $scope)
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
     * The arm an `instanceof` condition proves: its tested read narrowed class by class, or a member read through that
     * subject resolved under the narrowing TernaryHandler resolves the same arm's value under.
     *
     * @param  non-empty-list<class-string>  $tested
     */
    private function provenArm(Expr $arm, Expr $subject, array $tested, AnalysisScope $scope): ?ReceiverType
    {
        if ($this->isSameReadPath($subject, $arm)) {
            return $this->narrowed($this->resolve($arm, $scope), $tested);
        }

        $narrowed = $this->readsThrough($arm, $subject)
            ? $this->resolveNarrowed($subject, $tested, $arm, $scope, fn (): ?ReceiverType => $this->resolve($arm, $scope))
            : null;

        return $narrowed ?? $this->resolve($arm, $scope);
    }

    /**
     * Whether an expression reads a member through the subject: a property or method chain whose receiver is its read.
     */
    private function readsThrough(Expr $expr, Expr $subject): bool
    {
        while ($expr instanceof PropertyFetch
            || $expr instanceof NullsafePropertyFetch
            || $expr instanceof MethodCall
            || $expr instanceof NullsafeMethodCall
        ) {
            $expr = $expr->var;

            if ($this->isSameReadPath($expr, $subject)) {
                return true;
            }
        }

        return false;
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
     * What one class a true arm resolves to becomes under its tests: itself, the tested subclasses, or nothing. An
     * interface on either side of a test keeps it, since a subclass may pass that test and no one class names the pair.
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
        $attributeClass = resolve(ModelAttributeResolver::class)->resolveAttributeClass($model, $name);

        return $attributeClass === null ? $this->relationMember($model, $name) : ReceiverType::of($attributeClass);
    }

    /**
     * What a model's `getRelation('name')` hands back: the relation loaded under that name on each model it may be
     * called on, since it throws for a name that is not loaded.
     */
    private function loadedRelation(
        MethodCall|NullsafeMethodCall $call,
        ReceiverType $receiver,
        bool $shortCircuits,
    ): ?ReceiverType {
        $name = ($call->getArgs()[0] ?? null)?->value;

        if (! $name instanceof String_) {
            return null;
        }

        return $this->merge(
            array_map(
                fn (string $class): ?ReceiverType => is_a($class, Model::class, true)
                    ? $this->relationMember($class, $name->value)
                    : null,
                $receiver->classes,
            ),
            $shortCircuits,
        );
    }

    /**
     * What a model relation holds once loaded: its related model, a collection of them, or any of a morphTo's targets.
     *
     * @param  class-string<Model>  $model
     */
    private function relationMember(string $model, string $name): ?ReceiverType
    {
        $resolver = resolve(ModelAttributeResolver::class);
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
        $class = $abstract === null ? null : $this->classNameFetch($abstract)?->toString();

        return $class !== null && $this->isClassLike($class) ? ReceiverType::of($class) : null;
    }

    /**
     * The name an `X::class` fetch spells, or null for any other expression.
     */
    private function classNameFetch(Expr $expr): ?Name
    {
        return $expr instanceof ClassConstFetch
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
            && $expr->name->toLowerString() === 'class'
                ? $expr->class
                : null;
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
     * The classes a docblock union names, resolved against the declaring file's imports; null for any arm not a class.
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

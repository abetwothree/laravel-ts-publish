<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Binds the parameters of a method whose body is read apart from its call, such as a subject's helper: by the types
 * they declare, which hold for every call; and one whose declaration binds nothing, or an argument narrows or passes as
 * a model no row backs yet, by what that argument binds, which a value read's memos key on (CallSite::signature()).
 *
 * A body write that replaces a parameter ends its class binding there: the caller seeds the body (seedBody()) once
 * its parameters are bound. A written parameter binds no value, since a value binding outlives a write naming no class,
 * such as `$n = (string) $n`, where the body's own local answers.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type PassedBinding = array{
 *     model: class-string<Model>|null,
 *     classes: non-empty-list<class-string>|null,
 *     elementModel: class-string<Model>|null,
 *     value: ValueExpressionResult|null,
 *     unsaved: bool,
 *     refines: bool,
 * }
 *
 * @internal
 */
final class ParameterBinder
{
    use CollectsLocalVarBindings;

    /**
     * Bind a method's parameters for a read of its body: by what each declares, then by what its call passes. The
     * body's writes are scanned once, and only if a parameter's value binding needs them.
     *
     * @param  array<Stmt>  $stmts  the method's body
     * @param  array<string, PassedBinding>  $passed  each parameter's binding, from passedBinding()
     */
    public function bindCall(ReflectionMethod $method, array $stmts, array $passed, AnalysisScope $scope): void
    {
        $written = $this->writtenNames($stmts, $scope);

        $this->bindDeclared($method, $scope, $written);
        $this->bindPassedNames($passed, $scope, $written);
    }

    /**
     * Bind each parameter the scope leaves unbound as its argument binds it: a model in varModelBindings, an Eloquent
     * collection of one model in varCollectionBindings, other classes in varDeclaredBindings, and its value, which a
     * parameter the body writes leaves to its local.
     *
     * A model no row may back yet binds as a `new` of it, which the receiver resolver reads again with that mark, in
     * place of a declared concrete model's binding, as a narrower argument replaces a declared abstract class's,
     * interface's or union's. A closure's parameters bind here too, having no method for bindCall().
     *
     * @param  array<Stmt>  $stmts  the method's or the closure's body
     * @param  array<string, PassedBinding>  $passed  each parameter's binding, from passedBinding()
     */
    public function bindPassed(array $stmts, array $passed, AnalysisScope $scope): void
    {
        $this->bindPassedNames($passed, $scope, $this->writtenNames($stmts, $scope));
    }

    /**
     * How an argument binds the parameter it is passed: the one concrete model it holds, never null, by its class;
     * other classes and its value otherwise; null when it binds nothing. A `?->` can end it as null: no class binds.
     *
     * A binding that refines a declared class names a class, or is null, since its value alone would lose the class.
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
        $model = $classes !== null && count($classes) === 1 ? $this->concreteModel($classes[0]) : null;
        // A listed Eloquent collection keeps its items' model, which the receiver rules read for first() and the rest.
        $elementModel = $classes === [EloquentCollection::class] && ! $held->keyed ? $held->elementModel : null;

        // A value that may be null keeps its `null` arm for a bare read, as a nullable declared parameter does.
        if ($model !== null && ! $unsaved && ! ValueResult::hasNullArm($value['type'])) {
            return ['model' => $model, 'classes' => null, 'elementModel' => null, 'value' => null, 'unsaved' => false, 'refines' => $refines];
        }

        // The parameter holds the value alone: whether the caller's key may be absent, and `??` fills it, stays there.
        unset($value['absentReadsNull']);
        $value = $value['type'] === 'unknown' ? null : [...$value, 'optional' => false];

        return $classes === null && ($value === null || $refines)
            ? null
            : ['model' => null, 'classes' => $classes, 'elementModel' => $elementModel, 'value' => $value, 'unsaved' => $unsaved, 'refines' => $refines];
    }

    /**
     * Whether any parameter of a method takes what its call passes, so a read of its body depends on the call.
     */
    public function takesPassedValues(ReflectionMethod $method): bool
    {
        return array_any(
            $method->getParameters(),
            fn (ReflectionParameter $parameter): bool => $this->takesPassedValue($parameter, $method)
                || $this->narrowableClasses($parameter) !== null
                || $this->declaredModel($parameter) !== null,
        );
    }

    /**
     * The abstract class, interface or union of classes a parameter declares, which a narrower argument refines; null
     * for any other declaration, such as one concrete class, a Request or a variadic list.
     *
     * @return non-empty-list<class-string>|null
     */
    public function narrowableClasses(ReflectionParameter $parameter): ?array
    {
        $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter->getType());

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
        $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter->getType());

        return $classes !== null && count($classes) === 1 ? $this->concreteModel($classes[0]) : null;
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
            && $this->nativeClasses($parameter->getType()) === null
            && $this->declaredValue(
                $parameter,
                (string) $method->getDocComment(),
                LaravelTsPublish::methodDeclaringFileClass($method),
            ) === null;
    }

    /**
     * Bind each parameter by its declaration: one concrete, non-null model in varModelBindings, any other class-only
     * type in varDeclaredBindings, and its value, from the native type, else a `@param` where that is too vague.
     *
     * A Request parameter binds as a request, except in an API resource, whose output was inferred without the request
     * rules; a name the scope already binds is left as it is.
     *
     * @param  Closure(): list<string>  $written  the names the body writes
     */
    private function bindDeclared(ReflectionMethod $method, AnalysisScope $scope, Closure $written): void
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

            $classes = $parameter->isVariadic() ? null : $this->nativeClasses($parameter->getType());

            if ($classes !== null && array_any($classes, fn (string $class): bool => is_a($class, Request::class, true))) {
                continue;
            }

            $model = $classes !== null && count($classes) === 1 ? $this->concreteModel($classes[0]) : null;

            if ($model !== null && ! ($parameter->getType()?->allowsNull() ?? true)) {
                $scope->varModelBindings[$name] = $model;

                continue;
            }

            if ($classes !== null) {
                $scope->varDeclaredBindings[$name] = $classes;
            }

            $value = in_array($name, $written(), true) ? null : $this->declaredValue($parameter, $docComment, $context);

            if ($value !== null) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * Apply each argument's binding to the parameter it is passed.
     *
     * @param  array<string, PassedBinding>  $passed
     * @param  Closure(): list<string>  $written  the names the body writes
     */
    private function bindPassedNames(array $passed, AnalysisScope $scope, Closure $written): void
    {
        foreach ($passed as $name => ['model' => $model, 'classes' => $classes, 'elementModel' => $elementModel, 'value' => $value, 'unsaved' => $unsaved, 'refines' => $refines]) {
            $declaredModel = $scope->varModelBindings[$name] ?? null;

            if ($refines && isset($scope->varDeclaredBindings[$name])) {
                unset($scope->varDeclaredBindings[$name], $scope->varValueBindings[$name]);
            } elseif ($unsaved && $classes !== null && $declaredModel !== null && is_a($classes[0], $declaredModel, true)) {
                // A declared concrete model binds as a saved row, so a passed one no row backs yet replaces it.
                unset($scope->varModelBindings[$name]);
            } elseif ($scope->bindsName($name)) {
                continue;
            }

            if ($model !== null) {
                $scope->varModelBindings[$name] = $model;

                continue;
            }

            if ($classes !== null && $unsaved) {
                $scope->closureParamExprBindings[$name] = new New_(new FullyQualified($classes[0]));
            } elseif ($elementModel !== null) {
                $scope->varCollectionBindings[$name] = [
                    'type' => $value['type'] ?? ValueResult::arrayWrapType(class_basename($elementModel)),
                    'modelFqcn' => $elementModel,
                ];
            } elseif ($classes !== null) {
                $scope->varDeclaredBindings[$name] = $classes;
            }

            if ($value !== null && ! in_array($name, $written(), true)) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * The names a body writes, scanned on the first call and kept for the next.
     *
     * @param  array<Stmt>  $stmts
     * @return Closure(): list<string>
     */
    private function writtenNames(array $stmts, AnalysisScope $scope): Closure
    {
        $names = null;

        return function () use (&$names, $stmts, $scope): array {
            return $names ??= $this->collectWrittenVariableNames($stmts, $scope);
        };
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
     * The class as a model a variable binding can hold: an Eloquent model that is not abstract, else null.
     *
     * @param  class-string  $class
     * @return class-string<Model>|null
     */
    private function concreteModel(string $class): ?string
    {
        return is_a($class, Model::class, true) && ! new ReflectionClass($class)->isAbstract() ? $class : null;
    }

    /**
     * The classes a native type names when every arm but `null` is a loadable class, else null.
     *
     * @return non-empty-list<class-string>|null
     */
    private function nativeClasses(?ReflectionType $type): ?array
    {
        $arms = match (true) {
            $type instanceof ReflectionNamedType => [$type],
            $type instanceof ReflectionUnionType => $type->getTypes(),
            default => [],
        };

        $classes = [];

        foreach ($arms as $arm) {
            if (! $arm instanceof ReflectionNamedType) {
                return null;
            }

            $class = $arm->getName();

            if ($class === 'null') {
                continue;
            }

            if ($arm->isBuiltin() || ! (class_exists($class) || interface_exists($class))) {
                return null;
            }

            $classes[] = $class;
        }

        return $classes === [] ? null : $classes;
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
        $value = resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::resolveReflectionType($parameter->getType()));

        if ($value === null || TsTypeString::isVagueTsType($value['type'])) {
            $reader = resolve(PropertyDocblockTypeReader::class);
            $tag = $reader->extractParamTag($docComment, $parameter->getName(), $context);
            $value = $tag === null ? $value : $reader->readDeclared($tag, $context);
        }

        if ($value === null || TsTypeString::isVagueTsType($value['type']) || ! ValueResult::namesOnlyPublishedModels($value)) {
            return null;
        }

        return $parameter->isVariadic()
            ? [...$value, 'type' => ValueResult::arrayWrapType($value['type']), 'optional' => false]
            : [...$value, 'optional' => false];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
 * they declare, which every call passes, so one reading holds for all of them; and each parameter whose declaration
 * binds nothing by what the call's argument binds, which a value read's memos key on (CallSite::signature()).
 *
 * A body write that replaces a parameter ends its class binding there: the caller runs endBindingsAtWrites() once the
 * body's locals are seeded. A written parameter binds no value, since a value binding outlives a write naming no class,
 * such as `$n = (string) $n`, where the body's own local answers.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type PassedBinding = array{
 *     model: class-string<Model>|null,
 *     classes: non-empty-list<class-string>|null,
 *     value: ValueExpressionResult|null,
 *     unsaved: bool,
 * }
 *
 * @internal
 */
final class ParameterBinder
{
    use CollectsLocalVarBindings;

    /**
     * Bind each parameter by its declaration: one concrete, non-null model in varModelBindings, any other class-only
     * type in varDeclaredBindings, and its value, from the native type, else a `@param` where that is too vague.
     *
     * A Request parameter is left to the request rules, and a name the scope already binds is left as it is.
     *
     * @param  array<Stmt>  $stmts  the method's body
     */
    public function bindDeclared(ReflectionMethod $method, array $stmts, AnalysisScope $scope): void
    {
        $parameters = $method->getParameters();

        if ($parameters === []) {
            return;
        }

        $context = LaravelTsPublish::methodDeclaringFileClass($method);
        $docComment = (string) $method->getDocComment();
        $written = null;

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if ($scope->bindsName($name)) {
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

            $written ??= $this->collectWrittenVariableNames($stmts, $scope);
            $value = in_array($name, $written, true) ? null : $this->declaredValue($parameter, $docComment, $context);

            if ($value !== null) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * Bind each parameter the scope leaves unbound as its argument binds it: a model in varModelBindings, other classes
     * in varDeclaredBindings, and its value, which a parameter the body writes leaves to its local.
     *
     * A model no row may back yet binds as a `new` of it, which the receiver resolver reads again with that mark.
     *
     * @param  array<Stmt>  $stmts  the method's body
     * @param  array<string, PassedBinding>  $passed  each parameter's binding, from passedBinding()
     */
    public function bindPassed(array $stmts, array $passed, AnalysisScope $scope): void
    {
        $written = $passed === [] ? [] : $this->collectWrittenVariableNames($stmts, $scope);

        foreach ($passed as $name => ['model' => $model, 'classes' => $classes, 'value' => $value, 'unsaved' => $unsaved]) {
            if ($scope->bindsName($name)) {
                continue;
            }

            if ($model !== null) {
                $scope->varModelBindings[$name] = $model;

                continue;
            }

            if ($classes !== null && $unsaved) {
                $scope->closureParamExprBindings[$name] = new New_(new FullyQualified($classes[0]));
            } elseif ($classes !== null) {
                $scope->varDeclaredBindings[$name] = $classes;
            }

            if ($value !== null && ! in_array($name, $written, true)) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * How an argument binds the parameter it is passed: the one concrete model it holds, never null, by its class;
     * other classes and its value otherwise; null when it binds nothing. A `?->` can end it as null: no class binds.
     *
     * @param  ValueExpressionResult  $value
     * @return PassedBinding|null
     */
    public function passedBinding(array $value, ?ReceiverType $receiver): ?array
    {
        $held = $receiver === null || $receiver->shortCircuits ? null : $receiver;
        $unsaved = $held !== null && $held->unsaved && $held->models() !== [];
        // Only a `new` of one class carries a model's unsaved mark into the body: a copy that may be more binds none.
        $classes = $held === null || ($unsaved && count($held->classes) !== 1) ? null : $held->classes;
        $unsaved = $unsaved && $classes !== null;
        $model = $classes !== null && count($classes) === 1 ? $this->concreteModel($classes[0]) : null;

        // A value that may be null keeps its `null` arm for a bare read, as a nullable declared parameter does.
        if ($model !== null && ! $unsaved && ! ValueResult::hasNullArm($value['type'])) {
            return ['model' => $model, 'classes' => null, 'value' => null, 'unsaved' => false];
        }

        $value = $value['type'] === 'unknown' ? null : [...$value, 'optional' => false];

        return $classes === null && $value === null
            ? null
            : ['model' => null, 'classes' => $classes, 'value' => $value, 'unsaved' => $unsaved];
    }

    /**
     * Whether any parameter of a method takes the value its call passes, so a read of its body depends on the call.
     */
    public function takesPassedValues(ReflectionMethod $method): bool
    {
        return array_any(
            $method->getParameters(),
            fn (ReflectionParameter $parameter): bool => $this->takesPassedValue($parameter, $method),
        );
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

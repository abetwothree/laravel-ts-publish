<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Binds the parameters of a method whose body is read apart from its call, such as a subject's helper: by the types
 * they declare, which every call passes, so one reading holds for all of them and the `class@method` memos stay valid;
 * and, for a spread call read in place, an untyped parameter by the argument that call passes.
 *
 * A body write that replaces a parameter ends its binding there: the caller runs endBindingsAtWrites() once the body's
 * locals are seeded, as for any other parameter.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type PassedArgument = array{value: ValueExpressionResult, receiver: ReceiverType|null}
 *
 * @internal
 */
final class ParameterBinder
{
    /**
     * Bind each parameter by its declaration: one concrete, non-null model in varModelBindings, any other class-only
     * type in varDeclaredBindings, and its value, from the native type, else a `@param` where that is too vague.
     *
     * A Request parameter is left to the request rules, and a name the scope already binds is left as it is.
     */
    public function bindDeclared(ReflectionMethod $method, AnalysisScope $scope): void
    {
        $parameters = $method->getParameters();

        if ($parameters === []) {
            return;
        }

        $context = LaravelTsPublish::methodDeclaringFileClass($method);
        $docComment = (string) $method->getDocComment();

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

            $value = $this->declaredValue($parameter, $docComment, $context);

            if ($value !== null) {
                $scope->varValueBindings[$name] = $value;
            }
        }
    }

    /**
     * Bind each parameter the scope leaves unbound to what its call passes: one concrete model the argument holds,
     * never null, in varModelBindings, other classes in varDeclaredBindings, and its value.
     *
     * A model no row may back yet binds only by value: a class binding would drop the `null` its key can hold.
     *
     * @param  array<string, PassedArgument>  $passed  each parameter's argument, read where the call is written
     */
    public function bindPassed(array $passed, AnalysisScope $scope): void
    {
        foreach ($passed as $name => ['value' => $value, 'receiver' => $receiver]) {
            if ($scope->bindsName($name)) {
                continue;
            }

            $classes = $receiver === null || $receiver->shortCircuits || $receiver->unsaved ? null : $receiver->classes;
            $model = $classes !== null && count($classes) === 1 ? $this->concreteModel($classes[0]) : null;

            // A value that may be null keeps its `null` arm for a bare read, as a nullable declared parameter does.
            if ($model !== null && ! ValueResult::hasNullArm($value['type'])) {
                $scope->varModelBindings[$name] = $model;

                continue;
            }

            if ($classes !== null) {
                $scope->varDeclaredBindings[$name] = $classes;
            }

            if ($value['type'] !== 'unknown') {
                $scope->varValueBindings[$name] = [...$value, 'optional' => false];
            }
        }
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

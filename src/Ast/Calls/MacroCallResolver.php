<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The macro a call on a `Macroable` subject runs: `__call()` reaches it for a name the subject does not declare, before
 * anything the subject forwards to, so the call is typed from the macro's own callable, never a forwarded namesake.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type LocatedClosure = array{ClosureExpr|ArrowFunction, MethodContext}
 *
 * @internal
 */
final class MacroCallResolver
{
    /**
     * The callable registered under a name the subject declares no method for, or null.
     *
     * @param  ReflectionClass<object>  $subject
     */
    public function macroFor(ReflectionClass $subject, string $method): mixed
    {
        $class = $subject->getName();

        if ($subject->hasMethod($method) || ! in_array(Macroable::class, class_uses_recursive($class), true)) {
            return null;
        }

        /** @var array<string, mixed> $macros */
        $macros = new ReflectionProperty($class, 'macros')->getValue();

        return $macros[$method] ?? null;
    }

    /**
     * Whether a call by this name on the subject runs a macro.
     *
     * @param  ReflectionClass<object>  $subject
     */
    public function runsMacro(ReflectionClass $subject, string $method): bool
    {
        return $this->macroFor($subject, $method) !== null;
    }

    /**
     * The function a macro runs, recording the file that declares it: a closure, an invokable object, or a callable
     * array or string; null for anything else.
     */
    public function reflect(mixed $macro): ReflectionFunction|ReflectionMethod|null
    {
        try {
            $function = match (true) {
                $macro instanceof Closure => new ReflectionFunction($macro),
                is_object($macro) => new ReflectionMethod($macro, '__invoke'),
                is_array($macro) && array_is_list($macro) && count($macro) === 2
                    && (is_object($macro[0]) || is_string($macro[0])) && is_string($macro[1]) => new ReflectionMethod($macro[0], $macro[1]),
                is_string($macro) && str_contains($macro, '::') => ReflectionMethod::createFromMethodName($macro),
                is_string($macro) => new ReflectionFunction($macro),
                default => null,
            };
        } catch (ReflectionException) {
            return null;
        }

        $file = $function?->getFileName();

        if (is_string($file)) {
            DependencyRecorder::record($file);
        }

        return $function;
    }

    /**
     * What the macro a call runs returns: its native return, else its `@return`, else, for a closure, its body read
     * with `$this` bound to the runtime class, as `Macroable::__call()` binds it. Memoized per class, name and model.
     *
     * @return ValueExpressionResult|null
     */
    public function resolve(AnalysisScope $scope, string $method): ?array
    {
        $runtime = $scope->runtimeReflection;
        $modelClass = $scope->modelClass;

        return resolve(AnalysisMemo::class)->remember(
            'macro:'.$runtime->getName().'@'.$method.'@'.($modelClass ?? ''),
            function () use ($runtime, $method, $modelClass): ?array {
                $function = $this->reflect($this->macroFor($runtime, $method));
                $result = match (true) {
                    $function instanceof ReflectionMethod => resolve(MethodReturnTypeResolver::class)
                        ->resolve($function->getDeclaringClass()->getName(), $function->getName()),
                    $function instanceof ReflectionFunction => $this->functionType($function, $runtime->getName(), $modelClass),
                    default => null,
                };

                return $result !== null && ValueResult::namesOnlyPublishedModels($result) ? $result : null;
            },
        );
    }

    /**
     * A closure's or function's return: its native type, else its `@return`, else a closure's body.
     *
     * @param  class-string  $subjectClass
     * @param  class-string<Model>|null  $modelClass
     * @return ValueExpressionResult|null
     */
    private function functionType(ReflectionFunction $function, string $subjectClass, ?string $modelClass): ?array
    {
        $acceptor = resolve(ReflectedTypeAcceptor::class);
        $declared = $acceptor->accept(LaravelTsPublish::resolveReflectionType($function->getReturnType()));

        if ($declared !== null && ! TsTypeString::isVagueTsType($declared['type'])) {
            return $declared;
        }

        $documented = $acceptor->accept(LaravelTsPublish::functionDocblockReturnTypes($function));

        if ($documented !== null && ! TsTypeString::isVagueTsType($documented['type'])) {
            return $documented;
        }

        return $this->bodyType($function, $subjectClass, $modelClass) ?? $declared ?? $documented;
    }

    /**
     * A closure macro's body, read in a scope of its own; null when the read declines or is already under way.
     *
     * @param  class-string  $subjectClass
     * @param  class-string<Model>|null  $modelClass
     * @return ValueExpressionResult|null
     */
    private function bodyType(ReflectionFunction $function, string $subjectClass, ?string $modelClass): ?array
    {
        $located = $this->locate($function);
        $key = 'macro-body:'.$function->getFileName().':'.$function->getStartLine();
        $memo = resolve(AnalysisMemo::class);

        if ($located === null || ! $memo->enter($key)) {
            return null;
        }

        try {
            return resolve(AstEngine::class)->analyzeBoundClosure($subjectClass, $modelClass, $located[0], $located[1]);
        } finally {
            $memo->leave($key);
        }
    }

    /**
     * The node a closure was built from, with the class method that declares it; null for a closure declared outside
     * a class method, or one its lines cannot tell apart from another.
     *
     * @return LocatedClosure|null
     */
    private function locate(ReflectionFunction $function): ?array
    {
        $class = $function->getClosureScopeClass();
        $file = $function->getFileName();
        $start = $function->getStartLine();
        $end = $function->getEndLine();

        if (! $function->isClosure() || $class === null || $file === false || $start === false || $end === false) {
            return null;
        }

        $declaring = array_find(
            $class->getMethods(),
            fn (ReflectionMethod $method): bool => $method->getFileName() === $file
                && $method->getStartLine() <= $start
                && $method->getEndLine() >= $end,
        );
        $context = $declaring === null ? null : resolve(MethodLocator::class)->locate($class->getName(), $declaring->getName());
        $closures = new NodeFinder()->find(
            $context->method->stmts ?? [],
            fn (Node $node): bool => ($node instanceof ClosureExpr || $node instanceof ArrowFunction)
                && $node->getStartLine() === $start
                && $node->getEndLine() === $end,
        );
        $closure = count($closures) === 1 ? $closures[0] : null;

        return $context !== null && ($closure instanceof ClosureExpr || $closure instanceof ArrowFunction)
            ? [$closure, $context]
            : null;
    }
}

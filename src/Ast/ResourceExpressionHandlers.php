<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ArrayMergeHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\BinaryOpHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CastHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClassConstantHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClosureHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CoalesceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CollectionPipelineHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConstFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MethodChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\TernaryHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\VariableHandler;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Builds the ordered ExpressionHandler lists ExpressionDispatcher runs. Registration order is
 * dispatch precedence for any node class more than one handler claims — see
 * docs/components/ast-engine.md's "Handler ordering" section for the contract this pins.
 *
 * @internal
 */
final class ResourceExpressionHandlers
{
    /**
     * The full resource profile, a JsonResource subject's, in the dispatch order derived from the guard order of the
     * single if/else chain these handlers replaced. $engine mirrors the `make($this)` call site; unused today,
     * kept for a future handler that needs the engine at construction.
     *
     * @return list<ExpressionHandler>
     */
    public static function make(ExpressionEngine $engine): array
    {
        return self::handlers();
    }

    /**
     * The profile a subject runs when its caller names none: make() for a JsonResource, else forNonResourceSubjects().
     *
     * @return list<ExpressionHandler>
     */
    public static function forSubject(string $subjectClass, ExpressionEngine $engine): array
    {
        return is_a($subjectClass, JsonResource::class, true) ? self::make($engine) : self::forNonResourceSubjects();
    }

    /**
     * make() minus ConditionalMethodHandler, same relative order: a broadcast event's, model metadata's, shared data's
     * or a body-fallback method's profile. Only JsonResource::resolve() filters the MissingValue the `when*()` family
     * returns, so elsewhere `$this->when()` is another method, while toResource() and a filter mean the same anywhere.
     *
     * @return list<ExpressionHandler>
     */
    public static function forNonResourceSubjects(): array
    {
        return array_values(array_filter(
            self::handlers(),
            static fn (ExpressionHandler $handler): bool => ! $handler instanceof ConditionalMethodHandler,
        ));
    }

    /**
     * make() minus ConditionalMethodHandler, ToResourceHandler and RelationFilterHandler, in the same order. Named for
     * what it drops: its one production caller is ControllerExpressionHandlers::make().
     *
     * @return list<ExpressionHandler>
     */
    public static function withoutResourceHandlers(): array
    {
        return array_values(array_filter(
            self::handlers(),
            static fn (ExpressionHandler $handler): bool => ! $handler instanceof ConditionalMethodHandler
                && ! $handler instanceof ToResourceHandler
                && ! $handler instanceof RelationFilterHandler,
        ));
    }

    /**
     * make() minus ConditionalMethodHandler and ToResourceHandler, same relative order: a model getter body's profile,
     * for AstEngine::analyzeModelClosure(). RelationFilterHandler stays, as the only handler that types a to-many,
     * map-proxy or multi-model accessor filter over the model's own relations.
     *
     * @return list<ExpressionHandler>
     */
    public static function forModelClosures(): array
    {
        return array_values(array_filter(
            self::handlers(),
            static fn (ExpressionHandler $handler): bool => ! $handler instanceof ConditionalMethodHandler
                && ! $handler instanceof ToResourceHandler,
        ));
    }

    /**
     * Construct all 27 handlers in registration order — the single source every profile above filters.
     *
     * @return list<ExpressionHandler>
     */
    private static function handlers(): array
    {
        return [
            new FirstClassCallableHandler,
            new CastHandler,
            new ScalarHandler,
            new ConstFetchHandler,
            new ClassConstantHandler,
            new BinaryOpHandler,
            new CoalesceHandler,
            new ArrayMergeHandler,
            new KnownFunctionCallHandler,
            new ClosureHandler,
            new ConditionalMethodHandler,
            new ToResourceHandler,
            new InertiaWrapperHandler,
            new StaticCallHandler,
            new NewResourceHandler,
            new ThisPropertyHandler,
            new RelationFilterHandler,
            new InlineArrayHandler,
            new MethodChainHandler,
            new PropertyChainHandler,
            new RelationCollectionChainHandler,
            new CollectionPipelineHandler,
            new VariableHandler,
            new TernaryHandler,
            new ReceiverPropertyFetchHandler,
            new ReceiverMethodCallHandler,
            new KnownMethodRuleHandler,
        ];
    }
}

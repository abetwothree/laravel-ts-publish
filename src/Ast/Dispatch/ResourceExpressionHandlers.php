<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Dispatch;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\ArrayMergeHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\ConditionableCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\CollectionPipelineHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaTableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Members\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Members\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Members\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\BinaryOpHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\CoalesceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\MatchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\OffsetReadHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\TernaryHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Resources\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Resources\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Resources\ToResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\CastHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\ClassConstantHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\ClosureHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\ConstFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\VariableHandler;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

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
     * Each handler only Inertia runs, under the handler it goes before. InertiaResourcePropHandler precedes
     * ToResourceHandler, the first that types a resource as the value a nested key flattens it to. The table handler
     * precedes StaticCallHandler, whose last arm claims every static call, and so NewResourceHandler too.
     */
    private const array INERTIA_HANDLERS = [
        ToResourceHandler::class => [InertiaResourcePropHandler::class],
        StaticCallHandler::class => [InertiaTableHandler::class],
    ];

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
     * The profile a subject runs unless its caller names one: its own, through forInertia() where Inertia reads it.
     *
     * @return list<ExpressionHandler>
     */
    public static function forSubject(string $subjectClass, ExpressionEngine $engine, Reading $reading = Reading::Encoded): array
    {
        $own = is_a($subjectClass, JsonResource::class, true) ? self::make($engine) : self::forNonResourceSubjects();

        return $reading->encodes() ? $own : self::forInertia($own);
    }

    /**
     * make() minus ConditionalMethodHandler, same order: a broadcast event's, model metadata's, a body fallback's and
     * the Inertia profile's base. Only JsonResource::resolve() drops the MissingValue `when*()` returns, so elsewhere
     * `$this->when()` is another method, while toResource() and a filter mean the same anywhere.
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
     * The Inertia profile over the given handlers, which every value a page or a share middleware sends runs.
     *
     * @param  list<ExpressionHandler>  $handlers
     * @return list<ExpressionHandler>
     *
     * @throws LogicException when the list lacks a handler INERTIA_HANDLERS inserts one before
     */
    public static function forInertia(array $handlers): array
    {
        return self::invokingCallables(self::withPagePropHandlers($handlers));
    }

    /**
     * The same handlers where the caller calls each callable value, as Inertia does with props and shared data.
     *
     * @param  list<ExpressionHandler>  $handlers
     * @return list<ExpressionHandler>
     */
    private static function invokingCallables(array $handlers): array
    {
        return array_map(
            static fn (ExpressionHandler $handler): ExpressionHandler => $handler instanceof FirstClassCallableHandler
                ? new FirstClassCallableHandler(invoked: true)
                : $handler,
            $handlers,
        );
    }

    /**
     * The same handlers with each one only Inertia runs inserted before its anchor, as INERTIA_HANDLERS pairs them.
     *
     * @param  list<ExpressionHandler>  $handlers
     * @return list<ExpressionHandler>
     */
    private static function withPagePropHandlers(array $handlers): array
    {
        // Without its insertion point a page-prop handler would be dropped silently, and every value it types with it.
        foreach (array_keys(self::INERTIA_HANDLERS) as $anchor) {
            if (! array_any($handlers, static fn (ExpressionHandler $handler): bool => $handler instanceof $anchor)) {
                throw new LogicException(sprintf('forInertia() inserts a page-prop handler before %s, which the list lacks.', $anchor));
            }
        }

        $profile = [];

        foreach ($handlers as $handler) {
            foreach (self::INERTIA_HANDLERS as $anchor => $inserted) {
                foreach ($handler instanceof $anchor ? $inserted : [] as $class) {
                    $profile[] = new $class;
                }
            }

            $profile[] = $handler;
        }

        return $profile;
    }

    /**
     * Construct all 30 handlers in registration order — the single source every profile above filters.
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
            new ModelFinderHandler,
            new StaticCallHandler,
            new NewResourceHandler,
            new ThisPropertyHandler,
            new RelationFilterHandler,
            new InlineArrayHandler,
            new PropertyChainHandler,
            new RelationCollectionChainHandler,
            new CollectionPipelineHandler,
            new VariableHandler,
            new TernaryHandler,
            new MatchHandler,
            new OffsetReadHandler,
            new ReceiverPropertyFetchHandler,
            new ConditionableCallHandler,
            new ReceiverMethodCallHandler,
            new KnownMethodRuleHandler,
        ];
    }
}

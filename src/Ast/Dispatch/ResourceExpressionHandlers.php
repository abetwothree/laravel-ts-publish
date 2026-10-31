<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Dispatch;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
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
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MatchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\OffsetReadHandler;
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
     * ToResourceHandler, the first that types a resource as the value a nested key flattens it to; ModelFinderHandler
     * precedes StaticCallHandler, whose last arm claims every static call, so a query chain types as its paginator.
     */
    private const array INERTIA_HANDLERS = [
        ToResourceHandler::class => InertiaResourcePropHandler::class,
        StaticCallHandler::class => ModelFinderHandler::class,
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

        return $reading === Reading::Encoded ? $own : self::forInertia($own);
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
                if ($handler instanceof $anchor) {
                    $profile[] = new $inserted;
                }
            }

            $profile[] = $handler;
        }

        return $profile;
    }

    /**
     * Construct all 28 handlers in registration order — the single source every profile above filters.
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
            new PropertyChainHandler,
            new RelationCollectionChainHandler,
            new CollectionPipelineHandler,
            new VariableHandler,
            new TernaryHandler,
            new MatchHandler,
            new OffsetReadHandler,
            new ReceiverPropertyFetchHandler,
            new ReceiverMethodCallHandler,
            new KnownMethodRuleHandler,
        ];
    }
}

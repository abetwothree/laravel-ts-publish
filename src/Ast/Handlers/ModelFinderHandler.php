<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallChainWalker;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;

/**
 * Eloquent query chains rooted at a model — `Post::findOrFail($id)`, `Post::query()->latest()->get()`,
 * `Team::query()->paginate(10)` — typed from the terminal call, which is what decides the payload's shape.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ModelFinderHandler implements ExpressionHandler
{
    /** Terminals returning one model instance; the first three can miss and so are nullable. */
    private const SINGLE = [
        'find', 'first', 'firstWhere',
        'sole', 'firstOrFail', 'findOrFail', 'firstOrCreate', 'firstOrNew',
        'create', 'make', 'updateOrCreate',
    ];

    private const NULLABLE_SINGLE = ['find', 'first', 'firstWhere'];

    private const MANY = ['all', 'get'];

    /** Terminal => the paginator FQCN whose `@tolki/types` name wraps the model. */
    private const PAGINATORS = [
        'paginate' => 'Illuminate\\Pagination\\LengthAwarePaginator',
        'simplePaginate' => 'Illuminate\\Pagination\\Paginator',
        'cursorPaginate' => 'Illuminate\\Pagination\\CursorPaginator',
    ];

    private const SCALARS = ['count' => 'number', 'exists' => 'boolean'];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof StaticCall && ! $expr instanceof MethodCall) {
            return null;
        }

        if (! $expr->name instanceof Identifier || $expr->isFirstClassCallable()) {
            return null;
        }

        $terminal = $expr->name->toString();

        if (! $this->isTerminal($terminal)) {
            return null;
        }

        $modelFqcn = resolve(CallChainWalker::class)->resolveRootClass($expr, Model::class);

        if ($modelFqcn === null) {
            return null;
        }

        return $this->typeFor($terminal, $modelFqcn);
    }

    /**
     * Whether a terminal method name is one this handler knows the payload shape of.
     */
    private function isTerminal(string $terminal): bool
    {
        return in_array($terminal, self::SINGLE, true)
            || in_array($terminal, self::MANY, true)
            || isset(self::PAGINATORS[$terminal])
            || isset(self::SCALARS[$terminal]);
    }

    /**
     * Build the result for a known terminal against its rooted model.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult
     */
    private function typeFor(string $terminal, string $modelFqcn): array
    {
        if (isset(self::SCALARS[$terminal])) {
            return ['type' => self::SCALARS[$terminal], 'optional' => false];
        }

        $basename = class_basename($modelFqcn);

        if (isset(self::PAGINATORS[$terminal])) {
            $tolkiName = TolkiTypes::MAP[self::PAGINATORS[$terminal]];

            return [
                ...ValueResult::unknown(),
                'type' => $tolkiName.'<'.$basename.'>',
                'modelFqcn' => $modelFqcn,
                'customImports' => ['@tolki/types' => [$tolkiName]],
            ];
        }

        $type = in_array($terminal, self::MANY, true)
            ? $basename.'[]'
            : $basename.(in_array($terminal, self::NULLABLE_SINGLE, true) ? ' | null' : '');

        return [...ValueResult::unknown(), 'type' => $type, 'modelFqcn' => $modelFqcn];
    }
}

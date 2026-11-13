<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;

/**
 * The paginators Laravel builds and what a paginator's own step hands back: the same paginator, its kind and items
 * kept, as a setter such as `withQueryString()` does, or the same paginator over the items `through()` maps.
 * ModelFinderHandler and InertiaResourcePropHandler read a paginator's classes and steps here.
 *
 * @internal
 */
final class PaginatorSteps
{
    /** The paginator each paginating terminal of a query builds. */
    public const array BUILT = [
        'paginate' => LengthAwarePaginator::class,
        'simplePaginate' => Paginator::class,
        'cursorPaginate' => CursorPaginator::class,
    ];

    /** The classes whose instances are each one paginator kind, by the `@tolki/types` name TolkiTypes::MAP gives it. */
    public const array KINDS = [LengthAwarePaginatorContract::class, CursorPaginatorContract::class, Paginator::class];

    /** The paginator methods that hand back the same paginator over the same items. */
    private const array SAME_PAGE = [
        'appends', 'withQueryString', 'withPath', 'setPath', 'onEachSide', 'setPageName', 'setCursorName',
        'hasMorePagesWhen', 'escapeWhenCastingToString',
    ];

    /**
     * Whether a paginator's step hands back the same paginator over the same items; `fragment()` does only when given
     * a fragment, and returns the one it holds when given none.
     */
    public static function keepsPage(MethodCall|NullsafeMethodCall $call): bool
    {
        $name = self::nameOf($call);

        if ($name === 'fragment') {
            $fragment = $call->getArgs()[0] ?? null;

            return $fragment !== null && ! $fragment->unpack
                && ($fragment->value instanceof String_ || $fragment->value instanceof InterpolatedString);
        }

        return $name !== null && in_array($name, self::SAME_PAGE, true);
    }

    /**
     * Whether a paginator's step hands back the same paginator over the items its callback maps them to.
     */
    public static function mapsItems(MethodCall|NullsafeMethodCall $call): bool
    {
        return self::nameOf($call) === 'through' && count($call->getArgs()) === 1 && ! $call->getArgs()[0]->unpack;
    }

    /**
     * The expression a paginator's step is taken on, which holds the same kind of paginator; null for any other.
     */
    public static function steppedFrom(Expr $expr): ?Expr
    {
        return ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) && (self::keepsPage($expr) || self::mapsItems($expr))
            ? $expr->var
            : null;
    }

    /**
     * The name a call is written with, or null for a computed one or a first-class callable.
     */
    private static function nameOf(MethodCall|NullsafeMethodCall $call): ?string
    {
        return $call->name instanceof Identifier && ! $call->isFirstClassCallable() ? $call->name->toString() : null;
    }
}

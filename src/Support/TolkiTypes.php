<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * The PHP classes the `@tolki/types` package declares TypeScript types for.
 */
final class TolkiTypes
{
    /**
     * Maps PHP FQCNs to their TypeScript names in the `@tolki/types` package.
     *
     * @var array<string, string>
     */
    public const MAP = [
        'Illuminate\\Pagination\\LengthAwarePaginator' => 'LengthAwarePaginator',
        'Illuminate\\Pagination\\Paginator' => 'SimplePaginator',
        'Illuminate\\Pagination\\CursorPaginator' => 'CursorPaginator',
        'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator' => 'LengthAwarePaginator',
        'Illuminate\\Contracts\\Pagination\\Paginator' => 'SimplePaginator',
        'Illuminate\\Contracts\\Pagination\\CursorPaginator' => 'CursorPaginator',
        'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection' => 'AnonymousResourceCollection',
    ];
}

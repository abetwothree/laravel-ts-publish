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

    /**
     * Maps each paginator's `@tolki/types` name to the names of what a resource collection over it sends: the `meta`
     * and `links` blocks beside a collection's own body, the whole response of an anonymous or flat one, and the
     * document a JSON:API resource's collection sends.
     *
     * @var array<string, array{blocks: string, response: string, jsonApi: string}>
     */
    public const RESOURCE_PAGINATION = [
        'LengthAwarePaginator' => ['blocks' => 'ResourcePagination', 'response' => 'JsonResourcePaginator', 'jsonApi' => 'JsonApiPaginator'],
        'SimplePaginator' => ['blocks' => 'ResourceSimplePagination', 'response' => 'JsonResourceSimplePaginator', 'jsonApi' => 'JsonApiSimplePaginator'],
        'CursorPaginator' => ['blocks' => 'ResourceCursorPagination', 'response' => 'JsonResourceCursorPaginator', 'jsonApi' => 'JsonApiCursorPaginator'],
    ];

    /**
     * Maps what a JSON:API resource sends to its `@tolki/types` name: the document over one resource object, the one
     * over a collection that is not paginated, and the generic resource object in `included` that no published JSON:API
     * resource types.
     *
     * @var array{single: string, collection: string, resourceObject: string}
     */
    public const JSON_API_DOCUMENTS = [
        'single' => 'JsonApiDocument',
        'collection' => 'JsonApiCollection',
        'resourceObject' => 'JsonApiResourceObject',
    ];

    /**
     * Maps each kind of relationship a JSON:API resource object sends to its `@tolki/types` name: to-one, to-many, or
     * either, where neither the resolver nor the model says which.
     *
     * @var array{toOne: string, toMany: string, either: string}
     */
    public const JSON_API_RELATIONSHIPS = [
        'toOne' => 'JsonApiToOneRelationship',
        'toMany' => 'JsonApiToManyRelationship',
        'either' => 'JsonApiRelationship',
    ];
}

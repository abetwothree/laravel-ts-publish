<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

/**
 * What a resource's `toResponse()` sends, built as Laravel builds it: the class it wraps by, its body inside that live
 * wrap key, the pagination members of each paginator kind its payload may hold, then the `with()` and `additional()`
 * keys, which decide the one key a body with no live wrap key nests under.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type PayloadPaginators = non-empty-list<string|null>
 * @phpstan-type JsonApiIncluded = array{arguments: string, resources: list<class-string>, imports: list<string>}
 *
 * @internal
 */
final class ResourceResponseType
{
    /** Where a paginated response nests its list with no live wrap key, as its pagination members sit beside it. */
    private const string PAGINATED_LIST_KEY = 'data';

    /**
     * What one resource object's response sends, with the classes it names: a JSON:API resource's document, else its
     * own type inside its class's live wrap key, beside its `with()` keys.
     *
     * @param  class-string<JsonResource>  $resource
     * @return ValueExpressionResult|null null for a JSON:API one an `additional()` call adds keys to
     */
    public function single(string $resource, ?ResponseExtras $added = null): ?array
    {
        if (JsonApiResources::is($resource)) {
            $document = TolkiTypes::JSON_API_DOCUMENTS['single'];
            $included = $this->jsonApiIncluded($resource);

            return $added === null ? $this->jsonApiResult($resource, $document.$included['arguments'], [$document], $included) : null;
        }

        $extras = ResponseExtras::withOf($resource)->beside($added);

        return $this->withExtras($extras, [
            ...ValueResult::unknown(),
            'type' => $this->response($resource, TsNaming::resourceTypeName($resource), $resource, extras: $extras->sent),
            'resourceFqcn' => $resource,
        ], $resource);
    }

    /**
     * A named collection's response over each paginator kind its payload may hold, and unpaginated when it may hold
     * none: its body inside the live wrap key, beside that kind's pagination members and its `with()` keys.
     *
     * @param  class-string<ResourceCollection>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult|null null for a flat one over a paginator that collects no element
     */
    public function namedCollection(string $className, array $paginators, ?ResponseExtras $added = null): ?array
    {
        // The prop follows the collection's wrap, element and body, which no file the page records holds.
        DependencyRecorder::recordClass($className);

        // Laravel merges with() and additional() into the response once, so both join it in one step.
        $extras = ResponseExtras::withOf($className)->beside($added);
        $sent = $extras->sent;
        $name = TsNaming::resourceTypeName($className);
        $kinds = $this->paginatorKinds($paginators);

        if ($kinds === []) {
            return $this->withExtras($extras, [
                ...ValueResult::unknown(),
                'type' => $this->response($className, $name, $className, extras: $sent),
                'resourceFqcn' => $className,
            ], $className);
        }

        $unpaginated = in_array(null, $paginators, true) ? [$this->response($className, $name, $className, extras: $sent)] : [];
        $reflection = self::reflect($className);

        // Without a wrap key, a collection that sends its collected list IS the paginator's data: it is the paginator.
        if (ResourceWrapping::key($className) === null && resolve(ResourceCollectionBody::class)->sendsCollectedList($reflection)) {
            $element = ResourceCollections::collectedElement($reflection);

            if ($element === null) {
                return null;
            }

            $paginated = $this->paginatedResourceType(ResourceCollections::elementValue($element), $reflection, null, $kinds);

            return $this->withExtras($extras, $unpaginated === [] ? $paginated : [
                ...$paginated,
                'type' => $this->union([...$unpaginated, $paginated['type']]),
                'embeddedResourceFqcns' => [$className],
            ], $className);
        }

        $blocks = $this->paginationNames($kinds, 'blocks');

        return $this->withExtras($extras, [
            ...ValueResult::unknown(),
            'type' => $this->union([...$unpaginated, $this->beside($this->response($className, $name, $className, paginated: true), $blocks)]),
            'resourceFqcn' => $className,
            'customImports' => [TolkiTypes::MODULE => $blocks],
        ], $className);
    }

    /**
     * An anonymous collection's response over each paginator kind its payload may hold, and unpaginated when it may
     * hold none: its list inside the anonymous collection's live wrap key, or each kind's paginator response.
     *
     * @param  class-string<JsonResource>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult|null null for a JSON:API one an `additional()` call adds keys to
     */
    public function anonymousCollection(string $className, array $paginators, ?ResponseExtras $added = null): ?array
    {
        if (JsonApiResources::is($className)) {
            return $added === null ? $this->jsonApiCollection($className, $paginators) : null;
        }

        $reflection = self::reflect($className);
        $name = TsNaming::resourceTypeName($className);
        // The anonymous collection wraps by its own `$wrap`, `JsonResource`'s shared slot, never the collected one's.
        $wrapKey = ResourceWrapping::key(AnonymousResourceCollection::class);
        $kinds = $this->paginatorKinds($paginators);
        $types = [];
        $imports = [];

        if (in_array(null, $paginators, true)) {
            if ($wrapKey === 'data' && ! ResourceCollections::preservesKeys($reflection)) {
                $anonymous = TolkiTypes::MAP[AnonymousResourceCollection::class];
                $types[] = "{$anonymous}<{$name}>";
                $imports[] = $anonymous;
            } else {
                $types[] = $this->response(
                    AnonymousResourceCollection::class,
                    ResourceCollections::listType($name, $reflection),
                    extras: $added === null ? false : $added->sent,
                );
            }
        }

        if ($kinds !== []) {
            $item = ResourceCollections::elementValue(['fqcn' => $className, 'model' => false]);
            $paginated = $this->paginatedResourceType($item, $reflection, $wrapKey, $kinds);
            $types[] = $paginated['type'];
            $imports = [...$imports, ...$paginated['customImports'][TolkiTypes::MODULE] ?? []];
        }

        $result = [
            ...ValueResult::unknown(),
            'type' => $this->union($types),
            'resourceFqcn' => $className,
            ...($imports === [] ? [] : ['customImports' => [TolkiTypes::MODULE => $imports]]),
        ];

        return $added === null ? $result : $this->withExtras($added, $result, AnonymousResourceCollection::class);
    }

    /**
     * A guessed collection's response beside the one an empty receiver sends instead: the plain collection's, its empty
     * list inside that collection's live wrap key, or each paginator kind's response over no items.
     *
     * @param  ValueExpressionResult  $named
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult
     */
    public function besideEmptyReceiver(array $named, array $paginators, ?ResponseExtras $added): array
    {
        $collection = EmptyReceiverGuess::COLLECTION;
        $kinds = $this->paginatorKinds($paginators);
        $sent = $added === null ? false : $added->sent;
        $types = in_array(null, $paginators, true) ? [$this->response($collection, EmptyReceiverGuess::listType(), extras: $sent)] : [];
        $imports = $named['customImports'][TolkiTypes::MODULE] ?? [];

        if ($kinds !== []) {
            $item = [...ValueResult::unknown(), 'type' => EmptyReceiverGuess::ELEMENT];
            $paginated = $this->paginatedResourceType($item, self::reflect($collection), ResourceWrapping::key($collection), $kinds);
            $types[] = $paginated['type'];
            $imports = [...$imports, ...$paginated['customImports'][TolkiTypes::MODULE] ?? []];
        }

        // The named arm carries the added keys' classes already; each empty arm only spells them again.
        $types = $added === null ? $types : array_map(
            fn (string $type): string => $this->withExtras($added, [...ValueResult::unknown(), 'type' => $type], $collection)['type'],
            $types,
        );

        return [
            ...$named,
            'type' => $this->union([$named['type'], ...$types]),
            ...($imports === [] ? [] : ['customImports' => [TolkiTypes::MODULE => array_values(array_unique($imports))]]),
        ];
    }

    /**
     * What `EnumResource::collection()` sends: a list inside the anonymous collection's live wrap key.
     *
     * @param  ValueExpressionResult  $list
     * @return ValueExpressionResult
     */
    public function enumList(array $list): array
    {
        return [...$list, 'type' => $this->response(AnonymousResourceCollection::class, $list['type'])];
    }

    /**
     * Re-reflect by name so the invariant ReflectionClass template widens back to <object>.
     *
     * @param  class-string  $className
     * @return ReflectionClass<object>
     */
    private static function reflect(string $className): ReflectionClass
    {
        return new ReflectionClass($className);
    }

    /**
     * A response with the `with()` and `additional()` keys beside its body, which sits under the class's body key.
     *
     * @param  ValueExpressionResult  $response
     * @param  class-string<JsonResource>  $wrapsBy
     * @return ValueExpressionResult
     */
    private function withExtras(ResponseExtras $extras, array $response, string $wrapsBy): array
    {
        return $extras->appliedTo($response, $this->bodyKey($wrapsBy));
    }

    /**
     * The key Laravel nests a body under once a key is sent beside it: the live wrap key, else `data`.
     *
     * @param  class-string<JsonResource>  $wrapsBy
     */
    private function bodyKey(string $wrapsBy): ?string
    {
        return ResourceWrapping::responseKey($wrapsBy, true);
    }

    /**
     * The paginator response of an anonymous or flat collection over each kind it may wrap: its items under the
     * response's wrap key, `data` without one, keyed when the reflected class preserves keys.
     *
     * @param  ValueExpressionResult  $item  what one collected item publishes as
     * @param  ReflectionClass<object>  $reflection  the class whose preserve-keys state applies
     * @param  non-empty-list<string>  $kinds
     * @return ValueExpressionResult
     */
    private function paginatedResourceType(array $item, ReflectionClass $reflection, ?string $wrapKey, array $kinds): array
    {
        $list = ResourceCollections::listType($item['type'], $reflection);
        $key = $this->paginatedKey($wrapKey);

        if ($key !== 'data') {
            $blocks = $this->paginationNames($kinds, 'blocks');

            return [...$item, 'type' => $this->beside($this->nested($key, $list), $blocks), 'customImports' => [TolkiTypes::MODULE => $blocks]];
        }

        $responses = $this->paginationNames($kinds, 'response');
        $keyed = ResourceCollections::preservesKeys($reflection);
        $types = array_map(
            fn (string $response): string => $keyed
                ? $this->replacingData("{$response}<{$item['type']}>", 'data', $list)
                : "{$response}<{$item['type']}>",
            $responses,
        );

        return [...$item, 'type' => $this->union($types), 'customImports' => [TolkiTypes::MODULE => $responses]];
    }

    /**
     * A JSON:API resource's collection over each paginator kind its payload may hold, and unpaginated when it may hold
     * none: each kind's `@tolki/types` document, the bare list where the anonymous collection's live wrap key is empty
     * and nothing is paginated, and the list under any other key beside the document's other members.
     *
     * @param  class-string<JsonResource>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult
     */
    private function jsonApiCollection(string $className, array $paginators): array
    {
        $reflection = self::reflect($className);
        $list = ResourceCollections::listType(TsNaming::resourceTypeName($className), $reflection);
        $keyed = ResourceCollections::preservesKeys($reflection);
        // JSON:API's anonymous collection does not redeclare `$wrap`, so it shares JsonResource's slot.
        $wrapKey = ResourceWrapping::key(AnonymousResourceCollection::class);
        $keys = in_array(null, $paginators, true) ? [TolkiTypes::JSON_API_DOCUMENTS['collection'] => $wrapKey] : [];

        foreach ($this->paginatorKinds($paginators) as $kind) {
            $keys[TolkiTypes::RESOURCE_PAGINATION[$kind]['jsonApi']] = $this->paginatedKey($wrapKey);
        }

        $included = $this->jsonApiIncluded($className);
        $types = [];
        $documents = [];

        foreach ($keys as $document => $key) {
            if ($key === null) {
                $types[] = $list;

                continue;
            }

            $generic = $document.$included['arguments'];
            $types[] = $key === 'data' && ! $keyed ? $generic : $this->replacingData($generic, $key, $list);
            $documents[] = $document;
        }

        return $this->jsonApiResult($className, $this->union($types), $documents, $included);
    }

    /**
     * The generic arguments of a JSON:API resource's `@tolki/types` documents, `<R, I>`: `I` is each resource object an
     * include can add, a published JSON:API resource by its own type and any other by its `type` over the generic one;
     * `never` for a resource declaring no relationship, and left to its default where an include may add one unread.
     *
     * @param  class-string<JsonResource>  $className
     * @return JsonApiIncluded
     */
    private function jsonApiIncluded(string $className): array
    {
        $members = [];
        $resources = [];
        $imports = [];

        foreach (JsonApiResources::includedResources($className) as $included) {
            if ($included['resource'] !== null && PublishedClasses::exports($included['resource'])) {
                $members[] = TsNaming::resourceTypeName($included['resource']);
                $resources[] = $included['resource'];

                continue;
            }

            $object = TolkiTypes::JSON_API_DOCUMENTS['resourceObject'];
            $members[] = $included['type'] === 'string' ? $object : "{$object}<{$included['type']}>";
            $imports[] = $object;
        }

        if ($members === [] && JsonApiResources::declaresNoRelationships($className)) {
            $members[] = 'never';
        }

        $name = TsNaming::resourceTypeName($className);

        return [
            'arguments' => $members === [] ? "<{$name}>" : "<{$name}, ".implode(' | ', array_values(array_unique($members))).'>',
            'resources' => $resources,
            'imports' => array_values(array_unique($imports)),
        ];
    }

    /**
     * A JSON:API resource's prop type with the classes and `@tolki/types` names it spells; the included ones only
     * where a document spells its generic arguments.
     *
     * @param  class-string<JsonResource>  $className
     * @param  list<string>  $documents  the document names the type spells
     * @param  JsonApiIncluded  $included
     * @return ValueExpressionResult
     */
    private function jsonApiResult(string $className, string $type, array $documents, array $included): array
    {
        // The prop follows the relationships of the resource and of each one an include reaches, read off their files.
        DependencyRecorder::recordClass($className);

        $spelled = $documents === [] ? ['resources' => [], 'imports' => []] : $included;
        $imports = [...$documents, ...$spelled['imports']];

        return [
            ...ValueResult::unknown(),
            'type' => $type,
            'resourceFqcn' => $className,
            ...($spelled['resources'] === [] ? [] : ['embeddedResourceFqcns' => $spelled['resources']]),
            ...($imports === [] ? [] : ['customImports' => [TolkiTypes::MODULE => $imports]]),
        ];
    }

    /**
     * What Laravel's response sends for a body, by the live wrap key of the class it wraps by: the body nested under
     * that key unless it already holds it, both when it holds it on some paths only, and the body bare without a key,
     * except beside a paginator's members or a `with()` or `additional()` key, which nest it under its body key.
     *
     * @param  class-string<JsonResource>  $wrapsBy
     * @param  class-string<JsonResource>|null  $publishedBy  the class whose own type the body is; null for a list
     * @param  bool|null  $extras  whether a key is sent beside the body: always, never or on some paths (null)
     */
    private function response(
        string $wrapsBy,
        string $body,
        ?string $publishedBy = null,
        bool $paginated = false,
        ?bool $extras = false,
    ): string {
        $key = ResourceWrapping::key($wrapsBy);

        if ($key === null && $paginated) {
            return $this->nested(self::PAGINATED_LIST_KEY, $body);
        }

        // With no live wrap key, Laravel nests the body beside any extra key, even a body that holds `data` itself.
        if ($key === null) {
            $nests = $extras === false ? null : $this->bodyKey($wrapsBy);

            return match (true) {
                $nests === null => $body,
                $extras === null => $body.' | '.$this->nested($nests, $body),
                default => $this->nested($nests, $body),
            };
        }

        return match ($publishedBy === null ? false : resolve(ResourceResponseBody::class)->holdsWrapKey($publishedBy, $key)) {
            true => $body,
            false => $this->nested($key, $body),
            null => $body.' | '.$this->nested($key, $body),
        };
    }

    /**
     * A body nested under a response key: `{ <key>: <body> }`.
     */
    private function nested(string $key, string $body): string
    {
        return '{ '.JsEmitter::validJsObjectKey($key).': '.$body.' }';
    }

    /**
     * The key a paginated response nests its list under: the live wrap key, else `data`.
     */
    private function paginatedKey(?string $wrapKey): string
    {
        return $wrapKey ?? self::PAGINATED_LIST_KEY;
    }

    /**
     * A response type with its own `data` list replaced by the given list under the key: `Omit<T, 'data'> & { k: L }`.
     * A list under another key, or a keyed list, cannot sit beside the `data: T[]` the type declares.
     */
    private function replacingData(string $response, string $key, string $list): string
    {
        return "Omit<{$response}, 'data'> & ".$this->nested($key, $list);
    }

    /**
     * A body beside the pagination members of each paginator kind it may be sent over.
     *
     * @param  non-empty-list<string>  $blocks
     */
    private function beside(string $body, array $blocks): string
    {
        $grouped = static fn (string $type): string => count(TsTypeString::splitTopLevelUnion($type)) > 1
            ? "({$type})"
            : TsTypeShape::unionArm($type);

        return $grouped($body).' & '.$grouped(implode(' | ', $blocks));
    }

    /**
     * Types as the arms of one union, once each, each intersection or arrow grouped so the union reads as its arms.
     *
     * @param  list<string>  $types
     */
    private function union(array $types): string
    {
        $types = array_values(array_unique($types));
        $grouped = count($types) > 1;

        return implode(' | ', $grouped ? array_map(TsTypeShape::groupedArm(...), $types) : $types);
    }

    /**
     * The paginators of a payload, without the `null` that stands for its unpaginated arms.
     *
     * @param  PayloadPaginators  $paginators
     * @return list<string>
     */
    private function paginatorKinds(array $paginators): array
    {
        return array_values(array_filter($paginators, static fn (?string $paginator): bool => $paginator !== null));
    }

    /**
     * The `@tolki/types` names of what each paginator kind sends with a resource collection: the pagination blocks a
     * body sits beside, or the whole response.
     *
     * @param  non-empty-list<string>  $kinds
     * @param  'blocks'|'response'  $part
     * @return non-empty-list<string>
     */
    private function paginationNames(array $kinds, string $part): array
    {
        return array_map(static fn (string $kind): string => TolkiTypes::RESOURCE_PAGINATION[$kind][$part], $kinds);
    }
}

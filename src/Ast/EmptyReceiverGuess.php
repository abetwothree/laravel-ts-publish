<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

/**
 * Over an empty receiver, an argument-less `toResourceCollection()` builds a plain ResourceCollection before Laravel's
 * guess reads any attribute or convention. So a guessed collection may be sent as that one instead: `[]` in a resource
 * key, and that collection's response where the receiver is a whole prop or shared value.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class EmptyReceiverGuess
{
    /** The collection the guess builds over an empty receiver. */
    public const string COLLECTION = ResourceCollection::class;

    /** What that collection's items publish as: it holds none. */
    public const string ELEMENT = 'never';

    /**
     * The empty receiver's collected list, as ResourceCollections spells one.
     */
    public static function listType(): string
    {
        return ResourceCollections::listType(self::ELEMENT, new ReflectionClass(self::COLLECTION));
    }

    /**
     * A guessed collection as a resource key sends it, with the empty receiver's list beside the collection's own type;
     * a list already admits that empty one.
     *
     * @param  class-string<ResourceCollection>  $collection
     * @return ValueExpressionResult|null
     */
    public function nestedValue(string $collection): ?array
    {
        $body = resolve(ResourceCollectionBody::class);
        $nested = $body->nestedValue($collection);

        return $nested !== null && $body->nestsOwnType($collection)
            ? [...$nested, 'type' => $nested['type'].' | '.self::listType()]
            : $nested;
    }

    /**
     * Whether a guessed collection's response already admits the empty receiver's: under the same live wrap key, its
     * analysis is one list property named after that key, or with no key on either, the bare list. Read once per
     * collection and keys a run.
     *
     * @param  class-string<ResourceCollection>  $collection
     */
    public function responseAdmitsEmpty(string $collection): bool
    {
        $key = ResourceWrapping::key(self::COLLECTION);
        $own = ResourceWrapping::key($collection);

        return resolve(AnalysisMemo::class)->remember(
            'admits-empty-response:'.$collection.'@'.($key ?? '').'@'.($own ?? ''),
            function () use ($collection, $key, $own): bool {
                // The caller's type follows this body; the memo replays the files recorded here on every later read.
                DependencyRecorder::recordClass($collection);

                if ($own !== $key) {
                    return false;
                }

                $analysis = resolve(AstEngine::class)->analyzeMethod($collection);

                if ($key === null) {
                    return $analysis->properties === [] && self::isList($analysis->flatTypeAlias);
                }

                return count($analysis->properties) === 1
                    && $analysis->properties[0]['name'] === $key
                    && self::isList($analysis->properties[0]['type']);
            },
        );
    }

    /**
     * Whether a type is one list, which admits the empty receiver's `never[]`.
     */
    private static function isList(?string $type): bool
    {
        return $type !== null && str_ends_with($type, '[]') && count(TsTypeString::splitTopLevelUnion($type)) === 1;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use ReflectionMethod;

/**
 * The keys a resource's response sends beside its body, from its `with()` and an `additional()` call. Laravel merges
 * them in with `array_merge_recursive()`, so an object type intersects them, and once one is sent a body that no live
 * wrap key nests goes under `data` (ResourceWrapping::responseKey()).
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ObjectMembers = list<array{string, string}>
 *
 * @internal
 */
final readonly class ResponseExtras
{
    /** Each import queue a join carries on, with the single-class channel whose class leads the queue it replaces. */
    private const array QUEUES = [
        'embeddedResourceFqcns' => 'resourceFqcn',
        'embeddedModelFqcns' => null,
        'embeddedEnumFqcns' => null,
        'embeddedEnumResourceFqcns' => null,
    ];

    /**
     * @param  ValueExpressionResult|null  $object  the keys it types, as one object type with the classes it names
     * @param  bool|null  $sent  whether the response carries a key: always, never or on some paths only (null)
     */
    private function __construct(public ?array $object, public ?bool $sent) {}

    /**
     * No key beside the body.
     */
    public static function none(): self
    {
        return new self(null, false);
    }

    /**
     * The keys a resource's `with()` returns, its `$with` default where JsonResource's own runs, as its own body reads.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function withOf(string $resource): self
    {
        // The caller's type follows this method.
        DependencyRecorder::recordClass($resource);

        $reflection = self::reflect($resource);

        if (new ReflectionMethod($resource, 'with')->getDeclaringClass()->getName() === JsonResource::class) {
            if (! resolve(SubjectPropertyTypeResolver::class)->holdsDefault($reflection, 'with')) {
                return new self(null, null);
            }

            if ($reflection->getProperty('with')->getDefaultValue() === []) {
                return self::none();
            }
        }

        return self::ofAnalysis(resolve(AstEngine::class)->analyzeMethod($resource, 'with'));
    }

    /**
     * The keys `additional()`'s argument holds: an object literal's, none for `[]`, else keys it cannot type.
     *
     * @param  ValueExpressionResult  $value
     */
    public static function ofValue(array $value): self
    {
        if ($value['type'] === 'never[]') {
            return self::none();
        }

        $members = TsTypeShape::members($value['type']);

        return $members === null || $members === [] ? new self(null, null) : new self(self::asSent($value), self::anyRequired($members) ?: null);
    }

    /**
     * These keys with a later source's after them, as `array_merge_recursive()` puts `additional()`'s after `with()`'s.
     */
    public function beside(self $later): self
    {
        $object = match (true) {
            $this->object === null => $later->object,
            $later->object === null => $this->object,
            default => self::joined($this->object, $later->object),
        };

        return new self($object, $this->sentBeside($later));
    }

    /**
     * Whether a response carries a key from these or from another source.
     */
    public function sentBeside(?self $other): ?bool
    {
        $theirs = $other === null ? false : $other->sent;

        return match (true) {
            $this->sent === true || $theirs === true => true,
            $this->sent === false && $theirs === false => false,
            default => null,
        };
    }

    /**
     * A response with these keys beside its body: merged into its object literal, else intersected with it.
     *
     * @param  ValueExpressionResult  $response
     * @return ValueExpressionResult
     */
    public function appliedTo(array $response): array
    {
        return $this->object === null ? $response : self::joined($response, $this->object);
    }

    /**
     * A class's reflection as the invariant `ReflectionClass<object>` a property lookup takes.
     *
     * @param  class-string  $class
     * @return ReflectionClass<object>
     */
    private static function reflect(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }

    /**
     * The keys a `with()` analysis types, sent on every path when one is required and its body read whole.
     */
    private static function ofAnalysis(MethodAnalysis $analysis): self
    {
        $complete = $analysis->readsCompletely && ! $analysis->holdsUnreadKeys;

        if ($analysis->properties === []) {
            return $complete ? self::none() : new self(null, null);
        }

        $object = resolve(InlineObjectResult::class)->of($analysis);
        $members = TsTypeShape::members($object['type']) ?? [];
        $required = array_any($analysis->properties, fn (array $property): bool => ! $property['optional']);

        return new self($members === [] ? null : self::asSent($object), $required ?: null);
    }

    /**
     * An object as Inertia sends it: decoding the response and encoding it again turns an empty JSON object into `[]`.
     *
     * @param  ValueExpressionResult  $object
     * @return ValueExpressionResult
     */
    private static function asSent(array $object): array
    {
        return [...$object, 'type' => str_replace(TsTypeStringService::EMPTY_OBJECT, 'never[]', $object['type'])];
    }

    /**
     * A type with an object's keys beside it, its classes queued after its own: one literal, else an intersection.
     *
     * @param  ValueExpressionResult  $type
     * @param  ValueExpressionResult  $object  an object literal
     * @return ValueExpressionResult
     */
    private static function joined(array $type, array $object): array
    {
        $held = ValueResult::stripNullArm($type['type']);
        $mine = TsTypeShape::members($held);
        $theirs = TsTypeShape::members($object['type']) ?? [];
        $joined = $mine !== null && ! self::holdsSignature([...$mine, ...$theirs])
            ? self::merged($mine, $theirs)
            : (count(TsTypeString::splitTopLevelUnion($held)) > 1 ? "({$held})" : $held).' & '.$object['type'];

        $result = [...$type, 'type' => ValueResult::hasNullArm($type['type']) ? ValueResult::withNullArm($joined) : $joined];

        foreach (self::QUEUES as $queue => $single) {
            if (($object[$queue] ?? []) === []) {
                continue;
            }

            // A queue replaces its single-class channel when imports are matched to tokens, so the type's own leads it.
            $own = $type[$queue] ?? ($single !== null && isset($type[$single]) ? [$type[$single]] : []);
            $result[$queue] = [...$own, ...$object[$queue]];
        }

        foreach ($object['customImports'] ?? [] as $path => $names) {
            $result['customImports'][$path] = array_values(array_unique([...$result['customImports'][$path] ?? [], ...$names]));
        }

        foreach ($object['carriedFqcns'] ?? [] as $kind => $fqcns) {
            $result['carriedFqcns'][$kind] = array_values(array_unique([...$result['carriedFqcns'][$kind] ?? [], ...$fqcns]));
        }

        return $result;
    }

    /**
     * Two literals' members merged as `array_merge_recursive()` merges them, `unknown` where it may send a list.
     *
     * @param  ObjectMembers  $mine
     * @param  ObjectMembers  $theirs
     */
    private static function merged(array $mine, array $theirs): string
    {
        $members = [];

        foreach ([...$mine, ...$theirs] as [$key, $value]) {
            $name = trim(rtrim($key, '?'), '\'"');
            $earlier = $members[$name] ?? null;

            if ($earlier === null) {
                $members[$name] = [$key, $value];

                continue;
            }

            $first = TsTypeShape::members($earlier[1]);
            $second = TsTypeShape::members($value);
            $always = ! str_ends_with($earlier[0], '?') && ! str_ends_with($key, '?');
            $members[$name] = [
                str_ends_with($key, '?') ? $earlier[0] : $key,
                $always && $first !== null && $second !== null && ! self::holdsSignature([...$first, ...$second])
                    ? self::merged($first, $second)
                    : 'unknown',
            ];
        }

        return '{ '.implode('; ', array_map(static fn (array $member): string => $member[0].': '.$member[1], $members)).' }';
    }

    /**
     * Whether object literal members hold an index signature, which every other key beside it must fit.
     *
     * @param  ObjectMembers  $members
     */
    private static function holdsSignature(array $members): bool
    {
        return array_any($members, static fn (array $member): bool => str_starts_with($member[0], '['));
    }

    /**
     * Whether an object literal's members name a key with no optional marker.
     *
     * @param  ObjectMembers  $members
     */
    private static function anyRequired(array $members): bool
    {
        return array_any($members, static fn (array $member): bool => ! str_ends_with($member[0], '?'));
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
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
 * The keys a resource's response sends beside its body, from its `with()` and an `additional()` call, which Laravel
 * merges in once with `array_merge_recursive()`: into a literal response, else intersecting it, with a key both add
 * `unknown` unless it holds two objects. Once one is sent, a body no live wrap key nests goes under `data`.
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
     * @param  bool  $whole  whether it types every key it may send
     */
    private function __construct(public ?array $object, public ?bool $sent, private bool $whole = true) {}

    /**
     * The keys a resource's `with()` returns, its `$with` default where JsonResource's own runs, as its own body reads.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function withOf(string $resource): self
    {
        // The prop's type depends on this class's with(), which no file the page records holds.
        DependencyRecorder::recordClass($resource);

        $reflection = self::reflect($resource);

        if (new ReflectionMethod($resource, 'with')->getDeclaringClass()->getName() === JsonResource::class) {
            if (! resolve(SubjectPropertyTypeResolver::class)->holdsDefault($reflection, 'with')) {
                return new self(null, null, false);
            }

            if ($reflection->getProperty('with')->getDefaultValue() === []) {
                return self::none();
            }
        }

        return self::ofAnalysis(resolve(AstEngine::class)->analyzeMethod($resource, 'with', reading: Reading::Encoded));
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

        return $members === null || $members === []
            ? new self(null, null, false)
            : new self(self::asSent($value), self::anyRequired($members) ?: null);
    }

    /**
     * These keys with a later source's after them, as `array_merge_recursive()` puts `additional()`'s after `with()`'s.
     */
    public function beside(?self $later): self
    {
        if ($later === null) {
            return $this;
        }

        // A key one side may send unread can be any key the other types, and Laravel sends such a pair as a list.
        $mine = $later->whole ? $this->object : self::untyped($this->object);
        $theirs = $this->whole ? $later->object : self::untyped($later->object);
        $object = match (true) {
            $mine === null => $theirs,
            $theirs === null => $mine,
            default => self::joined($mine, $theirs),
        };
        $sent = match (true) {
            $this->sent === true || $later->sent === true => true,
            $this->sent === false && $later->sent === false => false,
            default => null,
        };

        return new self($object, $sent, $this->whole && $later->whole);
    }

    /**
     * A response with these keys beside the body it holds under a key: merged into its literal, else intersected.
     *
     * @param  ValueExpressionResult  $response
     * @return ValueExpressionResult
     */
    public function appliedTo(array $response, ?string $bodyKey): array
    {
        return $this->object === null ? $response : self::joined($response, $this->object, $bodyKey);
    }

    /**
     * No key beside the body.
     */
    private static function none(): self
    {
        return new self(null, false);
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
            return $complete ? self::none() : new self(null, null, false);
        }

        $object = resolve(InlineObjectResult::class)->of($analysis);
        $members = TsTypeShape::members($object['type']) ?? [];
        $required = array_any($analysis->properties, fn (array $property): bool => ! $property['optional']);

        return new self($members === [] ? null : self::asSent($object), $required ?: null, $complete && $members !== []);
    }

    /**
     * An object with every key `unknown`, which names no class.
     *
     * @param  ValueExpressionResult|null  $object
     * @return ValueExpressionResult|null
     */
    private static function untyped(?array $object): ?array
    {
        $members = $object === null ? null : TsTypeShape::members($object['type']);

        return $members === null ? $object : [
            'type' => self::printed(array_map(static fn (array $member): array => [$member[0], 'unknown'], $members)),
            'optional' => false,
        ];
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
     * @param  string|null  $bodyKey  the key the type holds its body under, which a key beside it merges into
     * @return ValueExpressionResult
     */
    private static function joined(array $type, array $object, ?string $bodyKey = null): array
    {
        $held = ValueResult::stripNullArm($type['type']);
        $mine = TsTypeShape::members($held);
        $theirs = TsTypeShape::members($object['type']) ?? [];
        $joined = $mine !== null && ! self::holdsSignature([...$mine, ...$theirs])
            ? self::merged($mine, $theirs)
            : self::intersected($held, $theirs, $bodyKey);

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
     * A type no literal spells beside an object's members, intersected, with the key the body sits under `unknown`.
     *
     * @param  ObjectMembers  $theirs
     */
    private static function intersected(string $held, array $theirs, ?string $bodyKey): string
    {
        $grouped = count(TsTypeString::splitTopLevelUnion($held)) > 1 ? "({$held})" : $held;
        $clashes = $bodyKey !== null && array_any($theirs, static fn (array $member): bool => self::keyName($member[0]) === $bodyKey);

        if (! $clashes) {
            return $grouped.' & '.self::printed($theirs);
        }

        $members = array_map(
            static fn (array $member): array => self::keyName($member[0]) === $bodyKey ? [$member[0], 'unknown'] : $member,
            $theirs,
        );

        // Laravel merges an added body key into the body, and without the `Omit<>` the type's own would narrow it back.
        return "Omit<{$held}, '{$bodyKey}'> & ".self::printed($members);
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
            $name = self::keyName($key);
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

        return self::printed(array_values($members));
    }

    /**
     * An object literal's members printed as the literal.
     *
     * @param  ObjectMembers  $members
     */
    private static function printed(array $members): string
    {
        return '{ '.implode('; ', array_map(static fn (array $member): string => $member[0].': '.$member[1], $members)).' }';
    }

    /**
     * The key a printed member names, without its quotes or optional marker.
     */
    private static function keyName(string $printed): string
    {
        return trim(rtrim($printed, '?'), '\'"');
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

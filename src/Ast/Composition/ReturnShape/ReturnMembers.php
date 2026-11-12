<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use ReflectionMethod;

/**
 * A method's own `@return` members, read once for the frame that reads its body: each key's member in an
 * `array{...}` shape, or the one value every key of an `array<string, V>` takes.
 *
 * The refiner merges a key with the member this gives it, and the keys whose member tells something are the ones the
 * frame reads in the gap mode, so both read one lookup.
 *
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type TypedKeys from AnalysisScope
 *
 * @internal
 */
final class ReturnMembers
{
    /**
     * The gap gate's switch: while on, every key reads as typed, so a run proves the gap mode changes no published byte.
     *
     * @internal
     */
    public static bool $typesEveryKey = false;

    /**
     * @param  array<array-key, ShapeTypeInfo>  $shape  each member by its key, an optional one's spelled `key?`
     * @param  ShapeTypeInfo|null  $value  the value of an `array<string, V>`, which has no shape
     * @param  TypedKeys  $typedKeys
     */
    private function __construct(
        private readonly array $shape,
        private readonly ?array $value,
        private readonly array|true $typedKeys,
    ) {}

    /**
     * Read a method's own `@return` members.
     */
    public static function of(ReflectionMethod $method): self
    {
        $shape = LaravelTsPublish::docblockReturnShapeInfos($method);
        $value = $shape === [] ? self::recordValue($method) : null;

        return new self($shape, $value, self::keysTyped($shape, $value));
    }

    /**
     * The member a key takes: the shape's, by its name or its optional spelling, else the record's value.
     *
     * @return ShapeTypeInfo|null
     */
    public function member(string $name): ?array
    {
        return $this->shape[$name] ?? $this->shape[$name.'?'] ?? $this->value;
    }

    /**
     * Whether the shape declares the key optional, `key?:`.
     */
    public function isOptional(string $name): bool
    {
        return isset($this->shape[$name.'?']);
    }

    /**
     * The keys whose member tells something of their value, each spelled as PHP reads its key: `rows?` as `rows` and
     * `0` as `'0'`; true where an informative `array<string, V>` types every key, or the gap gate is on.
     *
     * @return TypedKeys
     */
    public function typedKeys(): array|true
    {
        return self::$typesEveryKey ? true : $this->typedKeys;
    }

    /**
     * The value type of a `@return array<string, V>` docblock, one `Record<string, V>`, with the custom imports it
     * spells and the class behind each of its tokens, or null for any other return type.
     *
     * @return ShapeTypeInfo|null
     */
    private static function recordValue(ReflectionMethod $method): ?array
    {
        $info = LaravelTsPublish::docblockReturnTypeInfo($method);
        $arguments = TsTypeShape::recordArguments($info['type']);

        // `Record<string, ` names no class, so the record's token slots are its value's.
        return $arguments !== null && $arguments[0] === 'string' ? [...$info, 'type' => $arguments[1]] : null;
    }

    /**
     * The keys a shape or record value types, as typedKeys() gives them.
     *
     * @param  array<array-key, ShapeTypeInfo>  $shape
     * @param  ShapeTypeInfo|null  $value
     * @return TypedKeys
     */
    private static function keysTyped(array $shape, ?array $value): array|true
    {
        if ($value !== null) {
            return self::tells($value) ? true : [];
        }

        $keys = [];

        foreach ($shape as $key => $info) {
            if (self::tells($info)) {
                $keys[] = str_ends_with((string) $key, '?') ? substr((string) $key, 0, -1) : (string) $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Whether a member tells something of its value, as a key the merge can fill from it must.
     *
     * @param  ShapeTypeInfo  $info
     */
    private static function tells(array $info): bool
    {
        $member = LaravelTsPublish::shapeMemberForKey($info);

        return $member !== null && DeclaredTypeMerge::tells($member['type']) > 0;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use InvalidArgumentException;
use Laravel\Surveyor\Types;
use Laravel\Surveyor\Types\Contracts\Type;

/**
 * Converts Laravel Surveyor types into TypeScript type strings.
 *
 * Used by InertiaSharedDataAnalyzer and InertiaPageAnalyzer to
 * translate PHPDoc array shapes into TypeScript object types.
 */
class SurveyorTypeMapper
{
    /**
     * Convert a Surveyor Type to a TypeScript type string.
     */
    public static function convert(Type $type): string
    {
        return match (get_class($type)) {
            Types\ArrayType::class => self::convertArray($type),
            Types\ArrayShapeType::class => self::convertArrayShape($type),
            Types\BoolType::class => self::convertBool($type),
            Types\ClassType::class => self::convertClass($type),
            Types\IntType::class, Types\FloatType::class, Types\NumberType::class => self::decorate('number', $type),
            Types\IntersectionType::class => self::convertCompound($type->types, '&'),
            Types\MixedType::class => 'unknown',
            Types\NullType::class => 'null',
            Types\StringType::class => self::decorate('string', $type),
            Types\UnionType::class => self::convertCompound($type->types, '|'),
            Types\CallableType::class => self::convert($type->returnType),
            default => throw new InvalidArgumentException('Unsupported Surveyor type: '.get_class($type)),
        };
    }

    /**
     * Convert an array of Surveyor types (keyed by prop name) into a TypeScript object literal.
     *
     * @param  array<string|int, mixed>  $props
     */
    public static function objectToTypeString(array $props): string
    {
        if ($props === []) {
            return 'Record<string, never>';
        }

        $parts = [];

        foreach ($props as $key => $value) {
            $optional = $value instanceof Type && $value->isOptional();
            $separator = $optional ? '?: ' : ': ';

            if (is_array($value)) {
                $tsType = self::objectToTypeString($value);
            } elseif ($value instanceof Type) {
                $tsType = self::convert($value);
            } else {
                $tsType = 'unknown';
            }

            $parts[] = $key.$separator.$tsType;
        }

        return '{ '.implode(', ', $parts).' }';
    }

    /**
     * Convert a Surveyor ArrayType to TypeScript.
     */
    protected static function convertArray(Types\ArrayType $type): string
    {
        /** @var array<string|int, mixed> $value */
        $value = $type->value;

        $nullSuffix = $type->isNullable() ? ' | null' : '';

        if (array_is_list($value)) {
            /** @var list<mixed> $value */
            $types = [];

            foreach ($value as $item) {
                if ($item instanceof Type) {
                    $types[] = self::convert($item);
                } else {
                    $types[] = 'unknown';
                }
            }

            $unique = array_unique($types);
            $union = implode(' | ', $unique);

            if (count($unique) > 1) {
                return '('.$union.')[]'.$nullSuffix;
            }

            return $union.'[]'.$nullSuffix;
        }

        return self::objectToTypeString($value).$nullSuffix;
    }

    /**
     * Convert a Surveyor ArrayShapeType to TypeScript.
     */
    protected static function convertArrayShape(Types\ArrayShapeType $type): string
    {
        $keyType = self::convert($type->keyType);
        $valueType = self::convert($type->valueType);

        if ($keyType === 'number') {
            return self::decorate("{$valueType}[]", $type);
        }

        if ($keyType === 'unknown') {
            $keyType = 'string';
        }

        return self::decorate("Record<{$keyType}, {$valueType}>", $type);
    }

    /**
     * Convert a Surveyor BoolType to TypeScript.
     */
    protected static function convertBool(Types\BoolType $type): string
    {
        $value = 'boolean';

        if ($type->value !== null) {
            $value = $type->value ? 'true' : 'false';
        }

        return self::decorate($value, $type);
    }

    /**
     * Convert a Surveyor ClassType to TypeScript.
     */
    protected static function convertClass(Types\ClassType $type): string
    {
        $value = ltrim($type->value, '\\');

        $mapped = match ($value) {
            'Illuminate\\Support\\Stringable' => 'string',
            'Illuminate\\Support\\Collection' => 'unknown[]',
            default => str_replace('\\', '.', $value),
        };

        return self::decorate($mapped, $type);
    }

    /**
     * Convert a union or intersection type.
     *
     * @param  array<array-key, mixed>  $types
     */
    protected static function convertCompound(array $types, string $glue): string
    {
        $results = collect($types)
            ->map(function (mixed $item): ?string {
                if (is_array($item)) {
                    return collect($item)
                        ->filter()
                        ->map(fn (mixed $t): string => $t instanceof Type ? self::convert($t) : 'unknown')
                        ->unique()
                        ->implode(' | ');
                }

                if ($item === null) {
                    return null;
                }

                if ($item instanceof Type) {
                    return self::convert($item);
                }

                return 'unknown';
            })
            ->filter()
            ->unique();

        // Simplify: if "unknown" is in a union alongside concrete types (other than null), remove it
        if ($glue === '|' && $results->count() > 1 && $results->contains('unknown')) {
            $withoutUnknown = $results->filter(fn (string $t): bool => $t !== 'unknown');

            if ($withoutUnknown->count() === 1 && $withoutUnknown->first() === 'null') {
                return 'unknown';
            }

            $results = $withoutUnknown;
        }

        return $results->implode(' '.$glue.' ');
    }

    /**
     * Append " | null" when the type is nullable.
     */
    protected static function decorate(string $type, Type $result): string
    {
        if ($result->isNullable()) {
            $type .= ' | null';
        }

        return $type;
    }
}

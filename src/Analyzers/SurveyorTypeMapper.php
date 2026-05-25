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
     * Maps PHP FQCNs to their TypeScript names in the `@tolki/types` package.
     *
     * Includes both concrete pagination classes and their contract interfaces, plus
     * AnonymousResourceCollection which becomes `AnonymousResourceCollection<T>` in TypeScript.
     *
     * @var array<string, string>
     */
    public const TOLKI_TYPES_MAP = [
        'Illuminate\\Pagination\\LengthAwarePaginator' => 'LengthAwarePaginator',
        'Illuminate\\Pagination\\Paginator' => 'SimplePaginator',
        'Illuminate\\Pagination\\CursorPaginator' => 'CursorPaginator',
        'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator' => 'LengthAwarePaginator',
        'Illuminate\\Contracts\\Pagination\\Paginator' => 'SimplePaginator',
        'Illuminate\\Contracts\\Pagination\\CursorPaginator' => 'CursorPaginator',
        'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection' => 'AnonymousResourceCollection',
    ];

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
            default => isset(self::TOLKI_TYPES_MAP[$value])
                ? str_replace('\\', '.', $value).self::buildGenericSuffix($type)
                : str_replace('\\', '.', $value),
        };

        return self::decorate($mapped, $type);
    }

    /**
     * Build the generic suffix for a TOLKI_TYPES_MAP class type.
     *
     * Uses the Surveyor-provided generic types when available, falling back
     * to `<unknown>` when none are present (e.g. for bare paginator returns).
     */
    private static function buildGenericSuffix(Types\ClassType $type): string
    {
        $generics = $type->genericTypes();

        if (empty($generics)) {
            return '<unknown>';
        }

        $converted = array_values(array_map(
            fn (mixed $t): string => $t instanceof Type ? self::convert($t) : 'unknown',
            $generics,
        ));

        return '<'.implode(', ', $converted).'>';
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

    /**
     * Extract PHP FQCNs from a type string containing dot-notation class references.
     *
     * Matches multi-segment PascalCase dot-notation (e.g. `Workbench.App.Models.Post`)
     * and converts them back to FQCNs (`Workbench\App\Models\Post`). Filters to only
     * those that actually exist as a class or enum. Excludes `Inertia.*` references
     * which are TypeScript global namespaces, not PHP classes.
     *
     * @return list<class-string>
     */
    public static function extractDotNotationFqcns(string $typeString): array
    {
        // Match sequences of 2+ PascalCase segments separated by dots
        preg_match_all('/\b([A-Z][A-Za-z0-9]*(?:\.[A-Z][A-Za-z0-9]*)+)\b/', $typeString, $matches);

        /** @var list<class-string> $fqcns */
        $fqcns = [];

        foreach (array_unique($matches[1]) as $dotNotation) {
            // Exclude TypeScript globals (Inertia.*)
            if (str_starts_with($dotNotation, 'Inertia.')) {
                continue;
            }

            $fqcn = str_replace('.', '\\', $dotNotation);

            if (class_exists($fqcn) || enum_exists($fqcn) || interface_exists($fqcn)) {
                /** @var class-string $fqcn */
                $fqcns[] = $fqcn;
            }
        }

        return $fqcns;
    }

    /**
     * Rewrite dot-notation class references in a type string to their base names.
     *
     * For each FQCN in `$fqcns`, replaces the dot-notation form
     * (e.g. `Workbench.App.Models.Post`) with the base name (e.g. `Post`).
     * FQCNs in `TOLKI_TYPES_MAP` use their mapped TypeScript name instead of
     * the PHP class basename (e.g. `Paginator` → `SimplePaginator`).
     *
     * @param  list<class-string>  $fqcns
     */
    public static function rewriteDotNotationToBasenames(string $typeString, array $fqcns): string
    {
        foreach ($fqcns as $fqcn) {
            $dotNotation = str_replace('\\', '.', $fqcn);
            $basename = self::TOLKI_TYPES_MAP[$fqcn] ?? class_basename($fqcn);
            $typeString = str_replace($dotNotation, $basename, $typeString);
        }

        return $typeString;
    }
}

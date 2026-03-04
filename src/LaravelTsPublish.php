<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use BackedEnum;
use Closure;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * @phpstan-type TypeScriptTypeInfo = array{
 *    type: string,
 *    enums: list<string>,
 *    enumTypes: list<string>,
 *    classes: list<string>
 * }
 *
 * - type:      The TypeScript type string to use in the interface (e.g. 'StatusType', 'string | null')
 * - enums:     The PHP enum const names (e.g. ['Status']) — informational, useful for display
 * - enumTypes: The TypeScript type alias names to import (e.g. ['StatusType']) — used in import statements
 * - classes:   Other class short names to import from the models barrel (e.g. ['User'])
 */
class LaravelTsPublish
{
    /**
     * @return array<string, string|(callable(): string)>
     */
    public function typesMap(): array
    {
        return (new TypeScriptMap)->gather();
    }

    public function keyCase(string $key): string
    {
        $case = config()->string('ts-publish.relationship_case');

        return match ($case) {
            'camel' => Str::camel($key),
            'snake' => Str::snake($key),
            'pascal' => Str::studly($key),
            default => $key,
        };
    }

    /**
     * Resolve the TypeScript type for a given PHP type, using the following resolution order:
     *
     * 1. Exact map match
     * 2. #[TsType] on any class — explicit annotation wins for cast classes, enums, or anything else
     * 3. PHP enum → StatusType (type alias), enums: [Status], enumTypes: [StatusType]
     * 4. CastsAttributes implementor without #[TsType] → infer from get() return type (named or union), otherwise unknown
     * 5. Any other class → class_basename()
     * 6. encrypted:* compound casts
     * 7. Partial TS map string match
     * 8. unknown
     *
     * @return TypeScriptTypeInfo
     */
    public function phpToTypeScriptType(string $phpType): array
    {
        $typesMap = $this->typesMap(); // keys are already lowercased
        $lower = strtolower($phpType);
        $result = $this->emptyTypeScriptInfo();

        // 1. Exact map match
        $mapping = $typesMap[$lower] ?? null;

        if ($mapping !== null) {
            $result['type'] = is_string($mapping) ? $mapping : $mapping();

            return $result;
        }

        // 2. #[TsType] on any class — explicit override wins before any automatic resolution
        if (class_exists($phpType)) {
            $attrs = (new ReflectionClass($phpType))->getAttributes(TsType::class);
            if ($attrs) {
                $result['type'] = $attrs[0]->newInstance()->type;

                return $result;
            }
        }

        // 3. PHP enum class (before partial match to prevent e.g. "App\Enums\Status" matching "enum" => "string")
        //    - type uses the TypeScript type alias (StatusType) so it can be used as an interface member type
        //    - enums tracks the const name (Status) for informational/display purposes
        //    - enumTypes tracks the type alias name (StatusType) to include in import statements
        if (class_exists($phpType) && (new ReflectionClass($phpType))->isEnum()) {
            $name = class_basename($phpType);
            $result['type'] = $name.'Type';
            $result['enums'] = [$name];
            $result['enumTypes'] = [$name.'Type'];

            return $result;
        }

        // 4. Custom CastsAttributes class — infer from get() return type, otherwise unknown
        if (class_exists($phpType) && is_a($phpType, CastsAttributes::class, true)) {
            $castReturnType = $this->methodReturnedTypes(new ReflectionClass($phpType), 'get');

            if ($castReturnType['type'] !== 'unknown') {
                return $castReturnType;
            }

            $result['type'] = 'unknown';

            return $result;
        }

        // 5. Any other existing class
        if (class_exists($phpType)) {
            $name = class_basename($phpType);
            $result['type'] = $name;
            $result['classes'] = [$name];

            return $result;
        }

        // 6. encrypted:* compound casts (before partial match so "encrypted:array" doesn't resolve to string)
        if (str_starts_with($lower, 'encrypted:')) {
            $inner = substr($lower, strlen('encrypted:'));

            return $this->phpToTypeScriptType($inner);
        }

        // 7. Partial map match (e.g. "tinyint(1)" contains "tinyint")
        foreach ($typesMap as $key => $value) {
            if (str_contains($lower, $key)) {
                $result['type'] = is_string($value) ? $value : $value();

                return $result;
            }
        }

        $result['type'] = 'unknown';

        return $result;
    }

    /** @return TypeScriptTypeInfo */
    public function methodReturnedTypes(ReflectionClass $class, string $method): array // @phpstan-ignore missingType.generics
    {
        if (! $class->hasMethod($method)) {
            return $this->emptyTypeScriptInfo();
        }

        return $this->resolveReflectionType($class->getMethod($method)->getReturnType());
    }

    /** @return TypeScriptTypeInfo */
    public function closureReturnedTypes(Closure $closure): array
    {
        return $this->resolveReflectionType(new ReflectionFunction($closure)->getReturnType());
    }

    /** @return TypeScriptTypeInfo */
    public function resolveReflectionType(?ReflectionType $returnType): array
    {
        $result = $this->emptyTypeScriptInfo();

        // Single named type — includes ?T shorthand (allowsNull() + getName() !== 'null')
        if ($returnType instanceof ReflectionNamedType) {
            $result = $this->phpToTypeScriptType($returnType->getName());

            if ($returnType->allowsNull() && $returnType->getName() !== 'null') {
                $result['type'] .= ' | null';
            }

            return $result;
        }

        // Intersection type (e.g. Countable&Iterator) — no meaningful TS equivalent
        if ($returnType instanceof ReflectionIntersectionType) {
            return $result;
        }

        // Union type — includes DNF members (e.g. (A&B)|null), intersection slots become unknown
        if ($returnType instanceof ReflectionUnionType) {
            $types = [];
            $enums = [];
            $enumTypes = [];
            $classes = [];

            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType) {
                    $resolved = $this->phpToTypeScriptType($type->getName());
                    $types[] = $resolved['type'];
                    $enums = [...$enums,      ...$resolved['enums']];
                    $enumTypes = [...$enumTypes,  ...$resolved['enumTypes']];
                    $classes = [...$classes,    ...$resolved['classes']];
                } else {
                    $types[] = 'unknown'; // ReflectionIntersectionType inside a DNF union
                }
            }

            $result['type'] = implode(' | ', array_unique($types));
            $result['enums'] = array_values(array_unique($enums));
            $result['enumTypes'] = array_values(array_unique($enumTypes));
            $result['classes'] = array_values(array_unique($classes));

            return $result;
        }

        return $result;
    }

    public function validJsObjectKey(string $key): string
    {
        // If the key is a valid JS identifier, return as-is, otherwise quote it
        // It needs to start with a letter, $ or _, and can only contain letters, numbers, $ and _
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key)) {
            return $key;
        }

        // json_encode produces a properly escaped double-quoted string valid in JS/TS
        return (string) json_encode($key);
    }

    /**
     * Convert a PHP value to a raw JavaScript/TypeScript literal.
     *
     * Unlike @js() / Js::from(), this outputs readable object/array literals directly
     * instead of wrapping them in JSON.parse(...). Suitable for generating .ts files
     * where XSS-safe encoding is not needed.
     *
     * Examples:
     *   ['Draft' => 0, 'Published' => 1]  →  {Draft: 0, Published: 1}
     *   [0, 1]                             →  [0, 1]
     *   'pencil'                           →  'pencil'
     *   true                               →  true
     */
    public function toJsLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'".str_replace(['\\', "'", "\n", "\r", "\t"], ['\\\\', "\\'", '\\n', '\\r', '\\t'], $value)."'";
        }

        // BackedEnum → its scalar value; UnitEnum → its name as a string
        if ($value instanceof BackedEnum) {
            return $this->toJsLiteral($value->value);
        }

        if ($value instanceof UnitEnum) {
            return $this->toJsLiteral($value->name);
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return '['.implode(', ', array_map(fn ($v) => $this->toJsLiteral($v), $value)).']';
            }

            $pairs = [];
            foreach ($value as $key => $val) {
                $pairs[] = $this->validJsObjectKey((string) $key).': '.$this->toJsLiteral($val);
            }

            return '{'.implode(', ', $pairs).'}';
        }

        return 'null';
    }

    /** @var list<string> */
    public const array TS_PRIMITIVES = [
        'string', 'number', 'boolean', 'bigint', 'symbol',
        'null', 'undefined', 'object', 'unknown', 'any', 'never', 'void',
    ];

    /**
     * Extract importable type identifiers from a TypeScript type string,
     * filtering out primitives, inline types, and union syntax.
     *
     * @return list<string>
     */
    public function extractImportableTypes(string $typeString): array
    {
        $parts = explode('|', $typeString);
        $importable = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || in_array($part, self::TS_PRIMITIVES, true)) {
                continue;
            }

            // Skip inline object types, tuple types, and generic types
            if (str_starts_with($part, '{') || str_starts_with($part, '[') || str_contains($part, '<')) {
                continue;
            }

            // Strip array shorthand (e.g. MyType[]) to get the base type name
            $importable[] = str_ends_with($part, '[]') ? substr($part, 0, -2) : $part;
        }

        return array_values(array_unique($importable));
    }

    /** @return TypeScriptTypeInfo */
    public function emptyTypeScriptInfo(): array
    {
        return ['type' => 'unknown', 'enums' => [], 'enumTypes' => [], 'classes' => []];
    }
}

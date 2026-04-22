<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use BackedEnum;
use Closure;
use Composer\ClassMapGenerator\PhpFileParser;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * @phpstan-type TypeScriptTypeInfo = array{
 *    type: string,
 *    enums: list<string>,
 *    enumTypes: list<string>,
 *    classes: list<class-string>,
 *    customImports: array<string, list<string>>,
 *    enumFqcns: list<string>,
 *    classFqcns: list<string>,
 * }
 *
 * - type:          The TypeScript type string to use in the interface (e.g. 'StatusType', 'string | null')
 * - enums:         The PHP enum const names (e.g. ['Status']) — informational, useful for display
 * - enumTypes:     The TypeScript type alias names to import (e.g. ['StatusType']) — used in import statements
 * - classes:       Other class short names to import from the models barrel (e.g. ['User'])
 * - customImports: Custom import paths mapped to their type names (e.g. ['@js/types/product' => ['ProductDimensions']])
 * - enumFqcns:     The fully-qualified class names of resolved PHP enums (e.g. ['App\Enums\Status'])
 * - classFqcns:    The fully-qualified class names of resolved non-enum classes (e.g. ['App\Models\User'])
 */
class LaravelTsPublish
{
    protected static ?Closure $callCommandWith = null;

    /** @var list<string> */
    private const RESERVED_JS_IDENTIFIERS = [
        'break', 'case', 'catch', 'class', 'const', 'continue', 'debugger',
        'default', 'delete', 'do', 'else', 'export', 'extends', 'false',
        'finally', 'for', 'function', 'if', 'import', 'in', 'instanceof',
        'let', 'new', 'null', 'return', 'static', 'super', 'switch', 'this',
        'throw', 'true', 'try', 'typeof', 'var', 'void', 'while', 'with',
        'yield',
    ];

    /** @var list<string> */
    public const array TS_PRIMITIVES = [
        'string', 'number', 'boolean', 'bigint', 'symbol',
        'null', 'undefined', 'object', 'unknown', 'any', 'never', 'void',
    ];

    /**
     * Set something to do when the publish command runs, using a callback Closure
     */
    public static function callCommandUsing(Closure $resolver): void
    {
        self::$callCommandWith = $resolver;
    }

    /**
     * Invoke the callback set by callCommandUsing() before running the publish command.
     */
    public function callCommandWith(): void
    {
        if (self::$callCommandWith instanceof Closure) {
            (self::$callCommandWith)();
        }
    }

    /**
     * Resolve an absolute file path to a path relative to the project root.
     * Falls back to a vendor-relative path for files outside base_path().
     */
    public static function resolveRelativePath(string $absolutePath): string
    {
        $basePath = base_path().DIRECTORY_SEPARATOR;

        if (str_starts_with($absolutePath, $basePath)) {
            return Str::after($absolutePath, $basePath);
        }

        // File is outside base_path() (e.g. vendor in a package development context)
        if (str_contains($absolutePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            return 'vendor'.DIRECTORY_SEPARATOR.Str::after($absolutePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }

    /**
     * @return array<string, string|(callable(): string)>
     */
    public function typesMap(): array
    {
        return (new TypeScriptMap)->gather();
    }

    /**
     * @return array<class-string, string>
     */
    public function relationsMap(): array
    {
        return (new RelationMap)->gather();
    }

    /**
     * Resolve the nullability strategy for a relation type.
     *
     * Accepts a short class name (e.g. 'HasOne') or a FQCN.
     */
    public function relationStrategy(string $type): string
    {
        return (new RelationMap)->strategyFor($type);
    }

    public function keyCase(string $key, string $case): string
    {
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
     * 0. ?T nullable shorthand → recurse on T and append | null
     * 1. Exact map match
     * 2. #[TsType] on any class — explicit annotation wins for cast classes, enums, or anything else
     * 3. PHP enum → StatusType (type alias), enums: [Status], enumTypes: [StatusType]
     * 4. CastsAttributes implementor without #[TsType] → infer from get() return type (named or union), otherwise unknown
     * 5a. Arrayable (non-Model) → unknown[]
     * 5b. __toString (non-Model) → string
     * 5. Any other class → class_basename()
     * 6. encrypted:* compound casts
     * 7. Partial TS map string match
     * 8. unknown
     *
     * @return TypeScriptTypeInfo
     */
    public function toTsType(string $phpType): array
    {
        $typesMap = $this->typesMap(); // keys are already lowercased
        $lower = strtolower($phpType);
        $result = $this->emptyTypeScriptInfo();

        // 0. Nullable shorthand ?T → recurse on T and append | null
        if (str_starts_with($phpType, '?')) {
            $inner = $this->toTsType(substr($phpType, 1));
            if (! str_contains($inner['type'], 'null')) {
                $inner['type'] .= ' | null';
            }

            return $inner;
        }

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
                $tsType = $attrs[0]->newInstance()->type;

                if (is_array($tsType)) {
                    /** @var array{type: string, import?: string} $tsType */
                    $result['type'] = $tsType['type'];

                    if (isset($tsType['import'])) {
                        foreach ($this->extractImportableTypes($tsType['type']) as $importName) {
                            $result['customImports'][$tsType['import']][] = $importName;
                        }
                    }
                } else {
                    $result['type'] = $tsType;
                }

                return $result;
            }
        }

        // 3. PHP enum class (before partial match to prevent e.g. "App\Enums\Status" matching "enum" => "string")
        //    - type uses the TypeScript type alias (StatusType) so it can be used as an interface member type
        //    - enums tracks the const name (Status) for informational/display purposes
        //    - enumTypes tracks the type alias name (StatusType) to include in import statements
        if (class_exists($phpType) && (new ReflectionClass($phpType))->isEnum()) {
            $ref = new ReflectionClass($phpType);
            $tsEnumAttrs = $ref->getAttributes(TsEnum::class);
            $name = $tsEnumAttrs
                ? $tsEnumAttrs[0]->newInstance()->name
                : class_basename($phpType);

            $result['type'] = $name.'Type';
            $result['enums'] = [$name];
            $result['enumTypes'] = [$name.'Type'];
            $result['enumFqcns'] = [$phpType];

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

        // 5a. Arrayable (non-Model) → unknown[] — checked before step 5 to avoid Model being caught here
        //     Model implements Arrayable transitively, so we exclude Model subclasses explicitly.
        if (class_exists($phpType)
            && ! is_a($phpType, Model::class, true)
            && is_a($phpType, Arrayable::class, true)
        ) {
            $result['type'] = 'unknown[]';

            return $result;
        }

        // 5b. __toString magic method → string (covers both `implements \Stringable` and direct __toString)
        //     Exclude Model subclasses — Model defines __toString() returning JSON; models should stay at step 5.
        if (class_exists($phpType)
            && ! is_a($phpType, Model::class, true)
            && method_exists($phpType, '__toString')
        ) {
            $result['type'] = 'string';

            return $result;
        }

        // 5. Any other existing class
        if (class_exists($phpType)) {
            /** @var class-string $name */
            $name = class_basename($phpType);
            $result['type'] = $name;
            $result['classes'] = [$name];
            $result['classFqcns'] = [$phpType];

            return $result;
        }

        // 6. encrypted:* compound casts (before partial match so "encrypted:array" doesn't resolve to string)
        if (str_starts_with($lower, 'encrypted:')) {
            $inner = substr($lower, strlen('encrypted:'));

            return $this->toTsType($inner);
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

    /**
     * @template T of object
     *
     * @param  ReflectionClass<T>  $class
     * @return TypeScriptTypeInfo
     */
    public function propertyTypes(ReflectionClass $class, string $property): array
    {
        if (! $class->hasProperty($property)) {
            return $this->emptyTypeScriptInfo(); // @codeCoverageIgnore
        }

        return $this->resolveReflectionType($class->getProperty($property)->getType());
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<T>  $class
     * @return TypeScriptTypeInfo
     */
    public function methodReturnedTypes(ReflectionClass $class, string $method): array
    {
        if (! $class->hasMethod($method)) {
            return $this->emptyTypeScriptInfo();
        }

        return $this->resolveReflectionType($class->getMethod($method)->getReturnType());
    }

    /**
     * Like `methodReturnedTypes`, but falls back to the `@return` docblock
     * when the method has no signature return type.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $class
     * @return TypeScriptTypeInfo
     */
    public function methodOrDocblockReturnTypes(ReflectionClass $class, string $method): array
    {
        if (! $class->hasMethod($method)) {
            return $this->emptyTypeScriptInfo();
        }

        $reflectionMethod = $class->getMethod($method);
        $returnType = $reflectionMethod->getReturnType();

        if ($returnType !== null) {
            return $this->resolveReflectionType($returnType);
        }

        return $this->docblockReturnTypes($reflectionMethod);
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
            $result = $this->toTsType($returnType->getName());

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
            $infos = [];

            foreach ($returnType->getTypes() as $type) {
                $infos[] = $type instanceof ReflectionNamedType
                    ? $this->toTsType($type->getName())
                    : $this->emptyTypeScriptInfo(); // ReflectionIntersectionType inside a DNF union → unknown
            }

            return $this->mergeTypeScriptInfos($infos);
        }

        return $result;
    }

    /**
     * Parse the `@return` docblock of a method and resolve each type part
     * through toTsType, resolving short class names via the
     * declaring file's use statements.
     *
     * Supports multiline `@return` types including `array{...}` shapes
     * that span multiple lines in the docblock.
     *
     * @return TypeScriptTypeInfo
     */
    public function docblockReturnTypes(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return $this->emptyTypeScriptInfo();
        }

        $returnTypeString = $this->extractReturnTypeFromDocblock($docComment);

        if ($returnTypeString === null) {
            return $this->emptyTypeScriptInfo();
        }

        $parts = $this->splitPhpDocUnionType($returnTypeString);

        // Build a use-map from the declaring file for short name resolution
        $declaringClass = $method->getDeclaringClass();
        $useMap = $this->parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

        $infos = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue; // @codeCoverageIgnore
            }

            $resolved = $this->resolveDocblockTypeName($part, $useMap, $namespace);
            $infos[] = $this->toTsType($resolved);
        }

        if ($infos === []) {
            return $this->emptyTypeScriptInfo(); // @codeCoverageIgnore
        }

        if (count($infos) === 1) {
            return $infos[0];
        }

        return $this->mergeTypeScriptInfos($infos);
    }

    /**
     * Parse `@return Attribute<GetterType, SetterType>` from a method's docblock
     * and resolve the getter type.
     *
     * Falls back to `docblockReturnTypes()` when no `Attribute<…>` pattern is found.
     *
     * @return TypeScriptTypeInfo
     */
    public function attributeDocblockReturnTypes(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return $this->emptyTypeScriptInfo();
        }

        // Look for @return Attribute<GetterType, SetterType>
        // Use lookbehind for "* " to avoid matching @return in inline comments
        if (preg_match('/(?<=\* )@return\s+Attribute\s*<\s*([^,>\s]+)\s*,\s*([^>]+)\s*>/i', $docComment, $matches)) {
            $getterType = trim($matches[1]);

            return $this->resolveDocblockTypeString($method, $getterType);
        }

        // No Attribute<…> pattern — fall back to generic @return parsing
        return $this->docblockReturnTypes($method);
    }

    /**
     * Resolve a docblock type string (potentially a union like `string|null`)
     * through use-statement resolution and toTsType.
     *
     * @return TypeScriptTypeInfo
     */
    protected function resolveDocblockTypeString(ReflectionMethod $method, string $typeString): array
    {
        $parts = array_map('trim', explode('|', $typeString));

        $declaringClass = $method->getDeclaringClass();
        $useMap = $this->parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

        $parts = array_map(
            fn (string $part) => $this->resolveDocblockTypeName($part, $useMap, $namespace),
            $parts,
        );

        if (count($parts) === 1) {
            return $this->toTsType($parts[0]);
        }

        $infos = array_map(
            fn (string $part) => $this->toTsType($part),
            $parts,
        );

        return $this->mergeTypeScriptInfos($infos);
    }

    /**
     * Resolve a docblock type name to a FQCN using the file's use statements and namespace.
     *
     * @param  array<string, string>  $useMap
     */
    public function resolveDocblockTypeName(string $type, array $useMap, string $namespace): string
    {
        // Nullable shorthand ?T → resolve T and re-prepend ?
        if (str_starts_with($type, '?')) {
            return '?'.$this->resolveDocblockTypeName(substr($type, 1), $useMap, $namespace);
        }

        // Fully qualified
        if (str_starts_with($type, '\\')) {
            return substr($type, 1); // @codeCoverageIgnore
        }

        // Check use-map for the root segment
        $root = Str::before($type, '\\');

        if (isset($useMap[$root])) {
            $rest = Str::after($type, '\\');

            return $rest !== $type ? $useMap[$root].'\\'.$rest : $useMap[$root];
        }

        // Try the declaring class's namespace
        if ($namespace !== '') { // @codeCoverageIgnoreStart
            $qualified = $namespace.'\\'.$type;

            if (class_exists($qualified) || enum_exists($qualified)) {
                return $qualified;
            }
        } // @codeCoverageIgnoreEnd

        return $type;
    }

    /**
     * Extract the complete `@return` type string from a docblock,
     * including multiline `array{...}` shapes with nested braces.
     *
     * Returns null when no `@return` tag is found.
     */
    public function extractReturnTypeFromDocblock(string $docComment): ?string
    {
        // Normalize: strip comment markers, join into a single line
        $lines = explode("\n", $docComment);
        $content = '';

        foreach ($lines as $line) {
            $stripped = preg_replace('#^\s*/?\*+\s?#', '', $line) ?? '';
            $stripped = preg_replace('#\s*\*+/$#', '', $stripped);
            $content .= ' '.$stripped;
        }

        $content = trim($content);

        // Find @return tag
        if (! preg_match('/@return\s+/', $content, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = (int) $match[0][1] + strlen((string) $match[0][0]);
        $rest = trim(substr($content, $start));

        // If the type starts with array{, use brace matching to capture the full shape
        if (str_starts_with($rest, 'array{')) {
            $depth = 0;
            $end = 0;

            for ($i = 5; $i < strlen($rest); $i++) {
                if ($rest[$i] === '{') {
                    $depth++;
                } elseif ($rest[$i] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $i + 1;

                        break;
                    }
                }
            }

            if ($end > 0) {
                // Capture any trailing union, allowing spaces around `|` (e.g. `array{...} | null`)
                $after = substr($rest, $end);
                $afterTrimmed = ltrim($after);

                if (preg_match('/^(\s*\|\s*[^\s|@]+)+/', $afterTrimmed, $trailingMatch)) {
                    $trailingNormalized = (string) preg_replace('/\s*\|\s*/', '|', $trailingMatch[0]);

                    return $this->normalizeDocblockWhitespace(substr($rest, 0, $end).$trailingNormalized);
                }

                return $this->normalizeDocblockWhitespace(substr($rest, 0, $end));
            }
        }

        // Otherwise, capture until whitespace (single-line type)
        preg_match('/^(\S+)/', $rest, $typeMatch);

        return $typeMatch[1] ?? null;
    }

    /**
     * Collapse excessive whitespace in a docblock type string to single spaces.
     */
    protected function normalizeDocblockWhitespace(string $type): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($type));
    }

    /**
     * Split a PHPDoc type string on `|` at the top level (depth 0),
     * respecting nested `{}`, `<>`, and `()`.
     *
     * @return list<string>
     */
    public function splitPhpDocUnionType(string $type): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        for ($i = 0; $i < strlen($type); $i++) {
            $char = $type[$i];

            if ($char === '{' || $char === '<' || $char === '(') {
                $depth++;
            } elseif ($char === '}' || $char === '>' || $char === ')') {
                $depth--;
            } elseif ($char === '|' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Parse the `@return array{...}` docblock of a method into a map of
     * property name → TypeScript type string.
     *
     * Only handles top-level `array{key: type, ...}` shapes. Returns an empty
     * array when the `@return` tag is missing or not an array shape.
     *
     * @return array<string, string>
     */
    public function parseDocblockReturnArrayShape(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return [];
        }

        $returnType = $this->extractReturnTypeFromDocblock($docComment);

        if ($returnType === null || ! str_starts_with($returnType, 'array{')) {
            return [];
        }

        $declaringClass = $method->getDeclaringClass();
        $useMap = $this->parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

        return $this->parseArrayShapeToTsTypes($returnType, $useMap, $namespace);
    }

    /**
     * Parse a PHPDoc `array{key: type, ...}` shape string into a map
     * of property name → TypeScript type string.
     *
     * Handles nested `array{...}` shapes and union types recursively.
     *
     * @param  array<string, string>  $useMap
     * @return array<string, string>
     */
    public function parseArrayShapeToTsTypes(string $shape, array $useMap, string $namespace): array
    {
        if (! str_starts_with($shape, 'array{') || ! str_ends_with($shape, '}')) {
            return [];
        }

        $inner = trim(substr($shape, 6, -1));

        if ($inner === '') {
            return [];
        }

        $entries = $this->splitAtTopLevelCommas($inner);
        $result = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);

            // Match 'key: type' or 'key?: type'
            if (preg_match('/^(\w+)\??\s*:\s*(.+)$/s', $entry, $m)) {
                $result[$m[1]] = $this->resolvePhpDocTypeToTs(trim($m[2]), $useMap, $namespace);
            }
        }

        return $result;
    }

    /**
     * Resolve a PHPDoc type string (including nested array shapes) to a TypeScript type string.
     *
     * @param  array<string, string>  $useMap
     */
    public function resolvePhpDocTypeToTs(string $phpType, array $useMap, string $namespace): string
    {
        $phpType = trim($phpType);

        // Handle union types first (e.g. string|null, array{...}|null)
        // so that depth-aware splitting separates "array{...}" from "|null" correctly
        $unionParts = $this->splitPhpDocUnionType($phpType);

        if (count($unionParts) > 1) {
            $tsParts = array_map(
                fn (string $part) => $this->resolvePhpDocTypeToTs($part, $useMap, $namespace),
                $unionParts,
            );

            return implode(' | ', $tsParts);
        }

        // Handle array{...} shapes recursively (after union split, this is a pure shape)
        if (str_starts_with($phpType, 'array{')) {
            $innerTypes = $this->parseArrayShapeToTsTypes($phpType, $useMap, $namespace);

            if ($innerTypes !== []) {
                $parts = [];

                foreach ($innerTypes as $key => $type) {
                    $parts[] = $key.': '.$type;
                }

                return '{ '.implode(', ', $parts).' }';
            }

            return 'Record<string, unknown>';
        }

        // Simple type — resolve through the existing pipeline
        $resolved = $this->resolveDocblockTypeName($phpType, $useMap, $namespace);
        $info = $this->toTsType($resolved);

        return $info['type'];
    }

    /**
     * Split a string on commas at the top level (depth 0),
     * respecting nested `{}`, `<>`, and `()`.
     *
     * @return list<string>
     */
    protected function splitAtTopLevelCommas(string $input): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];

            if ($char === '{' || $char === '<' || $char === '(') {
                $depth++;
            } elseif ($char === '}' || $char === '>' || $char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Parse use statements from a class's source file into a short-name → FQCN map.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $class
     * @return array<string, string>
     */
    public function parseFileUseStatements(ReflectionClass $class): array
    {
        $fileName = $class->getFileName();

        if ($fileName === false) {
            return []; // @codeCoverageIgnore
        }

        $source = (string) file_get_contents($fileName);
        $map = [];

        preg_match_all(
            '/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $alias = $match[2] ?? '';
            $short = $alias !== '' ? $alias : class_basename($fqcn);
            $map[$short] = $fqcn;
        }

        return $map;
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
     * Ensure a string is safe to use as a bare JavaScript/TypeScript identifier
     * (i.e., for `const` declarations or export aliases — NOT object property keys,
     * where reserved words are valid in TypeScript interfaces and object literals).
     *
     * Guards against reserved JS/TS keywords (e.g. 'delete' → 'deleteMethod').
     *
     * @param  string  $name  The proposed identifier
     * @param  string  $suffix  Required suffix appended when $name is reserved (e.g., 'Method', 'Controller')
     */
    public function safeJsIdentifier(string $name, string $suffix): string
    {
        if (in_array($name, self::RESERVED_JS_IDENTIFIERS, true)) {
            return $name.$suffix;
        }

        return $name;
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
        return ['type' => 'unknown', 'enums' => [], 'enumTypes' => [], 'classes' => [], 'customImports' => [], 'enumFqcns' => [], 'classFqcns' => []];
    }

    /**
     * Merge a list of TypeScriptTypeInfo results into one, joining type strings with ' | '.
     *
     * @param  list<TypeScriptTypeInfo>  $infos
     * @return TypeScriptTypeInfo
     */
    public function mergeTypeScriptInfos(array $infos): array
    {
        $types = [];
        $enums = [];
        $enumTypes = [];
        $classes = [];
        /** @var array<string, list<string>> $customImports */
        $customImports = [];
        $enumFqcns = [];
        $classFqcns = [];

        foreach ($infos as $info) {
            $types[] = $info['type'];
            $enums = [...$enums, ...$info['enums']];
            $enumTypes = [...$enumTypes, ...$info['enumTypes']];
            $classes = [...$classes, ...$info['classes']];
            $enumFqcns = [...$enumFqcns, ...$info['enumFqcns']];
            $classFqcns = [...$classFqcns, ...$info['classFqcns']];

            foreach ($info['customImports'] as $path => $importTypes) {
                $customImports[$path] = [...($customImports[$path] ?? []), ...$importTypes];
            }
        }

        $result = $this->emptyTypeScriptInfo();
        $result['type'] = implode(' | ', array_unique($types));
        $result['enums'] = array_values(array_unique($enums));
        $result['enumTypes'] = array_values(array_unique($enumTypes));
        $result['classes'] = array_values(array_unique($classes));
        $result['customImports'] = $customImports;
        $result['enumFqcns'] = array_values(array_unique($enumFqcns));
        $result['classFqcns'] = array_values(array_unique($classFqcns));

        return $result;
    }

    /**
     * Convert a FQCN to a modular output directory path.
     *
     * Strips the class name, applies the configurable namespace prefix strip,
     * kebab-cases each segment, and joins with '/'.
     *
     * Example: 'Blog\Enums\ArticleStatus' → 'blog/enums'
     */
    public function namespaceToPath(string $fqcn): string
    {
        $namespace = Str::beforeLast($fqcn, '\\');

        $prefix = config()->string('ts-publish.namespace_strip_prefix', '');

        if ($prefix !== '' && str_starts_with($namespace, $prefix)) {
            $namespace = substr($namespace, strlen($prefix));
        }

        return collect(explode('\\', $namespace))
            ->filter()
            ->map(fn (string $segment) => Str::kebab($segment))
            ->implode('/');
    }

    /**
     * Compute the TypeScript relative import path from one namespace path to another.
     *
     * Example: 'blog/models' → 'blog/enums' = '../enums'
     * Example: 'app/models' → 'blog/enums' = '../../blog/enums'
     * Example: 'blog/models' → 'blog/models' = '.'
     */
    public function relativeImportPath(string $fromNamespacePath, string $toNamespacePath): string
    {
        if ($fromNamespacePath === $toNamespacePath) {
            return '.';
        }

        $fromParts = explode('/', $fromNamespacePath);
        $toParts = explode('/', $toNamespacePath);

        // Find common prefix length
        $commonLength = 0;
        $maxCommon = min(count($fromParts), count($toParts));

        while ($commonLength < $maxCommon && $fromParts[$commonLength] === $toParts[$commonLength]) {
            $commonLength++;
        }

        $upCount = count($fromParts) - $commonLength;
        $downSegments = array_slice($toParts, $commonLength);

        $relative = str_repeat('../', $upCount).implode('/', $downSegments);

        return rtrim($relative, '/');
    }

    /**
     * Sort import paths following eslint-plugin-simple-import-sort conventions:
     *
     * 1. Package imports (npm packages: start with letter/digit/_ or @letter)
     * 2. Absolute/other imports (everything else not starting with .)
     * 3. Relative imports (starting with .), deeper paths first
     *
     * Within each group, paths are sorted alphabetically (case-insensitive).
     *
     * @param  array<string, list<string>>  $imports
     * @return array<string, list<string>>
     */
    public function sortImportPaths(array $imports): array
    {
        uksort($imports, function (string $a, string $b): int {
            $groupA = $this->importSortGroup($a);
            $groupB = $this->importSortGroup($b);

            if ($groupA !== $groupB) {
                return $groupA <=> $groupB;
            }

            // Within relative imports, deeper paths come first
            if ($groupA === 2) {
                $depthA = count(array_filter(explode('/', $a), fn (string $s): bool => $s === '..'));
                $depthB = count(array_filter(explode('/', $b), fn (string $s): bool => $s === '..'));

                if ($depthA !== $depthB) {
                    return $depthB <=> $depthA;
                }
            }

            return strnatcasecmp($a, $b);
        });

        return $imports;
    }

    /**
     * Determine the sort group for an import path.
     *
     * 0 = Package (starts with letter/digit/_ or @letter)
     * 1 = Absolute/other (everything else not starting with .)
     * 2 = Relative (starts with .)
     */
    protected function importSortGroup(string $path): int
    {
        if (str_starts_with($path, '.')) {
            return 2;
        }

        if (preg_match('/^@?\w/', $path)) {
            return 0;
        }

        return 1;
    }

    /**
     * Prefix unqualified type names in a TypeScript type string with their global namespace.
     *
     * Used when generating the globals file, where types from other namespaces must be
     * fully qualified (e.g. `PaymentStatusType` → `enums.PaymentStatusType`).
     *
     * Pass 1 resolves per-file import aliases (e.g. `CrmUser` → `models.User`) before the
     * normal qualification pass so that aliased names are correctly qualified in the globals file.
     *
     * @param  string  $typeStr  The TypeScript type string to rewrite.
     * @param  array<string, list<string>>  $namespacedTypes  Map of namespace prefix → type names it owns.
     * @param  string  $skipNamespace  Skip types that already belong to this namespace (current context).
     * @param  array<string, string>  $aliasResolution  Per-file alias → 'namespace.OriginalName' map.
     */
    public function qualifyGlobalType(string $typeStr, array $namespacedTypes, string $skipNamespace = '', array $aliasResolution = []): string
    {
        // Pass 1: resolve per-file import aliases to their namespace-qualified equivalents
        foreach ($aliasResolution as $alias => $qualified) {
            $lastDot = strrpos($qualified, '.');
            $targetNs = $lastDot !== false ? substr($qualified, 0, $lastDot) : '';
            // Use bare name when the target namespace is the current (skip) namespace
            $replacement = ($targetNs === $skipNamespace)
                ? substr($qualified, $lastDot + 1)
                : $qualified;
            $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($alias, '/').'(?![A-Za-z0-9_$])/';
            $typeStr = preg_replace($pattern, $replacement, $typeStr) ?? $typeStr;
        }

        // Pass 2: qualify any remaining bare type names with their namespace.
        // Skip names that also exist in the skip namespace — those bare references
        // belong to the current context and must not be re-qualified with another namespace.
        /** @var list<string> $skipTypeNames */
        $skipTypeNames = $namespacedTypes[$skipNamespace] ?? [];

        foreach ($namespacedTypes as $namespace => $typeNames) {
            if ($namespace === $skipNamespace) {
                continue;
            }

            // Match longer names first to avoid partial replacements (e.g. 'StatusType' before 'Status')
            usort($typeNames, fn (string $a, string $b): int => strlen($b) - strlen($a));

            foreach ($typeNames as $typeName) {
                if (in_array($typeName, $skipTypeNames, true)) {
                    continue;
                }

                $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($typeName, '/').'(?![A-Za-z0-9_$])/';
                $typeStr = preg_replace($pattern, $namespace.'.'.$typeName, $typeStr) ?? $typeStr;
            }
        }

        return $typeStr;
    }

    /**
     * Replace `AsEnum<typeof ConstAlias>` patterns with the pre-computed type alias.
     *
     * Used when rendering resource properties in the globals file, where `typeof namespace.Member`
     * is illegal — namespace members are type-only (interfaces), not runtime values.
     *
     * @param  string  $typeStr  The TypeScript type string to rewrite.
     * @param  array<string, string>  $constToTypeMap  constAlias => 'namespace.TypeName'
     */
    public function rewriteAsEnumToType(string $typeStr, array $constToTypeMap): string
    {
        foreach ($constToTypeMap as $constAlias => $qualifiedTypeName) {
            $pattern = '/AsEnum<typeof\s+'.preg_quote($constAlias, '/').'\s*>/';
            $typeStr = preg_replace($pattern, $qualifiedTypeName, $typeStr) ?? $typeStr;
        }

        return $typeStr;
    }

    /**
     * Sanitize a string for safe inclusion in a JSDoc comment.
     *
     * Prevents premature comment termination by escaping the closing sequence.
     */
    public function sanitizeJsDoc(string $text): string
    {
        return str_replace('*/', '*\/', $text);
    }

    /**
     * Extract the human-readable description from a PHPDoc block,
     * ignoring all @-prefixed tags (@param, @return, @phpstan-*, etc.).
     */
    public function parseDocBlockDescription(string|false $docComment): string
    {
        if ($docComment === false || $docComment === '') {
            return '';
        }

        $lines = explode("\n", $docComment);
        $description = [];
        $inTag = false;

        foreach ($lines as $line) {
            // Strip leading whitespace, asterisks, and the opening/closing markers
            $cleaned = preg_replace('#^\s*/?\*+/?\s?#', '', $line) ?? '';
            $cleaned = preg_replace('#\s*\*+/\s*$#', '', $cleaned) ?? '';
            $trimmed = trim($cleaned);

            // Skip empty remnants from /** and */
            if ($trimmed === '' || $trimmed === '/') {
                $inTag = false;

                continue;
            }

            // Skip @-tag lines and mark as inside a (possibly multi-line) tag
            if (str_starts_with($trimmed, '@')) {
                $inTag = true;

                continue;
            }

            // Skip continuation lines of multi-line @-tags
            if ($inTag) {
                continue;
            }

            // Strip inline tags like {@inheritdoc}, {@see ...}, {@link ...}
            $trimmed = trim((string) preg_replace('/\s*\{@[^}]+\}\s*/', ' ', $trimmed));

            if ($trimmed === '') {
                continue;
            }

            $description[] = $trimmed;
        }

        return implode(' ', $description);
    }

    /**
     * Serialize a list of route arg metadata objects to a JavaScript array literal.
     *
     * Each entry is output as an inline object with only the fields that are present,
     * so that the generated TypeScript stays compact (no `undefined` noise).
     *
     * @param  list<array{name: string, required: bool, _routeKey?: string, _enumValues?: list<string|int>, where?: string}>  $args
     */
    public function routeArgsToJs(array $args): string
    {
        $entries = [];

        foreach ($args as $arg) {
            $parts = [];
            $parts[] = 'name: '.$this->toJsLiteral($arg['name']);
            $parts[] = 'required: '.$this->toJsLiteral($arg['required']);

            if (isset($arg['_routeKey'])) {
                $parts[] = '_routeKey: '.$this->toJsLiteral($arg['_routeKey']);
            }

            if (isset($arg['_enumValues'])) {
                $parts[] = '_enumValues: '.$this->toJsLiteral($arg['_enumValues']);
            }

            if (isset($arg['where'])) {
                $parts[] = 'where: '.$this->toJsLiteral($arg['where']);
            }

            $entries[] = '{'.implode(', ', $parts).'}';
        }

        return '['.implode(', ', $entries).']';
    }

    /**
     * Resolve the fully-qualified class name from a PHP file path.
     *
     * Returns null if the file does not exist or does not contain a class/enum declaration.
     */
    public function resolveClassFromFile(string $filePath): ?string
    {
        $absolutePath = str_starts_with($filePath, DIRECTORY_SEPARATOR)
            ? $filePath
            : base_path($filePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $classes = PhpFileParser::findClasses($absolutePath);

        return $classes[0] ?? null;
    }
}

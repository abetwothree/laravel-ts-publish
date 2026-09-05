<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use BackedEnum;
use Closure;
use Composer\ClassMapGenerator\PhpFileParser;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonSerializable;
use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
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
 *    enumFqcns: list<class-string>,
 *    classFqcns: list<class-string>,
 *    omit?: bool,
 * }
 *
 * `enums` holds PHP enum const names (display only); `enumTypes` holds the TS alias names emitted in imports.
 * `omit`, when true, signals a property that resolved to nothing useful and should be dropped from
 * generated output entirely rather than emitted as `unknown` (see omittedTypeScriptInfo()).
 */
class LaravelTsPublish
{
    protected static ?Closure $callCommandWith = null;

    /**
     * "{FQCN}::{method}" keys currently being expanded by arrayableShapeType(), as a recursion guard.
     *
     * @var array<string, true>
     */
    protected array $shapeExpansionStack = [];

    /**
     * Per-class cache of resolved phpstan/psalm type aliases: FQCN => alias name => resolved
     * definition, or null when the class carries no such alias (cached to skip re-parsing its docblock).
     *
     * @var array<string, array<string, array{definition: string, class: ReflectionClass<object>}|null>>
     */
    protected array $phpstanTypeAliasCache = [];

    /**
     * Per-class cache of published resource interface names: FQCN => #[TsResource(name:)] or basename.
     *
     * @var array<string, string>
     */
    protected array $resourceTypeNames = [];

    /**
     * Per-file cache of resolved `use`-statement short-name → FQCN maps, keyed by file path.
     *
     * @var array<string, array<string, class-string>>
     */
    protected array $useStatementsCache = [];

    /** @var list<string> */
    private const array RESERVED_JS_IDENTIFIERS = [
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
     * Resolve an absolute file path to a project-root-relative path, or a vendor-relative one.
     */
    public static function resolveRelativePath(string $absolutePath): string
    {
        $basePath = base_path().DIRECTORY_SEPARATOR;

        if (str_starts_with($absolutePath, $basePath)) {
            return Str::after($absolutePath, $basePath);
        }

        // Outside base_path(), e.g. vendor in a package development context
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
     * Resolve the nullability strategy for a relation type, given a short class name or a FQCN.
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
     * Resolve the TypeScript type for a given PHP type.
     *
     * The numbered steps below are ordered deliberately: every class-shaped resolution runs before the
     * partial map match, which would otherwise match a substring like "int" inside a class name.
     *
     * @return TypeScriptTypeInfo
     */
    public function toTsType(string $phpType): array
    {
        $typesMap = $this->typesMap(); // keys are already lowercased
        $lower = strtolower($phpType);
        $result = $this->emptyTypeScriptInfo();

        // 0. Nullable shorthand ?T
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

        // 1a. Bare-name fallback: a sized native DB type ("varchar(255)", "decimal(10,2)",
        //     "tinyint(4)") has no exact entry, but the name before '(' usually does. A literal
        //     parameterized key like "tinyint(1)" is still caught by step 1 above, so it can't be shadowed.
        $parenPos = strpos($lower, '(');

        if ($parenPos !== false && $parenPos > 0) {
            $bareMapping = $typesMap[substr($lower, 0, $parenPos)] ?? null;

            if ($bareMapping !== null) {
                $result['type'] = is_string($bareMapping) ? $bareMapping : $bareMapping();

                return $result;
            }
        }

        // 1b. Castable-with-arguments cast strings: "CastClass:arg1,arg2". The head must be
        //     an existing class so DB strings like "decimal:2" never enter this branch.
        $colonPos = strpos($phpType, ':');

        if ($colonPos !== false) {
            $head = substr($phpType, 0, $colonPos);
            $args = array_map(trim(...), explode(',', substr($phpType, $colonPos + 1)));

            if (class_exists($head)) {
                return $this->resolveCastWithArguments($head, $args, $result);
            }
        }

        // 2. #[TsType] explicit override, ahead of every automatic resolution
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

        // 3. PHP enum, before the partial match so "App\Enums\Status" can't hit the "enum" => "string" entry
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

        // 5a. Arrayable (non-Model) → object shape from toArray(). Model implements Arrayable
        //     transitively, so Model subclasses are excluded and left for step 5.
        if (class_exists($phpType)
            && ! is_a($phpType, Model::class, true)
            && is_a($phpType, Arrayable::class, true)
        ) {
            $shapeType = $this->arrayableShapeType($phpType, 'toArray');

            $result['type'] = $shapeType ?? 'unknown[]';

            return $result;
        }

        // 5a-bis. JsonSerializable (non-Model, non-Arrayable) → object shape from jsonSerialize().
        //     No shape falls through rather than forcing unknown[]: unlike Arrayable, a bare
        //     JsonSerializable isn't guaranteed to be array-shaped. Property inference is withheld
        //     for the same reason — jsonSerialize() carries no contract tying its return value to
        //     the object's own properties, unlike toArray()'s (array) $this convention.
        if (class_exists($phpType)
            && ! is_a($phpType, Model::class, true)
            && is_a($phpType, JsonSerializable::class, true)
        ) {
            $shapeType = $this->arrayableShapeType($phpType, 'jsonSerialize', fallbackToProperties: false);

            if ($shapeType !== null) {
                $result['type'] = $shapeType;

                return $result;
            }
        }

        // 5b. __toString → string. Models are excluded: Model::__toString() returns JSON.
        if (class_exists($phpType)
            && ! is_a($phpType, Model::class, true)
            && method_exists($phpType, '__toString')
        ) {
            $result['type'] = 'string';

            return $result;
        }

        // 5c. Plain class with typed public properties → inline them; the motivating case lacks a published file,
        //     though publication is a real axis 5c never tests. Approximates json_encode(), which drops uninitialized
        //     ones. JsonSerializable overrides that default; Model is subsumed by it, kept for 5a/5a-bis/5b symmetry.
        if (class_exists($phpType)
            && ! is_a($phpType, Model::class, true)
            && ! is_a($phpType, JsonSerializable::class, true)
            && $this->hasFullyTypedPublicProperties($phpType)
        ) {
            $shapeType = $this->publicPropertyShapeType($phpType);

            if ($shapeType !== null) {
                $result['type'] = $shapeType;

                return $result;
            }
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

        // 7. Partial map match (e.g. "tinyint(1)" contains "tinyint"). Class-like names are excluded —
        //    "Point" contains "int", "Update" contains "date" — which would emit a plausible wrong scalar.
        if (! $this->looksLikeClassName($phpType)) {
            foreach ($typesMap as $key => $value) {
                if (str_contains($lower, $key)) {
                    $result['type'] = is_string($value) ? $value : $value();

                    return $result;
                }
            }
        }

        $result['type'] = 'unknown';

        return $result;
    }

    /**
     * Whether a type string looks like a class name rather than a database or cast type string.
     *
     * Only the head — before the first '(', ':' or whitespace — is examined, because Laravel cast
     * parameters legitimately carry uppercase ("date:Y-m-d") while their type name never does.
     */
    protected function looksLikeClassName(string $phpType): bool
    {
        // PREG_SPLIT_NO_EMPTY: a leading ' ', '(' or ':' would otherwise make the head an empty
        // segment, letting ' Point' or '(Point)' through the gate into step 7's partial match.
        $head = preg_split('/[(:\s]/', $phpType, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';

        return $head !== ''
            && (preg_match('/[A-Z]/', $head) === 1 || str_contains($head, '\\'));
    }

    /**
     * Resolve a "CastClass:args" cast string. AsEnumCollection carries its enum;
     * AsCollection resolves its mapped element to an inline shape when possible;
     * other cast classes resolve as the bare class (args stripped).
     *
     * @param  list<string>  $args
     * @param  TypeScriptTypeInfo  $result
     * @return TypeScriptTypeInfo
     */
    protected function resolveCastWithArguments(string $head, array $args, array $result): array
    {
        if (is_a($head, AsEnumCollection::class, true) && ($args[0] ?? '') !== '' && enum_exists($args[0])) {
            $enumInfo = $this->toTsType($args[0]);
            $enumInfo['type'] .= '[]';

            return $enumInfo;
        }

        if (is_a($head, AsCollection::class, true)) {
            // AsCollection::using($collection, $map) => "AsCollection:$collection,$map"
            $mapClass = $args[1] ?? '';

            if ($mapClass !== '' && class_exists($mapClass)) {
                $elementInfo = $this->toTsType($mapClass);

                if (str_starts_with($elementInfo['type'], '{')) {
                    $elementInfo['type'] .= '[]';

                    return $elementInfo;
                }

                if ($elementInfo['enumFqcns'] !== []) {
                    $elementInfo['type'] .= '[]';

                    return $elementInfo;
                }
            }

            $result['type'] = 'unknown[]';

            return $result;
        }

        // Any other cast class — strip the arguments and resolve the class itself.
        return $this->toTsType($head);
    }

    /**
     * Build an inline TS object type from a method's `@return array{...}` shape, or from the
     * class's typed public properties when $fallbackToProperties is true and no shape docblock
     * exists, or null when neither yields anything.
     *
     * @param  class-string  $fqcn
     */
    protected function arrayableShapeType(string $fqcn, string $method, bool $fallbackToProperties = true): ?string
    {
        if (! method_exists($fqcn, $method)) {
            return null; // @codeCoverageIgnore
        }

        $key = $fqcn.'::'.$method;

        // A DTO documented `array{child: self}` — or a mutual pair — otherwise recurses until memory is
        // exhausted, aborting the whole publish run with no indication of which class caused it.
        if (isset($this->shapeExpansionStack[$key])) {
            return null;
        }

        $this->shapeExpansionStack[$key] = true;

        try {
            $shape = $this->parseDocblockReturnArrayShape(new ReflectionMethod($fqcn, $method));
        } finally {
            unset($this->shapeExpansionStack[$key]);
        }

        if ($shape === []) {
            return $fallbackToProperties ? $this->publicPropertyShapeType($fqcn) : null;
        }

        $parts = [];

        foreach ($shape as $key => $type) {
            // The shape map is string-only, so a class- or enum-backed value carries no FQCN and could
            // never emit an import — degrade that property rather than emit a token nothing imports.
            if ($this->shapeValueHasUnimportableToken($type)) {
                $type = 'unknown';
            }

            $parts[] = $key.': '.$type;
        }

        return '{ '.implode('; ', $parts).' }';
    }

    /**
     * Build an inline TS object shape from a class's public, non-static properties — the fallback for
     * an Arrayable/JsonSerializable DTO with no `@return array{...}` shape.
     *
     * Reuses $shapeExpansionStack under a `::__properties` key to guard against infinite recursion.
     *
     * @param  class-string  $fqcn
     */
    protected function publicPropertyShapeType(string $fqcn): ?string
    {
        $guard = $fqcn.'::__properties';

        if (isset($this->shapeExpansionStack[$guard])) {
            return null;
        }

        $this->shapeExpansionStack[$guard] = true;

        try {
            $reflection = new ReflectionClass($fqcn);
            $parts = [];

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $type = $this->propertyTypes($reflection, $property->getName())['type'];

                if ($this->shapeValueHasUnimportableToken($type)) {
                    $type = 'unknown';
                }

                $parts[] = $property->getName().': '.$type;
            }
        } finally {
            unset($this->shapeExpansionStack[$guard]);
        }

        return $parts === [] ? null : '{ '.implode('; ', $parts).' }';
    }

    /**
     * Whether a class declares at least one public, non-static property and every one of them is typed.
     *
     * Guards step 5c: an untyped property would inline as `unknown`, which is worse than the class token.
     *
     * @param  class-string  $fqcn
     */
    protected function hasFullyTypedPublicProperties(string $fqcn): bool
    {
        $found = false;

        foreach ((new ReflectionClass($fqcn))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (! $property->hasType()) {
                return false;
            }

            $found = true;
        }

        return $found;
    }

    /**
     * Whether a resolved shape value contains an identifier that would need an import to be valid.
     *
     * extractImportableTypes() can't be reused: it skips '<'/'{' content, which docblock shapes routinely have.
     * Object-literal keys are stripped first so 'owner' in '{ owner: User }' isn't read as a value token.
     */
    public function shapeValueHasUnimportableToken(string $type): bool
    {
        $withoutKeys = (string) preg_replace('/\b\w+\s*:/', '', $type);

        $tokens = preg_split('/[<>{}()|,;\[\]\s]+/', $withoutKeys, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (in_array($token, self::TS_PRIMITIVES, true)) {
                continue;
            }

            if (in_array($token, ['Record', 'Date', 'true', 'false'], true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Resolve a phpstan/psalm type alias visible from a class: a local `@phpstan-type` definition
     * first, then a `@phpstan-import-type Name from Class [as Alias]` (recursively, cycle-guarded
     * via $seen — the same "already expanding" guard arrayableShapeType() uses for shape recursion).
     *
     * @param  ReflectionClass<object>  $context
     * @param  array<string, true>  $seen
     * @return array{definition: string, class: ReflectionClass<object>}|null
     */
    public function resolvePhpstanTypeAlias(string $name, ReflectionClass $context, array $seen = []): ?array
    {
        $key = $context->getName();
        $seenKey = $key.'::'.$name;

        if (isset($seen[$seenKey])) {
            return null;
        }

        $seen[$seenKey] = true;

        if (array_key_exists($name, $this->phpstanTypeAliasCache[$key] ?? [])) {
            return $this->phpstanTypeAliasCache[$key][$name];
        }

        $doc = $context->getDocComment();
        $resolved = null;

        if ($doc !== false) {
            if (preg_match(
                '/@(?:phpstan|psalm)-type\s+'.preg_quote($name, '/').'\b(?=[\s=])\s*=?\s*(\S.*)$/m',
                $doc,
                $m,
                PREG_OFFSET_CAPTURE,
            )) {
                $definition = $this->balanceTypeDefinition($m[1][0], $doc, $m[1][1] + strlen($m[1][0]));
                $resolved = ['definition' => $definition, 'class' => $context];
            }

            if ($resolved === null && preg_match_all(
                '/@(?:phpstan|psalm)-import-type\s+(\w+)\s+from\s+([\w\\\\]+)(?:\s+as\s+(\w+))?/m',
                $doc,
                $imports,
                PREG_SET_ORDER,
            )) {
                foreach ($imports as $import) {
                    $localName = $import[3] ?? $import[1];

                    if ($localName !== $name) {
                        continue;
                    }

                    $sourceClass = $this->resolveDocblockTypeName(
                        $import[2],
                        $this->parseFileUseStatements($context),
                        $context->getNamespaceName(),
                    );

                    if (class_exists($sourceClass)) {
                        $resolved = $this->resolvePhpstanTypeAlias($import[1], new ReflectionClass($sourceClass), $seen);
                    }

                    break;
                }
            }
        }

        return $this->phpstanTypeAliasCache[$key][$name] = $resolved;
    }

    /**
     * Extend a single-line `@phpstan-type`/`@psalm-type` capture across further docblock lines when
     * its `{}`/`<>` depth is unbalanced — a multi-line `array{...}` shape — stripping each line's
     * leading `*` comment marker as it goes. Mirrors extractReturnTypeFromDocblock()'s depth-aware walk.
     */
    protected function balanceTypeDefinition(string $firstLine, string $doc, int $offsetAfterFirstLine): string
    {
        $definition = trim(rtrim($firstLine, " \t*/"));
        $depth = $this->braceAngleDepth($definition);

        if ($depth <= 0) {
            return $definition;
        }

        foreach (explode("\n", substr($doc, $offsetAfterFirstLine)) as $line) {
            $stripped = trim((string) preg_replace('#^\s*\*+/?\s?#', '', $line));

            if ($stripped === '') {
                continue;
            }

            $stripped = rtrim($stripped, " \t*/");
            $definition .= ' '.$stripped;
            $depth += $this->braceAngleDepth($stripped);

            if ($depth <= 0) {
                break;
            }
        }

        return trim($definition);
    }

    /**
     * Net `{`/`<` nesting depth added by a string, used to detect an unbalanced type capture.
     */
    protected function braceAngleDepth(string $s): int
    {
        return substr_count($s, '{') + substr_count($s, '<') - substr_count($s, '}') - substr_count($s, '>');
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
        $signatureInfo = $this->resolveReflectionType($reflectionMethod->getReturnType());

        if ($signatureInfo['type'] !== 'unknown' && ! $this->isVagueTsType($signatureInfo['type'])) {
            return $signatureInfo;
        }

        // A vague native signature (: array, : iterable, ...) carries no element information, so a
        // docblock shape like `@return array{value: int, label: string}` can usually do better.
        $docblockInfo = $this->docblockReturnTypes($reflectionMethod);

        if ($docblockInfo['type'] !== 'unknown' && ! $this->isVagueTsType($docblockInfo['type'])) {
            return $docblockInfo;
        }

        return $signatureInfo['type'] !== 'unknown' ? $signatureInfo : $docblockInfo;
    }

    /**
     * A "vague" TS type carries no element information, so a docblock generic can usually do better.
     *
     * An object-literal shape is never vague even when a key resolves to 'unknown' — a bare 'unknown'
     * substring only signals vagueness outside `{...}`, where no per-key structure exists.
     */
    public function isVagueTsType(string $type): bool
    {
        return $type === 'object' || (str_contains($type, 'unknown') && ! str_contains($type, '{'));
    }

    /** @return TypeScriptTypeInfo */
    public function closureReturnedTypes(Closure $closure): array
    {
        return $this->resolveReflectionType(new ReflectionFunction($closure)->getReturnType());
    }

    /**
     * Resolve a PHP function name (built-in or userland global) to its TypeScript return type.
     *
     * Only all-builtin scalar return types are mapped: toTsType()'s partial matching would turn a class
     * like `Carbon\CarbonInterface` into `number` here, with no import channel to attach to it.
     *
     * @return TypeScriptTypeInfo
     */
    public function nativePhpFunctionReturnedTypes(string $name): array
    {
        /** @var array<string, TypeScriptTypeInfo> $cache */
        static $cache = [];

        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }

        $result = $this->emptyTypeScriptInfo();

        try {
            $rf = new ReflectionFunction($name);
        } catch (ReflectionException) {
            return $cache[$name] = $result;
        }

        $returnType = $rf->getReturnType();

        if ($returnType === null) {
            return $cache[$name] = $result;
        }

        if ($returnType instanceof ReflectionNamedType && ! $returnType->isBuiltin()) {
            return $cache[$name] = $result;
        }

        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    return $cache[$name] = $result;
                }
            }
        }

        return $cache[$name] = $this->resolveReflectionType($returnType);
    }

    /** @return TypeScriptTypeInfo */
    public function resolveReflectionType(?ReflectionType $returnType): array
    {
        $result = $this->emptyTypeScriptInfo();

        if ($returnType instanceof ReflectionNamedType) {
            $result = $this->toTsType($returnType->getName());

            if ($returnType->allowsNull() && $returnType->getName() !== 'null') {
                $result['type'] .= ' | null';
            }

            return $result;
        }

        // Intersection types (Countable&Iterator) have no meaningful TS equivalent
        if ($returnType instanceof ReflectionIntersectionType) {
            return $result;
        }

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
     * The ReflectionClass whose file actually declares a method's body.
     *
     * PHP reflection quirk: for a trait-provided method, getDeclaringClass() reports the consuming
     * class, not the trait, even though getFileName()/getDocComment() still read from the trait.
     *
     * @return ReflectionClass<object>
     */
    public function methodDeclaringFileClass(ReflectionMethod $method): ReflectionClass
    {
        $declaringClass = $method->getDeclaringClass();
        $methodFile = $method->getFileName();

        if ($methodFile === false || $methodFile === $declaringClass->getFileName()) {
            return $declaringClass;
        }

        foreach ($this->flattenTraits($declaringClass) as $trait) {
            if ($trait->getFileName() === $methodFile) {
                return $trait;
            }
        }

        return $declaringClass; // @codeCoverageIgnore
    }

    /**
     * Every trait used by a class, including traits used by those traits.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    protected function flattenTraits(ReflectionClass $class): array
    {
        $traits = [];

        foreach ($class->getTraits() as $trait) {
            $traits[] = $trait;
            array_push($traits, ...$this->flattenTraits($trait));
        }

        return $traits;
    }

    /**
     * Resolve a full PHPDoc type string (splitting a top-level union) against an explicit class
     * context, trying a phpstan/psalm type alias for each union member first — the shared tail of
     * docblockReturnTypes() and resolveDocblockTypeString().
     *
     * @param  ReflectionClass<object>  $contextClass
     * @return TypeScriptTypeInfo
     */
    protected function resolveDocblockTypeStringAgainst(string $typeString, ReflectionClass $contextClass): array
    {
        $useMap = $this->parseFileUseStatements($contextClass);
        $namespace = $contextClass->getNamespaceName();

        $infos = [];

        foreach ($this->splitPhpDocUnionType($typeString) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue; // @codeCoverageIgnore
            }

            $infos[] = $this->resolveDocblockTypePartOrAlias($part, $useMap, $namespace, $contextClass);
        }

        if ($infos === []) {
            return $this->emptyTypeScriptInfo(); // @codeCoverageIgnore
        }

        return count($infos) === 1 ? $infos[0] : $this->mergeTypeScriptInfos($infos);
    }

    /**
     * Parse a method's `@return` docblock — including multiline `array{...}` shapes — into type info,
     * resolving short class names through the declaring file's use statements.
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

        return $this->resolveDocblockTypeStringAgainst($returnTypeString, $this->methodDeclaringFileClass($method));
    }

    /**
     * Parse `Attribute<Getter, Setter>` from a method's return docblock and resolve the getter type.
     *
     * Both the short and fully-qualified class forms are matched. A bare `Attribute` with no generic
     * args degrades to empty so the caller falls back to the closure's own signature type.
     *
     * @return TypeScriptTypeInfo
     */
    public function attributeDocblockReturnTypes(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return $this->emptyTypeScriptInfo();
        }

        $returnTypeString = $this->extractReturnTypeFromDocblock($docComment);

        if ($returnTypeString !== null
            && preg_match('/^\\\\?(?:Illuminate\\\\Database\\\\Eloquent\\\\Casts\\\\)?Attribute\s*<(.+)>$/s', trim($returnTypeString), $m)
        ) {
            $genericArgs = $this->splitAtTopLevelCommas(trim($m[1]));
            $getterType = trim($genericArgs[0] ?? '');

            if ($getterType !== '') {
                return $this->resolveDocblockTypeString($method, $getterType);
            }
        }

        $result = $this->docblockReturnTypes($method);

        if ($result['classFqcns'] === [Attribute::class]) {
            return $this->emptyTypeScriptInfo();
        }

        return $result;
    }

    /**
     * Resolve a docblock type string (potentially a union like `string|null`)
     * through use-statement resolution and toTsType.
     *
     * @return TypeScriptTypeInfo
     */
    protected function resolveDocblockTypeString(ReflectionMethod $method, string $typeString): array
    {
        return $this->resolveDocblockTypeStringAgainst($typeString, $this->methodDeclaringFileClass($method));
    }

    /**
     * Resolve one union member of a docblock type, trying a @phpstan-type/@phpstan-import-type alias first.
     *
     * A hit resolves via the *plain* pipeline, deliberately not alias-aware again, to avoid two purely-local
     * aliases naming each other recursing unguarded — the cycle guard only covers the @phpstan-import-type chain.
     *
     * @param  array<string, string>  $useMap
     * @param  ReflectionClass<object>  $contextClass
     * @return TypeScriptTypeInfo
     */
    public function resolveDocblockTypePartOrAlias(string $part, array $useMap, string $namespace, ReflectionClass $contextClass): array
    {
        $alias = $this->resolvePhpstanTypeAlias($part, $contextClass);

        if ($alias === null) {
            return $this->resolveDocblockTypePart($part, $useMap, $namespace);
        }

        return $this->resolveDocblockPartToInfo(
            $alias['definition'],
            $this->parseFileUseStatements($alias['class']),
            $alias['class']->getNamespaceName(),
        );
    }

    /**
     * Resolve an alias's raw definition string (a union, generic container, array shape, or plain
     * name) through the ordinary docblock pipeline — not alias-aware itself — against the use-map
     * and namespace of the class that actually wrote the definition.
     *
     * @param  array<string, string>  $useMap
     * @return TypeScriptTypeInfo
     */
    protected function resolveDocblockPartToInfo(string $definition, array $useMap, string $namespace): array
    {
        $infos = [];

        foreach ($this->splitPhpDocUnionType($definition) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue; // @codeCoverageIgnore
            }

            $infos[] = $this->resolveDocblockTypePart($part, $useMap, $namespace);
        }

        if ($infos === []) {
            return $this->emptyTypeScriptInfo(); // @codeCoverageIgnore
        }

        return count($infos) === 1 ? $infos[0] : $this->mergeTypeScriptInfos($infos);
    }

    /**
     * Resolve one member of a PHPDoc type union: generic containers first, then plain names.
     *
     * A part still containing '<' is an unrecognized generic — degrade it to 'unknown' rather than let
     * toTsType()'s partial string matching resolve the 'int' inside it to 'number'.
     *
     * @param  array<string, string>  $useMap
     * @return TypeScriptTypeInfo
     */
    public function resolveDocblockTypePart(string $part, array $useMap, string $namespace): array
    {
        $generic = $this->resolveGenericContainerType($part, $useMap, $namespace);

        if ($generic !== null) {
            return $generic;
        }

        if (str_starts_with($part, 'array{')) {
            $shapeType = $this->resolveArrayShapeString($part, $useMap, $namespace);

            if ($shapeType !== null) {
                return [...$this->emptyTypeScriptInfo(), 'type' => $shapeType];
            }
        }

        $resolved = $this->resolveDocblockTypeName($part, $useMap, $namespace);

        return str_contains($resolved, '<')
            ? $this->emptyTypeScriptInfo()
            : $this->toTsType($resolved);
    }

    /**
     * Resolve a PHPDoc generic container type (Collection<int, X>, array<string, X>,
     * list<X>, X[]) to a TypeScriptTypeInfo. Returns null when $type is not a
     * recognized container so callers can fall through to toTsType().
     *
     * @param  array<string, string>  $useMap
     * @return TypeScriptTypeInfo|null
     */
    public function resolveGenericContainerType(string $type, array $useMap, string $namespace): ?array
    {
        $type = trim($type);

        // Nullable-prefixed generic (?array<int, X>, ?Collection<int, X>) — strip, resolve, reattach.
        // Mirrors toTsType()'s step 0 for the non-generic case.
        if (str_starts_with($type, '?')) {
            $inner = $this->resolveGenericContainerType(substr($type, 1), $useMap, $namespace);

            if ($inner === null) {
                return null;
            }

            if (! str_contains($inner['type'], 'null')) {
                $inner['type'] .= ' | null';
            }

            return $inner;
        }

        // X[] shorthand (but not TS-style unions — split upstream)
        if (str_ends_with($type, '[]')) {
            $inner = $this->resolveDocblockContainerValue(substr($type, 0, -2), $useMap, $namespace);

            return $this->wrapAsArray($inner);
        }

        if (! preg_match('/^([\w\\\\]+)\s*<(.+)>$/s', $type, $m)) {
            return null;
        }

        $container = $this->resolveDocblockTypeName($m[1], $useMap, $namespace);
        $isList = strtolower($m[1]) === 'list';
        $isArray = strtolower($m[1]) === 'array' || strtolower($m[1]) === 'iterable';
        $isCollection = is_a($container, Collection::class, true);

        if (! $isList && ! $isArray && ! $isCollection) {
            return null;
        }

        $args = array_map('trim', $this->splitAtTopLevelCommas($m[2]));
        $keyType = count($args) === 2 ? strtolower($args[0]) : 'int';
        $valueTypeString = count($args) === 2 ? $args[1] : $args[0];

        $inner = $this->resolveDocblockContainerValue($valueTypeString, $useMap, $namespace);

        if ($keyType === 'string') {
            $inner['type'] = 'Record<string, '.$inner['type'].'>';

            return $inner;
        }

        if ($keyType === 'array-key' || $keyType === 'mixed') {
            return $this->wrapAsMaybeKeyedArray($inner);
        }

        return $this->wrapAsArray($inner);
    }

    /**
     * Resolve a container's value type — recurse for nested containers,
     * otherwise resolve through the normal docblock pipeline.
     *
     * @param  array<string, string>  $useMap
     * @return TypeScriptTypeInfo
     */
    protected function resolveDocblockContainerValue(string $valueType, array $useMap, string $namespace): array
    {
        $nested = $this->resolveGenericContainerType($valueType, $useMap, $namespace);

        if ($nested !== null) {
            return $nested;
        }

        if (str_starts_with(trim($valueType), 'array{')) {
            $shapeType = $this->resolveArrayShapeString(trim($valueType), $useMap, $namespace);

            if ($shapeType !== null) {
                return [...$this->emptyTypeScriptInfo(), 'type' => $shapeType];
            }
        }

        // Unions inside the value slot (e.g. Collection<int, A|B>)
        $parts = $this->splitPhpDocUnionType($valueType);
        $infos = [];

        foreach ($parts as $part) {
            $resolved = $this->resolveDocblockTypeName(trim($part), $useMap, $namespace);
            $infos[] = $this->toTsType($resolved);
        }

        return count($infos) === 1 ? $infos[0] : $this->mergeTypeScriptInfos($infos);
    }

    /**
     * Wrap a value type for a container whose key sequentiality is not guaranteed: `X[] | Record<string, X>`.
     *
     * @param  TypeScriptTypeInfo  $info
     * @return TypeScriptTypeInfo
     */
    protected function wrapAsMaybeKeyedArray(array $info): array
    {
        $record = 'Record<string, '.$info['type'].'>';
        $info = $this->wrapAsArray($info);
        $info['type'] .= ' | '.$record;

        return $info;
    }

    /** @param TypeScriptTypeInfo $info
     * @return TypeScriptTypeInfo */
    protected function wrapAsArray(array $info): array
    {
        $info['type'] = str_contains($info['type'], '|')
            ? '('.$info['type'].')[]'
            : $info['type'].'[]';

        return $info;
    }

    /**
     * Resolve a docblock type name to a FQCN using the file's use statements and namespace.
     *
     * @param  array<string, string>  $useMap
     */
    public function resolveDocblockTypeName(string $type, array $useMap, string $namespace): string
    {
        if (str_starts_with($type, '?')) {
            return '?'.$this->resolveDocblockTypeName(substr($type, 1), $useMap, $namespace);
        }

        if (str_starts_with($type, '\\')) {
            return substr($type, 1); // @codeCoverageIgnore
        }

        $root = Str::before($type, '\\');

        if (isset($useMap[$root])) {
            $rest = Str::after($type, '\\');

            return $rest !== $type ? $useMap[$root].'\\'.$rest : $useMap[$root];
        }

        if ($namespace !== '') { // @codeCoverageIgnoreStart
            $qualified = $namespace.'\\'.$type;

            if (class_exists($qualified) || enum_exists($qualified)) {
                return $qualified;
            }
        } // @codeCoverageIgnoreEnd

        return $type;
    }

    /**
     * Extract the complete return type string from a docblock, including multiline `array{...}` shapes.
     *
     * Returns null when no `@return`, `@phpstan-return`, or `@psalm-return` tag is found.
     */
    public function extractReturnTypeFromDocblock(string $docComment): ?string
    {
        // The "\n" join is significant: it lets the tag search below anchor to line starts, so a tag
        // named mid-sentence — "The @return value..." — isn't mistaken for the real tag.
        $lines = explode("\n", $docComment);
        $content = '';

        foreach ($lines as $line) {
            $stripped = preg_replace('#^\s*/?\*+\s?#', '', $line) ?? '';
            $stripped = preg_replace('#\s*\*+/$#', '', $stripped);
            $content .= "\n".$stripped;
        }

        $content = trim($content);

        // The negative lookbehind stops '@return' from matching inside '@phpstan-return'
        $tagPattern = null;
        foreach (['@return', '@phpstan-return', '@psalm-return'] as $tag) {
            if (preg_match('/^\s*(?<![\w-])'.preg_quote($tag, '/').'\s+/m', $content, $match, PREG_OFFSET_CAPTURE)) {
                $tagPattern = $match;

                break;
            }
        }

        if ($tagPattern === null) {
            return null;
        }

        $start = (int) $tagPattern[0][1] + strlen((string) $tagPattern[0][0]);
        $rest = trim(substr($content, $start));

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

        if (preg_match('/^[\w\\\\?]+\s*</', $rest)) {
            $depth = 0;
            $end = 0;

            for ($i = 0; $i < strlen($rest); $i++) {
                if ($rest[$i] === '<') {
                    $depth++;
                } elseif ($rest[$i] === '>') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $i + 1;

                        break;
                    }
                }
            }

            if ($end > 0) {
                // Trailing union, mirroring the array{...} case so `Collection<int, X>|null` keeps its `|null`
                $after = substr($rest, $end);
                $afterTrimmed = ltrim($after);

                if (preg_match('/^(\s*\|\s*[^\s|@]+)+/', $afterTrimmed, $trailingMatch)) {
                    $trailingNormalized = (string) preg_replace('/\s*\|\s*/', '|', $trailingMatch[0]);

                    return $this->normalizeDocblockWhitespace(substr($rest, 0, $end).$trailingNormalized);
                }

                return $this->normalizeDocblockWhitespace(substr($rest, 0, $end));
            }
        }

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
     * Parse a method's `@return array{...}` docblock into a map of property name → TypeScript type.
     *
     * Only top-level shapes are handled; anything else yields an empty array.
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

        $declaringClass = $this->methodDeclaringFileClass($method);
        $useMap = $this->parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

        return $this->parseArrayShapeToTsTypes($returnType, $useMap, $namespace);
    }

    /**
     * Parse a PHPDoc `array{key: type, ...}` shape into a map of property name → TypeScript type,
     * recursing through nested shapes and unions.
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

            // Match 'key: type' or 'key?: type' — the '?' is kept as part of the returned key so
            // callers formatting "$key: $type" emit the optional marker without extra bookkeeping.
            if (preg_match('/^(\w+)(\??)\s*:\s*(.+)$/s', $entry, $m)) {
                $result[$m[1].$m[2]] = $this->resolvePhpDocTypeToTs(trim($m[3]), $useMap, $namespace);
            }
        }

        return $result;
    }

    /**
     * Resolve a PHPDoc `array{...}` shape string to an inline TS object literal, or null when malformed.
     *
     * A per-key value with an unimportable token (a bare class/enum name) degrades to 'unknown': the
     * shape map is string-only, so it can never carry the FQCN an import would need.
     *
     * @param  array<string, string>  $useMap
     */
    protected function resolveArrayShapeString(string $phpType, array $useMap, string $namespace): ?string
    {
        if (! str_starts_with($phpType, 'array{') || ! str_ends_with($phpType, '}')) {
            return null;
        }

        $innerTypes = $this->parseArrayShapeToTsTypes($phpType, $useMap, $namespace);

        if ($innerTypes === []) {
            return 'Record<string, unknown>';
        }

        $parts = [];

        foreach ($innerTypes as $key => $type) {
            if ($this->shapeValueHasUnimportableToken($type)) {
                $type = 'unknown';
            }

            $parts[] = $key.': '.$type;
        }

        return '{ '.implode('; ', $parts).' }';
    }

    /**
     * Resolve a PHPDoc type string (including nested array shapes) to a TypeScript type string.
     *
     * @param  array<string, string>  $useMap
     */
    public function resolvePhpDocTypeToTs(string $phpType, array $useMap, string $namespace): string
    {
        $phpType = trim($phpType);

        // Unions first, so the depth-aware split separates "array{...}" from a trailing "|null"
        $unionParts = $this->splitPhpDocUnionType($phpType);

        if (count($unionParts) > 1) {
            $tsParts = array_map(
                fn (string $part) => $this->resolvePhpDocTypeToTs($part, $useMap, $namespace),
                $unionParts,
            );

            return implode(' | ', $tsParts);
        }

        $generic = $this->resolveGenericContainerType($phpType, $useMap, $namespace);

        if ($generic !== null) {
            return $generic['type'];
        }

        // After the union split this is a pure shape
        if (str_starts_with($phpType, 'array{')) {
            return $this->resolveArrayShapeString($phpType, $useMap, $namespace) ?? 'Record<string, unknown>';
        }

        $resolved = $this->resolveDocblockTypeName($phpType, $useMap, $namespace);

        // A type still containing '<' is an unrecognized generic — degrade it rather than let
        // toTsType()'s partial string matching resolve the 'int' inside it to 'number'.
        if (str_contains($resolved, '<')) {
            return 'unknown';
        }

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
     * Class imports only: `use function` and `use const` (plain or inside a group use)
     * are excluded so they can never leak into docblock type resolution.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $class
     * @return array<string, class-string>
     */
    public function parseFileUseStatements(ReflectionClass $class): array
    {
        $fileName = $class->getFileName();

        if ($fileName === false) {
            return []; // @codeCoverageIgnore
        }

        DependencyRecorder::record($fileName);

        if (isset($this->useStatementsCache[$fileName])) {
            return $this->useStatementsCache[$fileName];
        }

        $stmts = resolve(AstParser::class)->parseFile($fileName);
        $finder = new NodeFinder;
        $map = [];

        foreach ($finder->find($stmts, fn (Node $n) => $n instanceof Use_ || $n instanceof GroupUse) as $node) {
            if ($node instanceof GroupUse) {
                $this->addGroupUseClassImports($node, $map);
            } elseif ($node instanceof Use_) {
                $this->addUseClassImports($node, $map);
            }
        }

        return $this->useStatementsCache[$fileName] = $map;
    }

    /**
     * Add a plain `use` statement's class-import members to the map; function/const imports are skipped.
     *
     * @param  array<string, class-string>  $map
     */
    protected function addUseClassImports(Use_ $use, array &$map): void
    {
        if ($use->type !== Use_::TYPE_NORMAL) {
            return;
        }

        foreach ($use->uses as $item) {
            $alias = $item->alias !== null ? $item->alias->toString() : $item->name->getLast();
            /** @var class-string $fqcn */
            $fqcn = $item->name->toString();
            $map[$alias] = $fqcn;
        }
    }

    /**
     * Add a group `use` statement's class-import members to the map, honoring each item's own type.
     *
     * @param  array<string, class-string>  $map
     */
    protected function addGroupUseClassImports(GroupUse $use, array &$map): void
    {
        $prefix = $use->prefix->toString();

        foreach ($use->uses as $item) {
            $type = $item->type !== Use_::TYPE_UNKNOWN ? $item->type : $use->type;

            if ($type !== Use_::TYPE_NORMAL) {
                continue;
            }

            $alias = $item->alias !== null ? $item->alias->toString() : $item->name->getLast();
            /** @var class-string $fqcn */
            $fqcn = $prefix.'\\'.$item->name->toString();
            $map[$alias] = $fqcn;
        }
    }

    public function validJsObjectKey(string $key): string
    {
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key)) {
            return $key;
        }

        // json_encode produces a properly escaped double-quoted string valid in JS/TS
        return (string) json_encode($key);
    }

    /**
     * Ensure a string is safe as a bare JS/TS identifier ('delete' → 'deleteMethod').
     *
     * Not for object property keys — reserved words are legal there in TS interfaces and literals.
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
     * Unlike Js::from(), this emits readable object/array literals instead of JSON.parse(...) — the
     * output lands in generated .ts files, where XSS-safe encoding is not needed.
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

            if (str_starts_with($part, '{') || str_starts_with($part, '[') || str_contains($part, '<')) {
                continue;
            }

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
     * A TypeScriptTypeInfo signaling that a property resolved to nothing useful and should be
     * dropped from generated output entirely, rather than emitted as `unknown`.
     *
     * @return TypeScriptTypeInfo
     */
    public function omittedTypeScriptInfo(): array
    {
        return [...$this->emptyTypeScriptInfo(), 'omit' => true];
    }

    /**
     * Merge a list of TypeScriptTypeInfo results into one, joining type strings with ' | '.
     *
     * Class-backed entries dedupe by FQCN, not short name, so two classes sharing a class_basename()
     * keep separate tokens for rewriteTypeReferences() to alias independently.
     *
     * @param  list<TypeScriptTypeInfo>  $infos
     * @return TypeScriptTypeInfo
     */
    public function mergeTypeScriptInfos(array $infos): array
    {
        $types = [];
        $enums = [];
        $enumTypes = [];
        /** @var array<string, list<string>> $customImports */
        $customImports = [];
        $enumFqcns = [];

        /** @var array<string, class-string> $classFqcnToName */
        $classFqcnToName = [];

        /** @var list<class-string> $orderedClassFqcns */
        $orderedClassFqcns = [];

        /** @var list<string> $seenTypeTokens */
        $seenTypeTokens = [];

        foreach ($infos as $info) {
            if ($info['classFqcns'] !== []) {
                $isPlainClassUnion = $info['type'] === implode(' | ', $info['classes']);

                if ($isPlainClassUnion) {
                    foreach ($info['classFqcns'] as $i => $fqcn) {
                        if (! isset($classFqcnToName[$fqcn])) {
                            $classFqcnToName[$fqcn] = $info['classes'][$i];
                            $orderedClassFqcns[] = $fqcn;
                            $types[] = $info['classes'][$i];
                        }
                    }
                } else {
                    // Register every FQCN so imports fire, but keep the decorated string as one token
                    foreach ($info['classFqcns'] as $i => $fqcn) {
                        if (! isset($classFqcnToName[$fqcn])) {
                            $classFqcnToName[$fqcn] = $info['classes'][$i];
                            $orderedClassFqcns[] = $fqcn;
                        }
                    }

                    if (! in_array($info['type'], $seenTypeTokens, true)) {
                        $seenTypeTokens[] = $info['type'];
                        $types[] = $info['type'];
                    }
                }
            } else {
                if (! in_array($info['type'], $seenTypeTokens, true)) {
                    $seenTypeTokens[] = $info['type'];
                    $types[] = $info['type'];
                }
            }

            $enums = [...$enums, ...$info['enums']];
            $enumTypes = [...$enumTypes, ...$info['enumTypes']];
            $enumFqcns = [...$enumFqcns, ...$info['enumFqcns']];

            foreach ($info['customImports'] as $path => $importTypes) {
                $customImports[$path] = [...($customImports[$path] ?? []), ...$importTypes];
            }
        }

        $result = $this->emptyTypeScriptInfo();
        $result['type'] = implode(' | ', $types);
        $result['enums'] = array_values(array_unique($enums));
        $result['enumTypes'] = array_values(array_unique($enumTypes));
        $result['classes'] = array_values($classFqcnToName);
        $result['customImports'] = $customImports;
        $result['enumFqcns'] = array_values(array_unique($enumFqcns));
        $result['classFqcns'] = $orderedClassFqcns;

        return $result;
    }

    /**
     * Alias every bare type-name occurrence in one item's type string, walking occurrences left to right.
     *
     * A morph union's occurrence order is the order its FQCN list was built in, so same-basename FQCNs are
     * consumed in source order and the last one covers any further occurrence. No bare token survives.
     *
     * @param  list<string>  $itemFqcns  FQCN per occurrence, in source order, never deduped — a caller may
     *                                   supply more entries than real occurrences (e.g. a merged superset);
     *                                   aliasPropertyType() consumes only the matching prefix, in order
     * @param  array<string, string>  $nameMap  FQCN => unaliased type name
     * @param  array<string, string>  $aliases  FQCN => alias, for the subset that was aliased
     */
    public function aliasPropertyType(string $type, array $itemFqcns, array $nameMap, array $aliases): string
    {
        /** @var array<string, non-empty-list<string>> $queues */
        $queues = [];

        foreach ($itemFqcns as $fqcn) {
            $name = $nameMap[$fqcn] ?? null;

            if ($name !== null) {
                $queues[$name][] = $aliases[$fqcn] ?? $name;
            }
        }

        if ($queues === []) {
            return $type;
        }

        $names = array_keys($queues);
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $names = array_map(static fn (string $name): string => preg_quote($name, '/'), $names);

        $pattern = '/(?<![A-Za-z0-9_$.])(?:'.implode('|', $names).')(?![A-Za-z0-9_$])/';
        $cursors = [];

        return preg_replace_callback($pattern, static function (array $match) use ($queues, &$cursors): string {
            $name = $match[0];
            $cursor = $cursors[$name] ?? 0;
            $cursors[$name] = min($cursor + 1, count($queues[$name]) - 1);

            return $queues[$name][$cursor];
        }, $type) ?? $type;
    }

    /**
     * Resolve the TypeScript interface name a resource class is published under.
     *
     * #[TsResource(name:)] renames the emitted interface, so a reference that used class_basename()
     * instead named a type nothing declares. ResourceTransformer::initReflection() is the same rule.
     */
    public function resourceTypeName(string $fqcn): string
    {
        if (isset($this->resourceTypeNames[$fqcn])) {
            return $this->resourceTypeNames[$fqcn];
        }

        $name = class_basename($fqcn);

        if (class_exists($fqcn)) {
            $attributes = (new ReflectionClass($fqcn))->getAttributes(TsResource::class);

            if ($attributes !== []) {
                $name = $attributes[0]->newInstance()->name ?? $name;
            }
        }

        return $this->resourceTypeNames[$fqcn] = $name;
    }

    /**
     * Convert a FQCN to a modular output directory path.
     *
     * Example: 'Blog\Enums\ArticleStatus' → 'blog/enums'
     */
    public function namespaceToPath(string $fqcn): string
    {
        $namespace = Str::beforeLast($fqcn, '\\');

        $prefix = Config::string('ts-publish.namespace_strip_prefix', '');

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
     * Example: 'blog/models' → 'blog/enums' = '../enums'; 'models' → 'models/videos' = './videos'
     *
     * An empty from-path is the output root, not a directory named '' — one segment deep would climb out.
     */
    public function relativeImportPath(string $fromNamespacePath, string $toNamespacePath): string
    {
        if ($fromNamespacePath === $toNamespacePath) {
            return '.';
        }

        $fromParts = $fromNamespacePath === '' ? [] : explode('/', $fromNamespacePath);
        $toParts = explode('/', $toNamespacePath);

        $commonLength = 0;
        $maxCommon = min(count($fromParts), count($toParts));

        while ($commonLength < $maxCommon && $fromParts[$commonLength] === $toParts[$commonLength]) {
            $commonLength++;
        }

        $upCount = count($fromParts) - $commonLength;
        $downSegments = array_slice($toParts, $commonLength);

        // TypeScript reads a bare specifier like 'videos' as a module lookup, not a relative
        // path, so a descendant target must be prefixed with './'.
        if ($upCount === 0) {
            return './'.implode('/', $downSegments);
        }

        $relative = str_repeat('../', $upCount).implode('/', $downSegments);

        return rtrim($relative, '/');
    }

    /**
     * Sort import paths following eslint-plugin-simple-import-sort conventions: packages, then
     * absolute/other, then relative (deeper first), alphabetical (case-insensitive) within a group.
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
     * Determine the sort group for an import path: 0 = package, 1 = absolute/other, 2 = relative.
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
     * Pass 1 resolves per-file import aliases (`CrmUser` → `models.User`) first, so aliased names
     * reach the namespace-qualification pass already resolved.
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
            $replacement = ($targetNs === $skipNamespace)
                ? substr($qualified, $lastDot + 1)
                : $qualified;
            $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($alias, '/').'(?![A-Za-z0-9_$])/';
            $typeStr = preg_replace($pattern, $replacement, $typeStr) ?? $typeStr;
        }

        // Pass 2: names that also exist in the skip namespace belong to the current context,
        // so they must not be re-qualified with another namespace.
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
     * Split a type string into its top-level union members.
     *
     * Depth-aware over braces, parens, angle brackets, and square brackets, and skips
     * single-quoted literals whole, so a nested `|` never splits.
     *
     * @return list<string>
     */
    public function splitTopLevelUnion(string $typeStr): array
    {
        $members = [];
        $current = '';
        $depth = 0;
        $inString = false;

        foreach (str_split($typeStr) as $char) {
            if ($char === "'") {
                $inString = ! $inString;
            }

            if (! $inString) {
                if (str_contains('{(<[', $char)) {
                    $depth++;
                } elseif (str_contains('})>]', $char)) {
                    $depth = max(0, $depth - 1);
                } elseif ($char === '|' && $depth === 0) {
                    $members[] = trim($current);
                    $current = '';

                    continue;
                }
            }

            $current .= $char;
        }

        $members[] = trim($current);

        return array_values(array_filter($members, fn (string $member): bool => $member !== ''));
    }

    /**
     * Replace `AsEnum<typeof ConstAlias>` patterns with the pre-computed type alias.
     *
     * In the globals file there is no `AsEnum` import, so `AsEnum<typeof X>` and `XType` collapse to
     * the same qualified name — the pair must be folded first to avoid emitting a literal duplicate.
     *
     * @param  string  $typeStr  The TypeScript type string to rewrite.
     * @param  array<string, string>  $constToTypeMap  constAlias => 'namespace.TypeName'
     */
    public function rewriteAsEnumToType(string $typeStr, array $constToTypeMap): string
    {
        foreach ($constToTypeMap as $constAlias => $qualifiedTypeName) {
            $lastDot = strrpos($qualifiedTypeName, '.');
            $bareTypeName = $lastDot === false ? $qualifiedTypeName : substr($qualifiedTypeName, $lastDot + 1);

            // A trailing `[` means the bare arm is array-shaped and the AsEnum arm is not (or vice
            // versa) — a genuinely different pair, not the redundant same-shaped one this folds.
            $pairPattern = '/AsEnum<typeof\s+'.preg_quote($constAlias, '/').'\s*>\s*\|\s*'
                .preg_quote($bareTypeName, '/').'(?![A-Za-z0-9_$\[])/';
            $typeStr = preg_replace($pairPattern, $qualifiedTypeName, $typeStr) ?? $typeStr;

            // Same pair reversed: the bare alias would be re-qualified afterwards, recreating the duplicate.
            // Only ResourceTransformer::rewriteEnumResourceTypes() emits this pair shape, always forward-ordered.
            $reversedPattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($bareTypeName, '/').'\s*\|\s*AsEnum<typeof\s+'
                .preg_quote($constAlias, '/').'\s*>/';
            $typeStr = preg_replace($reversedPattern, $qualifiedTypeName, $typeStr) ?? $typeStr;

            $singlePattern = '/AsEnum<typeof\s+'.preg_quote($constAlias, '/').'\s*>/';
            $typeStr = preg_replace($singlePattern, $qualifiedTypeName, $typeStr) ?? $typeStr;
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
     * Format a description string into a JSDoc comment block.
     *
     * Single-line descriptions render inline; multi-line ones become a ` * `-prefixed block.
     *
     * @param  int  $indent  Number of leading spaces to prefix every line of the output.
     */
    public function formatJsDoc(string $description, int $indent = 0): string
    {
        $sanitized = $this->sanitizeJsDoc($description);
        $prefix = str_repeat(' ', $indent);

        if (! str_contains($sanitized, "\n")) {
            return "{$prefix}/** {$sanitized} */";
        }

        $lines = explode("\n", $sanitized);
        $result = "{$prefix}/**\n";

        foreach ($lines as $line) {
            if ($line === '') {
                $result .= "{$prefix} *\n";
            } else {
                $result .= "{$prefix} * {$line}\n";
            }
        }

        $result .= "{$prefix} */";

        return $result;
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
            $cleaned = preg_replace('#^\s*/?\*+/?\s?#', '', $line) ?? '';
            $cleaned = preg_replace('#\s*\*+/\s*$#', '', $cleaned) ?? '';
            $trimmed = trim($cleaned);

            // Empty remnants of /** and */
            if ($trimmed === '' || $trimmed === '/') {
                // Preserve interior blank lines only — not inside a tag block, not before any text
                if (! $inTag && $description !== []) {
                    $description[] = '';
                }
                $inTag = false;

                continue;
            }

            // An @-tag line opens a (possibly multi-line) tag block
            if (str_starts_with($trimmed, '@')) {
                $inTag = true;

                continue;
            }

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

        // Trailing blank lines produced by the closing */ line
        while ($description !== [] && end($description) === '') {
            array_pop($description);
        }

        return implode("\n", $description);
    }

    /**
     * Serialize a list of route arg metadata objects to a JavaScript array literal.
     *
     * Only fields that are present are emitted, so the generated TypeScript carries no `undefined` noise.
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

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesAccessorType;
use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Throwable;

/**
 * Resolves model attributes and relations to TypeScript types via the accessor → cast → DB type waterfall.
 *
 * Registered as a singleton so inspected model contexts are cached per FQCN for the whole publish run.
 *
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type AttributeInfo from ModelInfo
 * @phpstan-import-type RelationInfo from ModelInfo
 */
class ModelAttributeResolver
{
    use ResolvesAccessorType;

    /**
     * Per-FQCN cache of inspected model context.
     *
     * @var array<class-string, array{
     *     instance: Model,
     *     reflection: ReflectionClass<Model>,
     *     attributes: Collection<int, AttributeInfo>,
     *     relations: Collection<int, RelationInfo>,
     *     relationNullable: RelationNullable,
     * }>
     */
    protected array $contexts = [];

    /**
     * Reverse morph-target map: parent FQCNs declaring a MorphOne/MorphMany pointing at a child.
     *
     * Keyed twice per relation — `childFqcn|morphName` so two differently-named morphTos on one child
     * don't share a union, and a plain `childFqcn` legacy bucket for when the morph name is unknown.
     *
     * @var array<string, list<class-string>>
     */
    protected array $morphTargetMap = [];

    /**
     * Per-FQCN cache of real database column names, keyed the same way as $contexts.
     *
     * @var array<class-string, list<string>>
     */
    protected array $dbColumnNamesCache = [];

    /**
     * Per model-and-attribute cache of resolveAttributeClass(), since receiver resolution asks once per expression.
     *
     * @var array<string, class-string|null>
     */
    protected array $attributeClassCache = [];

    /**
     * Resolve a model attribute's TypeScript type through the accessor → cast → DB type waterfall.
     *
     * @param  class-string  $modelFqcn
     * @return TypeScriptTypeInfo
     */
    public function resolveAttribute(string $modelFqcn, string $attributeName): array
    {
        $empty = LaravelTsPublish::emptyTypeScriptInfo();
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return $empty;
        }

        $attr = $ctx['attributes']->firstWhere('name', $attributeName);

        if ($attr === null) {
            return $this->resolveAttributeFallbacks($modelFqcn, $ctx, $attributeName);
        }

        $cast = $attr['cast'];

        if (($cast === 'attribute' || $cast === 'accessor')) {
            try {
                $accessorInfo = $this->resolveAccessorType($attributeName, $ctx['instance'], $ctx['reflection']);

                if ($accessorInfo['type'] !== 'unknown') {
                    $accessorInfo = $this->refineWithPropertyDocblock($ctx['reflection'], $attributeName, $accessorInfo);

                    return $this->appendNullable($accessorInfo, $attr['nullable']);
                }
            } catch (Throwable) { // @codeCoverageIgnore
                // Fall through to cast/DB type
            }
        }

        if ($cast !== null && $cast !== '' && $cast !== 'attribute' && $cast !== 'accessor') {
            $tsInfo = LaravelTsPublish::toTsType($cast);

            $tsInfo = $this->refineWithPropertyDocblock($ctx['reflection'], $attributeName, $tsInfo);

            return $this->appendNullable($tsInfo, $attr['nullable']);
        }

        if ($attr['type'] === null || $attr['type'] === '') {
            return $empty;
        }

        $tsInfo = LaravelTsPublish::toTsType($attr['type']);

        if ($tsInfo['type'] === 'unknown') {
            return $empty; // @codeCoverageIgnore
        }

        $tsInfo = $this->refineWithPropertyDocblock($ctx['reflection'], $attributeName, $tsInfo);

        return $this->appendNullable($tsInfo, $attr['nullable']);
    }

    /**
     * Resolve names that are not literal attributes: camelCase aliases, and withCount()/withExists() virtuals.
     *
     * The snake_case fallback is tried first because a same-named accessor is a declared type, while the
     * suffix fallbacks are a fixed number/boolean guess.
     *
     * @param  class-string  $modelFqcn
     * @param  array{attributes: Collection<int, AttributeInfo>, relations: Collection<int, RelationInfo>, reflection: ReflectionClass<Model>, ...}  $ctx
     * @return TypeScriptTypeInfo
     */
    protected function resolveAttributeFallbacks(string $modelFqcn, array $ctx, string $attributeName): array
    {
        $empty = LaravelTsPublish::emptyTypeScriptInfo();

        // Guarded on the snake form being a literal attribute, so the recursive call always lands on
        // resolveAttribute()'s exact-match branch and can never re-enter this method.
        $snake = Str::snake($attributeName);
        $snakeAttr = $snake === $attributeName ? null : $ctx['attributes']->firstWhere('name', $snake);

        // Only accessors: Eloquent camel-cases the key when looking for a mutator method, but never when
        // reading $attributes, so $order->placedAt on a plain placed_at column is null at runtime.
        if ($snakeAttr !== null && ($snakeAttr['cast'] === 'attribute' || $snakeAttr['cast'] === 'accessor')) {
            return $this->resolveAttribute($modelFqcn, $snake);
        }

        // Requires a matching relation, so a real column ending in "_count" is never guessed at here.
        foreach (['_count' => 'number', '_exists' => 'boolean'] as $suffix => $tsType) {
            if (! str_ends_with($attributeName, $suffix)) {
                continue;
            }

            $base = Str::camel(substr($attributeName, 0, -strlen($suffix)));

            if ($ctx['relations']->firstWhere('name', $base) !== null) {
                return [...$empty, 'type' => $tsType];
            }
        }

        // A relation name (or its camel alias) is excluded so an ide-helper @property-read tag that
        // merely documents a relation is never mistaken for a query-selected virtual attribute.
        $isRelation = $ctx['relations']->contains(
            fn (array $relation): bool => $relation['name'] === $attributeName || $relation['name'] === Str::camel($attributeName),
        );

        if (! $isRelation) {
            foreach ($this->propertyDocblockClasses($ctx['reflection']) as $class) {
                $refined = $this->refineFromClassDocblock($class, $attributeName, $empty);

                if ($refined !== null) {
                    return $refined;
                }
            }
        }

        return $empty;
    }

    /**
     * Refine a vague resolved type using class-level @property/@property-read docblock tags
     * (Larastan/ide-helper convention).
     *
     * Searches the class/parent chain (child wins), then each class's traits, recursively.
     *
     * @param  ReflectionClass<Model>  $reflection
     * @param  TypeScriptTypeInfo  $tsInfo
     * @return TypeScriptTypeInfo
     */
    public function refineWithPropertyDocblock(ReflectionClass $reflection, string $attributeName, array $tsInfo): array
    {
        if (! TsTypeString::isVagueTsType($tsInfo['type'])) {
            return $tsInfo;
        }

        foreach ($this->propertyDocblockClasses($reflection) as $class) {
            $refined = $this->refineFromClassDocblock($class, $attributeName, $tsInfo);

            if ($refined !== null) {
                return $refined;
            }
        }

        return $tsInfo;
    }

    /**
     * Classes to search for an @property tag, in priority order: the class/parent chain first
     * (child wins), then every trait used anywhere in that chain, recursively.
     *
     * @param  ReflectionClass<Model>  $reflection
     * @return list<ReflectionClass<object>>
     */
    protected function propertyDocblockClasses(ReflectionClass $reflection): array
    {
        /** @var list<ReflectionClass<object>> $chain */
        $chain = [];

        for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
            $chain[] = $class;
        }

        $traits = [];

        foreach ($chain as $class) {
            $traits = [...$traits, ...$this->collectTraitsRecursively($class)];
        }

        return [...$chain, ...$traits];
    }

    /**
     * Every trait used by a class, and every trait those traits themselves use.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    protected function collectTraitsRecursively(ReflectionClass $class): array
    {
        $traits = [];

        foreach ($class->getTraits() as $trait) {
            $traits[] = $trait;
            $traits = [...$traits, ...$this->collectTraitsRecursively($trait)];
        }

        return $traits;
    }

    /**
     * Attempt a refinement from a single class's own @property/@property-read docblock tag; null if none.
     *
     * Also matches a `$`-less `@property Type name` form some vendor traits use, but only when undescribed —
     * otherwise a trailing description's last word could be mistaken for the property name, yielding a wrong type.
     *
     * @param  ReflectionClass<object>  $class
     * @param  TypeScriptTypeInfo  $current
     * @return TypeScriptTypeInfo|null
     */
    protected function refineFromClassDocblock(ReflectionClass $class, string $attributeName, array $current): ?array
    {
        $doc = $class->getDocComment();

        if ($doc === false) {
            return null;
        }

        $name = preg_quote($attributeName, '/');

        $matched = preg_match('/@property(?:-read)?[ \t]+([^$\r\n]+?)[ \t]+\$'.$name.'\b/', $doc, $m)
            || preg_match('/@property(?:-read)?[ \t]+((?:[^$\r\n \t,]|,[ \t]*)+)[ \t]+'.$name.'\b(?=[ \t]*(?:\r?\n|\*\/|$))/', $doc, $m);

        if (! $matched) {
            return null;
        }

        $useMap = LaravelTsPublish::parseFileUseStatements($class);
        $namespace = $class->getNamespaceName();

        $infos = [];

        foreach (LaravelTsPublish::splitPhpDocUnionType($m[1]) as $part) {
            $infos[] = LaravelTsPublish::resolveDocblockTypePartOrAlias($part, $useMap, $namespace, $class);
        }

        $resolved = count($infos) === 1 ? $infos[0] : LaravelTsPublish::mergeTypeScriptInfos($infos);

        return $this->isStrictlyMoreStructured($resolved['type'], $current['type']) ? $resolved : null;
    }

    /**
     * Whether a docblock-derived refinement is more structured than the type it would replace.
     *
     * A refinement still naming 'unknown' is accepted only when the replaced type is entirely vague
     * and the refinement isn't equally vague — e.g. `Record<string, unknown>` beats `unknown[]`, but neither wins.
     */
    protected function isStrictlyMoreStructured(string $candidate, string $current): bool
    {
        if (! TsTypeString::isVagueTsType($candidate)) {
            return true;
        }

        return $this->isEntirelyVagueTsType($current) && ! $this->isEntirelyVagueTsType($candidate);
    }

    /**
     * Whether a type carries no structure at all, as opposed to merely containing 'unknown'
     * somewhere within an otherwise structured shape (e.g. `Record<string, unknown>`).
     */
    protected function isEntirelyVagueTsType(string $type): bool
    {
        $bare = str_ends_with($type, ' | null') ? substr($type, 0, -strlen(' | null')) : $type;

        return in_array($bare, ['unknown', 'unknown[]', 'object', 'unknown[] | Record<string, unknown>'], true);
    }

    /**
     * Whether a non-column attribute is a write-only mutator that ModelTransformer::transformMutators()
     * itself omits: no getter closure and no docblock Get generic to type it from.
     *
     * @param  class-string  $modelFqcn
     */
    public function isOmittedMutator(string $modelFqcn, string $attributeName): bool
    {
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return false;
        }

        $accessorInfo = $this->resolveAccessorType($attributeName, $ctx['instance'], $ctx['reflection']);
        $resolved = $this->refineWithPropertyDocblock($ctx['reflection'], $attributeName, $accessorInfo);

        return $resolved['omit'] ?? false;
    }

    /**
     * The PHP class an attribute's value is an instance of: its enum, date, or class cast, or its accessor's return class.
     *
     * Only an attribute the model inspector lists answers; a name typed by an `@property` tag alone has no class.
     *
     * @param  class-string  $modelFqcn
     * @return class-string|null
     */
    public function resolveAttributeClass(string $modelFqcn, string $attributeName): ?string
    {
        $key = $modelFqcn.'::'.$attributeName;

        if (! array_key_exists($key, $this->attributeClassCache)) {
            $this->attributeClassCache[$key] = $this->findAttributeClass($modelFqcn, $attributeName);
        }

        return $this->attributeClassCache[$key];
    }

    /**
     * Determine whether a resolved model cast belongs to the date/datetime family, including
     * immutable_* variants and the `:format` suffix on custom_datetime casts.
     */
    public function isDateFamilyCast(string $cast): bool
    {
        return in_array(explode(':', $cast)[0], [
            'date', 'datetime', 'custom_datetime', 'timestamp',
            'immutable_date', 'immutable_datetime', 'immutable_custom_datetime',
        ], true);
    }

    /**
     * Resolve an attribute's value class uncached, asking Castable before CastsAttributes as resolveCasterClass() does.
     *
     * @param  class-string  $modelFqcn
     * @return class-string|null
     */
    protected function findAttributeClass(string $modelFqcn, string $attributeName): ?string
    {
        $ctx = $this->resolveContext($modelFqcn);
        $attr = $ctx === null ? null : $ctx['attributes']->firstWhere('name', $attributeName);

        if ($ctx === null || $attr === null) {
            return null;
        }

        $cast = (string) $attr['cast'];

        if ($cast === 'attribute' || $cast === 'accessor') {
            return $this->accessorReturnClass($ctx['reflection'], $ctx['instance'], $attributeName);
        }

        $head = Str::before($cast, ':');

        // Laravel's timestamp cast returns the Unix integer, not a date object.
        if ($this->isDateFamilyCast($cast)) {
            return match (true) {
                $head === 'timestamp' => null,
                str_starts_with($cast, 'immutable_') => CarbonImmutable::class,
                default => Carbon::class,
            };
        }

        if (is_a($head, Castable::class, true)) {
            return $this->castableValueClass($head, $cast);
        }

        if (is_a($head, CastsAttributes::class, true)) {
            return $this->methodReturnClass($head, 'get');
        }

        return enum_exists($head) ? $head : null;
    }

    /**
     * The class an accessor's getter returns: its closure's native type, its `Attribute<Get, Set>` docblock's Get,
     * or an old-style `getXAttribute()`'s native type.
     *
     * @param  ReflectionClass<Model>  $reflection
     * @return class-string|null
     */
    protected function accessorReturnClass(ReflectionClass $reflection, Model $instance, string $attributeName): ?string
    {
        ['newStyle' => $newStyle, 'oldStyle' => $oldStyle] = $this->accessorMethodNames($attributeName);

        try {
            $attribute = $reflection->hasMethod($newStyle) ? $reflection->getMethod($newStyle)->invoke($instance) : null;
        } catch (Throwable) {
            $attribute = null;
        }

        if ($attribute instanceof Attribute) {
            $getter = $attribute->get instanceof Closure ? new ReflectionFunction($attribute->get) : null;

            // A native getter type is authoritative even when builtin; only an untyped getter defers to the docblock.
            if ($getter !== null && $getter->hasReturnType()) {
                return $this->singleClass(
                    $getter->getReturnType(),
                    $getter->getClosureCalledClass()?->getName() ?? $reflection->getName(),
                    $getter->getClosureScopeClass()?->getName() ?? $reflection->getName(),
                );
            }

            return $this->attributeDocblockGetClass($reflection->getMethod($newStyle));
        }

        return $reflection->hasMethod($oldStyle) ? $this->methodReturnClass($reflection->getName(), $oldStyle) : null;
    }

    /**
     * The one class an `Attribute<Get, Set>` docblock's Get argument names, ignoring a `null` arm and generic arguments.
     *
     * @return class-string|null
     */
    protected function attributeDocblockGetClass(ReflectionMethod $method): ?string
    {
        $returnType = LaravelTsPublish::extractReturnTypeFromDocblock((string) $method->getDocComment());

        if ($returnType === null
            || ! preg_match('/^\\\\?(?:Illuminate\\\\Database\\\\Eloquent\\\\Casts\\\\)?Attribute\s*<(.+)>$/s', trim($returnType), $m)
        ) {
            return null;
        }

        $get = LaravelTsPublish::splitAtTopLevelCommas($m[1])[0] ?? '';
        $names = array_values(array_filter(
            LaravelTsPublish::splitPhpDocUnionType(ltrim($get, '?')),
            fn (string $part): bool => strtolower($part) !== 'null',
        ));

        // Generic arguments never change the class, but text after the closing `>`, such as `[]`, does.
        if (count($names) !== 1 || ! preg_match('/^([\\\\\w]+)(?:<.*>)?$/s', $names[0], $match)) {
            return null;
        }

        $declaringClass = LaravelTsPublish::methodDeclaringFileClass($method);
        $class = LaravelTsPublish::resolveDocblockTypeName(
            $match[1],
            LaravelTsPublish::parseFileUseStatements($declaringClass),
            $declaringClass->getNamespaceName(),
        );

        return class_exists($class) || interface_exists($class) || enum_exists($class) ? $class : null;
    }

    /**
     * The class a `Castable` cast's value is: the native `get()` return of the caster `castUsing()` builds.
     *
     * Laravel's own `AsCollection`/`AsStringable` casters are anonymous classes with no `get()` return type, so they
     * answer null rather than naming the Castable itself, which the value never is.
     *
     * @param  class-string<Castable>  $castable
     * @return class-string|null
     */
    protected function castableValueClass(string $castable, string $cast): ?string
    {
        try {
            $caster = $castable::castUsing(str_contains($cast, ':') ? explode(',', Str::after($cast, ':')) : []);
        } catch (Throwable) {
            return null;
        }

        $casterClass = is_object($caster) ? $caster::class : $caster;

        return is_a($casterClass, CastsAttributes::class, true) ? $this->methodReturnClass($casterClass, 'get') : null;
    }

    /**
     * The one class a method's native return type names, with `static` as the class and `self` as its declarer.
     *
     * @return class-string|null
     */
    protected function methodReturnClass(string $class, string $method): ?string
    {
        $reflection = new ReflectionMethod($class, $method);

        return $this->singleClass($reflection->getReturnType(), $class, $reflection->getDeclaringClass()->getName());
    }

    /**
     * The class a reflected type names, or null for a builtin, union, or intersection type.
     *
     * `static` names the class the value was read through; `self` names the class that declared the type.
     *
     * @return class-string|null
     */
    protected function singleClass(?ReflectionType $type, string $staticClass, string $selfClass): ?string
    {
        if (! $type instanceof ReflectionNamedType || ($type->isBuiltin() && $type->getName() !== 'static')) {
            return null;
        }

        $class = match ($type->getName()) {
            'static' => $staticClass,
            'self' => $selfClass,
            default => $type->getName(),
        };

        return class_exists($class) || interface_exists($class) || enum_exists($class) ? $class : null;
    }

    /**
     * Resolve a relation name to its TypeScript type and related model FQCN.
     *
     * `morphFqcns` carries every parent a MorphTo may resolve to, because `modelFqcn` can only name one
     * and every token in the emitted union still needs an import.
     *
     * @param  class-string  $modelFqcn
     * @return array{type: string, modelFqcn: class-string<Model>|null, morphFqcns: list<class-string>}
     */
    public function resolveRelation(string $modelFqcn, string $relationName): array
    {
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return ['type' => 'unknown', 'modelFqcn' => null, 'morphFqcns' => []];
        }

        $relation = $ctx['relations']->firstWhere('name', $relationName);

        if ($relation === null) {
            return ['type' => 'unknown', 'modelFqcn' => null, 'morphFqcns' => []];
        }

        $isMorphTo = $relation['type'] === 'MorphTo'
            || (str_ends_with($relation['type'], 'MorphTo') && ! str_ends_with($relation['type'], 'MorphToMany'));

        if ($isMorphTo) {
            $targets = $this->resolveMorphToTargets($modelFqcn, $relationName);

            return $this->buildMorphUnionInfo($targets, $relation, $ctx);
        }

        DependencyRecorder::recordClass($relation['related']);

        $relatedModel = class_basename($relation['related']);
        $containsMany = str_contains(strtolower($relation['type']), 'many');

        if ($containsMany) {
            return ['type' => $relatedModel.'[]', 'modelFqcn' => $relation['related'], 'morphFqcns' => []];
        }

        $type = $relatedModel;
        $nullableRelations = Config::boolean('ts-publish.models.nullable_relations');

        if ($nullableRelations && $ctx['relationNullable']->isNullable($relation)) {
            $type .= ' | null';
        }

        return ['type' => $type, 'modelFqcn' => $relation['related'], 'morphFqcns' => []];
    }

    /**
     * Resolve the return type of a method (instance or static) on a model.
     *
     * @param  class-string  $modelFqcn
     * @return TypeScriptTypeInfo
     */
    public function resolveMethodReturnType(string $modelFqcn, string $methodName): array
    {
        try {
            /** @var ReflectionClass<Model> $reflection */
            $reflection = new ReflectionClass($modelFqcn);

            return LaravelTsPublish::methodOrDocblockReturnTypes($reflection, $methodName);
        } catch (Throwable) {
            return LaravelTsPublish::emptyTypeScriptInfo();
        }
    }

    /**
     * The single Eloquent Model FQCN an accessor's getter returns, or null when it is not exactly one.
     *
     * @param  class-string  $modelFqcn
     * @return class-string<Model>|null
     */
    public function resolveAccessorModelFqcn(string $modelFqcn, string $attributeName): ?string
    {
        $fqcns = $this->resolveAccessorModelFqcns($modelFqcn, $attributeName);

        return count($fqcns) === 1 ? $fqcns[0] : null;
    }

    /**
     * Return every Eloquent Model FQCN that an accessor's getter may return.
     *
     * @param  class-string  $modelFqcn
     * @return list<class-string<Model>>
     */
    public function resolveAccessorModelFqcns(string $modelFqcn, string $attributeName): array
    {
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return [];
        }

        $attr = $ctx['attributes']->firstWhere('name', $attributeName);

        if ($attr === null || ($attr['cast'] !== 'attribute' && $attr['cast'] !== 'accessor')) {
            return []; // @codeCoverageIgnore
        }

        try {
            $accessorInfo = $this->resolveAccessorType($attributeName, $ctx['instance'], $ctx['reflection']);

            /** @var list<class-string<Model>> $fqcns */
            $fqcns = array_values(array_filter(
                $accessorInfo['classFqcns'],
                fn (string $fqcn) => is_a($fqcn, Model::class, true),
            ));

            // These models get inlined into a resource by ->only()/->except(), so their files are real
            // cache dependencies.
            foreach ($fqcns as $fqcn) {
                DependencyRecorder::recordClass($fqcn);
            }

            return $fqcns;
        } catch (Throwable) { // @codeCoverageIgnore
            return []; // @codeCoverageIgnore
        }
    }

    /**
     * @param  class-string  $modelFqcn
     * @return Collection<int, AttributeInfo>|null
     */
    public function getAttributes(string $modelFqcn): ?Collection
    {
        return $this->resolveContext($modelFqcn)['attributes'] ?? null;
    }

    /**
     * @param  class-string  $modelFqcn
     * @return Collection<int, RelationInfo>|null
     */
    public function getRelations(string $modelFqcn): ?Collection
    {
        return $this->resolveContext($modelFqcn)['relations'] ?? null;
    }

    /**
     * Names of the model's real database columns, read straight from the schema.
     *
     * Mirrors ModelTransformer::transformColumns()'s $dbColumns exactly (same schema listing call).
     * Raw listing — includes $hidden columns; callers want publishedColumnNames() for the emitted interface.
     *
     * @param  class-string  $modelFqcn
     * @return list<string>
     */
    public function databaseColumnNames(string $modelFqcn): array
    {
        if (isset($this->dbColumnNamesCache[$modelFqcn])) {
            return $this->dbColumnNamesCache[$modelFqcn];
        }

        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return [];
        }

        /** @var list<string> $columns */
        $columns = $ctx['instance']->getConnection()->getSchemaBuilder()->getColumnListing($ctx['instance']->getTable());

        return $this->dbColumnNamesCache[$modelFqcn] = $columns;
    }

    /**
     * Names of the database columns that actually reach the emitted model interface.
     *
     * Callers naming keys against that interface need this, not the raw schema listing:
     * `Pick<Model, K>` constrains K to keyof Model, so a stale key will not compile.
     *
     * @param  class-string  $modelFqcn
     * @return list<string>
     */
    public function publishedColumnNames(string $modelFqcn): array
    {
        $columns = $this->databaseColumnNames($modelFqcn);
        $attributes = $this->getAttributes($modelFqcn);

        if ($attributes === null) {
            return $columns; // @codeCoverageIgnore
        }

        $excludeHidden = $this->excludeHiddenAttributes();

        $published = $attributes
            ->reject(fn (array $attr): bool => $excludeHidden && $attr['hidden'])
            ->pluck('name')
            ->all();

        return array_values(array_filter(
            $columns,
            fn (string $column): bool => in_array($column, $published, true),
        ));
    }

    /**
     * Names of the attributes a model instance writes when it serializes: its published columns, then its appended
     * attributes (`$appends` or `#[Appends]`). toArray() keeps only the names `$visible` lists, when it lists any, and
     * drops those `$hidden` lists, for columns and appends alike, whatever `exclude_hidden` says.
     *
     * @param  class-string  $modelFqcn
     * @return list<string>
     */
    public function serializedAttributeNames(string $modelFqcn): array
    {
        $instance = $this->getInstance($modelFqcn);

        if ($instance === null) {
            return []; // @codeCoverageIgnore
        }

        $visible = $instance->getVisible();
        $hidden = $instance->getHidden();

        /** @var list<string> $appends */
        $appends = $instance->getAppends();

        return array_values(array_unique(array_filter(
            [...$this->publishedColumnNames($modelFqcn), ...$appends],
            fn (string $name): bool => ($visible === [] || in_array($name, $visible, true)) && ! in_array($name, $hidden, true),
        )));
    }

    /**
     * Whether Eloquent $hidden attributes are excluded from published output.
     *
     * Deliberately uncached: every site that filters $hidden must observe the same value.
     */
    public function excludeHiddenAttributes(): bool
    {
        return Config::boolean('ts-publish.models.exclude_hidden', false);
    }

    /**
     * @param  class-string  $modelFqcn
     */
    public function getRelationNullable(string $modelFqcn): ?RelationNullable
    {
        return $this->resolveContext($modelFqcn)['relationNullable'] ?? null;
    }

    /**
     * @param  class-string  $modelFqcn
     */
    public function getInstance(string $modelFqcn): ?Model
    {
        return $this->resolveContext($modelFqcn)['instance'] ?? null;
    }

    /**
     * The TypeScript spelling of a model's primary key type, or null when the model cannot be instantiated.
     *
     * Returns null rather than defaulting, because the callers disagree on the no-instance case: the
     * receiver rules decline, while the auth and getKey rules fall back to `string`. Folding a default in
     * here would make an uninstantiable model publish `string` where it currently declines.
     *
     * @param  class-string  $modelFqcn
     */
    public function keyTsType(string $modelFqcn): ?string
    {
        $instance = $this->getInstance($modelFqcn);

        if ($instance === null) {
            return null;
        }

        // getCasts() casts an incrementing key as getKeyType(), and castAttribute() treats `int` and `integer` alike.
        return in_array($instance->getKeyType(), ['int', 'integer'], true) ? 'number' : 'string';
    }

    /**
     * @param  class-string  $modelFqcn
     * @return ReflectionClass<Model>|null
     */
    public function getReflection(string $modelFqcn): ?ReflectionClass
    {
        return $this->resolveContext($modelFqcn)['reflection'] ?? null;
    }

    /**
     * Scan every model's MorphOne/MorphMany relations to build the child → parents morph target map.
     *
     * @param  list<class-string>  $modelFqcns  All model FQCNs that will be processed.
     */
    public function buildMorphTargetMap(array $modelFqcns): void
    {
        /** @var array<string, list<class-string>> $map */
        $map = [];

        foreach ($modelFqcns as $parentFqcn) {
            $ctx = $this->resolveContext($parentFqcn);

            if ($ctx === null) {
                continue;
            }

            foreach ($ctx['relations'] as $relation) {
                if (str_contains($relation['type'], 'MorphToMany')) {
                    $pivotKey = $this->morphPivotKey($ctx['instance'], $relation['name']);

                    if ($pivotKey !== null && ! in_array($parentFqcn, $map[$pivotKey] ?? [], true)) {
                        $map[$pivotKey][] = $parentFqcn;
                    }

                    continue;
                }

                if (! $this->isMorphParentRelation($parentFqcn, $relation)) {
                    continue;
                }

                $childFqcn = $relation['related'];

                DependencyRecorder::recordClass($childFqcn);

                // Written under both keys: the morph-name-specific bucket so two differently-named
                // morphTos on one child don't share a union, and the plain childFqcn bucket as the
                // legacy aggregate a child relation falls back to when its own name can't be read.
                $keys = [$childFqcn];
                $morphName = $this->relationMorphName($ctx['instance'], $relation['name']);

                if ($morphName !== null) {
                    $keys[] = $childFqcn.'|'.$morphName;
                }

                foreach ($keys as $key) {
                    if (! isset($map[$key])) {
                        $map[$key] = [];
                    }

                    if (! in_array($parentFqcn, $map[$key], true)) {
                        $map[$key][] = $parentFqcn;
                    }
                }
            }
        }

        // Sorted so the generated union type is stable across runs.
        foreach ($map as $key => $parents) {
            sort($parents);
            $map[$key] = $parents;
        }

        $this->morphTargetMap = $map;
    }

    /**
     * Whether a relation is a morph parent, counting custom subclasses of MorphOne/MorphMany.
     *
     * @param  class-string  $parentFqcn
     * @param  RelationInfo  $relation
     */
    protected function isMorphParentRelation(string $parentFqcn, array $relation): bool
    {
        if ($relation['type'] === 'MorphOne' || $relation['type'] === 'MorphMany') {
            return true;
        }

        $reflection = $this->getReflection($parentFqcn);

        if ($reflection === null || ! $reflection->hasMethod($relation['name'])) {
            return false;
        }

        $returnType = $reflection->getMethod($relation['name'])->getReturnType();

        if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return false;
        }

        $fqcn = $returnType->getName();

        return is_a($fqcn, MorphOne::class, true)
            || is_a($fqcn, MorphMany::class, true);
    }

    /**
     * The `Pivot|morphName` map key for a morphToMany whose custom pivot model carries the morphTo back.
     */
    protected function morphPivotKey(Model $instance, string $relationName): ?string
    {
        try {
            $relation = $instance->{$relationName}();
        } catch (Throwable) {
            return null;
        }

        if (! $relation instanceof MorphToMany || $relation->getInverse()) {
            return null;
        }

        $pivot = $relation->getPivotClass();

        // getPivotClass()'s class-string<Pivot> bound is docblock-only — using() takes no native
        // parameter type — so a caller can still hand it an unrelated class at runtime.
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (in_array($pivot, [Pivot::class, MorphPivot::class], true) || ! is_a($pivot, Model::class, true)) {
            return null;
        }

        DependencyRecorder::recordClass($pivot);

        $morphType = $relation->getMorphType();

        return $pivot.'|'.(str_ends_with($morphType, '_type') ? substr($morphType, 0, -5) : $morphType);
    }

    /**
     * Return the list of parent model FQCNs that morphTo the given child model under the given
     * morph (relation) name — falling back to the legacy childFqcn-only bucket (every parent
     * regardless of name) when no parent declared a relation under that specific name, then
     * unioning in any parent that instead targets a *subclass* of the given child under the
     * same morph name, since those rows still satisfy the base child's morphTo.
     *
     * @param  class-string  $childModelFqcn
     * @return list<class-string>
     */
    public function getMorphToTargets(string $childModelFqcn, string $morphName): array
    {
        $targets = $this->morphTargetMap[$childModelFqcn.'|'.$morphName]
            ?? $this->morphTargetMap[$childModelFqcn]
            ?? [];

        foreach ($this->morphTargetMap as $key => $parents) {
            if (! str_contains($key, '|')) {
                continue;
            }

            [$mappedChild, $mappedName] = explode('|', $key, 2);

            if ($mappedName === $morphName && $mappedChild !== $childModelFqcn && is_subclass_of($mappedChild, $childModelFqcn)) {
                $targets = [...$targets, ...$parents];
            }
        }

        $targets = array_values(array_unique($targets));
        sort($targets);

        return $targets;
    }

    /**
     * Resolve a MorphTo relation's target model FQCNs — a docblock generic first, then the reverse-relation map.
     *
     * Single source of truth for MorphTo targets: both resolveRelation() and ModelTransformer's pipeline call this.
     *
     * @param  class-string  $modelFqcn
     * @return list<class-string>
     */
    public function resolveMorphToTargets(string $modelFqcn, string $relationName): array
    {
        $docblockTargets = $this->morphToDocblockTargets($modelFqcn, $relationName);

        if ($docblockTargets !== []) {
            return $docblockTargets;
        }

        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return [];
        }

        $morphName = $this->relationMorphName($ctx['instance'], $relationName) ?? '';

        return $this->getMorphToTargets($modelFqcn, $morphName);
    }

    /**
     * The class a MorphTo generic is bounded by, for member reflection only — never emitted or imported.
     *
     * Unlike morphToDocblockTargets(), a bare `Model` or an abstract class still answers, because reflecting
     * a member on the bound is sound even when no concrete target is known.
     *
     * @param  class-string  $modelFqcn
     * @return class-string<Model>
     */
    public function resolveMorphToBound(string $modelFqcn, string $relationName): string
    {
        $members = $this->morphToGenericMembers($modelFqcn, $relationName) ?? [];

        if (count($members) === 1 && class_exists($members[0]) && is_a($members[0], Model::class, true)) {
            return $members[0];
        }

        return Model::class;
    }

    /**
     * Concrete Model subclasses named by a morphTo method's `@return MorphTo<X|Y, ...>` docblock generic.
     *
     * Bare `Model` and abstract targets yield `[]`, so the caller falls through to the
     * reverse-relation map instead of importing a useless base class.
     *
     * @param  class-string  $modelFqcn
     * @return list<class-string<Model>>
     */
    protected function morphToDocblockTargets(string $modelFqcn, string $relationName): array
    {
        $targets = [];

        foreach ($this->morphToGenericMembers($modelFqcn, $relationName) ?? [] as $fqcn) {
            if (! class_exists($fqcn) || ! is_a($fqcn, Model::class, true) || $fqcn === Model::class) {
                return [];
            }

            if ((new ReflectionClass($fqcn))->isAbstract()) {
                return [];
            }

            /** @var class-string<Model> $fqcn */
            $targets[] = $fqcn;
        }

        return $targets;
    }

    /**
     * The names in a morphTo method's `@return MorphTo<X|Y, ...>` first generic argument, resolved against the
     * declaring file's imports; null when the method or the generic is absent.
     *
     * @param  class-string  $modelFqcn
     * @return list<string>|null
     */
    protected function morphToGenericMembers(string $modelFqcn, string $relationName): ?array
    {
        $reflection = $this->getReflection($modelFqcn);

        if ($reflection === null || ! $reflection->hasMethod($relationName)) {
            return null;
        }

        $method = $reflection->getMethod($relationName);
        $returnType = LaravelTsPublish::extractReturnTypeFromDocblock((string) $method->getDocComment());

        if ($returnType === null
            || ! preg_match('/^\\\\?(?:Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\)?MorphTo\s*<(.+)>$/s', trim($returnType), $m)
        ) {
            return null;
        }

        $declaringClass = LaravelTsPublish::methodDeclaringFileClass($method);
        $useMap = LaravelTsPublish::parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

        // Only the first generic argument names the target(s) — the second ($this, by Laravel's
        // own convention) carries no target information and is discarded here.
        return array_map(
            fn (string $part): string => LaravelTsPublish::resolveDocblockTypeName(trim($part), $useMap, $namespace),
            LaravelTsPublish::splitPhpDocUnionType(trim(Str::before($m[1], ','))),
        );
    }

    /**
     * Build a MorphTo relation's resolveRelation()-shaped result from a resolved target list —
     * shared by the docblock-generic and reverse-map branches so both apply nullability and
     * morphFqcns identically.
     *
     * @param  list<class-string>  $targets
     * @param  RelationInfo  $relation
     * @param  array{relationNullable: RelationNullable, ...}  $ctx
     * @return array{type: string, modelFqcn: class-string<Model>|null, morphFqcns: list<class-string>}
     */
    protected function buildMorphUnionInfo(array $targets, array $relation, array $ctx): array
    {
        $type = $targets !== []
            ? implode(' | ', array_map(class_basename(...), $targets))
            : 'unknown';

        $nullableRelations = Config::boolean('ts-publish.models.nullable_relations');

        // 'unknown' already admits null, so appending the suffix would only add noise.
        if ($type !== 'unknown' && $nullableRelations && $ctx['relationNullable']->isNullable($relation)) {
            $type .= ' | null';
        }

        return ['type' => $type, 'modelFqcn' => null, 'morphFqcns' => $targets];
    }

    /**
     * The morph "name" (e.g. 'imageable') for a MorphTo/MorphOne/MorphMany relation.
     *
     * Building an Eloquent relation queries nothing — addConstraints() only appends to the query
     * builder — so calling it on an unpersisted instance is safe.
     */
    protected function relationMorphName(Model $instance, string $relationName): ?string
    {
        try {
            $relation = $instance->{$relationName}();
        } catch (Throwable) {
            return null;
        }

        if (! $relation instanceof MorphTo && ! $relation instanceof MorphOneOrMany) {
            return null; // @codeCoverageIgnore
        }

        $morphType = $relation->getMorphType();

        return str_ends_with($morphType, '_type') ? substr($morphType, 0, -5) : $morphType;
    }

    /**
     * Lazily build and cache the model context (instance, reflection, attributes, relations).
     *
     * @param  class-string  $modelFqcn
     * @return array{instance: Model, reflection: ReflectionClass<Model>, attributes: Collection<int, AttributeInfo>, relations: Collection<int, RelationInfo>, relationNullable: RelationNullable}|null
     */
    protected function resolveContext(string $modelFqcn): ?array
    {
        if (isset($this->contexts[$modelFqcn])) {
            return $this->contexts[$modelFqcn];
        }

        if (! class_exists($modelFqcn)) {
            return null;
        }

        try {
            /** @var Model $instance */
            $instance = resolve($modelFqcn);

            $data = resolve(ModelInspector::class)->inspect($modelFqcn);

            /** @var Collection<int, AttributeInfo> $attributes */
            $attributes = $data->attributes;

            if ($attributes->isEmpty() && ! $instance->getConnection()->getSchemaBuilder()->hasTable($instance->getTable())) {
                AnalysisWarnings::add($modelFqcn, sprintf(
                    'Table [%s] does not exist on connection [%s], so its columns are not published. Run the migrations, then publish again.',
                    $instance->getTable(),
                    $instance->getConnection()->getName(),
                ));
            }

            /** @var ReflectionClass<Model> $reflection */
            $reflection = new ReflectionClass($modelFqcn);

            $this->contexts[$modelFqcn] = [
                'instance' => $instance,
                'reflection' => $reflection,
                'attributes' => $attributes,
                'relations' => $data->relations,
                'relationNullable' => new RelationNullable($instance, $attributes),
            ];

            return $this->contexts[$modelFqcn];
        } catch (Throwable) { // @codeCoverageIgnore
            return null; // @codeCoverageIgnore
        }
    }

    /**
     * Append ' | null' to a TypeScriptTypeInfo's type when nullable and not already present.
     *
     * @param  TypeScriptTypeInfo  $tsInfo
     * @return TypeScriptTypeInfo
     */
    protected function appendNullable(array $tsInfo, ?bool $nullable): array
    {
        if ($nullable && ! str_contains($tsInfo['type'], 'null')) {
            $tsInfo['type'] .= ' | null';
        }

        return $tsInfo;
    }
}

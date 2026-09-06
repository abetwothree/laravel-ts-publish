# Model metadata

> User-facing docs: [README § Models](../../README.md#models) and the
> [Model Metadata page](https://tolki.abe.dev/ts/model-metadata.html). Verified by
> [the type-inference gates](../testing/type-inference-gates.md) — `default-example/app/models/user_meta.ts`
> is inside `tsconfig.json`'s `include`, so the token gate compiles the committed companion.

Model metadata is a publishing phase that writes one runtime companion, `{model}_meta.ts`, beside each
model interface. The companion exports a single `as const satisfies` object whose values come from a
`ModelMetadataProvider` and whose type comes from three sources with fixed precedence:

```typescript
// workbench/resources/js/types/data/default-example/app/models/user_meta.ts
import type { RoleType } from '../enums';

export const UserModelMetadata = {
    morphClass: 'Workbench\\App\\Models\\User',
    enabled: true,
    limits: {minimum: 1, maximum: null},
    role: 'Admin',
} as const satisfies {
    morphClass: string;
    enabled: boolean;
    limits: { minimum: number; maximum: null };
    role: RoleType;
};
```

The phase is independent of the model-interface phase: its own config block (`ts-publish.model_metadata`),
collector, generator, transformer, writer, `--only-model-metadata` flag, and "functional" membership under
`--only-functional` (`TsPublishCommand::resolvePublishFlags()`'s type registry marks `model_metadata`
functional and `models` not). The two phases meet in exactly one place: the barrel they share.

## Pipeline

`Runner::generateModelMetadata()` → `ModelMetadataCollector` (which models) → `ModelMetadataGenerator`
(cache entry, `filename()`) → `ModelMetadataTransformer` (the work) → `ModelMetadataWriter` (render the
Blade view, write the file). Each of the four is swappable through `ts-publish.model_metadata.*_class`,
and the payload itself comes from a swappable `ModelMetadataProvider::provide()`.

`ModelMetadataTransformer::transform()` runs, in order:

| Step | What it does |
| --- | --- |
| `initInstance()` | Resolves the model through the container, records `modelName` (short name) and `namespacePath` (`LaravelTsPublish::namespaceToPath()`). |
| `resolveProvider()` | `ModelMetadataProviderResolver` reads `model_metadata.provider_class` (default `DefaultModelMetadataProvider`), resolves it through the container, and rejects anything not implementing `ModelMetadataProvider`. `DependencyRecorder::recordClass()` then records the provider's file — plus its parents' and interfaces' — as cache dependencies. |
| `collectMetadata()` | Calls `provide($model)` and rejects a payload with any integer key. |
| `transformPropertyTypes()` | Delegates to `Analyzers\Metadata\ModelMetadataAnalyzer::analyze($providerClass, $payloadKeys, $namespacePath)` and stores the returned `ModelMetadataAnalysis` — see [Type precedence](#type-precedence). |
| `validateProperties()` | `undeclaredKeys()` and `missingKeys()` on the analysis must both be empty; every key whose source is not `casts` may not spell a token no import brings in (`LaravelTsPublish::shapeValueHasUnimportableToken($type, $analysis->importedNames())`). |
| `resolveImports()` | `TsCastsImportResolver` assigns collision-free local names to the `#[TsCasts]` imports; the analysis's inferred `typeImports` merge in on top; one local name arriving from two paths is refused. |
| `transformProperties()` | Normalizes every value — see [Value normalization](#value-normalization). |
| `coerceEmptyArrays()` | Spells `[]` as `{}` where the resolved type says object — see [Empty containers](#empty-containers). |

`data()` hands a `TsModelMetadataDto` (`modelName`, `filename`, `properties`, `propertyTypes`,
`typeImports`) to the `laravel-ts-publish::model-meta` view, which emits `import type` lines, the value
object, and the `satisfies` shape in that order. Values and type members come from two separate maps, both
keyed by the payload's own keys: `resolveImports()` writes a `propertyTypes` entry for every payload key and
would fail on a key no source typed, which is why `validateProperties()` runs before it.

The snapshot the generation cache stores is the transformer serialized minus `transientProperties()`
(`modelInstance`, `provider`, `metadata`, `analysis`) via `SnapshotsTransformerState`. Everything a writer
or an aggregate reads survives; everything that only existed to compute it does not.

## Type precedence

`ModelMetadataAnalyzer` owns the static type side and returns a `final readonly ModelMetadataAnalysis`
(`types`, `sources`, `requiredKeys`, `importPaths`, `typeImports`, plus the `undeclaredKeys()`,
`missingKeys()`, `importFreeKeys()` and `importedNames()` questions the transformer asks). The transformer
keeps only the **value** side. That split is the reason the transformer's validation reads as four
questions rather than four private helpers over its own state.

For each key the TypeScript type is the first available of:

1. **`#[TsCasts]` on `provide()`** — explicit overrides with their own import channel (`TsCastsImportResolver`,
   which aliases when one type name arrives from two paths). A cast key is **required** unless the cast itself
   says `optional: true` or the docblock already spelled it `key?:` — `optionalOverrides` carries an entry only
   for a cast that states `optional` at all, so `mergeTypes()`'s `??` falls through to the docblock only then.
   Casting a key that some payloads omit, without `optional: true`, therefore fails every model that omits it.
2. **The `@return array{...}` shape on `provide()`** (`LaravelTsPublish::parseDocblockReturnArrayShape()`).
   `key?:` marks an optional key; every other declared key is required and must be present in every payload.
   A docblock string carries no FQCN, so a class or enum *named there* has no import path and fails the token
   check unless `#[TsCasts]` covers it.
3. **Body inference** over `provide()`. Only keys the concrete payload returned are kept, and any inferred
   type containing `unknown` is discarded.

`mergeTypes()` spreads inferred types under declared ones, applies casts on top, and records a per-key
`source` of `'inferred' | 'docblock' | 'casts'`. `importFreeKeys()` then hands the transformer every payload
key whose source is not `casts` — those are exactly the keys that cannot carry an import of their own, so
those are the keys the token check runs over. `tests/Fixtures/PrecedenceModelMetadataProvider.php` pins all
three tiers, with one key where inference and the docblock disagree and one where `#[TsCasts]` overrides both.

### Body inference is an engine consumer

`ModelMetadataAnalyzer::analyzeBody()` is the second caller of `AstEngine::bindingsFor()` (after
`InertiaPageAnalyzer`). As a *class* it is shaped like `InertiaSharedDataAnalyzer` — an analyzer that layers
docblock and `#[TsCasts]` overrides onto engine output and then builds the imports — but it does not reach
the engine the same way: shared data calls `AstEngine::analyzeMethod()`, which seeds no bindings, while
metadata locates and binds by hand:

- **`MethodLocator::locateOwn($declaringClass, 'provide')`** — the **declaring** class from
  `ReflectionMethod::getDeclaringClass()`, not the configured provider class. `ResourceAstAnalyzer`'s
  parent walk rebuilds the analyzer *without* the seeded scope, so an inherited body located from the
  subclass would lose its binding. Locating the declaring class instead keeps it
  (`tests/Fixtures/InheritedModelMetadataProvider.php` pins an inherited `provide()` still typing
  `$model->getTable()` as `string`).
- **A trait-supplied `provide()` is the one shape that degrades.** `locateOwn()` searches the declaring
  class's **own file**, and for a trait method the declaring class is the *using* class, whose file holds no
  `provide()` node. `locateOwn()` declines, `analyzeBody()` falls back to `AstEngine::analyzeMethod()`, and
  `analyzeMethod()` seeds no bindings — so calls on `$model` return to `unknown` and their keys need a
  docblock or `#[TsCasts]`. `tests/Fixtures/ProvidesTraitModelMetadata.php` pins it.
- **`AstEngine::bindingsFor($context)`** seeds `varModelBindings[$param] = Model::class` for the
  `Model $model` parameter — the **declared** type, because `is_a(Model::class, Model::class, true)` is true —
  along with `requestVarNames` and the single-write local variables. A method call on the bound variable
  resolves through `VariableHandler` → `ResolvesRelatedModelTypes::analyzeRelatedModelMethodCall()` →
  `ModelAttributeResolver::resolveMethodReturnType()`, which reflects Laravel's own `@return string`
  docblocks, so `getTable()`, `getKeyName()`, `getRouteKeyName()` and `getMorphClass()` all infer `string`.
- **`new ResourceAstAnalyzer($context->reflection, null, 'provide', null, $scope)->analyze()`** — no model
  class (so no `ModelInspector` load) and no handler-profile override, i.e. the **default resource profile**.
  Every inference that does not involve the bound parameter is therefore identical to what
  `AstEngine::analyzeMethod()` would have produced. Multiple `return` branches merge, and any engine failure
  is caught (`safeAnalyzeBody()`) and infers nothing rather than failing the run.

### What the binding does and does not type

An **attribute read** on the bound parameter is not typed. `$model->status` reaches
`ModelAttributeResolver::resolveAttribute(Model::class, 'status')`, whose `resolveContext()` asks the
container for an instance of the abstract base and gets `BindingResolutionException: Target
[Illuminate\Database\Eloquent\Model] is not instantiable` — so it returns null before any `ModelInspector`
call, and the type pass performs no schema lookup or database round-trip at all. The read therefore infers
nothing, the key lands in `undeclaredKeys()`, and the run fails naming it unless a docblock or `#[TsCasts]`
types it. A cast types as the cast regardless (`CastHandler`): `(string) $model->status` is `string` and
`(int) $model->count` is `number`.

Binding `$model` to the **concrete** model instead was considered and declined. `model_metadata.provider_class`
is one provider for *every* model, so a body reading `$model->status` is only meaningful for models that have
that column — and PHPStan already rejects that read on a `Model`-typed parameter without an `instanceof`
guard. What a shared provider actually reads are method calls the declared-type binding already types. The
change would buy no simplification, put a schema lookup back into the type pass, and make identical provider
bodies infer differently per companion. The engine plumbing (`AnalysisScope::$varModelBindings`) accepts a
concrete class already, so this stays a one-line change if a provider author ever asks for it; the reasoning
is recorded in the plan's follow-ups ledger, not here.

Also deliberately not done: the controller handler profile (`InertiaResourcePropHandler` / `ModelFinderHandler`
have no meaning in a provider and would move inference for existing providers), and class-level `#[TsCasts]`
on the provider (`InertiaSharedDataAnalyzer` reads it; metadata reads `provide()`'s only — adding it is a
feature, not a fix).

### Inferred imports and the FQCN-keyed channel

Imports for inferred types come from `Ast\AnalysisImports::build($analysis, $namespacePath)`, after
`inferredTypeImports()` puts the analysis's FQCN channels through four steps of hygiene:

1. `forgetTsCastsCustomImports()` strips the `customImports` that `ResourceAstAnalyzer::applyTsCastsFromMethod()`
   already appended for `provide()`'s own `#[TsCasts]`. `TsCastsImportResolver` owns those imports, aliases
   included; leaving them here would emit a bare duplicate beside an aliased one.
2. `modelFqcns` and `nestedResources` are cleared. A runtime metadata array need not satisfy a model
   interface, so a model-typed value keeps failing the token check instead of quietly importing a model.
3. Enum channels are pruned by property name: a key the docblock or `#[TsCasts]` overrode, or that the payload
   never returned, must not keep an import alive for a type the file does not spell.
4. What survives is filtered again against the names actually spelled in the final inferred types, sorted, and
   keyed by path.

Step 3 has one exemption, and it is load-bearing:

```php
($analysis->directEnumFqcns[$name] ?? null) === $name
```

`DispatchesFqcnResults` keys an *embedded* enum by its own FQCN — key equals value — because the consumer
that channel was built for reads only the value. A value two direct enums can produce merges through that
channel, so its entries carry no property name to prune by. `provide(): ['role' => $model->exists ? $this->role() : $this->status()]`
infers `RoleType | StatusType`; the property `role` survives the prune on its own, but the two enum entries are
keyed `Workbench\App\Enums\Role` and `Workbench\App\Enums\Status`, neither of which is a payload key — pruning
by name would drop both imports and leave a type spelling two names the file never imports. The key-equals-value
test spares them, and the still-spelled filter in step 4 owns them instead.
`tests/Unit/Analyzers/Metadata/ModelMetadataAnalyzerTest.php` pins both halves: the union keeps both imports,
and a key named after a real class (`error`) still drops its stale channel — a `class_exists()` test in place of
the FQCN comparison would have spared that one and produced a collision the user cannot alias away.

Enum FQCNs render through `LaravelTsPublish::toTsType()`, so a `#[TsEnum]` custom name wins and the fallback is
`{Basename}Type`, resolved relative to the companion's namespace path (`../enums` from `app/models`). Value
(`AsEnum`) imports are ignored: `inferredTypeImports()` reads only `build()`'s `typeImports`, because a companion
emits `import type` lines exclusively.

### Docblock containers

Docblock shapes render through the same helpers the rest of the package uses: `array<string, T>` →
`Record<string, T>`, `list<T>` and `array<int, T>` → `T[]`, `array<array-key, T>` / `array<mixed, T>` →
`T[] | Record<string, T>` (`wrapAsMaybeKeyedArray()` — key sequentiality is not guaranteed, so neither spelling
alone is honest), nested `array{...}` → an inline object literal. A helper returning a native `array` with no
docblock infers `unknown[]`, which `inferTypes()` discards; that key is then rejected as undeclared unless the
return shape or `#[TsCasts]` names it.

## Value normalization

`normalizeMetadataValue()` is a recursive walk carrying a path (`value.nested.key`) for error messages, a depth
counter, and an object-identity stack for cycle detection.

| PHP value | Emitted as |
| --- | --- |
| `null`, `bool`, `string` | Themselves. |
| `int` | Itself, if `abs($value) <= 2^53 - 1`; otherwise rejected ("return it as a string and declare the key as string" — a string under a `number` type fails `tsc`). |
| `float` | Itself if finite (`LaravelTsPublish::toJsLiteral()` emits `json_encode()`'s shortest round-trip form); `INF` / `NAN` rejected. |
| `BackedEnum` / `UnitEnum` | `LaravelTsPublish::enumScalar()` — `->value` / `->name` — re-normalized, so an int-backed case is range-checked like any other integer. |
| `array` | Walked; each child is one nesting level deeper. `array_is_list()` decides `[…]` vs `{…}` at emit time. |
| `stdClass` | Empty → kept as the **empty-object marker**; otherwise walked as an associative array. |
| `Arrayable` / `JsonSerializable` | `toArray()` / `jsonSerialize()`, then walked again. |
| Anything else (closures, resources, other objects) | Rejected with the path. |

**An array unwrap is free; a non-array unwrap costs a level.** A wrapper around an array is not a nesting level
of its own — the array it returns charges the level — so wrapping 64 arrays in 64 `Arrayable`s nests exactly as
deep as 64 bare arrays. But a serializer that returns a *fresh object* every call crosses no array level and
repeats no object identity, so neither the depth counter nor the cycle guard would ever stop it: it would recurse
until the stack died. Charging one level for a non-array unwrap is what bounds it.
`tests/Fixtures/FreshObjectJsonSerializableMetadataValue.php` is that case, and it fails on the depth limit.

`MAX_METADATA_VALUE_DEPTH = 64` counts levels below the top-level property: 64 nests through, 65 throws naming
the model and the path. Cycle detection is a *path* stack (`$objectStack`, added on entry and removed in a
`finally`), so the same object under two sibling keys is fine and only a genuine cycle throws.

## Empty containers

PHP cannot distinguish `[]` from `{}`. Under `as const satisfies`, TypeScript accepts `readonly []` against
`T[]`, `readonly T[]`, `Array<T>`, the empty tuple, and any union containing one of those, but **not** against
`Record<K, V>` or an index signature (`TS2322`, "Index signature for type 'string' is missing in type
'readonly []'"), nor an object literal with members (`TS2741`), nor a tuple with required elements (`TS2322`,
"Source has 0 element(s) but target requires 2"). Two mechanisms cover it:

- **Explicit.** A provider returns `(object) []` (or `new stdClass`). The normalizer keeps an empty `stdClass`
  as-is and `toJsLiteral()` renders it `{}` — whatever the declared type says.
- **Type-directed.** `coerceEmptyArrays()` walks each top-level value alongside its resolved type string through
  `Support\TsTypeShape`. A bare `[]` becomes an empty `stdClass` when `TsTypeShape::isObjectLike()` holds for the
  type at that path. Nested paths use `memberType()` (object-literal member, `Record` value type, or index
  signature) and `elementType()` (array element); a PHP list consults `elementType()` first and falls back to
  `memberType()`, because a list can be typed as an object literal with numeric keys (`{ 0: …; 1: … }`) — the
  reverse has no payload it fixes.

`isObjectLike()` requires *every* non-null union arm to be object-like, so a mixed union such as
`number[] | Record<string, number>` (what `array<array-key, int>` renders as) leaves the value `[]`. So does any
opaque type: an imported `#[TsCasts]` alias, a scalar, `unknown`. `tests/Fixtures/EmptyValuesModelMetadataProvider.php`
holds one of each shape. `armIsObject()` recognises only `{…}` and `Record<`, so a `#[TsCasts]` tuple type with
required elements is not object-like: its empty array keeps `[]` and fails `tsc`. The docblock path cannot reach
that, because `array{0: X, 1: Y}` renders as the object literal `{ 0: X; 1: Y }`, which coerces correctly.

`TsTypeShape` understands only the syntax this pipeline emits — inline object literals and index signatures,
`Record<K, V>`, `T[]` / `readonly T[]` / `Array<T>` / `ReadonlyArray<T>`, tuples, and top-level unions with
`null` / `undefined`. Intersections (`A & B`) and arrow-function types are opaque, so an empty array under either
stays `[]`. It is also the home of the one top-level splitter for *TypeScript* type strings,
`TsTypeShape::splitTopLevel()`; `LaravelTsPublish::splitTopLevelUnion()` delegates to it. PHPDoc types are a
separate domain with its own splitter, `LaravelTsPublish::splitPhpDocUnionType()`.

Why a *string* at all: the engine's DTOs export only the type string, and the list-vs-object decision is made,
then stringified, at three choke points — `InlineArrayHandler` (`never[]` for a literal `[]`,
`Record<string, unknown>` for unresolved keys), `LaravelTsPublish::resolveGenericContainerType()` with
`wrapAsArray()` / `wrapAsMaybeKeyedArray()`, and `mergeTypeScriptInfos()`. It is not carried through
`ValueResult::mergeUnion()` or the handlers. A structured shape channel would have to start at those three
points; until one exists, the string is what every source has in common.

## Filenames and barrels

`ModelMetadataTransformer::filenameFor($class)` is `Str::kebab(class_basename($class)).static::FILENAME_SUFFIX`,
i.e. `user_meta` for `User`. `Str::kebab()` never *introduces* an underscore (`PostMeta` → `post-meta`), so no
model interface file can collide with a companion, and `isMetadataFilename()` can classify any barrel export by
its suffix alone. That property is what lets [the barrel writer](barrel-writer.md) split ownership of one shared
`index.ts` between two phases. `workbench/app/Models/PostMeta.php` exists to pin the near-miss (`post-meta.ts`
next to `post_meta.ts`).

Both methods read `static::FILENAME_SUFFIX`, and `filename()` dispatches through `static::filenameFor()`, so a
`transformer_class` subclass owns both the filenames it writes and the barrel exports it claims — whether it
redefines the constant or overrides the methods. `Runner::preservedModelBarrelExports()` resolves the
**configured** transformer class for exactly that reason.

`filenameFor()` and `isMetadataFilename()` are an override **pair**: the first names a companion, the second
decides whether a barrel export is one, and ownership only works while they agree. Redefining `FILENAME_SUFFIX`
keeps them in step for free. Overriding one method and inheriting the other does not, and nothing enforces it —
`Runner::validateModelMetadataTransformer()` checks only `is_a()`, which cannot see the relationship. See
[known-gaps.md](../known-gaps.md).

## Failure semantics

`Runner::generateModelMetadata()` isolates each model: a provider or transformer exception is caught, recorded in
`Runner::$modelMetadataFailures` (`subject` = model FQCN, `message` = exception class and message), and generation
continues with the next model. The barrel keeps that model's existing `_meta` export (see
[phase ownership](barrel-writer.md#phase-ownership-of-model-barrels)), the previous `{model}_meta.ts` stays on
disk untouched, and `TsPublishCommand::runAll()` reports every failure through `reportError()` (stderr under
`--quiet`, where Prompts output is suppressed) and returns `FAILURE`. A green tree plus exit 1 is the intended
outcome: the last known good companion is more useful to a running frontend than a deleted one.

`RunnerForSource` does not catch — a `--source` run fails on the first exception, and the command turns that into
`FAILURE`. A `--source` run also writes **no barrels at all**, so it can neither prune nor preserve exports.

`Runner::run()` calls `validateModelMetadataConfiguration()` before any file is written, but **only when the
phase will actually run** (`shouldPublishModelMetadata`): the provider must resolve and implement the contract,
and `generator_class` must extend `ModelMetadataGenerator`. A phase skipped by a flag is not validated — pinned by
`tests/Unit/RunnerTest.php`'s *a flag-skipped metadata phase is not validated*. `WatcherJsonWriter` follows
config rather than the run flags and so can meet an invalid provider that no validation has seen; it catches the
resolution failure itself and watches no provider file, leaving the run that publishes metadata to fail loudly.

## Cache

`ModelMetadataGenerator` implements `ProvidesCacheSignature`. `cacheSignature()` exists because metadata can
depend on inputs that live in no file the dependency recorder can see — `Relation::morphMap()` registered in a
service provider being the motivating one. It calls `provide()` and hashes `[$providerClass, $payload]` with
`xxh128`, so a payload change busts the entry even when no dependency file changed. `provide()` is called
*outside* the `try`: a provider that throws is a real failure the runner must record, not a cache miss to swallow.
An unserializable payload (a closure inside it) yields a random signature — a deliberate permanent miss rather
than a hash that cannot be trusted.

Two consequences worth knowing before changing this:

- **`provide()` runs twice per model on a cache-missing run** — once in `cacheSignature()`, once in
  `collectMetadata()`. Sharing the payload needs a per-run static stash the runner resets, and `--source` runs
  never call `cacheSignature()` at all.
- **The hash covers the provider's *raw* payload.** An `Arrayable` / `JsonSerializable` value is fingerprinted by
  its serialized object state, not by what `toArray()` / `jsonSerialize()` returns. If that output depends on
  anything but the object's own properties, the companion can go stale; return an array from the provider
  instead. Hashing the normalized payload means lifting `normalizeMetadataValue()` out of the transformer into
  something the generator can call.

Both are recorded as deferred, with their reasoning, in the plan's follow-ups ledger.

Manifest entries are keyed `GeneratorFQCN::ModelFQCN` before `GenerationManifest::entryKey()` hashes them, which
is what keeps one model's interface entry and its metadata entry apart.

## Configuration surface

| Key | Default | Notes |
| --- | --- | --- |
| `enabled` | `false` | Independent of `models.enabled`. |
| `provider_class` | `DefaultModelMetadataProvider` | Emits `morphClass: string` (the morph-map alias when one is configured). The default is applied in `ModelMetadataProviderResolver`; the shipped config file shows the key commented out and imports nothing — the file's convention for every `*_class` override. |
| `template` | `laravel-ts-publish::model-meta` | Blade view receiving `TsModelMetadataDto` as `$data`. |
| `included` / `excluded` / `additional_directories` | inherit from `models.*` | `ModelMetadataCollector::finderSettings()` overrides a setting only when `Config::has()` says the `model_metadata` key was explicitly set — including when it is set to `[]`. |
| `collector_class` / `generator_class` / `transformer_class` / `writer_class` | the package classes | Each is read with an inline default at its point of use. |

Every read passes an inline default because the `mergeConfigFrom()` behind Spatie's `hasConfigFile()` is a
one-level `array_merge`: a user-supplied partial `model_metadata` block replaces the package's whole block, and a
config cached from a release that predates the block has none of it.

## Known gaps

Four entries in [known-gaps.md](../known-gaps.md) belong to this phase. Each names the mechanism that would have
to change, so read them there rather than re-deriving them:

- **Two same-named enums in one companion collide instead of aliasing.** The inferred-import channel prunes and
  filters by the *rendered* name, while the exempted entries are keyed by FQCN — so it cannot tell a stale
  `StatusType` from a live one. Fixing it means carrying the FQCN alongside the rendered name through the prune
  and routing inferred imports through the same alias resolver the cast imports use.
- **An empty `[]` under an imported type alias still ships as `[]`.** `TsTypeShape` reads type *strings*; a bare
  imported identifier is opaque to it. Fixing it means resolving the alias, which puts module resolution inside a
  transformer that today does pure string inspection. Return `(object) []` instead.
- **A body-inferred enum that enum publishing excludes imports a file that is never written.**
  `AnalysisImports` resolves the path from the enum's namespace and cannot know about `enums.excluded`,
  `#[TsExclude]`, or a directory outside `enums.additional_directories`. Resources have
  `PublishedResourceRegistry` for this gate; enums have no equivalent.
- **A model class name containing an underscore can collide with a companion.** `User_meta` kebabs to `user_meta`.
  PSR-1 class names carry no underscores, so this is accepted rather than guarded.

## Tests

- `tests/Unit/Analyzers/Metadata/ModelMetadataAnalyzerTest.php` — precedence, the parameter binding, inherited and
  trait-supplied bodies, and every import-channel rule above.
- `tests/Unit/Transformers/ModelMetadataTransformerTest.php` — value normalization, depth and cycle limits, empty
  containers, import collisions, and the validation messages.
- `tests/Unit/Writers/ModelMetadataWriterTest.php` — rendering and the `*_class` / `template` overrides. Most of
  these set `output_to_files` false, so the companion they render never reaches the tree the token gate compiles
  — read a green gate as saying nothing about those shapes either way.
- `tests/Unit/RunnerTest.php` — validate-before-generate, per-model failure isolation, and barrel ownership.
- `tests/Feature/Commands/TsPublishCommandTest.php` — the flags, the non-zero exit, and `--only-*` sequences over a
  real temp directory.

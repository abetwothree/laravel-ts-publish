# Model metadata

Model metadata is the publishing phase that writes a runtime companion, `{model}_meta.ts`, beside each model interface.
A companion is one `as const satisfies` object, as in the committed
[`user_meta.ts`](../../workbench/resources/js/types/data/default-example/app/models/user_meta.ts): its values come
from a `ModelMetadataProvider`, and its type comes from `#[TsCasts]`, the provider's docblock, or body inference.
[`Runner::generateModelMetadata()`](../../src/Runners/Runner.php) runs the phase independently of model interfaces,
and the two phases meet only in the barrel they share. Usage is on the tolki
[Model Metadata](https://tolki.abe.dev/ts/model-metadata.html) page.

## Where things live

The collector, generator, transformer and writer are each swappable through `ts-publish.model_metadata.*_class`. The
phase lives in these files:

- [`ModelMetadataTransformer`](../../src/Transformers/ModelMetadataTransformer.php): collects and validates the
  payload, resolves imports, normalizes values, and names the companion file.
- [`ModelMetadataAnalyzer`](../../src/Analyzers/Metadata/ModelMetadataAnalyzer.php): the static type side, returned as
  a [`ModelMetadataAnalysis`](../../src/Analyzers/Metadata/ModelMetadataAnalysis.php).
- [`ModelMetadataProviderResolver`](../../src/Metadata/ModelMetadataProviderResolver.php): resolves `provider_class`
  and rejects a class that does not implement the contract.
- [`ModelMetadataGenerator`](../../src/Generators/ModelMetadataGenerator.php): renders through the writer and owns the
  cache signature.
- [`ModelMetadataCollector`](../../src/Collectors/ModelMetadataCollector.php): picks the models that get a companion.
- [`TsTypeShape`](../../src/Support/TsTypeShape.php): reads a type string to decide empty-array coercion.
- [`TsCastsImportResolver`](../../src/Support/TsCastsImportResolver.php): aliases the `#[TsCasts]` imports.
- [`ModelMetadataWriter`](../../src/Writers/ModelMetadataWriter.php): renders
  [`model-meta.blade.php`](../../resources/views/model-meta.blade.php), which emits the `import type` lines, the value
  object and the `satisfies` shape.

The token gate compiles the committed `user_meta.ts`, but most `ModelMetadataWriterTest` cases set `output_to_files`
to false, so a green gate says nothing about the shapes only those tests render.

## Type precedence

For each key, `#[TsCasts]` on `provide()` beats the `@return array{...}` shape, which beats body inference.
`ModelMetadataAnalyzer::mergeTypes()` records which source won each key, and three rules follow from the sources:

- **Required keys**: the docblock makes a key required unless it spells `key?:`. A cast's own `optional` flag
  overrides that, a cast without the flag keeps the docblock's answer, and a cast on a key the docblock omits is
  required. So a cast on a key some payloads leave out fails every model that leaves it out, unless the cast says
  `optional: true` or the docblock says `key?:`.
- **Imports**: a docblock string carries no FQCN, so a class or enum named there has no import and fails the token
  check unless a cast covers it. `ModelMetadataAnalysis::importFreeKeys()` returns every key no cast typed, and the
  token check runs over those.
- **Inference**: body inference keeps only the keys the payload returned and discards any type containing `unknown`.
  Such a key then needs a docblock or a cast, or the run fails naming it.

`ModelMetadataTransformer::validateProperties()` runs before `resolveImports()`, which writes a type for every payload
key and would fail on a key no source typed.

### Body inference is an engine consumer

`ModelMetadataAnalyzer::analyzeBody()` does not call `AstEngine::analyzeMethod()`, which seeds no bindings. It locates
and binds `provide()` itself:

- **Declaring class**: it locates `provide()` on the class that declares it, so `self::`, `parent::` and
  `$this->method()` resolve against the file the body lives in. No test distinguishes this from the configured class.
- **`locate()`, not `locateOwn()`**: for a trait method the declaring class is the using class, whose file holds no
  `provide()` node, and `locateOwn()` declines that shape. `ProvidesTraitModelMetadata` pins the trait case.
- **Declared-type binding**: `AstEngine::bindingsFor()` binds the `Model $model` parameter to `Model`. Method calls on
  it reflect Laravel's own docblocks, so `getTable()`, `getKeyName()`, `getRouteKeyName()` and `getMorphClass()` infer
  `string`.
- **Located context**: it hands the located context to `ResourceAstAnalyzer`, whose `analyze()` would otherwise run
  `locateOwn()` again and decline the trait file. The run has no model class and uses the default resource profile, so
  anything not involving `$model` infers as `analyzeMethod()` would. An engine failure infers nothing.

An attribute read such as `$model->status` infers nothing. `ModelAttributeResolver` cannot instantiate the abstract
`Model`, so no schema lookup happens. A cast still types, as in `(string) $model->status`. Keep the binding on the
declared type. One provider serves every model, PHPStan already rejects that read on a `Model` parameter, and a
concrete binding would put a schema lookup back into the type pass. `AnalysisScope::$varModelBindings` already accepts
a concrete class, so it stays a one-line change if a provider author asks for it. The body also skips the controller
handler profile, whose handlers mean nothing in a provider. It reads `#[TsCasts]` from `provide()` only, on purpose.
`InertiaSharedDataAnalyzer` also reads class-level casts, but adding that here would be a new feature, not a fix.

### Inferred imports

`ModelMetadataAnalyzer::inferredTypeImports()` passes `Ast\AnalysisImports::build()` only the channels the inferred
types need, in four steps:

1. It strips the `customImports` that `ResourceAstAnalyzer::applyTsCastsFromMethod()` added for `provide()`'s own
   `#[TsCasts]`, because `TsCastsImportResolver` owns and aliases those.
2. It clears `modelFqcns` and `nestedResources`. A metadata value need not satisfy a model interface, so a model-typed
   value keeps failing the token check instead of importing a model.
3. It prunes each enum channel whose key the docblock or a cast overrode, or the payload never returned.
4. It keeps only the names the inferred types still spell. A companion emits `import type` lines only, so `AsEnum`
   value imports are ignored.

Step 3 spares an entry whose key equals its value. `DispatchesFqcnResults` keys an embedded enum by its own FQCN, so a
two-enum union has no property name to prune by, and step 4 owns those entries. Don't swap that test for
`class_exists()`, or a payload key named after a real class, such as `error`, keeps a stale channel.
`ModelMetadataAnalyzerTest` pins both halves.

## Empty containers

PHP cannot tell `[]` from `{}`, and `readonly []` fails `tsc` against a `Record`, an index signature, an object literal
with members, or a tuple with required elements. A provider can return `(object) []`, which
`ModelMetadataTransformer::normalizeMetadataValue()` keeps as the one PHP value that spells `{}`. Otherwise
`coerceEmptyArrays()` spells a bare `[]` as `{}` where `TsTypeShape::isObjectLike()` holds for the type at that path.
A PHP list asks `TsTypeShape::elementType()` first and falls back to `memberType()`, because a list can be typed as an
object literal with numeric keys.

`isObjectLike()` requires every non-null arm to be an object literal or a `Record`, so a mixed union, an imported alias
or a `#[TsCasts]` tuple keeps `[]`. Coercion reads the type string because the engine's DTOs export nothing else. The
list-or-object decision is stringified in `InlineArrayHandler`, in `LaravelTsPublish::resolveGenericContainerType()`
and in `mergeTypeScriptInfos()`, so a structured shape channel would have to start at those three points.

## Filenames and barrels

`ModelMetadataTransformer::filenameFor()` is the kebab-cased class basename plus `FILENAME_SUFFIX` (`_meta`).
`Str::kebab()` never introduces an underscore, so `PostMeta` becomes `post-meta` and cannot collide with a companion,
and `isMetadataFilename()` can classify a barrel export by its suffix alone. The model barrel's ownership rules are in
[BarrelWriter § Phase ownership of model barrels](barrel-writer.md#phase-ownership-of-model-barrels).

Both methods read `static::FILENAME_SUFFIX`, and `filename()` dispatches through `static::filenameFor()`, so a
`transformer_class` subclass owns both the files it writes and the exports it claims.
`Runner::preservedModelBarrelExports()` asks the configured transformer for that reason. The two methods are an
override pair. Redefining the suffix keeps them in step, but overriding one and inheriting the other orphans
companions, and `Runner::validateModelMetadataTransformer()` checks only `is_a()`. The test fixture
`PrefixedModelMetadataTransformer` overrides the pair correctly.

## Failures and validation

`Runner::generateModelMetadata()` isolates each model. A provider or transformer exception goes into
`Runner::$modelMetadataFailures`, the model's existing companion and barrel export are kept, and
`TsPublishCommand::runAll()` reports each failure through `reportError()` and returns `FAILURE`. A green tree with exit
code 1 is intended, because the last good companion is more useful to a running frontend than a deleted one.
`RunnerForSource` catches nothing and writes no barrels.

`Runner::run()` validates before it writes any file. A run that publishes metadata checks the provider,
`generator_class` and `transformer_class`. A run that skips metadata but publishes models still checks
`transformer_class`, because the barrel asks it which exports metadata owns, and it never resolves the provider.
`WatcherJsonWriter` follows config rather than run flags, so it catches an unresolvable provider itself and watches no
provider file.

## Cache

`ModelMetadataGenerator::cacheSignature()` hashes the provider class and its payload, because metadata can depend on
input that no dependency file records, such as a `Relation::morphMap()` registered in a service provider. It calls
`provide()` outside its `try`, because a throwing provider is a failure the runner must record, not a cache miss. An
unserializable payload gets a random signature, a deliberate permanent miss. Two limits are deliberate:

- **Two calls**: `provide()` runs twice per model on a cache miss, once here and once in `collectMetadata()`. Sharing
  the payload would need a per-run stash the runner resets, and a `--source` run never calls `cacheSignature()`.
- **Raw payload**: an `Arrayable` or `JsonSerializable` value is fingerprinted by its object state, not by what it
  serializes to. Hashing the normalized payload means moving `normalizeMetadataValue()` somewhere the generator can
  call.

`BaseRunner::cachedGenerate()` keys each cache entry by generator class and model, which keeps a model's interface
entry apart from its metadata entry.

## Configuration

`ModelMetadataCollector::finderSettings()` inherits `included`, `excluded` and `additional_directories` from
`models.*`, and overrides one only when `Config::has()` finds it under `model_metadata`, even when it is `[]`. Every
`model_metadata` read passes an inline default. Spatie's `hasConfigFile()` merges config one level deep, so a partial
user block replaces the package's whole block, and a config cached before the block existed has none of it. The phase
is independent of `models.enabled`, and `--only-functional` publishes it while it skips model interfaces.

## Related

The barrel rules are on [BarrelWriter](barrel-writer.md#phase-ownership-of-model-barrels), and `bindingsFor()`'s other
consumers are on [AST engine](ast-engine.md). These [known gaps](../known-gaps.md) belong to this phase:

- [Two same-named enums collide instead of aliasing: a companion throws,
  a](../known-gaps.md#two-same-named-enums-collide-instead-of-aliasing-a-companion-throws-a-route-file-ships-invalid-ts)
  route file ships invalid TS
- [An empty `[]` under an imported type alias still ships as
  `[]`](../known-gaps.md#an-empty--under-an-imported-type-alias-still-ships-as-)
- [A body-inferred metadata enum that enum publishing excludes imports a file that
  is](../known-gaps.md#a-body-inferred-metadata-enum-that-enum-publishing-excludes-imports-a-file-that-is-never-written)
  never written
- [A model class name containing an underscore can collide with a metadata
  companion](../known-gaps.md#a-model-class-name-containing-an-underscore-can-collide-with-a-metadata-companion)
- [A `transformer_class` that overrides only
  one](../known-gaps.md#a-transformer_class-that-overrides-only-one-of-the-two-filename-methods-orphans-its-companions)
  of the two filename methods orphans its companions

# Known gaps

**What this file is for.** It carries the accepted limits that someone working from a fresh clone cannot
discover any other way — you have the code and the test suite, but not the working notes of whoever
deferred the work. Two kinds of entry earn a place here:

- **It changes what you get out of the package.** A shape the generator types as `unknown`, `null`, or an
  empty interface, and a user would otherwise file as a bug.
- **It means a green signal is narrower than it looks.** A gate that passes without checking what you
  would assume it checks.

Nothing here is a regression. These are limits that were understood and accepted.

**What does not belong here.** Internal refactor debt, test-suite quality notes, release chores, and
anything whose real audience is "whoever picks that work back up" — those live with the plan that deferred
them, under its Follow-Ups Ledger, which is where that work is actually re-read from. Filing them here
buries them. If you decline something a reviewer raised, ask which of the two bullets above it satisfies;
if neither, it does not go in this file.

## Types the generator will not give you

### `config()` on an absent key with no default types as null

`config('key', $default)` types from the default expression only when the key is absent; a key set to
`null` types as `null`, and named arguments are honoured. The one residual: `config('unset.key')` with
no default types as `null`, which is the live value on the generating machine. All reads follow that
machine's config, so a `.env` that differs from production changes the emitted type.

### On Laravel 12, `#[Collects]` cannot be resolved — use the `$collects` property

`#[Collects]` is a Laravel 13 attribute. On Laravel 12 the class does not exist, so a `ResourceCollection`
that names its collected resource *only* that way resolves to nothing, and the generated type degrades from
`export type PostFlatCollection = PostResource[];` to an `export interface PostFlatCollection` with no
members. That is worse than it looks: an empty interface accepts `42` and `"str"` under `tsc --strict` —
it rejects only `null` and `undefined` — so the type reads as specific while checking almost nothing.

Laravel's own `collects()` cannot resolve the attribute on 12 either, so the package mirrors the framework
rather than guessing. **The workaround is fully supported:** `public $collects = PostResource::class;` works
on both versions, as does the `FooCollection` → `FooResource` naming convention. The guard is in
`src/Analyzers/Concerns/InspectsAstNodes.php`; see
[docs/laravel-version-guards.md](./laravel-version-guards.md) for how the version floor was established and
which tests are skipped below it.

### `EnumResource::collection()` inside a mixed ternary, nested one level down

Task 28 fixed `ResourceTransformer::rewriteEnumResourceTypes()`'s top-level `$isMixed` branch, which
assumed the wrapped arm of a mixed EnumResource/direct-access ternary was always scalar.
`InlineArrayHandler::expandMixedEnumType()` (`src/Ast/Handlers/InlineArrayHandler.php`) has the same
defect for the identical ternary shape nested inside an inline array literal, and there it is worse:
when both arms independently render the same array-shaped type string — an
`EnumResource::collection()` wrap and a direct read of an already-list accessor, both `X[]` — the
merge that builds the property's type collapses them to one member before `expandMixedEnumType()`
ever runs, so it substitutes that single member and the direct arm's own presence in the union is
lost outright, not just under-suffixed. Verified against a throwaway fixture during Task 28's fix
round; not reproduced as a committed test or golden-tree property, so nothing here pins it yet.

### A broadcast event's own uninitialized typed property still types as required

Task 29 made an uninitialized typed public property optional wherever a class's shape is inlined —
`LaravelTsPublish::publicPropertyShapeType()` (`src/LaravelTsPublish.php`), reached from `toTsType()`'s
step 5c and from `arrayableShapeType()`'s no-docblock fallback. A broadcast event's own top-level
property list never reaches that method: `AstEngine::analyzePublicProperties()` reflects the event
class directly and hardcodes `'optional' => false` for every property, regardless of whether reflection
says the property was ever assigned. An event with `public Carbon $occurredAt;` and no default therefore
still emits `occurredAt: string;` in its generated `.ts` file — required — even though `json_encode()`
would omit the key exactly as Task 29's fix accounts for everywhere else. Verified against a throwaway
event fixture during Task 29's fix round, then removed once it stopped pinning anything the golden tree
would show; not reproduced as a committed test or golden-tree property, so nothing here pins it yet.
Fixing it means threading the same `hasDefaultValue()`/`isPromoted()` check into
`analyzePublicProperties()`, which is a change to every existing broadcast event's blast radius, not a
one-fixture addition — worth doing as its own task. `analyzePublicProperties()`'s own docblock already
states it never marks a property optional — nullability is `| null`, optionality is a `#[TsCasts]`
concern — so a future fix has to reconcile that deliberate boundary rather than be surprised by it. A
related case is inherent rather than fixable: a public non-promoted `readonly` property that a
hand-written constructor always assigns still renders `?:`, because a `readonly` property cannot carry
a declaration default for static reflection to read — that `readonly` form is absent from the corpus.
The same imprecision without `readonly` is present: `DeferredAssignmentDto::$assignedLater` is assigned
by every construction and still emits `assignedLater?`, which is what lets it nest a `?:` inside a shape
value for `NestedOptionalKeyDto`. It is deliberate there — the fixture needs an optional key — but it is
the same heuristic, so a future fix to optionality has to expect that fixture to move.

### `#[TsCasts]` and the top-level spread flatten disagree by scope, in three separate ways

Task 32 flattens a top-level `...SomeResource::make(...)->resolve()`, `...$model->toArray()`, or
`...$collection->toArray()` spread into the host resource's own properties
(`ResourceAstAnalyzer::analyzeSpreadArm()` and its three arm builders, `src/Analyzers/ResourceAstAnalyzer.php`).
Only one of the three places `#[TsCasts]` can apply is wired up for it, and a fourth interaction —
unrelated to spreading — makes the gap sharper than "missing", not just narrower than it looks.

- **A spread resource's own `#[TsCasts]` is skipped.** `analyzeResourceSpreadArm()` calls
  `AstEngine::analyzeMethod($resourceFqcn, 'toArray')`, which runs `ResourceAstAnalyzer::analyze()`
  directly — never `ResourceTransformer::parseResourceTsCastsOverrides()`/`applyOverrides()`, which is
  where a resource's own `#[TsCasts]` attribute is read and applied. A `#[TsCasts]` override declared on
  `PostResource` itself would apply when `PostResource.ts` is generated standalone, and silently not
  apply to the identical property once `PostResource` is spread into another resource.
- **The spread resource's own backing model's `#[TsCasts]` is skipped for the same reason** — it is
  `ResourceTransformer::parseModelTsCastsOverrides()` that reads it, and the resource arm never reaches
  that transformer either.
- **The model and collection arms *do* apply the spread target's own `#[TsCasts]`** (`analyzeModelSpreadArm()`,
  same file) — that half is fixed, and it is why `User::options`, cast to a plain array at the DB/Eloquent
  level, still flattens as `Record<string, unknown> | null` and not `unknown[] | null`: `Address::$appends`'s
  `full_address` gets the same treatment.
- **The host resource's own `#[TsCasts]` is applied by property name, blind to where the property actually
  came from.** `ResourceTransformer::applyOverrides()` walks `$this->modelTsCastsOverrides` (from the *host*
  resource's own backing model) and rewrites `$this->properties[$property]` by name alone — it has no
  notion that a flattened property named `created_at` or `settings` came from a *different* model than
  the host's own. A host resource's `#[TsCasts]` entry for `created_at` — a common override, since raw
  `datetime` casts rarely need one but developers add them anyway for consistency — silently overwrites
  a same-named column flattened from an entirely unrelated spread arm, in whichever direction the
  override happens to point.

None of this is a regression to `unknown`: every case above still emits a real, plausible-looking type —
just possibly the wrong one, or missing a refinement its own standalone file carries. Fixing the first two
cleanly means giving the resource arm a path to the spread resource's and its model's own overrides without
re-running the whole `ResourceTransformer` pipeline recursively; fixing the fourth means `applyOverrides()`
knowing which flattened properties are actually the host's own versus foreign, which the current
`ResourceAnalysis::properties` list (name/type/optional/description only) does not carry. Both are scope
changes to existing, working code paths, not one-fixture additions — worth doing as their own task.

### `$request->validated('key')` only types a literal, top-level key

A literal, top-level key on a bound `FormRequest` types from `rules()` — `$request->validated('title')`
reads `StorePostRequest::rules()` through `FormRequestRulesAnalyzer`, the same analyzer the form request's
own generated interface uses (`requestMethodRule()` in `src/Ast/Handlers/KnownMethodRuleHandler.php`). Two
shapes short of that decline or diverge silently rather than fixing:

- **A dotted key declines even when the rule defines it.** `FormRequestRulesAnalyzer::analyze()` returns
  only the top-level trie nodes: a nested rule like `'options.default' => ['string']` composes into
  `options`'s own object type, but never surfaces as its own `fieldPath` entry in the returned list.
  `$request->validated('options.default')` on `Workbench\App\Http\Requests\NestedEdgeCasesRequest` — whose
  `rules()` declares exactly that key — resolves to `null` and the property types as `unknown`, though the
  rule is both defined and resolvable. Verified directly: the handler returns `null` for that call.
  Nested validation rules (`'address.street' => 'required|string'`) are routine Laravel, so a user is
  likely to hit this immediately after learning `validated()` is typed at all.
- **A `#[TsCasts]` override on the form request is not honoured.** `StorePostRequest::rating` carries a
  `#[TsCasts]` override to `number | bigint`; `$request->validated('rating')` types as the raw-rule
  `number | null` instead — verified directly: the handler returns `['type' => 'number | null', 'optional'
  => true]`, not the override. The override is applied by `FormRequestTransformer::applyTsCastsOverrides()`
  when the form request's own `.ts` interface is generated, a call site `KnownMethodRuleHandler` never
  reaches. Same shape as the entry above: two generated descriptions of the same field, disagreeing.

Fixing the first means flattening `analyze()`'s trie output (or walking it by dotted path) instead of
scanning only its top-level nodes; fixing the second means either routing through
`FormRequestTransformer`'s override application or duplicating its `#[TsCasts]` parsing at this call site.
Both are scope changes beyond the single literal top-level key this call site was built for.

### Inertia shared data does not rewrite `EnumResource` types for Tolki

An `EnumResource::make(...)` returned from `HandleInertiaRequests::share()` is analyzed as its bare enum
type by `InertiaSharedDataAnalyzer::buildTypeImports()`, but with Tolki enabled the shared-data analyzer
neither rewrites it to `AsEnum<typeof Enum>` nor emits the enum's value import. This predates the
`typeImports` consolidation: the removed `importStatements` channel was generated only from `#[TsCasts]`
and contained only `import type` lines.

Use an import-aware `#[TsCasts]` override for that shared property. Supporting the serialized enum shape
requires the same type-rewrite and separate value-import pipeline used by resource generation; moving the
value import into `typeImports` would be incorrect.

### Two same-named enums in one metadata companion collide instead of aliasing

Model metadata imports the enums body inference resolves, so a value the AST reads as an enum contributes
an `import type` line of its own. Those inferred imports are pruned by property name, except when the
engine has no property name to prune by: a value two direct enums can produce merges through
`embeddedEnumFqcns`, and `DispatchesFqcnResults::dispatchFqcnResults()` keys those by FQCN.
`ModelMetadataAnalyzer::inferredTypeImports()` spares
FQCN-keyed entries deliberately — pruning them by key would drop the union's own imports — which leaves
the still-spelled filter as their only owner, and that filter matches the *rendered* TypeScript name, not
the FQCN. Two enums with the same basename in different namespaces both render `StatusType`, so it cannot
tell a stale channel from a live one.

A provider whose `@return array{...}` retypes a key holding `App\Status | Crm\Status`, alongside a live key
typed `Crm\Status`, therefore emits `StatusType` from both paths and trips the collision guard in
`ModelMetadataTransformer::resolveImports()`:

```
Model metadata for model [App\Models\User] imports [StatusType] from both [../../crm/enums]
and [../enums]; declare one of them with an import-aware #[TsCasts] whose type is a distinct name
that module exports.
```

This is a property of the inferred-import channel, not of the union case alone: two *live* keys typed
`App\Status` and `Crm\Status` fail the same way. Re-declaring one of the two keys as
`['type' => 'StatusType', 'import' => '../enums']` still collides, because `TsCastsImportResolver` aliases
only when two *cast* entries share a name and cannot see the inferred import at all — which is why the
guard asks for a *distinct* name that the module really exports:
`['type' => 'AppStatusType', 'import' => '@/types/app-status']`, with that module re-exporting
`export type { StatusType as AppStatusType }`. Writing the alias inline as `'Status as AppStatusType'` is
not a substitute — it lands verbatim in the property type and emits invalid TypeScript.

On the docblock-displaced union there is no handle at all: the channel is keyed by FQCN, so moving the key
to `#[TsCasts]` does not prune it either. Drop that key to a single enum, which restores a property name
for the prune to match.

Fixing it means carrying the FQCN alongside the rendered name through the prune so the filter can compare
identities rather than basenames, and then routing inferred imports through the same alias resolver the
cast imports use. That is a channel change, not a patch at the filter.

### An empty `[]` under an imported type alias still ships as `[]`

Model metadata coerces an empty PHP array to `{}` wherever the property's resolved TypeScript type is
object-like, because PHP cannot tell an empty map from an empty list and `[]` does not satisfy `Record<>`
or an object literal. The decision is made by `TsTypeShape::isObjectLike()` reading the type *string*
(`ModelMetadataTransformer::coerceEmptyArray()`), and
a bare imported identifier is opaque to it — `TsTypeShape::armIsObject()` recognises only `{...}` and
`Record<`. A `#[TsCasts]` type that names an imported alias therefore keeps `[]`,
however object-like the alias resolves to on the TypeScript side.

`EmptyValuesModelMetadataProvider`'s `opaque` key pins exactly that shape — `'opaque' =>
['type' => 'OpaqueShape', 'import' => '@/types/opaque-shape']` holding `[]`. Point the alias at the
map it reads as (`export type OpaqueShape = Record<string, unknown>;`) and the emitted companion fails:

```
error TS2322: Type 'readonly []' is not assignable to type 'OpaqueShape'.
  Index signature for type 'string' is missing in type 'readonly []'.
```

Every other property in that same companion type-checks clean, so this is the residue of a bug that used to
hit every object-like property, not a new one. **The workaround is to return `(object) []`**, which the
provider may now do explicitly and which survives coercion untouched.

No gate catches it. The writer tests that render this companion all run with `ts-publish.output_to_files`
false (`ModelMetadataWriterTest` → *renders empty containers in the spelling their types require*), so
the file never reaches the generated tree
the token gate compiles — read a green gate as saying nothing about this case either way.

Fixing it means resolving the alias to a type the shape inspector can read, which puts a module-resolution
step inside a transformer that today does pure string inspection. Widening `isObjectLike()` to guess that
any unknown identifier is object-like is not the fix: it would spell `{}` for an alias of `string[]`,
turning a narrow wrong answer into a broad one.

### A body-inferred metadata enum that enum publishing excludes imports a file that is never written

`ModelMetadataAnalyzer::inferredTypeImports()` in `src/Analyzers/Metadata/ModelMetadataAnalyzer.php` turns an
enum a provider body returns into `import type { XType } from '../enums'` through `Ast\AnalysisImports`, which
resolves the path from the enum's namespace and does not know whether `enums.excluded`, `#[TsExclude]`, or a
directory outside `enums.additional_directories` keeps that enum out of the published tree. The companion then
fails `tsc` — `TS2305` where the namespace published a barrel without that member, `TS2307` where it published
nothing at all — rather than `ts:publish` failing. Include the enum, or declare the property with an
import-aware `#[TsCasts]`. Resources have `PublishedResourceRegistry` for this gate; enums do not.

### A model class name containing an underscore can collide with a metadata companion

Companion files are `Str::kebab(ModelName).'_meta'`, and `Str::kebab()` never produces an underscore, so
`UserMeta` (`user-meta.ts`) cannot collide with `User`'s `user_meta.ts`. A class literally named `User_meta`
kebabs to `user_meta` and would share the companion's filename: the phase that writes last wins the file, the
barrel carries one export for two things, and `ModelMetadataTransformer::isMetadataFilename()` hands that
export to the metadata phase. PSR-1 class names do not carry underscores, so this is accepted rather than
guarded. The rule lives at `ModelMetadataTransformer::FILENAME_SUFFIX` in
`src/Transformers/ModelMetadataTransformer.php`.

### A `transformer_class` that overrides only one of the two filename methods orphans its companions

`ModelMetadataTransformer::filenameFor()` names a companion and `isMetadataFilename()` decides whether a barrel
export is one. Barrel ownership holds only while the two agree, and nothing enforces that they do:
`Runner::validateModelMetadataTransformer()` checks `is_a()`, which cannot see a relationship between two static
methods.

Redefining `FILENAME_SUFFIX` keeps them in step for free, because both read it through `static::`. Overriding one
method and inheriting the other does not. A subclass overriding only `filenameFor()` writes `meta.user.ts` while
the inherited predicate still asks `str_ends_with($filename, '_meta')`, so on a run that skips the metadata phase
the export is pruned from the barrel and the file is left orphaned on disk. Overriding only `isMetadataFilename()`
fails symmetrically: the phase claims exports it never wrote.

This is not a consequence of dispatching `filename()` through `static::` — a half-overridden pair was already
broken before that, in the failed-model preservation path, where `$transformerClass::filenameFor()` produced a
name that never matched the written file. `static::` makes both paths fail consistently rather than one of them
succeed by accident.

Override the pair together. `tests/Fixtures/PrefixedModelMetadataTransformer.php` is the worked example.

### A trait-supplied `provide()` in its own file contributes no inferred types

`MethodLocator::findIn()` declines when the method's declaring file is not the class's own file, before it
parses anything. A trait method's declaring file is the *trait's*, so a trait living in its own file — the
ordinary way to ship one — always declines. `ModelMetadataAnalyzer::analyzeBody()` then falls back to
`AstEngine::analyzeMethod()`, which seeds no `$model` binding.

The consequence is wider than `$model` calls: that fallback contributes **no inferred types at all**. Every
key needs a docblock or `#[TsCasts]`, including plain literals. `TraitModelMetadataProvider` pins it — its
analysis is `['label' => 'docblock', 'flag' => 'casts']` with `table` undeclared, and `label` is a string
literal typed only because the docblock names it.

Only a *separate-file* trait degrades. A trait declared in the same file as the class using it binds and
infers normally, so the shape is about file layout rather than traits as such.

Closing it is a two-part change this package does not have today. `analyzeBody()` locates a `MethodContext`,
uses it only for `bindingsFor()`, then discards the node; `ResourceAstAnalyzer::analyze()` re-runs
`locateOwn()` for itself and misses again. It would need `ResourceAstAnalyzer` to accept a pre-located
context, and `analyzeBody()` to switch from `locateOwn()` to `locate()`.

Note that `docs/components/model-metadata.md` still explains this gap by the mechanism that preceded
`MethodLocator`'s end-line matching — it says `locateOwn()` searches the using class's own file and finds no
`provide()` node there. The outcome it describes is right for this shape, but the reasoning is not: the
decline now happens from reflection alone. The old wording also predicts a *bind* when the using class's file
happens to declare an unrelated `provide()`, which is exactly the case that used to bind to the wrong body.

## Deliberate non-goals

Absent on purpose. Do not "fix" these without raising it first.

- **Non-Inertia and JSON responses are never typed.** Only `Inertia::render()` page props and the
  shared-data middleware are analyzed.
- **No `ts-publish.analyzer.handlers` config key, and no supported extension of the AST engine.** The
  only user-facing surface of the engine is `AstEngine`. Every handler, concern, resolver and value
  object under `src/Ast/` is internal and changes without notice as inference grows; nothing there is a
  compatibility promise, and code that extends it is on its own.
- **Form requests stay runtime.** They are resolved by instantiating and calling `rules()`, on purpose.
- **Collector class maps are not invalidated mid-process.** `CoreCollector::classMap()` scans each directory
  once per process, and `Runner::run()` / `RunnerForSource::run()` clear it first, so a `ts:publish` run
  always reads the disk. Host code that calls `collect()` or `allows()` directly on either side of writing a
  `.php` file — a custom collector, a `tinker` or test-helper loop that generates a model and re-collects —
  gets the pre-write answer from both. Call `CoreCollector::flushClassMapCache()` between the write and the
  second call. Stat-based invalidation would charge every run for a case no package run path reaches:
  nothing in `src/` writes a `.php` file.

## Green signals that are narrower than they look

### No metadata test exercises two `provide()` methods in one file

`MethodLocator` itself is well covered: `MethodLocatorTest` asserts `locateOwn()` against two classes sharing
a file, a method nested in an earlier anonymous class, and a trait method competing with an unrelated class
declared before it.

What nothing pins is the metadata phase's *dependence* on that disambiguation. No file in `src/`, `tests/` or
`workbench/` declares two `function provide(`, so no metadata fixture reaches the path
`ModelMetadataAnalyzer::analyzeBody()` actually relies on. That matters more than an ordinary coverage hole
because of how this used to fail: before the end-line match, a provider sharing a file with an earlier
same-named method had the *wrong body* analyzed, and the run published those types with `undeclaredKeys`
empty — no exception, no gate signal, nothing to notice. A regression would be equally quiet.

Closing it costs one fixture: a provider whose file declares a decoy `provide()` first, plus a case in
`ModelMetadataAnalyzerTest` asserting the real body's types.

### Handler ordering is pinned pairwise, corpus-bounded

Nine of the twenty-four handlers in the resource profile claim `MethodCall`
(`src/Ast/ResourceExpressionHandlers.php`), so for a `$this->foo()` expression the dispatcher's registration
order is what decides which one answers. Every one of the 36 unordered pairs among those nine is now run
in both orders by `tests/Unit/Ast/MethodCallOrderingMatrixTest.php`: five pairs disagree and are held in
the direction `handlers()` lists them (its `METHOD_CALL_PINNED` map, and the `MethodCall` row of the ordering table in
[docs/components/ast-engine.md](./components/ast-engine.md#the-honest-ordering-inventory)); the other 31
are proven inert against that same corpus.

The residual limit is the corpus, not the method: an expression shape the matrix never constructs cannot
be proven to disagree there, however plausible it looks by inspection — a new shape that turns an inert
pair into a disagreeing one fails the matrix, which is the signal to pin it. Read the pin count the same
way as before: "the divergences someone has actually gone and found", not "the only divergences that
exist" — now bounded by the matrix's corpus rather than by nothing at all.

### The publish-speed gate is one-sided

`.github/scripts/publish-bench.sh` fails only when head is slower than the merge-base by more than
`MAX_RATIO=1.25`. A large speedup becomes headroom the next branch can spend, so after merging one,
fast-forward `main` so the fast side becomes the base. Both arms install from the same `composer.lock`,
and the run prints each arm's framework version.

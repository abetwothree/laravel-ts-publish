# ResourceAstAnalyzer

`ResourceAstAnalyzer` reads a method body as PHP AST, not reflection, and returns a `ResourceAnalysis`: each returned
key with its TypeScript type, optional flag and import channels.
[`ResourceTransformer`](../../src/Transformers/ResourceTransformer.php) runs it on a resource's `toArray()`, then
aliases the imports and applies the `AsEnum` rewrite. `AstEngine`, `InertiaPageAnalyzer` and `ModelMetadataAnalyzer`
run it on other method bodies, as [AST engine](ast-engine.md) describes. Start at
[`ResourceAstAnalyzer::analyze()`](../../src/Analyzers/ResourceAstAnalyzer.php), and read what users see in the
[API resources docs](https://tolki.abe.dev/ts/api-resources.html).

## Where things live

These are the classes a change to resource analysis usually touches:

| Class | Owns |
| --- | --- |
| [`ResourceAstAnalyzer`](../../src/Analyzers/ResourceAstAnalyzer.php) | The walk over a method body: return branches, spreads, `merge()`, arrays built in a variable, delegation |
| [`ReadsReturnedVariables`](../../src/Ast/Concerns/ReadsReturnedVariables.php) | An array built in a variable: the walk over its writes, and whether a returned one is read completely |
| [`FiltersModelAttributes`](../../src/Analyzers/Concerns/FiltersModelAttributes.php) | A top-level `$this->only()` or `$this->except()` on the resource's own model |
| [`ResolvesModelTypes`](../../src/Analyzers/Concerns/ResolvesModelTypes.php) | Whole-model delegation: the property set of a resource with no `toArray()` |
| [`InspectsResourceCalls`](../../src/Analyzers/Concerns/InspectsResourceCalls.php) | Which classes are resources and the published-set gate |
| [`BuildsJsonApiResourceObject`](../../src/Analyzers/Concerns/BuildsJsonApiResourceObject.php) | A JSON:API resource's resource object, composed from the analyzer's reads of the runtime class |
| [`JsonApiResources`](../../src/Ast/JsonApiResources.php) | Whether a class is a JSON:API resource, its `type` literal and the relationships an include can send |
| [`ResourceCollections`](../../src/Ast/ResourceCollections.php) | A collection's facts: the API resource it collects, its kept keys, `R[]` or `Record<string, R>` |
| [`ResourceCollectionBody`](../../src/Ast/ResourceCollectionBody.php) | Whether a collection's `toArray()`, wherever declared, sends its collected list, and whether a nested one sends its own body |
| [`ResourceExpressionHandlers`](../../src/Ast/ResourceExpressionHandlers.php) | The ordered handler profile every value goes through |
| [`RelationFilterHandler`](../../src/Ast/Handlers/RelationFilterHandler.php) | `only()` and `except()` on a relation, a model accessor or a `map` proxy |
| [`ConditionalMethodHandler`](../../src/Ast/Handlers/ConditionalMethodHandler.php) | The `when*()` family, `unless()` and `transform()` |
| [`ToResourceHandler`](../../src/Ast/Handlers/ToResourceHandler.php) | `toResource()`, `?->toResource()` and `toResourceCollection()`, adding `\| null` after a `?->` in the chain |
| [`StaticCallHandler`](../../src/Ast/Handlers/StaticCallHandler.php) | `X::make()`, `X::collection()` and a method chained on a new resource |
| [`SubjectHelperReturnResolver`](../../src/Ast/SubjectHelperReturnResolver.php) | A `$this->helper()` whose returns wrap an enum in an `EnumResource`, read in the helper's own bindings |
| [`InlineArrayHandler`](../../src/Ast/Handlers/InlineArrayHandler.php) | A nested array literal, its spread arms and its `AsEnum` wraps, and a keyless list |
| [`ReturnShapeRefiner`](../../src/Ast/ReturnShapeRefiner.php) | Keys the body left `unknown`, filled from the method's own `@return` |
| [`IndexSignatureReconciler`](../../src/Ast/IndexSignatureReconciler.php) | Template-literal index signatures against the keys beside them |
| [`ReflectedTypeAcceptor`](../../src/Ast/ReflectedTypeAcceptor.php) | A reflected type into the FQCN channels, or a decline |
| [`MethodAnalysis`](../../src/Ast/MethodAnalysis.php) | The property list and the FQCN channels; `ResourceAnalysis` extends it |

The controller profile drops `ConditionalMethodHandler`, `ToResourceHandler` and `RelationFilterHandler`, the
model-getter profile drops the first two, and any other non-resource subject drops `ConditionalMethodHandler` alone. In
every profile, the order handlers run in decides which one answers; see
[AST engine § Handler ordering](ast-engine.md#handler-ordering).

## How `analyze()` reads a method

`analyze()` builds the analysis in a fixed order, and each step carries a rule:

- **A JSON:API resource's `toArray()` is never its response**: `analyze()` publishes the resource object
  ([below](#jsonapi-resources)), unless a caller located the body or the read is an ancestor's for a subclass. A plain
  resource that overrides `toAttributes()` publishes it, whatever its `toArray()`, since Laravel sends it from 12.45.
- **A `toArray()` that sends the collected list is the collection's body-less response in return position, and the
  bare list anywhere else**: [`ResourceCollectionBody`](../../src/Ast/ResourceCollectionBody.php) says which bodies
  send it. The subject then publishes `buildCollectionDelegatedAnalysis()` under its own method's `@return` and casts,
  and one that names no API resource lists the raw models its parent's resource wraps
  ([below](#a-stacked-body-less-collection-collects-what-its-own-class-names)).
  A spread, a variable, `+` or `array_merge()` that builds on the list reads it as `[key: number]: R`
  (`collectedListAnalysis()`), and a value as `R[]` (`StaticCallHandler`).
- **The class's own declaration counts, a trait it uses included**:
  [`MethodLocator::locateDeclared()`](../../src/Ast/MethodLocator.php); a method only an ancestor declares still walks
  `analyzeParentCall()`. An ancestor's analysis wins only when it has properties or a flat type. An empty one falls
  through to `buildCollectionDelegatedAnalysis()` or `buildModelSerializedAnalysis()`.
- **A `parent::` or `$this->` call reaching JsonResource's own `with()`, `resolve()` or `jsonSerialize()` reads that
  body for the runtime class** (`frameworkMethodAnalysis()`), unless `resolveResourceData()`, `toAttributes()` or
  `$attributes` replaces `toArray()`; JsonApiResource's own `resolve()` sends the resource object under `data`. A
  whole `return` of the collected list is flat, else `[key: number]: R`. A body only the runtime class declares is
  still read as the subject's, which takes only the runtime class's
  [collection facts](#a-stacked-body-less-collection-collects-what-its-own-class-names). A re-entered read is `null`.
- **Every array-literal `return` is a branch, and so is a returned variable the walk reads completely**
  (`ReadsReturnedVariables::variableBranch()`): `analyzeAllReturnBranches()` merges them through
  `mergeReturnBranches()`, so a key one branch lacks publishes optional, and a guard's `return []` is an empty branch.
  A variable the walk does not read completely is skipped, unless no branch read completely has a key and every
  `return` is a literal or a variable, none inside a `try`, a `switch` or a bare block: then it is read leniently as a
  branch, as a lone variable is. A variable skipped only for a lenient read still adds, optional, each key and signature
  entry no read branch sets. Otherwise, with no branch read completely holding a key, the sweep declines and the first
  `return` is read; any other return is skipped.
- **Any other body falls back to its own first `return`**, never a closure's, shaped by the method's own `@return` and
  `#[TsCasts]` as a branch is: `parent::toArray()`, `parent::with()`, `parent::resolve()`, `parent::jsonSerialize()`,
  a collection's collected list read as above, an `array_merge()` of literals and `parent::` calls, `$this->only()` or
  `$this->except()`, a bare `$this->method()`, which resolves like a `...$this->method()` spread, or a variable. The
  same forms, read by `analyzeArrayExpression()`, are the base of a variable `ReadsReturnedVariables` walks; a `+=` of
  one adds only new keys and every signature entry, a whole re-assignment drops the writes before it, and a key first
  written in a branch, loop, `try` or `switch` publishes optional, except in a `do` body before its first `break` or
  `continue`. An `unset()` of a named key drops it and its import channels, or makes it optional where it may not
  run; any other `unset()` of the variable is a write the walk does not read.
- **A spread method sweeps every `return` too**: `analyzeThisMethodSpread()` merges array literals, arrays built in a
  variable and `[]` as branches, and falls back to `analyzeFirstReturn()` when any `return` is something else. It finds
  the method in its class, a trait or a parent through `MethodLocator::locate()`; `...self::m()` and `...static::m()`
  spread the same way. It sets every name-keyed table aside through `AnalysisScope::nameBindings()`, keeping only the
  guard proofs `ReadsNonNullGuards::proofsAcrossCall()` names, empties the `resolvingLocalVars` guard and clears
  `closureRelationModelClass`, since the helper's `$this` is the subject. It re-derives `requestVarNames` and
  `declaringFileClass`, binds the method's parameters, and what the call passes them through `CallSite::passedTo()`, as
  [AST engine § A called method's parameters](ast-engine.md#a-called-methods-parameters) says, and restores all of them
  in a `finally`. `AnalysisScope::$visitedSpreadMethods` turns a self-spread or a cycle into an empty analysis
  instead of recursing until memory runs out.
- **The body wins, then `@return`, then `#[TsCasts]`**: `ReturnShapeRefiner::refine()` fills only keys the body left
  `unknown`, so a stale docblock never overrides a resolved type. `applyTsCastsFromMethod()` then applies the method's
  own casts to every entry of a key, except on the `share()` the shared-data analyzer reads. Both can change a key
  after the merge, so `IndexSignatureReconciler::reconcile()` runs again after them.

### A spread helper drops an untypable branch

`analyzeThisMethodSpread()` merges with `dropsUntypedBranches: true`, as do a merge call's sides (`merge()`,
`mergeWhen()`, `mergeUnless()` and a merge closure's returns), so `branchUnion()` leaves out a branch that
resolved to `unknown` and unions the rest, as a ternary drops its untypable arm. A key still publishes `unknown` when
every branch is `unknown`, or, for a spread helper alone, when only `null` is left, since that `null` says nothing
about the dropped value; a merge call keeps that `null` (`keepsLoneNull`), as a ternary does.
`toArray()`'s own branches and `InertiaPageAnalyzer`'s merge keep the strict rule: one `unknown` branch makes the key
`unknown`, because `unknown` absorbs whatever it joins.

### What the method's `@return` may fill

`ReturnShapeRefiner` reads a `@return array{…}` shape per key, or a `@return array<string, V>` value type for every
unfilled key. A `key?:` entry also marks the key optional. It skips a value whose token needs an import, since the
shape map holds strings and cannot carry the FQCN (`TsTypeString::shapeValueHasUnimportableToken()`).

A spread helper's `@return` may also name a type no PHP class answers to, which the app declares as a global:
`IncludesExtras`'s `custom_val: CustomObject` publishes `CustomObject`. A name that resolves through the file's `use`
statements to a class, interface, enum or trait is never kept, since it needs an import. The analyzed method's own
`@return` keeps no such name (`keepsUnresolvedNames: false`), because there an unresolved name is as likely an
unimported class, which `tsc` rejects with TS2304.

### A stacked body-less collection collects what its own class names

Take `LeafCollection extends MidCollection extends ResourceCollection`, neither declaring `toArray()`. Laravel's
`collects()` reads the class's own `#[Collects]`, then the `$collects` it inherits, then the naming convention on its
own name, and `sendsCollectedList()` lets `LeafCollection` resolve that before its walk reaches `MidCollection`. Its
wrap key is the live `$wrap` that `ResourceWrapping::key()` reads by late static binding: the slot of the nearest class
that declares `$wrap`, as the booted app left it, `null` included. It reads `#[PreserveKeys]` off its own class only, as
Laravel does; an inherited `$preserveKeys` property still applies. When its `collects()` finds nothing, Laravel sends
raw models, so the element is the published model backing the nearest ancestor's pick, else that pick while the run
publishes it (`ResourceCollections::collectedElement()`). With neither, as for an ancestor pick that is no API resource,
the raw items list as `unknown`. The class's own pick that is no API resource, or one the run does not publish, is
declined, never replaced. A `toArray()` an ancestor or a trait declares still wins, and runs with the runtime class's
facts: `ResourceAstAnalyzer::$runtimeClass` seeds the nested analyzer's scope, so its `$this->collection` and
`$this->resource` read `LeafCollection`'s element. The `Handover*Collection`, `F38*Collection` and
`StubSprocket*Collection` fixtures pin it.

### An inherited shape needs an inherited model

A subclass's inherited analysis types its columns only when a model backs it.
[`ModelClassResolver::resolve()`](../../src/Ast/ModelClassResolver.php) reads the nearest ancestor's `@mixin` or
`@extends` when the class has none of its own; its docblock lists the full precedence. That step runs for any resource
without its own tag, not only a body-less one. `BodylessOrderResource` pins the body-less case.

## JSON:API resources

A `JsonApiResource` subclass publishes the resource object Laravel sends as `data`, read off the runtime class:

- **`id` and `type`**: `id` is `string`, as Laravel casts `toId()`. `type` is Laravel's own expression over the class
  name, run when `ts:publish` runs, or the one literal a `toType()` override returns
  (`JsonApiResources::typeLiteral()`), else `string`.
- **`attributes`**: the first source `toAttributes()` reads: an override, through `analyzeThisMethodSpread()`, then
  JsonApiResource's own sources, which a `...parent::toAttributes()` in the override reads too: the `$attributes`
  names as `$this->resource->{name}` reads, an own `toArray()` read as `analyze()` reads a body, so a whole or spread
  `parent::toArray()` is the model's own array, then whole-model delegation. A keyless name in the array an override
  or an own `toArray()` returns reads the model attribute too (`$readsKeylessAttributeNames`). The model's
  `#[TsCasts]`, then the resource's, lay over its members and never over `id` or `type`, so `ResourceTransformer`
  lays neither at the top level. It is optional unless one member is always sent, since Laravel drops an empty one.
- **`relationships`, `links`, `meta`**: every relationship `$relationships` or a `toRelationships()` literal declares,
  each optional, as only an `include` sends it (`JsonApiResources::relationships()`); `links` and `meta` read a
  `toLinks()` or `toMeta()` override, else the `$jsonApiLinks` or `$jsonApiMeta` default.
- **`resolve()` and `jsonSerialize()`**: a read reaching JsonApiResource's own `resolve()` is the resource object
  under `data`, declined inside a method that builds the object, where Laravel never returns.

## Attribute filters on any model receiver

`only()` and `except()` type against any receiver holding a model, and two owners answer them:

- **[`RelationFilterHandler`](../../src/Ast/Handlers/RelationFilterHandler.php)**: a relation or model accessor read as
  `$this->relation` or `$this->resource->relation`, and the `map` proxy, as in `$this->comments->map->only([...])`.
- **[`ReceiverMethodReturnResolver::attributeFilterRule()`](../../src/Ast/ReceiverMethodReturnResolver.php)**: every
  other receiver holding one model class, such as a bare `$this->only([...])` the resource forwards to its model, a
  `whenLoaded()` closure parameter or a local variable.

Both prefer a reference to the model's own interface, `Pick<Model, …>`, over an inline shape. That interface already
carries the model's `#[TsCasts]` and `@property` refinements, which a shape rebuilt here would lose.

The two owners agree by construction. Both call `ResolvesFilteredRelationTypes::literalKeyFilterResult()` (the
`Pick<>`, else the inline shape) and `attributeRecordResult()` (`Record<string, unknown>` for a runtime key list). They
pass the same model, keys and `AnalysisScope::$carriesImports`, behind the same `typesAsModelFilter()` override check.
For a `Support\Collection` member, both ask `FiltersAttributeKeys::runsCollectionFilter()`. Three differences remain:

- **`?->`**: the relation arm adds `| null` itself, and the receiver rule leaves it to `ReceiverMethodCallHandler`.
- **A literal key list that types no member**: the relation arm declines, and the receiver rule, which runs later,
  publishes `Record<string, unknown>`, since `Model::only(['nope'])` returns `['nope' => null]`.
- **An abstract or `Illuminate\` model**: the receiver rule declines through `ValueResult::namesOnlyPublishedModels()`,
  while the relation arm still emits a `Pick<>`. `RelationFilterHandler` runs first, so a single-model relation
  publishes its `Pick<>`; see
  [AST engine § The honest ordering inventory](ast-engine.md#the-honest-ordering-inventory).

In a model-backed scope the generic reflectors decline every `only()` and `except()`. Laravel declares
`HasAttributes::except()` as `@return array`, which reflects to the list `unknown[]`, and a many-relation's filter keeps
models by primary key. [AST engine § The honest ordering inventory](ast-engine.md#the-honest-ordering-inventory) lists
those handlers. It also records the controller scope, which has no model, where one of them still reflects a filter.

A model's own method or accessor body is analyzed with the model as its subject. There `$this->resource` is the
model's own member, because only an API resource has the proxy (`AnalysisScope::hasResourceProxy()`). How filters
answer in that body, and in a body that carries no imports, is in
[receiver types § The body fallback carries no FQCN channel](receiver-types.md#the-body-fallback-carries-no-fqcn-channel).

The relation arm publishes by member kind, adds `| null` through `?->`, and declines (`null`, so a later handler
answers) on anything else:

- **A single-model relation or model-returning accessor**: the `Pick<>` or inline shape for a literal key list, and
  `Record<string, unknown>` for any other. It declines when the model overrides the filter with a return reflection
  can type, or when a literal list names nothing it can type.
- **A many-relation**: the relation's own read, `Comment[]` with its model channel, whatever the key list, because
  `Eloquent\Collection::only()` and `except()` keep whole models by primary key.
- **A member holding a `Support\Collection`**: `Record<string, unknown>`, because that class's filters select entries
  by key. The collection class wins over any element model an accessor names, so
  `Attribute<Collection<int, User>, never>` publishes the record, not a `User` pick. A collection cast (`'collection'`,
  `'encrypted:collection'`, `AsCollection`, `AsEncryptedCollection` or a `using()` class) is read from the cast string,
  since Laravel's casts declare no return type.
- **An accessor holding an `Eloquent\Collection`**: a list of its models, such as `Comment[]` or `(Comment | User)[]`,
  or `unknown[]` when no element model is named or the scope carries no import. A cast that builds one holds decoded
  JSON with no key to filter by, so it declines.
- **An accessor typed as a union of models**: one arm per model; see
  [Multi-model accessor unions reference each arm's own model](#multi-model-accessor-unions-reference-each-arms-own-model).

Three filters stay `unknown`, because neither owner can type them:

- a collection-cast column read through another receiver, as in `$this->twin->options->only([...])`
- an `AsEnumCollection` column, whose value is a list of enum cases
- a relation typed as a union of models, such as `MorphTo<CrmUser|User, $this>`

The `map` proxy arm types `$var->map->only([...])` as a list of the filtered shape when it can bind the element model.
It binds a to-many `whenLoaded()` parameter, or a to-many relation read as `$this->comments` or
`$this->resource->comments`. A model that overrides the filter with a typed return publishes that return as a list,
such as `string[]` for `only(): string`. It publishes `unknown` with no element model, as for `$this->resource->map`,
with a runtime key list, or with keys that name nothing. A model with a real `map` relation gets the relation arm
first.

### `$this->resource` spells the same filter

A resource forwards what it does not declare to `$this->resource`, so every filter publishes the same type under
`$this->only([...])` and `$this->resource->only([...])`. That holds in spread and value position, with a literal or a
runtime key list, on the resource's own model, a single-model relation and a many-relation. Each shape has one owner:

- **Spread on the own model**: `FiltersModelAttributes::filtersOwnModel()` accepts `$this` or `$this->resource`, and
  both `analyzeReturnArray()`'s spread branch and `analyzeThisAttributeFilter()` ask it. Change the test there, since
  widening only the branch leaves `analyzeThisAttributeFilter()` declining.
- **Value on the own model**: the receiver rule, for both spellings. `RelationFilterHandler` never matches
  `$this->resource` itself, since PHP reads the declared `JsonResource::$resource` before any `__get()`, even on a
  model with a `resource` relation.
- **Value on a relation**: `RelationFilterHandler`, which matches `$this->resource->relation` wherever it matches
  `$this->relation`. A `?->` on the proxy, as in `$this->resource?->comments->only([1])`, adds `| null` once, since
  the resource can wrap nothing.

`ProxyFilterDirectResource` and `ProxyFilterWrappedResource` write the same filters both ways, and
`ResourceAstAnalyzerTest` asserts they publish identical properties and imports. Two shapes stay outside the pair.
A top-level `...$receiver->only([...])` on another receiver holding one concrete model flattens
(`analyzeReceiverModelFilter()`, sharing `ReceiverMethodReturnResolver::singleModel()` with the value rule), while
`except()` does not, since its pool would list accessors and relations `Model::except()` never returns.
`$this?->only($keys)` publishes
`Record<string, unknown>`, while `$this->resource?->only($keys)` adds `| null`, since only the resource can wrap
nothing.

### When a `Pick` reference is emitted

`ResolvesFilteredRelationTypes::relationFilterModelReference()` emits `Pick<Model, …>` only when every filter key is
in `ModelAttributeResolver::publishedColumnNames()`. For `only()` the picked keys are the caller's list. For `except()`
they are the complement, in schema order, so the type names what the value holds. A key that is an accessor, a mutator
or a relation, which `only()` can request, falls back to the inline shape.

The gate reads the emitted interface, not the schema. `Pick<T, K>` requires `K extends keyof T`, and with
`exclude_hidden` on, the model interface omits `$hidden` columns, so a hidden key there fails with TS2344.
`publishedColumnNames()` subtracts them in that case; see
[model attribute resolver § `publishedColumnNames()` and the `exclude_hidden` coupling](model-attribute-resolver.md#publishedcolumnnames-and-the-exclude_hidden-coupling).

The reference picks the survivors instead of omitting the excluded keys, for two reasons:

- **It reads the same under every model template**: the key set comes from `publishedColumnNames()` alone. An
  `Omit<Order, …>` would re-widen under `model-full` to every member `keyof Order` holds there, relations included.
- **Its key list is its member set**: `tests/Feature/ModelOnlyExceptSemanticsTest.php` diffs that list against a real
  `$post->except()` result. An `Omit<>` names only what it removes, so it cannot be checked that way.

The include and except branches diverge on purpose, as Eloquent's do. `HasAttributes::except()` iterates
`getAttributes()`, so it returns database columns only. It never returns a relation, or a get-only accessor, which
`mergeAttributeFromAttributeCasts()` does not merge back. `HasAttributes::only()` calls `getAttribute()` per key, so it
also returns accessors and relations. `resolveFilteredRelationType()` follows both. Its except branch lists the related
model's database columns alone, so naming a relation or an accessor in `except()` changes nothing. Its include branch
resolves accessors and relations by name.

A write-only mutator, which `ModelAttributeResolver::isOmittedMutator()` reports (no getter and no docblock `Get`), has
no read type. The relation filter's inline shape and the implicit property sets, whole-model delegation and
`$this->except()`, leave it out, as `ModelTransformer` does. A top-level `$this->only(['search_index'])` keeps it as
`unknown`, because `getAttribute()` still returns the key (`OrderOnlyResource`). A real database column is never
dropped, even when its type is `unknown`.

### Top-level `only()` and `except()` start from the whole model

`return $this->only([...])`, `return $this->except([...])` and their spreads filter `buildModelDelegatedAnalysis()`:
every attribute, accessors included, and every relation under its method name. Whole-model delegation does not start
from this pool. `buildModelSerializedAnalysis()` publishes `Model::toArray()`'s set: the columns and the appended
accessors, each relation optional under the key `toArray()` writes (snake_case while `$snakeAttributes` is on).
`$visible` and `$hidden` filter that set only while `exclude_hidden` is on. A relation keyed like an attribute makes one
required key typed with both. The two filters use the pool differently:

- **`only()`**: keeps each requested key the set has, in the model's own order. It then appends, in request order, a
  key the set lacks when `ModelAttributeResolver::resolveAttribute()` can type it, such as a `withCount()` virtual or
  an `@property` name, because `HasAttributes::only()` returns whatever `getAttribute()` finds. A key nothing can type
  is dropped, never published as `unknown`.
- **`except()`**: subtracts from the full set. That set holds accessors and relations, which `Model::except()` never
  returns, so the type lists keys the payload does not carry: `OrderExceptResource` publishes `user` and `items`. Only
  the relation filter's except branch is columns-only.

`exclude_hidden` follows Eloquent's split between keys the caller named and keys derived for it. `Model::only()`
returns a `$hidden` attribute, while `toArray()` and `except()` strip it:

- **Dropped when hidden**: `return $this->except([...])`, `$this->relation->except([...])` and whole-model delegation
  (no `toArray()`, or `parent::toArray($request)` returned or spread), which then also keeps only what `$visible`
  lists, a relation by its method name.
- **Kept**: `return $this->only([...])`, `$this->relation->only([...])` (as an inline shape, not a `Pick<>`),
  `$this->whenHas('column')` and a plain `$this->column` read.

`analyzeOnlyFilter()` is the one caller that passes `excludeHidden: false` to `buildModelDelegatedAnalysis()`. The
relation except branch drops hidden columns itself.

### Multi-model accessor unions reference each arm's own model

`RelationFilterHandler` types a filter on an accessor whose type is a union of models, such as
`Attribute<CrmUser|User, never>`, with one arm per model. Each arm tries the `Pick<>` first, so it keeps its model's
`#[TsCasts]` refinements. It falls back to its own inline shape when a key is not that model's published column. An
arm whose model overrides the filter publishes the override's return, as in `string | Pick<User, 'id'>`. An
override that resolves to `unknown` declines the whole filter.

The arms' FQCNs feed the positional import queues that
[AST engine § `addProperty()` is the only way a value becomes a property](ast-engine.md#addproperty-is-the-only-way-a-value-becomes-a-property)
describes, so two rules keep each arm's FQCN beside its own token:

- **Dedupe arms on the FQCN, never the rendered string**: `relationFilterModelReference()` renders `class_basename()`,
  so two `User` models picking the same columns render alike. A string dedupe would drop the second arm and its
  import. `WarehouseResource::$last_user_activity_by_partial` pins
  `Pick<CrmUser, 'id' | 'name'> | Pick<ModelsUser, 'id' | 'name'> | null`.
- **A declining arm adds only the FQCNs nested in its inline shape**: that shape spells no bare model name, so the
  arm's own FQCN would shift the queue onto the next arm. `WarehouseResource::$probe_mixed` pins
  `{ id: number } | Pick<ModelsUser, 'id' | 'phone'> | null`.

## The `when*()` family

[`ConditionalMethodHandler`](../../src/Ast/Handlers/ConditionalMethodHandler.php) maps each call against
`JsonResource`'s own signature with `CallArguments`, so a positional or named argument lands where Laravel binds it.
Its rules follow Laravel's `ConditionallyLoadsAttributes` and the global `transform()` helper:

- **No explicit default, optional key**: without a `default` argument the value can be a `MissingValue`, which Laravel
  removes, so the key publishes optional.
- **An explicit default makes the key required**: `hasExplicitDefaultArg()` counts arguments the way
  `func_num_args()` does, so a `null` written at the default position counts, and a spread counts as no default.
  `applyConditionalDefault()` then unions the default's type in through `ValueResult::unionResults()`, which carries
  its import channels. Joining the type strings by hand would emit a token with no import.
  The key stays optional when the value or the default can itself be a `MissingValue`, as in
  `when($c, $this->whenLoaded('author'), 'x')`.
- **A `MissingValue` passed on stays optional**: `ValueResult::unionResults()` makes a union optional when an arm is, so
  `??`, a ternary, `match` and a closure's several returns carry it, and `CoalesceHandler` counts the right operand only
  past a left that can be `null`. A form-request field absent from the request reads `null`, so `??` and a short `?:`
  replace it (`ValueResult::checkedOperand()`); a ternary or closure over it stays optional, as a direct read does.
- **An `unknown` arm is never unioned in**: an `unknown` default leaves the value arm's type, and an `unknown` value
  arm, such as `whenPivotLoaded()`'s or an untypable `whenHas()` or `whenAppended()` value, keeps the key `unknown`,
  since `T | unknown` is `unknown`. The key is optional only if the value arm is. A default left out is recorded in
  `DroppedUnionArms` as `conditional-default`; see
  [AST engine § Dropped union arms](ast-engine.md#dropped-union-arms) for the policy.
- **A default closure that needs more arguments than Laravel passes is skipped**: Laravel calls a default as
  `value($default)` with no arguments, except `transform()`, whose helper calls `$default($value)`. A closure requiring
  more parameters would throw `ArgumentCountError`, so `InspectsAstNodes::closureRequiresArguments()` keeps it out of
  the union, and the key keeps the value arm's type and optionality.
- **A `never[]` arm drops beside an array arm**: a literal `[]` is `never[]`, since `json_encode([])` is `[]`, and it
  adds nothing to a real array type. So `whenLoaded('children', $this->children, [])` is `Category[]`. The drop is
  sound only because `never[]` means provably empty; typing an empty literal as `unknown[]` would break it.
- **`whenNotNull()` and `whenNull()` take a value, not a callback**: both call `when()` on `is_null($value)`.
  `whenNotNull()` strips the top-level `| null` from its value with `ValueResult::stripNullArm()`, which leaves a
  nested one. `whenNull()`'s value arm is `null`.
- **`unless()` and `mergeUnless()` reuse `when()` and `mergeWhen()`**: negating the condition changes which arm runs,
  never either arm's type.
- **`mergeWhen()` and `mergeUnless()` merge their default**: `ResourceAstAnalyzer::analyzeMergeExpression()` reads the
  value and a passed default as branches through `mergeReturnBranches()`, so a key both set is required with both
  types and a key one sets is optional. An untypable side drops out of the union, as
  [a spread helper's branch does](#a-spread-helper-drops-an-untypable-branch). A closure's returned arrays, `[]`
  included, a returned variable the walk reads completely, and any other value `mergedValueAnalysis()` reads are
  branches: the resource's own model, as `Model::toArray()` writes it, or a whole array `analyzeArrayExpression()`
  reads, such as `$this->method()`. The same values passed without a closure merge alike, since Laravel merges
  `value($value)`. A side read as no array is an empty branch; one the analysis cannot read also counts as a lenient
  read, so a variable holding the merge is not read completely. A key set before a merge keeps its value and presence,
  as `mergeData()` unions the merged keys after it; `MethodAnalysis::dropKeysHeldBy()` applies that rule and PHP's `+`
  alike, keeping each signature entry.
- **A `whenLoaded()` value arm takes `| null`** unless `ModelAttributeResolver::relationLoadsNull()` rules it out:
  Laravel returns `null` for a relation loaded as `null` before it reads the value. A to-many never loads `null`; a
  relation the model does not declare can, under `nullable_relations`.
- **`whenHas()`, `whenAppended()` and `whenExistsLoaded()` type from their value argument**: each returns
  `value($value, …)`. A closure's first parameter binds to `$this->{attribute}`, to the `{relation}_exists` flag or,
  for `whenAppended()`, to nothing. The attribute answers `whenHas()` and `whenAppended()` only for a value-less call or
  an `EnumResource` wrap, which only moves the enum onto `enumFqcn`. A value the engine cannot type publishes `unknown`:
  the attribute's type is wrong for a value that transforms it. `whenExistsLoaded()` keeps its flag for such a value.
- **A skipped or literal `null` value publishes `null`**: none of those three swaps in an identity closure as
  `whenLoaded()` does, so `whenExistsLoaded('user', null, 'absent')` is `string | null`.
- **`whenExistsLoaded()` publishes `boolean`**: the type `ModelAttributeResolver` gives a model's own `*_exists`
  attribute, so a resource and its model agree on the flag.
- **A `whenAggregated()` aggregate follows the driver, and takes `| null` unless the model's declaration rules it
  out**: the model's own declaration of `{relation}_{function}_{column}` types it, else for `MIN()` and `MAX()` a
  user's `custom_ts_mappings` entry for the related column's type, else
  [`AggregateValueType`](../../src/Ast/AggregateValueType.php) reads the related column's type and the connection's
  driver, so a decimal `SUM()` is `number` on SQLite and `string` on MySQL, else it is `number`. Any aggregate but a
  count is `NULL` over no rows, which Laravel returns before it calls a value closure; one whose function the call
  computes takes no arm.
- **`transform()` types from the callback**: the helper returns `$callback($value)` for a filled value, with the
  callback's first parameter bound to the value. Its default receives the value too, `null` arm included:
  `transform($this->rating, fn ($r) => 'x', fn ($r) => $r)` publishes `string | null`.
- **A first-class callable types as the call it stands for in a `value()` position**: Laravel calls the `Closure`, so
  `when($c, $this->label(...))` types as `$this->label()`. Anywhere else the key holds the `Closure`, which
  `json_encode()` writes as `{}`, so it publishes `Record<string, never>` and `ts:publish` warns.

`InspectsResourceCalls::$conditionalMethods` names the family a second time, for a resource constructed around a
conditional call, such as `Resource::make($this->whenLoaded(...))`, which publishes optional.
`ReadsNullablePayloads::resourceWrapType()` adds `| null` to a resource built around a payload that can be `null`,
such as `Resource::make($this->parent)`, and publishes `null` alone for a payload that is only ever `null`, such as
`new Resource(null)` or a parameter holding a `null` default, inside a resource only; a collection never takes either,
and neither does a payload a guard proves non-null ([AST engine § Narrowing](ast-engine.md#narrowing)).

A model-level `#[TsCasts]` wins over every rule here in the published file, because
`ResourceTransformer::applyOverrides()` runs after analysis. `Address` casts `latitude`, so check conditional typing
on a key no cast covers.

## `$this->resource` inside a relation closure is the resource's own model

Inside a `whenLoaded()` closure `$this` is still the resource.
[`PropertyChainHandler`](../../src/Ast/Handlers/PropertyChainHandler.php) and `ReceiverMethodCallHandler` root every
chain at `$modelClass`, and
`RelationCollectionChainHandler` and `StaticCallHandler` never type a `$this->prop` call from
`AnalysisScope::$closureRelationModelClass`, the relation's model. A model that declares a real `resource` relation
reaches it only as `$this->resource->resource`. `ClosureResourceRootResource` pins a property chain and a method call
on `$this->resource` inside such a closure.

## Collection pipelines

Two handlers type a chain of collection operations.
[`RelationCollectionChainHandler`](../../src/Ast/Handlers/RelationCollectionChainHandler.php) takes a chain rooted at
`$this->{manyRelation}` or `$this->resource->{manyRelation}`; a `?->` on the proxy adds `| null` once.
[`CollectionPipelineHandler`](../../src/Ast/Handlers/CollectionPipelineHandler.php) takes one rooted at `collect($arg)`,
and reads the element type off `$arg` when it resolves to `X[]`. A top-level `|` declines,
since the elements could come from either arm. Each handler tracks in its own `match` whether the keys are still
`0..n-1`. Once they are not, it adds the object arm `json_encode()` emits, from
`SpellsKeyedCollections::keyedObjectArm()`. A bare `collect($arg)` is typed by
[`KnownFunctionCallHandler`](../../src/Ast/Handlers/KnownFunctionCallHandler.php): no argument is `never[]`, and a constant
is its element types. A list literal, bare or collected, is typed by
[`ValueResolver::resolveListLiteral()`](../../src/Ast/ValueResolver.php).

The two `match` statements must agree on every op both take. They stay separate because a `collect()` root takes only
a subset of the ops, with no `take`, `pluck`, `concat` or `first`/`last` terminal. These rules hold around them:

- **`values` restores `0..n-1`, and `all` keeps the keys**: `all()` hands back the underlying array, and a
  `Collection<X>` and its array both render `X[]`, so it is identity on the published type.
- **`concat($source)` is identity only on exact type equality**: anything but the receiver's own collection type
  declines the chain, since `Comment[]` joined with `Tag[]` is a different collection, not a longer one.
- **A `collect()` pipeline binds its `map()` parameter to a value**: its elements need not be models, so the parameter
  goes in `AnalysisScope::$varValueBindings`, where a relation chain binds its element model in `$varModelBindings`.
  See [AST engine § Writing a scope binding](ast-engine.md#writing-a-scope-binding).
- **`data_get($target, 'a.b')` is the chain `$target?->a?->b`**: `KnownFunctionCallHandler` unions an explicit default
  in beside the chain's `null` arm, since `data_get()` returns the default only for a missing key. A key with a `*`
  segment declines, because it returns a list of every match.

`VariableHandler` also peels a trailing `values()` or `all()` off a receiver it can type.
[AST engine § The honest ordering inventory](ast-engine.md#the-honest-ordering-inventory) records when, and why it
agrees with `CollectionPipelineHandler`.

## Inline-array spreads become intersection arms

In a nested array literal, [`InlineArrayHandler`](../../src/Ast/Handlers/InlineArrayHandler.php) turns a spread into an
arm of an intersection instead of flattening its keys. `InlineArrayHandler::classifySpreadArm()` recognizes four kinds:

- **Resource arm**: a spread of one named resource, such as `UserResource::make($m)->resolve($request)`, never an
  array or collection of one.
- **Model arm**: `$var->toArray()` on a closure-bound model, or `$this->relation->toArray()` along real relations.
- **Collection arm**: `$var->toArray()` on a to-many `whenLoaded()` parameter, or a relation chain that ends to-many.
  It publishes `Record<number, User>`, because spreading a collection renumbers its elements from 0.
- **Object arm**: any other value typed as one object, such as a `Record<string, string>` cast or a relation's
  `Pick<User, 'id'>`. It joins as that type, with the imports the value carries.

`$this->toArray()` and the other spreads `analyzeReturnArray()` already flattens are not arms. At the method's own top
level, the same classifier flattens the spread into the resource's keys instead. A resource arm gives its properties,
a model arm its published columns and appended accessors, and a collection a `[key: number]: Model` member. An object
arm names no class to flatten, so it is left out there.

Each arm is `Omit<>`'d against every explicit key and every later arm's `keyof`. PHP's `[...$a, 'k' => $v]` lets the
later write win, while TypeScript's `&` intersects both and makes a disagreeing key `never`. Three rules follow:

- **The subtraction is unconditional**: `Omit<T, K>` does not require `K extends keyof T`, so
  `buildSpreadArmTypes()` needs only a later arm's name, never its shape.
- **A collection arm is never subtracted, and never subtracts**: its keys are indexes, and every other key is a
  string. The one collision left is a numeric key beside it: the arm keeps
  `[...$members->toArray(), '0' => true]` as `Record<number, User> & { "0": boolean }`, and the publisher's
  [re-index](#numeric-keys) sends `(User | boolean)[]`.
- **Arm order is source order**: every kind travels in one list, split by kind only when imports are dispatched.
  Resource arms go on `embeddedResourceFqcns`, model and collection arms on `embeddedModelFqcns`, and an object arm's
  classes on the import channels its value carried. Grouping by kind any earlier would subtract against the wrong
  arm. `NestedResourceSpreadResource`'s `members_model_then_resource_spread` and `members_resource_then_model_spread`
  pin both directions.

PHP's array union `$a + $b` keeps every key `$a` sets. In value position
[`BinaryOpHandler`](../../src/Ast/Handlers/BinaryOpHandler.php) types it as the one literal
`InspectsAstNodes::arrayUnionLiteral()` builds. With every key a literal, that literal holds the items PHP keeps, in
PHP's order, so int keys come out as the runtime sends them. Otherwise it is `[...$b, ...$a]`. An operand the literal
cannot read from its syntax is spread as the value it resolves to, so an object joins as an object arm, and an untyped
one as `Record<string, unknown>`, since PHP throws on `+` unless both sides are arrays. With no array on either side,
`+` stays `number`. As a whole return, `analyzeArrayExpression()` reads each side and merges them as `+=` does.

A model arm names the bare model interface, which is a floor, not an exact match. It matches `attributesToArray()`,
since the `model-split` template renders columns and appended accessors into that interface. It omits the relations
`relationsToArray()` adds, which depend on what is loaded, and it keeps `$hidden` columns unless `exclude_hidden` is
set. Only this collector reads `$var->toArray()` as a model, so a model's `toArray()` in value position gets no shape.

## Resource classes a value names

### `toResource()` convention guesses are gated on the published set

`Model::toResource()` and `Collection::toResourceCollection()` reach a resource three ways: an explicit class argument,
a `#[UseResource]` or `#[UseResourceCollection]` attribute, or Laravel's naming convention. Only the convention invents
a class name. `InspectsResourceCalls::isResourceClass()` accepts any class that exists, including a `#[TsExclude]`d or
third-party resource that gets no file, and its import would name a missing module. So every site that invents a
candidate also checks [`PublishedResourceRegistry`](../../src/Cache/PublishedResourceRegistry.php);
`ResourceCollections::isPublishedResource()` is the one predicate, and
`InspectsResourceCalls::isPublishedResourceClass()` delegates to it. These are the sites:

- the candidate loop in `GuessesModelResources::resolveResourceForModel()`
- both naming loops in `GuessesModelResources::resolveResourceCollectionForModel()`
- the naming-convention pick of `ResourceCollections::collectedElement()`, the one resolver every `#[Collects]`
  caller shares, where an unpublished pick collects nothing rather than fall through to `FooResource`

A class the developer wrote down stays on `isResourceClass()`: an explicit argument, `#[UseResource]`,
`#[UseResourceCollection]`, `SomeResource::make()` and `new SomeResource()`. `#[Collects]` and `$collects` are taken
as written too, unless they name no API resource ([`#[Collects]` and `#[PreserveKeys]`](#collects-and-preservekeys)).

The registry fails open: while it is empty, `isPublished()` answers `true`, so `RunnerForSource`, which never
registers, analyzes without narrowing. `Runner::run()` and `RunnerForSource::run()` reset it first.
`Runner::generateResources()` registers the whole collected list before generating, because a resource may reference
one collected after it. The registry is process-static, so `Tests\TestCase::setUp()` resets it too.
Models have the same check through `PublishedModelRegistry`, which a source run does fill. See
[ModelAttributeResolver § Models a run publishes](model-attribute-resolver.md#models-a-run-publishes).

In the modular files a leaked guess fails `tsc` with TS2305 or TS2724, or with TS2307 when the run writes nothing into
the guessed class's directory. In `laravel-ts-global.ts` it fails with TS2304 or TS2552. `unimportable-token-gate.sh`
counts each of them, TS2307 in its relative-specifier sub-gate; see
[type-inference gates](../testing/type-inference-gates.md). `ResourceAstAnalyzerTest`'s published-set tests pin the
check itself with the `#[TsExclude]`d `AttachmentResource`, `AttachmentCollection` and `LedgerResource` fixtures.

### A morph union binds every target, and `toResource()` unions their resources

`ConditionalMethodHandler::analyzeWhenLoaded()` binds a `morphTo` closure parameter to every target, in
`AnalysisScope::$varClassBindings`, and a variadic one to the list of them, its element never `null`
(`ImageSubjectsResource`). `ToResourceHandler` then maps each target model to its resource and publishes the union,
such as `reviewable?: ArtistResource | VenueResource`, reporting the classes on `embeddedResourceFqcns`. A relation that
can load as `null` adds the arm every `whenLoaded()` value takes, so `ImageReviewResource.reviewable` ends in `| null`.
The union is all or nothing. If one target has no resource, the key stays `unknown`, since a union missing an arm is
wrong for that arm, not vaguer. Its order is the morph-target order, which sorts by model FQCN or follows a
`@return MorphTo<X|Y>` docblock, never by resource name.
`MethodAnalysis::addProperty()` also queues the union's classes under the property in `inlineResourceFqcns`, one per
token, and `InlineArrayHandler` builds the same queue for an inline array. `ImageReviewResource` pins both.

### `#[Collects]` and `#[PreserveKeys]`

[`ResourceCollections`](../../src/Ast/ResourceCollections.php) holds a collection's facts for the analyzer and the
handlers alike: `collectedElement()`, `preservesKeys()`, `listValue()`, `listType()` and `reindexesNumericKeys()`.
`collectedElement()` reads `Illuminate\Http\Resources\Attributes\Collects` behind `class_exists()`, because Laravel 12
has no such class; see [version-guarded Laravel classes](../laravel-version-guards.md). It follows Laravel's
`collects()` order: `#[Collects]`, the `$collects` default, then for `FooCollection` the bare `Foo` when that class
exists, else `FooResource`. The first class found decides. One that is no API resource, where Laravel throws a
`LogicException`, collects nothing and adds an analysis warning.
`AnalysisScope::seedCollectionFacts()` seeds `$collectedElement` and `$reindexesNumericKeys` from it, on `rebase()`
and, from the runtime class, for any scope `ResourceAstAnalyzer` reads, its own or one a caller supplies:
`$this->collection` lists that element, and `$this->resource`'s items carry it on `ReceiverType::$collectedElement`,
since Laravel sets one to the other. A collection is backed by no model, whatever its docblock or
`#[TsResource(model:)]` names: `ModelClassResolver::resolve()` returns `null` for it.

`preservesKeys()` honors both `#[PreserveKeys]` and `public $preserveKeys = true`.
`ValueResult::collectionListType()` is the one place that spells a collected list: `Record<string, R>` when the
collection keeps its keys, else `R[]`. `ResourceCollections::listValue()` is that list as a value, the element on its
import channel; `$this->collection`, a value-position `parent::toArray()` over the collected list, `$this->resource`'s
items (`heldItems()`, with the scope's `reindexesNumericKeys`), a nested collection and the delegated response read it.
`only()`/`except()` on the items spell it once with `false` and once with `true`, since those keep the keys they
filter by. Without an element, the first three are `unknown[]`. The delegated response is `{ <wrap>: unknown[] }` while
`ResourceCollections::keepsRawItems()` holds, since `collects()` then picks nothing and Laravel sends the raw items, and
is empty where Laravel throws or maps the items into an unpublished resource. A nested collection is `null`, so its
caller keeps its own fallback.
A site that spelled the list itself would keep emitting `R[]` for a keyed collection, and only a fixture of that exact
call shape would notice.
`grep -rnE 'collectionListType\(|ResourceCollections::(listType|listValue)\(' src/` lists the sites.

`listType()` spells the list alone for a site that names its API resource and reflects on the class Laravel keys it by:
`SomeResource::collection()` (a `ResourceCollection` subclass's included, an anonymous list of that class),
`toResourceCollection(SomeResource::class)`, an argument-less `toResourceCollection()` that finds no collection class,
from the `#[UseResource]` arm or the bare naming fallback, and the Inertia props below.

A `ResourceCollection` subclass nested by `new`, `make()` or a guessed `toResourceCollection()` is
`ResourceCollectionBody::nestedValue()`. While the run publishes it and its `toArray()` does not send the collected
list, it publishes its own type, the body Laravel sends; otherwise the list over its `collectedElement()`, keyed by
its own `preservesKeys()`. An excluded one keeps that list, so no import names a missing file.

`InertiaResourcePropHandler` types `new` and `make()` of an API resource or collection, `R::collection()`,
`EnumResource::collection()`, `toResource()` and `toResourceCollection()`, in a prop or a shared value at any depth, as
its `toResponse()` data. A single resource and a body without the live wrap key sit inside that key, such as
`{ data: R }`, unless [`ResourceResponseBody`](../../src/Ast/ResourceResponseBody.php) finds the body already holds it;
`EnumResource` declares `$wrap = ''`, so its `make()` stays bare. Over a paginator, a collection's own body sits beside
the pagination blocks of the payload's kind, and an anonymous or flat one is that kind's paginator response, such as
`JsonResourceSimplePaginator<R>`, keyed by `preservesKeys()`; `TolkiTypes::RESOURCE_PAGINATION` names both per kind. A
payload of several kinds, or sometimes none, publishes each.

### A method called on a new resource types its payload

`StaticCallHandler` types a method called on `new self($x)`, `self::make($x)` or a chain of them. A method that
returns the same instance keeps the receiver's type, the wrapped value's `| null` included, plus `| null` for a nullable
return. It says so with a native `static`, `self` or the class name, or with a docblock `@return $this`. Otherwise
the method body is the payload. `spreadAnalysis()` analyzes it, and `BuildsInlineObjectTypes::buildInlineObjectType()`
flattens it. An empty analysis declines, since `{}` would claim the payload has no keys.

Three answers are possible, and only the last is right. Do not change it to the receiver type:

| Emitted type | Verdict |
| --- | --- |
| `FluentSelfResource`, the receiver type | Wrong. The instance never reaches the payload, so this promises keys the response does not have. |
| `unknown` | The floor. True, but it tells the frontend nothing. |
| `{ id: number }`, the method body | Right. `FluentSelfResource::summary(): array` returns `['id' => $this->id]`. |

Only the analyzer's own class is in scope, because `spreadAnalysis()` can analyze only methods of
`$scope->subjectReflection`. A foreign receiver such as `new CategoryResource($x)->summary()` keeps the `unknown`
floor instead of the analyzer's own same-named method; `FluentSelfResource::foreign_summary` pins that. `resolve()` is
exempt, since it is Laravel's serializer: `new SomeResource($x)->resolve()` publishes `SomeResource`, as
`SomeResource::make($x)->resolve()` does, never with the wrapped value's `| null`.

## Numeric keys

A numeric key goes through two layers:

- **Key spelling**: every literal key publishes as the key `json_encode()` writes, whatever the subject, so `42 =>`
  and `'6' =>` print `"42"` and `"6"`. A literal whose stored keys run 0 to n-1 is a list, which
  `ValueResolver::resolveListLiteral()` types.
- **Re-index**: an API resource sends an array whose keys all pass `is_numeric()` as a list, at any depth, and keeps
  every key beside a named one. `reindexNumericKeyedArrays()` rewrites the property types once, at the publisher,
  with the publishing class's preserve-keys flag, since an inherited `toArray()` runs under the subclass's
  `$preserveKeys`. `ResourceTransformer::runAstAnalysis()` calls it, as do `AstEngine::analyze()` for a `toArray()`
  and a spread `R::make()->resolve()` arm, which runs `R`'s own filter. A key a method cast types keeps the cast, and a
  key whose result carries `keepsKeys` keeps its keys, since the filter never enters an object. `collect()` sets it, as
  does `ValueResolver::resolveSerializedNew()`, and `toTsType()` sets it on a class's own JSON shape, which
  `ReflectedTypeAcceptor` and the model attribute reads carry. `Model::toArray()` writes an `Arrayable` value as an
  array, so a delegated or spread model drops it there (`ModelAttributeResolver::serializesAsArray()`). A union carries
  it when each arm that holds an object literal or a `Record<number, …>` (`TsTypeShape::holdsKeyedObject()`) does,
  since the re-index rewrites no other arm. So does an inline literal with a key that is not a number and no spread,
  when each member holding one does (`InlineArrayHandler::keepsMemberKeys()`): the filter keeps a named key's keys.

`DepotTierResource` and its preserve-keys subclass `DepotTierKeyedResource` pin both layers.

## Interpolated keys

`ReadsReturnedVariables::collectVariableArrayAssignments()` publishes a key built from literal text around a variable,
such as `$data["{$name}_label"] = …` or `$data[$name.'_label'] = …`, as a template-literal index signature:
``[key: `${string}_label`]``. `InspectsAstNodes::resolveKeyName()` does the same for such a key written in a returned,
merged or nested literal. `InspectsAstNodes::interpolatedKeyName()` maps the parts to `IndexSignatureKey::fromParts()`,
which needs both a literal and a dynamic part and declines a literal part holding a backtick, because
`IndexSignatureKey::is()` has no escape for one.

Each literal part is escaped the way TypeScript reads template text: a backslash doubled, `${` as `\${`, and a carriage
return as `\r`. Written raw, a backslash would fail to compile (TS1125, TS1337) or match other text, and a CR would
read as a line feed. `IndexSignatureKey::literalSegments()` undoes all three.

A literal key whose text reads as a signature, `'[key: string]'`, is left out by `InspectsAstNodes::literalKeyName()`
with a warning, because printed bare it would type other keys.

A `#[TsCasts]` key may name such a signature by another spelling. `JsEmitter::castTargets()` decides which signature
it retypes, as [support helpers § `JsEmitter`](support-helpers.md#jsemitter) describes.

The key is never optional, since `[key: T]?:` is a syntax error. Its value gains `| undefined` instead, through
`ValueResult::asIndexSignatureValue()`; an optional `#[TsCasts]` on a signature is settled the same way, through
`JsEmitter::signatureSafeMember()`. Nested shapes and broadcast events print a signature bare, as resources do. The
`| undefined` is there for two reasons:

- **Runtime accuracy**: a key matching the pattern is not guaranteed present.
- **Consumer builds**: under plain `strict`, without `exactOptionalPropertyTypes`, a signature beside an optional
  named key it covers fails TS2411 unless its value admits `undefined`.

This repo's `tsconfig.json` sets `exactOptionalPropertyTypes`, so the workbench compiles either way. That is not
evidence the `| undefined` is redundant.

A value the body cannot type reaches `ReturnShapeRefiner` as `unknown | undefined`. The refiner treats that type as
unfilled for a signature name only, fills it from `@return array<string, V>`, and adds `| undefined` back. A
`@return array{…}` shape names literal keys only, so it never fills a signature. The body's own value stays in the
entry's `bodyType` and the fill in its `fillType`, for the reconcile below.

### Index signatures are reconciled with the keys beside them

TypeScript checks a signature against every named key its pattern covers (TS2411), and against a signature whose
pattern contains its own (TS2413). So `IndexSignatureReconciler::reconcile()` keeps a value the body did not give a
signature, a docblock fill or a union with other keys, only where no such check can fail. It runs wherever the keys
beside a signature are all known:

- **In the analyzer**: at the end of `analyzeReturnArray()`, and again after the refiner and `#[TsCasts]` in
  `analyze()` and `analyzeThisMethodSpread()`.
- **In each publisher, over every cast in force**, method casts included: `ResourceTransformer::runAstAnalysis()`,
  `BroadcastEventTransformer::transformProperties()`, `InertiaPageAnalyzer::buildPageData()`,
  `InertiaSharedDataAnalyzer::buildResult()`, and `AnalysisComposer::compose()` for `AstEngine::analyze()`. The two
  transformers also pass whether the interface has an extends clause, from `#[TsExtends]` or a `ts_extends.*` config
  entry. A publisher that adds or retypes keys after analysis must pass them. The reconcile ignores a cast key's import
  channels, since the cast type is what publishes. After the reconcile, the two transformers and `AnalysisComposer`
  fit them to the cast's text through `CastChannels::fit()`, and both Inertia analyzers drop them; see
  [`#[TsCasts]` overrides](ts-casts.md#a-cast-is-final-for-its-key).

It reads the keys as they will be published. A named key counts once, by its last entry, since a later write replaces
an earlier one, and a cast key counts with its cast type. Every entry of a signature's own name counts, with its own
value: its `fillType`, else its `bodyType`, else its type, so a matched key a cast retypes leaves no earlier arm. The
pattern is read back as TypeScript reads it, so `${string}` matches any run of characters, the empty one included.
Each signature then gets one outcome:

- **Union**: when its entries and every key its pattern matches can join, the entries fold into the first, typed with
  every arm plus `| undefined`. The named keys keep their own types.
- **Put back**: when an entry or a matched key cannot join, when another signature's pattern may overlap its own, or
  when the interface has an extends clause. Each entry a fill or a union changed goes back to its `bodyType`, which
  for a union holds its entries' body values joined where they can join; then the entries fold into the first where
  those values can join. The fill stays in `fillType`, and the next reconcile re-arms it before it decides.
- **Left alone**: a signature with no other entry, no matching key and no overlapping pattern, unless the interface
  has an extends clause.

A publisher passes its subject, so a matched named key that alone keeps the union out, beside entries that could
join, warns once: TypeScript checks that key against the value the signature keeps (TS2411). The analyzer passes
none, since a later write or cast may still make the key joinable.

A key cannot join a union when any of these holds:

- its type has a top-level `unknown` arm, which would swallow the typed arms
- it holds a token `shapeValueHasUnimportableToken()` rejects, such as a class name, a global name or a template
  literal type, where a string or number literal is fine
- it holds a string literal with a backslash, which the union splitter can cut apart
- it is not a cast key and carries an FQCN channel, whose token is rewritten under its own key's name

Two patterns are proven disjoint only when their leading literal texts, or their trailing ones, cannot both hold for
one key. `bodyType` is how a later conflict finds the body value. The refiner sets it on a fill, and a union sets it
to its entries' body values joined, or to the last entry's where they cannot join. `mergeReturnBranches()` unions it
across branches, and a method's `#[TsCasts]` clears it, since that type is the app's own. `fillType` follows it: the
refiner sets it, a union and a put-back fold keep their entries' fills joined, `mergeReturnBranches()` unions it, and
a method's `#[TsCasts]` clears it. `SamePatternKeysResource` pins the unions, and the test-only
`IndexSignatureConflictResource` and `SamePatternDeclinedResource` pin the conflicts.

## Import dispatch rules

A reflected type becomes an engine result only through `ReflectedTypeAcceptor::accept()`, which holds one invariant:
a type token never outruns its import. A result naming a class the package cannot import is rejected whole, and the
value degrades to `unknown`.

| Reflected shape | Accepted? | Dispatch channel |
| --- | --- | --- |
| Primitives and their unions (`string`, `int \| null`) | yes | none needed |
| One enum | yes | `directEnumFqcn` |
| Several enums (`Status\|Priority`) | yes | `embeddedEnumFqcns` |
| One `Model` subclass | yes; a method return then declines an abstract, `Illuminate\` or unpublished model | `modelFqcn` |
| Several `Model` subclasses | yes | `embeddedModelFqcns` |
| Models and enums together (`Order\|Status`) | yes | `embeddedEnumFqcns` and `embeddedModelFqcns`, never the single-entry channels |
| A `#[TsType(import: ...)]` class | yes | `customImports` |
| A non-`Model` class token, one `toTsType()` did not turn into a shape | **no**, `unknown` | none: no published file to import |
| `void`, `never`, `unknown`, `unknown \| null` or an empty type | **no**, `unknown` | none: no TypeScript shape |

A single-entry channel needs exactly one FQCN of its kind and none of the other kind, so a union of models and enums
always rides the multi-entry channels. Every class token must be a `Model`, or the whole result is rejected. Keeping
the enum half of `Order|SomeDto` would still leak the unimportable `SomeDto`.

### The reverse direction: a result carried back into a `TypeScriptTypeInfo`

[`ResultTypeInfoBridge::toTypeInfo()`](../../src/Ast/ResultTypeInfoBridge.php) is the inverse, for a consumer such as
`AccessorBodyAnalyzer` that needs an engine result in the model layer's `TypeScriptTypeInfo`. It reads only the five
channels the acceptor writes: `directEnumFqcn`, `modelFqcn`, `embeddedEnumFqcns`, `embeddedModelFqcns` and
`customImports`. It unions `customImports` rather than replacing them. Reading any other channel, such as a resource
channel, changes behavior and needs its own audit.

The bridge merges the classes the result names, replaces the merged type with the result's, and drops the merge's
`classTokenFqcns`, which queued the old type. Where the result spells one name for two classes, it sets the result's own
queue instead, unless that queue outruns its tokens.
[Import name registry § Rewriting aliased type references](import-name-registry.md#rewriting-aliased-type-references)
states the queue's contract. `Handover::parties` pins it through the model file, a resource that reads the accessor and
one that delegates.

### `directEnumFqcns` holds two kinds of entry

`MethodAnalysis::addProperty()` keys `directEnumFqcns` by property name for a value's own `directEnumFqcn`. It also
self-keys each FQCN of the value's `embeddedEnumFqcns`, through `DispatchesFqcnResults`. `modelFqcns` and
`nestedResources` mix the same two kinds. A consumer that tests a key must treat only property names as properties.
`ResourceTransformer::rewriteEnumResourceTypes()`'s mixed check is key-sensitive, so it looks up property names only.
Its import clean-up compares values, so it is right for both kinds.

### A resource's imports follow its published types

`ResourceTransformer` fits the analysis's imports to the types it publishes. It tests each name with
`TsTypeString::typeNameOccursIn()`, because an unused import fails `tsc` with TS6196 under `noUnusedLocals`. Two
steps depend on it:

- **After a `#[TsCasts]` override**: `dropOverriddenEnumResources()` drops a key's enum-resource records when its type
  holds none of the enums' type names, as a later spread's key leaves them, and runs before
  `pruneOverriddenEnumImports()`, which removes the names it reads. `pruneOverriddenAnalysisImports()` and
  `pruneOverriddenEnumImports()` drop each model, resource, `#[TsType]` and enum type import that no property type or
  extends clause still spells. Before aliasing, `keepSpelled()` keeps a class for a key whose type spells it only if
  that key's import channels carry it or no other class of its name, so a class no key reads forces an alias only
  where a type or an extends clause spells its name with no class of that name behind it.
  After the `AsEnum` rewrite, `pruneUnspelledImports()` drops each import whose local, aliased name no type spells.
- **After the resource's own `only()` or `except()`**: `FiltersModelAttributes::filterAnalysisByKeys()` rebuilds the
  analysis from its properties, `directEnumFqcns` and `modelFqcns` alone. That loses a multi-class attribute's FQCNs,
  every enum after the first and every `#[TsType]` import. `resolveMultiClassAccessorFqcns()` and
  `resolveMultiEnumAccessorFqcns()` import them back by the key's name, which is the attribute's own. A class or a
  `#[TsType]` import comes back only while the key's type spells it, and for a cast key only what its text spells and
  its import does not bring. The `Stockroom` and `Bulletin` resources pin these reads.

The token test matches a name wherever it stands, inside a string or a comment too, so it can keep an import that ends
up unused; see
[known gaps](../known-gaps.md#a-tscasts-value-that-spells-an-imported-name-inside-a-string-template-or-comment-keeps-the-import).

### `mergeReturnBranches()` carries every `MethodAnalysis::merge()` channel, plus two flat scalars

`mergeReturnBranches()` stays on the analyzer, off `MethodAnalysis`, because it needs a per-branch property map to
union a key that several branches set, which a field-by-field merge cannot express. It takes every channel from
`MethodAnalysis::merge()` on a scratch analysis, so the two cannot drift, and a channel added to `merge()` reaches
branch merging too. It is public for `InertiaPageAnalyzer`, which merges one component's renders through it.

The inline queues are never deduped, in `merge()` as in `addProperty()`, for the reason
[AST engine § `addProperty()` is the only way a value becomes a property](ast-engine.md#addproperty-is-the-only-way-a-value-becomes-a-property)
gives. Dropping duplicates would be lossless only when a queue is its distinct FQCNs in first-appearance order,
followed by repeats of the last one, and nothing holds a resource to that shape. `BranchedInlineFqcnResource` pins the
branch merge, and `ChildInlineFqcnResource` a spread parent.

`ValueResult::mergeUnion()` is the one producer that skips a repeat. `analyzeClosureUnion()` has already deduped
identical branch strings, so a second branch naming the same whole model renders no second token. `mergeUnion()` queues
that model on first sight, in loop position beside the branch's own embedded FQCNs. Hoisting it to the front would
swap same-basename models silently. `SameBasenameModelTrioResource`'s `collapsed_arms`, `reversed_arms` and
`control_arms` pin both halves.

`ValueResult::spellsTwoClassesAlike()` decides when `unionResults()` hands a union to `withMembersByClass()`: one name
spells two models, two resources or two enums read directly. `withMembersByClass()` reads it by class through
[`ClassTokenQueue`](../../src/Support/ClassTokenQueue.php), by the rule in
[Import name registry](import-name-registry.md#rewriting-aliased-type-references). A union holding an `EnumResource`
arm merges its enums by text, since the `AsEnum` rewrite spells that arm's token by its own import channel.
`CoalesceHandler`, `ConditionalMethodHandler::applyConditionalDefault()` and the two receiver sites enter through
`unionResults()`. `mergeReturnBranches()` still merges by text, for return branches and a merge call's value and
default alike. `HandoverResource` pins the `??`, ternary, to-many and inline-array shapes, and `HandoverNoticeResource`
the `when()` and `whenNull()` ones.

Three more rules keep each FQCN beside its own token:

- **Inline members keep their own arms**: `ThisPropertyHandler` carries a multi-class accessor's FQCNs through
  `ValueResult::withAttributeChannels()`. `InlineArrayHandler` walks members in order, taking each member's
  `inlineModelFqcns` over the self-keyed `modelFqcns` map. `WarehouseResource::$probe_nested` pins it.
- **An overriding key clears its parent's channels**: when a key overrides a spread parent's key,
  `analyzeReturnArray()` clears every channel for it through `MethodAnalysis::forgetChannels()`, the inline ones
  included. The child's occurrences then never consume the parent's queue.
- **Arm order is kept**: the branch union hoists one trailing `| null` through `TsTypeString::hoistNull()` and moves
  no type-name token relative to its queue.

`flatTypeAlias` and `flatTypeAliasFqcn`, the two scalars `merge()` never touches, take the first non-null branch
value. Only `buildCollectionDelegatedAnalysis()` sets them, and it never merges branches, so no fixture exercises that
rule.

## Enum resources

### The tolki `AsEnum` wrap substitutes a token; it never rebuilds the type

With `enums.use_tolki_package` on, an `EnumResource::make()` or `::collection()` wrap publishes `AsEnum<typeof Role>`
where the analyzer wrote the bare `RoleType`. Both rewrite paths substitute that token in place with
`TsTypeString::substituteEnumType()`: `ResourceTransformer::rewriteEnumResourceTypes()` for a top-level key, and
`InlineArrayHandler` for a key inside an inline array. Substitution keeps a richer shape, such as
`RoleType[] | Record<string, RoleType>` or a default's extra `string` arm. Rebuilding from the FQCN could express only
`X`, `X[]` and their nullable forms. The token pattern skips a namespace-qualified `foo.RoleType` and a longer
`RoleTypeExtra`. `RelationChainResource::$member_role_resources_filtered` and `$wrapped_filtered` pin the two paths on
the same PHP shape, and they must never disagree. `ResourceWriter` imports `AsEnum` only for a type that spells it.

### The inline wrap's own const token is aliased by the transformer, not here

`InlineArrayHandler` writes the enum's bare const name into `AsEnum<typeof Role>` during analysis, before
`resolveImportConflicts()` has assigned any alias. A top-level key never shows that bare name, because
`rewriteEnumResourceTypes()` builds its string from `constImportAliases` itself. For a nested wrap,
`ResourceTransformer` and `AnalysisComposer` alias the bare name after `resolveImportConflicts()`. They call
`TsTypeString::aliasTypeofConst()` over `inlineEnumResourceFqcns`, in the order `InlineArrayHandler` built it.
`aliasTypeofConst()` changes only the name after `typeof`. The same name bare is another enum's type.

The order matters because two members can wrap different enums that share one const name. `DealResource::$status_pair`
pins two `Status` enums, each keeping its own alias:
`{ app: AsEnum<typeof EnumsStatus>; crm: AsEnum<typeof CrmStatus> }`. `rewriteTypeReferences()` cannot do this,
since its name map holds type names and never `enumConstMap`. See
[import name registry § `ResourceTransformer`](import-name-registry.md#resourcetransformer) for the registration side.

### A mixed ternary inside an inline array

A mixed ternary wraps the enum in one arm and reads it directly in the other, as in
`$flag ? EnumResource::make($this->status) : $this->status`. `ValueResult::mergeUnion()` marks it by setting both
`enumFqcn` and `directEnumFqcn`, but the merged type string can collapse both arms into one token. So
`ValueResult::withEnumArmShapes()`, which [`TernaryHandler`](../../src/Ast/Handlers/TernaryHandler.php) and
[`MatchHandler`](../../src/Ast/Handlers/MatchHandler.php) call, reads each arm's own result and records the shape,
`wrapIsCollection` and `directIsArray`, in `MethodAnalysis::$enumResourceArmShapes`. It declines when an arm is itself
mixed, or when the wrap arms, or the direct arms, disagree on their shape.

Both rewrites build `AsEnum<typeof Status> | StatusType` from those flags, each arm with its own `[]`.
`rewriteEnumResourceTypes()` does it for a top-level key, and `InlineArrayHandler::expandMixedEnumType()` for a nested
one. The nested key cannot defer to the transformer, because only flat property lists leave the handler's
method-local analysis. `TeamStatusAuditResource::$audit` pins the nested case with two array-shaped arms.

Where no arm shape was recorded, as for `??` or a nested mixed ternary, `expandMixedEnumType()` reads the merged
members. A lone `StatusType` becomes `AsEnum<typeof Status> | StatusType`, and a `StatusType[]` member, which
`EnumResource::collection()` forced, becomes `AsEnum<typeof Status>[]`. That fallback can substitute the bare token
away, so `InlineArrayHandler` drops any enum import whose name no longer occurs in the type. This repo's
`tsconfig.json` sets `noUnusedLocals`, and `tsc` rejects an unused import under it.

In `laravel-ts-global.ts`, `TsTypeString::rewriteAsEnumToType()` folds an exact `AsEnum<typeof Status> | StatusType`
pair into one qualified reference, since both arms qualify to the same name there. A pair with `[]` on either side
stands for two different things, so it stays two references.

### An enum reached through a local or a helper

`ResolvesEnumPropertyArgTypes::resolveEnumFromPropertyArg()` follows a local assigned once as it follows a `when()`
parameter, so `$case = Status::Draft` wraps that case. Any other payload, such as a coalesce or a method's return, goes
to `resolveEnumFromResolvedPayload()`. It wraps the resolved type only when that holds one enum and nothing else, or a
list of it for `::collection()`. Two enums, even two that share a name, stay `unknown`. The payload's own `| null` stays
in every subject: an enum branch returns before `resourceWrapType()`.

[`SubjectHelperReturnResolver`](../../src/Ast/SubjectHelperReturnResolver.php) reads a `$this->helper()`, since no
declared return names the enum a wrap holds. It reads one only when every return is `null`, an `EnumResource` wrap or a
ternary of those, and one wraps. It seeds the body as a spread's is: the caller's name tables and relation model are set
aside, but for its proofs of `$this` reads, its parameters bind by what they declare and what the call passes, and its
guards narrow. A union that types no wrap declines. An untyped helper that can end without a value takes `| null`.
`PostStatusSourcesResource` pins a local, a helper and a method's return. Any other helper types from its declaration
or, when that is vague or missing, is
[read as a value](receiver-types.md#a-method-read-as-a-value-carries-import-channels) in a fresh scope that carries none
of the caller's proofs.

## Related

These pages own the rules this page links to:

- [AST engine](ast-engine.md): dispatch, handler ordering, `AnalysisScope` bindings and closure parameters, the run
  memo, and dropped union arms.
- [Receiver types](receiver-types.md): how a receiver's class is found, the receiver rules for `only()` and
  `except()`, and the body fallback.
- [Model attribute resolver](model-attribute-resolver.md), [accessor body analyzer](accessor-body-analyzer.md),
  [import name registry](import-name-registry.md) and [support helpers](support-helpers.md).
- [`#[TsCasts]` overrides](ts-casts.md): cast precedence, the final-text rule and the imports a cast keeps.
- [Type-inference gates](../testing/type-inference-gates.md): the CI checks for a type regressing to `unknown` and for
  a token emitted without its import.

These [known gaps](../known-gaps.md) come from the rules on this page:

- [A union arm the engine cannot type is left out](../known-gaps.md#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm)
- [A merge whose two sides name same-basename classes publishes only one of them](../known-gaps.md#a-merge-whose-two-sides-name-same-basename-classes-publishes-only-one-of-them)
- [A helper that returns an empty `[]` on one path publishes an object shape](../known-gaps.md#a-helper-that-returns-an-empty--on-one-path-publishes-an-object-shape-though--encodes-as-an-array)
- [`#[TsCasts]` and the top-level spread flatten disagree by scope](../known-gaps.md#tscasts-and-the-top-level-spread-flatten-disagree-by-scope-in-three-separate-ways)
- [A model spread inside a `collect()->map()` closure names the wrong model, or none](../known-gaps.md#a-model-spread-inside-a-collect-map-closure-names-the-wrong-model-or-none)
- [`Model::toArray()` on a receiver publishes `Record<string, unknown>`, deliberately](../known-gaps.md#modeltoarray-on-a-receiver-publishes-recordstring-unknown-deliberately)
- [On Laravel 12, `#[Collects]` cannot be resolved](../known-gaps.md#on-laravel-12-collects-cannot-be-resolved-so-use-the-collects-property)
- [An index signature its body types can fail to compile beside a key it cannot take in](../known-gaps.md#an-index-signature-its-body-types-can-fail-to-compile-beside-a-key-it-cannot-take-in)
- [A mixed enum ternary whose arms are both array-shaped ships a duplicated union member in the globals](../known-gaps.md#a-mixed-enum-ternary-whose-arms-are-both-array-shaped-ships-a-duplicated-union-member-in-the-globals)

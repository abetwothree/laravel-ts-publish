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
| [`FiltersModelAttributes`](../../src/Analyzers/Concerns/FiltersModelAttributes.php) | A top-level `$this->only()` or `$this->except()` on the resource's own model |
| [`ResolvesModelTypes`](../../src/Analyzers/Concerns/ResolvesModelTypes.php) | Whole-model delegation: the property set of a resource with no `toArray()` |
| [`InspectsResourceCalls`](../../src/Analyzers/Concerns/InspectsResourceCalls.php) | Which classes are resources, the published-set gate and `#[Collects]` resolution |
| [`ChecksPreserveKeys`](../../src/Analyzers/Concerns/ChecksPreserveKeys.php) | `R[]` or `Record<string, R>` for a collected resource |
| [`ResourceExpressionHandlers`](../../src/Ast/ResourceExpressionHandlers.php) | The ordered handler profile every value goes through |
| [`RelationFilterHandler`](../../src/Ast/Handlers/RelationFilterHandler.php) | `only()` and `except()` on a relation, a model accessor or a `map` proxy |
| [`ConditionalMethodHandler`](../../src/Ast/Handlers/ConditionalMethodHandler.php) | The `when*()` family, `unless()` and `transform()` |
| [`ToResourceHandler`](../../src/Ast/Handlers/ToResourceHandler.php) | `toResource()` and `toResourceCollection()` |
| [`StaticCallHandler`](../../src/Ast/Handlers/StaticCallHandler.php) | `X::make()`, `X::collection()` and a method chained on a new resource |
| [`InlineArrayHandler`](../../src/Ast/Handlers/InlineArrayHandler.php) | A nested array literal, its spread arms and its `AsEnum` wraps |
| [`ReturnShapeRefiner`](../../src/Ast/ReturnShapeRefiner.php) | Keys the body left `unknown`, filled from the method's own `@return` |
| [`IndexSignatureReconciler`](../../src/Ast/IndexSignatureReconciler.php) | Template-literal index signatures against the keys beside them |
| [`ReflectedTypeAcceptor`](../../src/Ast/ReflectedTypeAcceptor.php) | A reflected type into the FQCN channels, or a decline |
| [`MethodAnalysis`](../../src/Ast/MethodAnalysis.php) | The property list and the FQCN channels; `ResourceAnalysis` extends it |

The controller profile drops `ConditionalMethodHandler`, `ToResourceHandler` and `RelationFilterHandler`, and the
model-getter profile drops the first two. In every profile, the order handlers run in decides which one answers; see
[AST engine § Handler ordering](ast-engine.md#handler-ordering).

## How `analyze()` reads a method

`analyze()` builds the analysis in a fixed order, and each step carries a rule:

- **Only the class's own file counts**: [`MethodLocator::locateOwn()`](../../src/Ast/MethodLocator.php) misses a
  `toArray()` declared in another file, so a subclass without one walks its ancestors through
  `analyzeParentToArray()`. An ancestor's analysis wins only when it has properties. An empty one falls through to
  `buildCollectionDelegatedAnalysis()` or `buildModelDelegatedAnalysis()`. That guard is why the walk can run before
  the collection check: Laravel's own `ResourceCollection::toArray()` yields no properties.
- **Every array-literal `return` is a branch**: `analyzeAllReturnBranches()` merges them through
  `mergeReturnBranches()`, so a key one branch lacks publishes optional, and a guard's `return []` is an empty branch.
  It declines only when no `return` has items.
- **Any other body falls back to the first `return`**: `parent::toArray()`, an `array_merge()` of literals and
  `parent::` calls, `$this->only()` or `$this->except()`, or a bare `$this->method()`, which resolves like a
  `...$this->method()` spread.
- **A spread method sweeps every `return` too**: `analyzeThisMethodSpread()` merges array literals, arrays built in a
  variable and `[]` as branches, and falls back to `analyzeFirstReturn()` when any `return` is something else. It
  finds the method in its class, a trait or a parent through `MethodLocator::locate()`. It empties the method-local
  tables (`localVarBindings`, `varModelBindings`, `varClassBindings`, `varGuardBindings` and `varDocBindings`) and the
  `resolvingLocalVars` guard. It re-derives `requestVarNames` and `declaringFileClass` for the spread method, and
  restores all of them in a `finally`. `closureParamExprBindings`, `varCollectionBindings` and `varValueBindings` stay
  as the caller left them. `AnalysisScope::$visitedSpreadMethods` turns a self-spread or a cycle into an empty analysis
  instead of recursing until memory runs out.
- **The body wins, then `@return`, then `#[TsCasts]`**: `ReturnShapeRefiner::refine()` fills only keys the body left
  `unknown`, so a stale docblock never overrides a resolved type. `applyTsCastsFromMethod()` then applies the method's
  own casts. Both can change a key after the merge, so `IndexSignatureReconciler::reconcile()` runs again after them.

### A spread helper drops an untypable branch

`analyzeThisMethodSpread()` merges with `dropsUntypedBranches: true`, so `branchUnion()` leaves out a branch that
resolved to `unknown` and unions the rest, as a ternary drops its untypable arm. A key still publishes `unknown` when
every branch is `unknown`, or when only `null` is left, since that `null` says nothing about the dropped value.
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

### A stacked body-less collection resolves its parent's `$collects`

Take `LeafCollection extends MidCollection extends ResourceCollection`, neither declaring `toArray()`.
`LeafCollection`'s walk reaches `MidCollection`, whose own collection delegation returns a wrapped `{ data: … }`
shape. That shape has properties, so `LeafCollection` publishes its parent's collected type, even when its own
`#[Collects]` names another resource. When `MidCollection` sets `$wrap = null`, its delegation returns no properties, so
`LeafCollection` resolves its own `$collects`. It still inherits `$wrap = null`, because
`buildCollectionDelegatedAnalysis()` reads the default through reflection. No fixture has this shape.

### An inherited shape needs an inherited model

A subclass's inherited analysis types its columns only when a model backs it.
[`ModelClassResolver::resolve()`](../../src/Ast/ModelClassResolver.php) reads the nearest ancestor's `@mixin` or
`@extends` when the class has none of its own; its docblock lists the full precedence. That step runs for any resource
without its own tag, not only a body-less one. `BodylessOrderResource` pins the body-less case.

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
model's own member, because only a resource sets `AnalysisScope::$forwardsUndeclaredMembersTo`. How filters answer in
that body, and in a body that carries no imports, is in
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
  `$this->relation`.

`ProxyFilterDirectResource` and `ProxyFilterWrappedResource` write the same filters both ways, and
`ResourceAstAnalyzerTest` asserts they publish identical properties and imports. Two shapes stay outside the pair.
`...$this->author->only([...])` flattens under neither spelling. `$this?->only($keys)` publishes
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

`return $this->only([...])`, `return $this->except([...])` and their spreads filter `buildModelDelegatedAnalysis()`.
That is the property set whole-model delegation publishes: every attribute, accessors included, and every relation.
The two filters use it differently:

- **`only()`**: keeps each requested key the set has, in the model's own order. It then appends, in request order, a
  key the set lacks when `ModelAttributeResolver::resolveAttribute()` can type it, such as a `withCount()` virtual or
  an `@property` name, because `HasAttributes::only()` returns whatever `getAttribute()` finds. A key nothing can type
  is dropped, never published as `unknown`.
- **`except()`**: subtracts from the full set. That set holds accessors and relations, which `Model::except()` never
  returns, so the type lists keys the payload does not carry: `OrderExceptResource` publishes `user` and `items`. Only
  the relation filter's except branch is columns-only.

`exclude_hidden` follows Eloquent's split between keys the caller named and keys derived for it. `Model::only()`
returns a `$hidden` attribute, while `toArray()` and `except()` strip it:

- **Dropped when hidden**: whole-model delegation (no `toArray()`, or `parent::toArray($request)` returned or spread),
  `return $this->except([...])` and `$this->relation->except([...])`.
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
  `applyConditionalDefault()` then unions the default's type in through `ValueResult::mergeUnion()`, which carries its
  import channels. Joining the type strings by hand would emit a token with no import.
- **An `unknown` arm is never unioned in**: an `unknown` default leaves the value arm's type, and an `unknown` value
  arm, such as `whenPivotLoaded()`'s, keeps the key `unknown`, since `T | unknown` is `unknown`. The key stays required
  either way. This drop is not recorded in `DroppedUnionArms`; see
  [AST engine § Dropped union arms](ast-engine.md#dropped-union-arms) for the policy.
- **A default closure that needs more arguments than Laravel passes is skipped**: Laravel calls a default as
  `value($default)` with no arguments, except `transform()`, whose helper calls `$default($value)`. A closure requiring
  more parameters would throw `ArgumentCountError`, so `InspectsAstNodes::closureRequiresArguments()` keeps it out of
  the union, and the key stays required with the value arm's type.
- **A `never[]` arm drops beside an array arm**: a literal `[]` is `never[]`, since `json_encode([])` is `[]`, and it
  adds nothing to a real array type. So `whenLoaded('children', $this->children, [])` is `Category[]`. The drop is
  sound only because `never[]` means provably empty; typing an empty literal as `unknown[]` would break it.
- **`whenNotNull()` and `whenNull()` take a value, not a callback**: both call `when()` on `is_null($value)`.
  `whenNotNull()` strips the top-level `| null` from its value with `ValueResult::stripNullArm()`, which leaves a
  nested one. `whenNull()`'s value arm is `null`.
- **`unless()` and `mergeUnless()` reuse `when()` and `mergeWhen()`**: negating the condition changes which arm runs,
  never either arm's type.
- **`whenHas()`, `whenAppended()` and `whenExistsLoaded()` type from their value argument**: each returns
  `value($value, …)`. A closure's first parameter binds to `$this->{attribute}`, to the `{relation}_exists` flag or,
  for `whenAppended()`, to nothing. The attribute or flag answers only in three cases: no value is written, the value
  resolves to `unknown`, or the value is an `EnumResource` wrap. A wrap only moves the enum onto `enumFqcn`.
- **A skipped or literal `null` value publishes `null`**: none of those three swaps in an identity closure as
  `whenLoaded()` does, so `whenExistsLoaded('user', null, 'absent')` is `string | null`.
- **`whenExistsLoaded()` publishes `boolean`**: the type `ModelAttributeResolver` gives a model's own `*_exists`
  attribute, so a resource and its model agree on the flag.
- **`transform()` types from the callback**: the helper returns `$callback($value)` for a filled value, with the
  callback's first parameter bound to the value. Its default receives the value too, `null` arm included:
  `transform($this->rating, fn ($r) => 'x', fn ($r) => $r)` publishes `string | number | null`.

`InspectsResourceCalls::$conditionalMethods` names the family a second time, for a resource constructed around a
conditional call, such as `Resource::make($this->whenLoaded(...))`, which publishes optional.

A model-level `#[TsCasts]` wins over every rule here in the published file, because
`ResourceTransformer::applyOverrides()` runs after analysis. `Address` casts `latitude`, so check conditional typing
on a key no cast covers.

## `$this->resource` inside a relation closure is the resource's own model

Inside a `whenLoaded()` closure, `AnalysisScope::$closureRelationModelClass` holds the relation's model, and a bare
`$this->prop` reads that relation. `$this->resource->…` always reads the resource's own model, whichever closure it is
in. So [`PropertyChainHandler`](../../src/Ast/Handlers/PropertyChainHandler.php) and
[`MethodChainHandler`](../../src/Ast/Handlers/MethodChainHandler.php) root a chain whose first step is `resource` at
`$modelClass`. They also skip the `startIndex` shortcut that treats a closure chain's first step as the relation
proxy, which would drop the chain's first real relation. A model that declares a real `resource` relation keeps the
relation walk. `ClosureResourceRootResource` pins both spellings.

## Collection pipelines

Two handlers type a chain of collection operations.
[`RelationCollectionChainHandler`](../../src/Ast/Handlers/RelationCollectionChainHandler.php) takes a chain rooted at
`$this->{manyRelation}`. [`CollectionPipelineHandler`](../../src/Ast/Handlers/CollectionPipelineHandler.php) takes one
rooted at `collect($arg)`, and reads the element type off `$arg` when it resolves to `X[]`. A top-level `|` declines,
since the elements could come from either arm. Each handler tracks in its own `match` whether the keys are still
`0..n-1`. Once they are not, it adds the object arm `json_encode()` emits, from
`SpellsKeyedCollections::keyedObjectArm()`.

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
arm of an intersection instead of flattening its keys. `InlineArrayHandler::classifySpreadArm()` recognizes three kinds:

- **Resource arm**: a spread of one named resource, such as `UserResource::make($m)->resolve($request)`, never an
  array or collection of one.
- **Model arm**: `$var->toArray()` on a closure-bound model, or `$this->relation->toArray()` along real relations.
- **Collection arm**: `$var->toArray()` on a to-many `whenLoaded()` parameter, or a relation chain that ends to-many.
  It publishes `Record<number, User>`, because spreading a collection renumbers its elements from 0.

`$this->toArray()` and the other spreads `analyzeReturnArray()` already flattens are not arms. At the method's own top
level, the same classifier flattens the spread into the resource's keys instead. A resource arm gives its properties,
a model arm its published columns and appended accessors, and a collection a `[key: number]: Model` member.

Each arm is `Omit<>`'d against every explicit key and every later arm's `keyof`. PHP's `[...$a, 'k' => $v]` lets the
later write win, while TypeScript's `&` intersects both and makes a disagreeing key `never`. Three rules follow:

- **The subtraction is unconditional**: `Omit<T, K>` does not require `K extends keyof T`, so
  `buildSpreadArmTypes()` needs only a later arm's name, never its shape.
- **A collection arm is never subtracted, and never subtracts**: its keys are indexes, and every other key is a
  string. The one collision left is a numeric string key beside it. In a resource, `InspectsAstNodes::resolveKeyName()`
  drops an int key but keeps `'0'`. So `[...$members->toArray(), '0' => true]` publishes
  `Record<number, User> & { "0": boolean }`, though PHP overwrites index 0.
- **Arm order is source order**: all three kinds travel in one list, split by kind only when imports are dispatched.
  Resource arms go on `embeddedResourceFqcns`, and model and collection arms on `embeddedModelFqcns`. Grouping by kind
  any earlier would subtract against the wrong arm. `NestedResourceSpreadResource`'s
  `members_model_then_resource_spread` and `members_resource_then_model_spread` pin both directions.

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
candidate asks `isPublishedResourceClass()`, which also checks
[`PublishedResourceRegistry`](../../src/Cache/PublishedResourceRegistry.php). These are the sites:

- the candidate loop in `ToResourceHandler::resolveResourceForModel()`
- both naming loops in `ToResourceHandler::resolveResourceCollectionForModel()`
- the naming-convention branch of `InspectsResourceCalls::resolveCollectedResourceClass()`, the one resolver every
  `#[Collects]` caller shares

A class the developer wrote down stays on `isResourceClass()`: an explicit argument, `#[UseResource]`,
`#[UseResourceCollection]`, `#[Collects]`, `$collects`, `SomeResource::make()` and `new SomeResource()`.

The registry fails open: while it is empty, `isPublished()` answers `true`, so `RunnerForSource`, which never
registers, analyzes without narrowing. `Runner::run()` and `RunnerForSource::run()` reset it first.
`Runner::generateResources()` registers the whole collected list before generating, because a resource may reference
one collected after it. The registry is process-static, so `Tests\TestCase::setUp()` resets it too.

In the modular files a leaked guess fails `tsc` with TS2305 or TS2724, or with TS2307 when the run writes nothing into
the guessed class's directory. In `laravel-ts-global.ts` it fails with TS2304 or TS2552. `unimportable-token-gate.sh`
counts each of them, TS2307 in its relative-specifier sub-gate; see
[type-inference gates](../testing/type-inference-gates.md). `ResourceAstAnalyzerTest`'s published-set tests pin the
check itself with the `#[TsExclude]`d `AttachmentResource`, `AttachmentCollection` and `LedgerResource` fixtures.

### A morph union binds every target, and `toResource()` unions their resources

`ConditionalMethodHandler::analyzeWhenLoaded()` binds a `morphTo` closure parameter to every target, in
`AnalysisScope::$varClassBindings`. `ToResourceHandler` then maps each target model to its resource and publishes the
union, such as `reviewable?: ArtistResource | VenueResource`, reporting the classes on `embeddedResourceFqcns`. The
union is all or nothing. If one target has no resource, the key stays `unknown`, since a union missing an arm is wrong
for that arm, not vaguer. Its order is the morph-target order, which sorts by model FQCN or follows a
`@return MorphTo<X|Y>` docblock, never by resource name.

### `#[Collects]` and `#[PreserveKeys]`

`resolveCollectedResourceClass()` reads `Illuminate\Http\Resources\Attributes\Collects` behind `class_exists()`, because
Laravel 12 has no such class; see [version-guarded Laravel classes](../laravel-version-guards.md).

`ChecksPreserveKeys::collectionPreservesKeys()` honors both `#[PreserveKeys]` and `public $preserveKeys = true`.
`wrapCollectionElementType()` is the one place that turns it into `Record<string, R>` instead of `R[]`, and every
collection-typing site calls it. A site that skipped it would keep emitting `R[]` for a keyed collection, and only a
fixture of that exact call shape would notice. `grep -rn 'wrapCollectionElementType(' src/` lists the sites.

Each site reflects on the class Laravel instantiates:

- **The singular resource**: `SomeResource::collection()` and `toResourceCollection(SomeResource::class)`, which both
  run `JsonResource::collection()`.
- **The `ResourceCollection` subclass**: `make()`, `new` and a body-less collection.
- **`resolveResourceCollectionForModel()`'s `collectionFqcn`**: an argument-less `toResourceCollection()`. That is the
  plain resource when no collection class was found, from the `#[UseResource]` arm or the bare naming fallback.

Inertia page props share the trait through `InertiaResourcePropHandler`, so a key-preserving paginated collection
publishes `Omit<JsonResourcePaginator<R>, 'data'> & { data: Record<string, R> }`.

### A method called on a new resource types its payload

`StaticCallHandler` types a method called on `new self($x)`, `self::make($x)` or a chain of them. A method that
returns the same instance keeps the receiver's type, plus `| null` for a nullable return. It says so with a native
`static`, `self` or the class name, or with a docblock `@return $this`. Otherwise the method body is the payload.
`spreadAnalysis()` analyzes it, and `BuildsInlineObjectTypes::buildInlineObjectType()` flattens it. An empty analysis
declines, since `{}` would claim the payload has no keys.

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
`SomeResource::make($x)->resolve()` does.

## Interpolated keys

`collectVariableArrayAssignments()` publishes a key built from literal text around a variable, such as
`$data["{$name}_label"] = …` or `$data[$name.'_label'] = …`, as a template-literal index signature:
``[key: `${string}_label`]``. `interpolatedKeyName()` needs both a literal and a dynamic part. It declines a literal
part holding a backtick, because `JsEmitter::isIndexSignatureKey()` has no escape for one.

Each literal part is escaped the way TypeScript reads template text: a backslash doubled, `${` as `\${`, and a carriage
return as `\r`. Written raw, a backslash would fail to compile (TS1125, TS1337) or match other text, and a CR would
read as a line feed. `IndexSignatureReconciler::literalSegments()` undoes all three.

A `#[TsCasts]` key may name such a signature by another spelling. `JsEmitter::castTargets()` decides which signature
it retypes, as [support helpers § `JsEmitter`](support-helpers.md#jsemitter) describes. An Inertia page and Inertia
shared data still emit the losing spelling's import, unused.

The key is never optional, since `[key: T]?:` is a syntax error. Its value gains `| undefined` instead, through
`TsTypeString::orUndefined()`, for two reasons:

- **Runtime accuracy**: a key matching the pattern is not guaranteed present.
- **Consumer builds**: under plain `strict`, without `exactOptionalPropertyTypes`, a signature beside an optional
  named key it covers fails TS2411 unless its value admits `undefined`.

This repo's `tsconfig.json` sets `exactOptionalPropertyTypes`, so the workbench compiles either way. That is not
evidence the `| undefined` is redundant.

A value the body cannot type reaches `ReturnShapeRefiner` as `unknown | undefined`. The refiner treats that type as
unfilled for a signature name only, fills it from `@return array<string, V>`, and adds `| undefined` back. A
`@return array{…}` shape names literal keys only, so it never fills a signature. The body's own value stays in the
entry's `bodyType`, for the reconcile below.

### Index signatures are reconciled with the keys beside them

TypeScript checks a signature against every named key its pattern covers (TS2411), and against a signature whose
pattern contains its own (TS2413). So `IndexSignatureReconciler::reconcile()` keeps a value the body did not give a
signature, a docblock fill or a union with other keys, only where no such check can fail. It runs wherever the keys
beside a signature are all known:

- **In the analyzer**: at the end of `analyzeReturnArray()`, and again after the refiner and `#[TsCasts]` in
  `analyze()` and `analyzeThisMethodSpread()`.
- **In each publisher, over the keys its casts lay over the analysis**: `ResourceTransformer::runAstAnalysis()` and
  `BroadcastEventTransformer::transformProperties()` also pass whether the interface has an extends clause, from
  `#[TsExtends]` or a `ts_extends.*` config entry. `InertiaPageAnalyzer::buildPageData()` and
  `InertiaSharedDataAnalyzer::buildResult()` pass their casts. A publisher that adds or retypes keys after analysis
  must pass them. The reconcile ignores a cast key's FQCN channels, since the cast type is what publishes. After the
  reconcile, `BroadcastEventTransformer` and both Inertia analyzers drop those channels. `ResourceTransformer` keeps
  them, so `rewriteEnumResourceTypes()` still rewrites a cast `EnumResource` key, and making it drop them like the
  others would stop that rewrite.

It reads the keys as they will be published. A named key counts once, by its last entry, since a later write replaces
an earlier one, and a cast key counts with its cast type. Every entry of a signature's own name counts. The pattern is
read back as TypeScript reads it, so `${string}` matches any run of characters, the empty one included. Each signature
then gets one outcome:

- **Union**: when its entries and every key its pattern matches can join, the entries fold into the first, typed with
  every arm plus `| undefined`. The named keys keep their own types.
- **Put back**: when an entry or a matched key cannot join, when another signature's pattern may overlap its own, or
  when the interface has an extends clause. Each entry a fill or a union changed goes back to its `bodyType`.
- **Left alone**: a signature with no other entry, no matching key and no overlapping pattern, unless the interface
  has an extends clause.

A key cannot join a union when any of these holds:

- its type has a top-level `unknown` arm, which would swallow the typed arms
- it holds a token `shapeValueHasUnimportableToken()` rejects, such as a class name, a global name or a template
  literal type, where a string or number literal is fine
- it holds a string literal with a backslash, which the union splitter can cut apart
- it is not a cast key and carries an FQCN channel, whose token is rewritten under its own key's name

Two patterns are proven disjoint only when their leading literal texts, or their trailing ones, cannot both hold for
one key. `bodyType` is how a later conflict finds the body value. The refiner sets it on a fill, and a union sets it
to the last entry's body value. `mergeReturnBranches()` unions it across branches, and a method's `#[TsCasts]` clears
it, since that type is the app's own. `SamePatternKeysResource` pins the unions, and the test-only
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
| One `Model` subclass | yes | `modelFqcn` |
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

- **After a `#[TsCasts]` override**: `pruneOverriddenAnalysisImports()` and `pruneOverriddenEnumImports()` drop each
  model, `#[TsType]` and enum type import that no property type or extends clause still spells. The model prune reads
  class basenames before aliasing, so two same-basename models both stay imported while either is spelled, and one can
  stay imported unused under its alias.
- **After the resource's own `only()` or `except()`**: `FiltersModelAttributes::filterAnalysisByKeys()` rebuilds the
  analysis from its properties, `directEnumFqcns` and `modelFqcns` alone. That loses a multi-class attribute's FQCNs,
  every enum after the first and every `#[TsType]` import. `resolveMultiClassAccessorFqcns()` and
  `resolveMultiEnumAccessorFqcns()` import them back by the key's name, which is the attribute's own. A class or a
  `#[TsType]` import comes back only while the key's type spells it. The `Stockroom` and `Bulletin` resources pin
  these reads.

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

Three more rules keep each FQCN beside its own token:

- **Inline members keep their own arms**: `ThisPropertyHandler` carries a multi-class accessor's FQCNs through
  `ValueResult::withAttributeChannels()`. `InlineArrayHandler` walks members in order, taking each member's
  `inlineModelFqcns` over the self-keyed `modelFqcns` map. `WarehouseResource::$probe_nested` pins it.
- **An overriding key clears its parent's channels**: when a key overrides a spread parent's key,
  `analyzeReturnArray()` clears every channel for it, the three inline ones included. The child's occurrences then
  never consume the parent's queue.
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
the same PHP shape, and they must never disagree.

### The inline wrap's own const token is aliased by the transformer, not here

`InlineArrayHandler` writes the enum's bare const name into `AsEnum<typeof Role>` during analysis, before
`resolveImportConflicts()` has assigned any alias. A top-level key never shows that bare name, because
`rewriteEnumResourceTypes()` builds its string from `constImportAliases` itself. For a nested wrap,
`ResourceTransformer` and `AnalysisComposer` alias the bare name after `resolveImportConflicts()`. They call
`TsTypeString::aliasPropertyType()` over `inlineEnumResourceFqcns`, in the order `InlineArrayHandler` built it.

The order matters because two members can wrap different enums that share one const name. `DealResource::$status_pair`
pins two `Status` enums, each keeping its own alias:
`{ app: AsEnum<typeof EnumsStatus>; crm: AsEnum<typeof CrmStatus> }`. `rewriteTypeReferences()` cannot do this,
since its name map holds type names and never `enumConstMap`. See
[import name registry § `ResourceTransformer`](import-name-registry.md#resourcetransformer) for the registration side.

### A mixed ternary inside an inline array

A mixed ternary wraps the enum in one arm and reads it directly in the other, as in
`$flag ? EnumResource::make($this->status) : $this->status`. `ValueResult::mergeUnion()` marks it by setting both
`enumFqcn` and `directEnumFqcn`, but the merged type string can collapse both arms into one token. So
[`TernaryHandler`](../../src/Ast/Handlers/TernaryHandler.php) re-resolves each arm and records its shape,
`wrapIsCollection` and `directIsArray`, in `MethodAnalysis::$enumResourceArmShapes`. It declines when either arm is
itself mixed.

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

## Related

These pages own the rules this page links to:

- [AST engine](ast-engine.md): dispatch, handler ordering, `AnalysisScope` bindings and closure parameters, the run
  memo, and dropped union arms.
- [Receiver types](receiver-types.md): how a receiver's class is found, the receiver rules for `only()` and
  `except()`, and the body fallback.
- [Model attribute resolver](model-attribute-resolver.md), [accessor body analyzer](accessor-body-analyzer.md),
  [import name registry](import-name-registry.md) and [support helpers](support-helpers.md).
- [Type-inference gates](../testing/type-inference-gates.md): the CI checks for a type regressing to `unknown` and for
  a token emitted without its import.

These [known gaps](../known-gaps.md) come from the rules on this page:

- [A union arm the engine cannot type is left out](../known-gaps.md#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm)
- [A `return []` guard makes keys optional in a method body, but not inside a `merge()` closure](../known-gaps.md#a-return--guard-makes-keys-optional-in-a-method-body-but-not-inside-a-merge-closure)
- [A helper that returns an empty `[]` on one path publishes an object shape](../known-gaps.md#a-helper-that-returns-an-empty--on-one-path-publishes-an-object-shape-though--encodes-as-an-array)
- [`#[TsCasts]` and the top-level spread flatten disagree by scope](../known-gaps.md#tscasts-and-the-top-level-spread-flatten-disagree-by-scope-in-three-separate-ways)
- [A model spread inside a `collect()->map()` closure names the wrong model, or none](../known-gaps.md#a-model-spread-inside-a-collect-map-closure-names-the-wrong-model-or-none)
- [`Model::toArray()` on a receiver declines, deliberately](../known-gaps.md#modeltoarray-on-a-receiver-declines-deliberately)
- [On Laravel 12, `#[Collects]` cannot be resolved](../known-gaps.md#on-laravel-12-collects-cannot-be-resolved-so-use-the-collects-property)
- [A morph union whose targets' resources share a basename spells the same token twice](../known-gaps.md#a-morph-union-whose-targets-resources-share-a-basename-spells-the-same-token-twice)
- [An index signature its body types can fail to compile beside a key it cannot take in](../known-gaps.md#an-index-signature-its-body-types-can-fail-to-compile-beside-a-key-it-cannot-take-in)
- [A mixed enum ternary whose arms are both array-shaped ships a duplicated union member in the globals](../known-gaps.md#a-mixed-enum-ternary-whose-arms-are-both-array-shaped-ships-a-duplicated-union-member-in-the-globals)

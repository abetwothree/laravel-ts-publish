# AST engine

[`AstEngine`] is the entry point to the package's one php-parser layer. It reads a PHP method body, or an accessor's
closure, and resolves its return shape into TypeScript property types plus the FQCN channels that imports are built
from. Every inference feature runs on it: API resources, broadcast events, Inertia page props and shared data, model
metadata and accessor bodies. Open [`AstEngine`] for the entry points, [`ResourceAstAnalyzer`] for the walk over a
body's return branches, and [`ExpressionDispatcher`] for how one expression reaches the handler that types it.

## Where things live

These are the classes a change to the engine usually touches:

| Class | Owns |
| --- | --- |
| [`AstEngine`] | The public `analyze()`, and the `@internal` `analyzeMethod()`, `analyzePublicProperties()`, `bindingsFor()` and `analyzeModelClosure()` |
| [`ResourceAstAnalyzer`] | The `ExpressionEngine`: walks a method's return branches and resolves each value through the dispatcher |
| [`ExpressionDispatcher`] | Tries each handler that claims an expression's node class, in registration order |
| [`ExpressionHandler`], [`ExpressionEngine`] | The handler contract, and the callback a handler uses for a sub-expression it does not own |
| [`ResourceExpressionHandlers`], [`ControllerExpressionHandlers`] | The ordered handler profiles |
| [`src/Ast/Handlers/`](../../src/Ast/Handlers) | One handler per expression family |
| [`AnalysisScope`] | The mutable state of one analysis: subject, backing model and the name-keyed binding tables |
| [`MethodAnalysis`] | The analysis DTO: property rows plus the FQCN channels |
| [`AnalysisComposer`], [`AnalysisImports`] | Turn a `MethodAnalysis` into import maps, and the composer also aliases and wraps |
| [`AstParser`], [`MethodLocator`] | Parse a file, cached and recorded as a cache dependency, and find one method's AST node |
| [`AnalysisMemo`] | The cycle guards and the per-run memo of analyses |
| [`DroppedUnionArms`] | The record of union arms the engine left out |
| [`ReturnLiteralReader`] | The one string literal a method returns, for `broadcastAs()` |

[Receiver types](receiver-types.md) covers `ReceiverClassResolver` and the handlers that type `$x->m()` and `$x->prop`.

## A handler declines what it cannot type

`ExpressionHandler::resolve()` returns `null` to decline. The dispatcher then tries the next handler that claims the
node class, and when none answers, the caller degrades to `unknown`. A handler that answers `unknown` pre-empts every
handler after it, so a handler declines a shape it cannot type unless that `unknown` is deliberate. The answer of
`RelationFilterHandler`'s map-proxy arm is one such case, as
[ResourceAstAnalyzer § Attribute filters][attribute-filters] records. `MethodChainHandler` and `PropertyChainHandler`
test for a decline with `TsTypeString::isUnknownOnly()`, which ignores `null` arms.

## Handler ordering

When more than one handler claims a node class, registration order decides which one answers. A reorder that changes the
winner changes published types, so treat it as a behavior change, not a refactor.
`ResourceExpressionHandlers::handlers()` is the one ordered list, and each profile filters it:

- **`make()`**: the resource profile. It also runs for every subject `AstEngine::analyzeMethod()` reaches, such as a
  broadcast event, model metadata or a DTO.
- **`withoutResourceHandlers()`**: drops `ConditionalMethodHandler`, `ToResourceHandler` and `RelationFilterHandler`.
  Its one production caller is `ControllerExpressionHandlers::make()`.
- **`forModelClosures()`**: drops only the first two, for `AstEngine::analyzeModelClosure()`. `RelationFilterHandler`
  stays as the only handler that types a to-many relation's filter, a map proxy, a multi-model accessor's filter, or a
  filter on a collection-cast column or an `Eloquent\Collection` accessor.

Events and metadata run `make()`, so the three resource-only handlers are live in their bodies. Pointing
`analyzeMethod()` at `withoutResourceHandlers()` would move published event and metadata types. Measure the diff both
ways before you change it.

`ReceiverPropertyFetchHandler` and `ReceiverMethodCallHandler` sit last, with only `KnownMethodRuleHandler` after them,
so every specific handler answers first. `CollectionPipelineHandler` sits right after `RelationCollectionChainHandler`,
whose op table it shares. See [ResourceAstAnalyzer § Collection pipelines][collection-pipelines].

`ResourceExpressionHandlersTest` pins `make()`'s sequence, the filtered profiles and the behavioral pins below.
`MethodCallOrderingMatrixTest` runs every pair of `MethodCall` claimants in both orders over a curated corpus. A pair in
its `METHOD_CALL_PINNED` map must answer as `handlers()` orders it, and every other pair must agree. When a new
expression shape makes an inert pair disagree, the matrix fails. Pin the pair in the direction `handlers()` lists it.

### Controller profile

`ControllerExpressionHandlers::make()` inserts `ModelFinderHandler` and `InertiaResourcePropHandler` into
`withoutResourceHandlers()` immediately before `StaticCallHandler`. That handler's last arm claims every static call on
a named class and never declines one. `NewResourceHandler`, right after it, resolves a `ResourceCollection` to its
element array. Placed later, the two additions would be unreachable for those shapes. `ControllerExpressionHandlersTest`
pins the structure and the three orderings.

`InertiaPageAnalyzer` runs this profile over `AstEngine::bindingsFor()`'s scope, seeded from the action's route-bound
models, `Request` parameters and locals. The seeded scope is why `compact()`, a route-bound `$post` and
`$request->integer()` type with no Inertia-specific handler code.

### The honest ordering inventory

This table lists every node class that more than one resource-profile handler claims. A pinned pair must keep its order.
Every other pair is inert: the two handlers never answer the same expression, or they answer it the same way.
[Known gaps][gap-ordering] explains why the pins cover only the divergences a corpus has found.

| Node class | Claimants, in registration order | Pinned, winner first | Why the other pairs are inert |
| --- | --- | --- | --- |
| `MethodCall` | `FirstClassCallableHandler`, `KnownFunctionCallHandler`, `ConditionalMethodHandler`, `ToResourceHandler`, `StaticCallHandler`, `RelationFilterHandler`, `RelationCollectionChainHandler`, `CollectionPipelineHandler`, `VariableHandler`, `ReceiverMethodCallHandler`, `KnownMethodRuleHandler` | `FirstClassCallableHandler` before `ConditionalMethodHandler` and before `ToResourceHandler`: the loser calls `getArgs()`, which asserts `!isFirstClassCallable()` and fatals. `FirstClassCallableHandler` before `KnownFunctionCallHandler`: otherwise `auth()->user(...)` gets the guard's model, a confident type for a `Closure`. | The matrix proves each pair on its corpus. See the notes below. |
| `NullsafeMethodCall` | `RelationFilterHandler`, `MethodChainHandler`, `ReceiverMethodCallHandler` | None | `MethodChainHandler` declines every `only()`/`except()`, every answer that is only `unknown`, and every chain whose last step is not a relation, where it would reflect on the wrong receiver. `RelationFilterHandler` and `ReceiverMethodCallHandler` agree on a filter. No matrix runs this class. |
| `PropertyFetch` | `ThisPropertyHandler`, `PropertyChainHandler`, `VariableHandler`, `ReceiverPropertyFetchHandler` | `ThisPropertyHandler` before `PropertyChainHandler`: only `ThisPropertyHandler` threads a multi-model accessor's FQCNs out as `embeddedModelFqcns`, so the swap loses an arm's import | `VariableHandler` never reads a `$this` receiver, which the first two require. `ReceiverPropertyFetchHandler` declines every `$this->prop` leaf, and swapping it ahead of `PropertyChainHandler` or `VariableHandler` leaves the committed types unchanged. |
| `NullsafePropertyFetch` | `PropertyChainHandler`, `ReceiverPropertyFetchHandler` | None | `PropertyChainHandler` declines a chain that is only `unknown`, and the two agree on a chain both type |
| `StaticCall` | `InertiaWrapperHandler`, `StaticCallHandler`, `ReceiverMethodCallHandler` | `InertiaWrapperHandler` before `StaticCallHandler`: `StaticCallHandler` never declines a call on a named class, so it would reflect the wrapper and floor the prop at `unknown` | `StaticCallHandler` declines only a class expression it cannot name, such as `$record::className()`, the one shape `ReceiverMethodCallHandler` reaches. The `Inertia` facade declares no wrapper method, so `ReceiverMethodCallHandler` declines `Inertia::always(...)`. |
| `FuncCall` | `ArrayMergeHandler`, `KnownFunctionCallHandler` | None | `KnownFunctionCallHandler` rejects `array_merge()`'s reflected `unknown[]`. `ArrayMergeHandler` stays first in case that changes. |
| `BinaryOp\Coalesce` | `BinaryOpHandler`, which claims all of `BinaryOp`, and `CoalesceHandler` | None | `BinaryOpHandler` has no `Coalesce` branch |

These `MethodCall` pairs are the ones most likely to break under an edit:

- **Attribute filters**: on a model-backed scope, `RelationCollectionChainHandler`, `MethodChainHandler` and
  `VariableHandler`'s `$variable->method()` arm decline every `only()`/`except()`. `RelationFilterHandler` claims none
  on `$this->resource` itself. On a model-less scope, as in the controller profile, `RelationCollectionChainHandler`
  still reflects `$this->post->only([...])` to `Record<string, unknown>` and `except($keys)` to `unknown[]`. Those
  answers win over the receiver rules' `Pick<Post, …>` and `Record<string, unknown>`, and the matrix runs only a
  resource scope, so that divergence is unpinned.
- **`RelationFilterHandler` and `ReceiverMethodCallHandler`**: both claim a single-model relation filter, and they agree
  by construction, as [ResourceAstAnalyzer § Attribute filters][attribute-filters] explains. `ReceiverHandlersTest`'s
  "both claim … and answer it the same" cases pin the agreement.
- **`CollectionPipelineHandler` and `VariableHandler`**: both claim a trailing `values()` or `all()` on a `collect()`
  chain. They agree because the peel spells `values()` through `SpellsKeyedCollections::valuesList()` and peels only an
  `Enumerable` receiver or a `map()` it types itself. Typing the `collect()` argument needs a third handler, so
  `CollectionPipelineHandlerTest` pins this pair instead of the matrix.
- **`$this->method()`**: `SubjectMethodTypeResolver` declines when nothing in scope declares the method, so
  `RelationCollectionChainHandler` never floors `$this->when()` or `$this->can()` at `unknown`. It rejects
  `Model::getKey()`'s `mixed`, so only `ReceiverMethodCallHandler` answers a forwarded `$this->getKey()`.

One `PropertyFetch` pair can disagree where the corpus is silent. `VariableHandler` types `$x->member` from the
attributes of the model `varModelBindings` binds to `$x`, or of a `whenLoaded()` closure's relation model. It answers a
relation there as `unknown` instead of declining, where `ReceiverPropertyFetchHandler` would type it. No fixture reads a
relation that way.

The controller profile adds `ModelFinderHandler` to the `StaticCall` and `MethodCall` claimants, and
`InertiaResourcePropHandler` to the `StaticCall` and `New_` claimants. `ControllerExpressionHandlersTest` pins those
pairs, and the matrix does not run this profile.

## AnalysisScope

[`AnalysisScope`] is one mutable object shared by a whole analysis, not a value copied down the walk. Its field
docblocks say what each binding table holds. These three facts span other classes:

- **`modelClass` is scoped, not fixed**: `NarrowsInstanceofSubjects::resolveNarrowed()` narrows it, with
  `forwardsUndeclaredMembersTo`, while a ternary's proven arm resolves, for `TernaryHandler` and `ReceiverClassResolver`
  alike, then restores both. The narrowing happens below `AstEngine`'s `analysis:` memo key, so the key does not
  describe the model a nested resolution ran under.
- **`requestVarNames` skips a resource's `Request` parameter**: `ResourceAstAnalyzer::resolveRequestVarNames()` seeds it
  for any subject but a `JsonResource`. Typing the calls on a resource's `toArray(Request $request)` would move
  committed resource output.
- **`carriesImports` is false for the two readers that keep no FQCN channel**: the body fallback, and a getter that
  `AstEngine::analyzeModelClosure()` reads without imports. See [Receiver types § The body fallback][body-fallback].

### Writing a scope binding

A writer that binds a name for one body, such as a closure, a loop or a spread, first snapshots what it changes. It then
mutates the scope, resolves the body, and restores the snapshot in a `finally`. Every mutation, and every seeding step
that can throw, sits inside that `try`.

The scope outlives the expression, so a binding an exception leaves behind raises nothing. The binding answers a later
property with a wrong but plausible type, far from the writer that leaked it. Restore the whole table, not the one key
you believe you wrote.

Closure writers and `ClosureHandler` snapshot with `AnalysisScope::nameBindings()` and restore with
`restoreNameBindings()`. `ResourceAstAnalyzer::analyzeThisMethodSpread()` and
`NarrowsInstanceofSubjects::resolveNarrowed()` hand-roll their snapshots.
`ResourceAstAnalyzer::bindForeachLoopVariables()` takes none, because a `foreach` binding is method-wide by design. The
closure writers' snapshots restore over it, so a same-named closure parameter still resolves against its own binding, as
`ClosureParamShadowResource` pins.

#### A closure parameter owns its name

Readers rank the name-keyed tables differently. `VariableHandler` reads `varDocBindings` first and `varValueBindings`
last, while `ReceiverClassResolver::fromVariable()` reads `varClassBindings` first and never reads `varValueBindings`.
So an outer binding of a parameter's name, left in any table, outranks the parameter's own binding for some reader.
Nested in `map(fn (Comment $c) => …)`, a `collect(...)->map(fn ($c) => …)` would read its string element as a `Comment`.

Every writer that binds a closure parameter therefore calls `AnalysisScope::claimParameters()` inside its `try`. The
claim drops each parameter name from every table and marks the closure claimed. The writer reads the value the call
passes before it claims, since the claim can release a name that value shares, then binds the parameter to it.
`ClosureHandler` calls `releaseUnclaimedParameters()`, which makes the same drop for a closure no writer claimed, such
as a `whenNotNull()` value closure.

Each writer binds the first parameter to what Laravel passes the closure:

| Writer | Laravel passes | First parameter bound to |
| --- | --- | --- |
| The three map writers | `($value, $key)` | The element, never the key |
| `whenLoaded()` | The loaded relation | Its model, its collection in `varCollectionBindings`, or its `morphTo` targets |
| `whenHas()`, `whenExistsLoaded()` | The attribute, the `{relation}_exists` flag | That property read |
| `whenCounted()`, `whenAggregated(…, 'count')` | The count | `number` |
| `whenAggregated()`, other aggregates | The aggregate | Nothing: its type depends on column, function and driver |
| `transform()`'s callback | The filled value | A `$this->prop` read, a passed variable's bindings, or the value's type, a nullable model read as the model |
| `transform()`'s default | The blank value | The value's full type, `null` included |
| `when()`, `unless()`, `merge()`, `mergeWhen()`, `mergeUnless()`, `whenAppended()`, other defaults | Nothing | Nothing beyond the unpassed-parameter rule below |

These rules settle the cases the table leaves open:

- **A to-many `whenLoaded()` parameter holds the collection**: binding it to the element model would type a bare `$x` as
  a wrong singular type.
- **An unpassed parameter holds its default**: every conditional and merge writer calls
  `AnalysisScope::bindUnpassedParameters()`, which binds an optional one to its default's type, evaluated as PHP does,
  so `when($this->title, fn ($t = null) => $t)` publishes `null`, as Laravel returns. It binds a variadic one to
  `never[]`, and leaves one whose default it cannot type unbound. A map closure's parameters past the first, and a
  `whenNotNull()` or `whenNull()` value closure's, stay unbound.
- **A variadic first parameter collects its one argument**: `whenLoaded('author', fn (...$a) => $a)` is `User[]`. The
  map writers skip one, since `map()` passes two values of different types, and a `morphTo` `whenLoaded()` binds nothing
  for one.
- **`when()` and `unless()` bind a required first parameter anyway**: they bind the condition's `$this->prop`, although
  Laravel passes nothing and the call throws `ArgumentCountError`. The workbench pins it with
  `ConditionalParamPrimitiveResource` and `ConditionalParamEnumResource`, so it stays.
- **`whenAggregated()` publishes `number` for an aggregate it cannot type**: a driver can return a numeric or date
  string instead, such as a MySQL `SUM()`, which that `number` does not describe.

`ClosureHandlerTest` pins the release, and each writer's own tests pin its claim. `ShadowedClosureParamResource` stays
green with either mechanism alone, so it pins neither.

### Narrowing

Two mechanisms decide which class a variable holds at one point in a body, and each restores its binding in a `finally`:

- **An early-exit guard**: [`CollectsInstanceofGuards`] reads a top-level `if` with no `elseif` or `else` whose body
  ends in `return` or `throw`, and whose condition is `! $x instanceof C` or an `||` chain holding one. It binds `$x` to
  `C` in `varGuardBindings` with the offset where the `if` ends. An earlier `return` and the guard's own body therefore
  read `$x` unnarrowed.
- **A ternary's proven arm**: `TernaryHandler` resolves it under `NarrowsInstanceofSubjects::resolveNarrowed()`, which
  binds a variable in `varClassBindings`, or sets `modelClass` for `$this->resource` when one model is left. An arm that
  writes its subject is not narrowed.

A guard binds `$x` only when no write to it can land after the test, and a write before the guard is fine.
`GuardWritePostResource` pins both cases. Neither mechanism sees a write through a reference.

A positive `if ($x instanceof C) { … }` narrows nothing, because the walk is flat and the binding would still hold after
the body, where `$x` is what the test excluded.

`ClosureHandler` runs both passes over a `Closure`'s statements after unsetting every name the body writes from the
outer `localVarBindings` and `varDocBindings`. So a body-local shadows an outer one even when written twice.

[Receiver types § A ternary's `instanceof` condition][ternary] covers how a proven arm's classes narrow, and
[known gaps][gap-narrowing] records what each spelling publishes.

### Declared locals

An inline `/** @var T $x */` on a top-level `$x = …;` records a `varDocBindings` span through
`CollectsLocalVarBindings::bindDeclaredType()`. The span runs from after that statement to the next top-level statement
that writes `$x`, and `T` resolves against `AnalysisScope::$declaringFileClass`, a trait's file for a trait's method.

Inside the span the assigned value's reading wins, and `T` fills in only where that reading is vague.
`VariableHandler::declaredLocalValue()` applies that to a value, `ReceiverClassResolver::fromVariable()` to a receiver,
and `VariableHandler::ambientModel()` to the ambient model inside a `whenLoaded()` closure. Each states its own test for
vague. A `$x->prop` on a parameter bound in `varModelBindings` still reads that binding first, and a span can outlive a
write through a reference.

### What deliberately stays unbound

These shapes stay unbound on purpose, so they resolve as `unknown` rather than as a guess:

- **A local written more than once**: the flat walk cannot tell which write is live at a given return. An early-exit
  guard still narrows one reassigned only before it, and an inline `@var` types it for its span.
- **A first-class callable**, such as `->map(...)`: there is no closure body to bind a parameter into.
- **A relation-chain `map()` whose argument is not a closure**, such as `'strtoupper'`: the same reason.

## Subject mode

Subject mode types `$this->prop` from the subject's own declaration. It always applies when `AnalysisScope::$modelClass`
is `null`, as for a broadcast event, a DTO or any plain class. On a model-backed subject, it applies to a property
`SubjectPropertyTypeResolver::declaresOwnProperty()` claims, which answers before the model's attributes and relations.
PHP reads a declared property before `JsonResource::__get()` forwards to the model, so a resource's own `$stats`
publishes that object, not a same-named attribute. A result naming an abstract or `Illuminate\` model declines rather
than emit a token nothing imports.

A name the framework declares is never the subject's own, however the subject redeclares it: `JsonResource`'s
`resource`, `with` and `additional`, `ResourceCollection`'s `collects` and `collection`, every `Model` property, and
every static property. The exclusion keeps `$this->resource` meaning the backing model. `preserveKeys` is deliberately
outside that set, because Laravel reads it with `property_exists()` rather than declaring it.

[`SubjectPropertyTypeResolver`] is the one home for how such a property types, for
`AstEngine::analyzePublicProperties()` and both handler arms. It reads the `@var` docblock, then the native type, then
an untyped property's literal default. The default counts only while no method the class runs writes the property, an
ancestor's or a trait's included, and in application code a computed `$this->{$name}` write counts as one. The check
cannot see a subclass's write or a by-reference one, such as `array_push($this->tags, 5)`.

The dispatcher never hands a chain's inner node to a handler, so there are two arms. `ThisPropertyHandler` types a leaf
such as `$this->teamId`. `PropertyChainHandler` receives `$this->post->title` whole and resolves its root itself. With
no backing model, only a `Model` root hands off to the relation walk. On a model-backed subject it declines a root
`declaresOwnProperty()` claims, so `ReceiverPropertyFetchHandler` follows `$this->stats?->views` through `PostStats`.

## Dependency recording policy

Every analyzer file read goes through `AstParser::parseFile()`, directly or through [`MethodLocator`]. It records the
file with `DependencyRecorder::record()` before it checks its AST cache, so a cache hit records the dependency too. A
read that bypasses it leaves the generation cache serving stale output when that file changes.
`AstParser::parseSource()` records nothing, so use it only for source that is not on disk.

`MethodLocator` hands a method's file to `parseFile()` and records nothing itself. `locate()` finds a method wherever it
is declared, matching the name case-insensitively as PHP dispatches. `locateOwn()` searches the class's own file only. A
method inherited from another file is a deliberate miss, the signal callers use to detect delegation, while a parent
declared in the same file is a hit. Both memoize misses.

The body fallback records its dependencies the same way, because `MethodReturnTypeResolver` re-enters `analyzeMethod()`,
which reads the helper's file through `MethodLocator`. So a resource typed from a helper's body is invalidated when that
helper changes.

### The run memo replays what it recorded

[`AnalysisMemo`] is a container singleton holding the engine's cycle guards and a per-run memo of four analyses:
`AstEngine::analyzeMethod()`, `MethodReturnTypeResolver::resolve()`, `AccessorBodyAnalyzer::analyze()` and
`ModelAttributeResolver`'s accessor waterfall. Every key but `method-return:`, whose answer does not depend on the
import mode, gains an `@importless` suffix for an analysis that carries no imports.

A stored answer keeps the dependency paths recorded while it was computed, and every reuse records them again. The
generation cache therefore sees what a fresh computation would have shown it. An unpinned reuse also replays its
dropped-arm count, and happens only where computing the answer again could not differ, by the conditions in
`AnalysisMemo::reproducible()`. The outermost `analyzeMethod()` in a chain is pinned instead: stored even when a cycle
cut it short, and reused whatever is on the stack.

`AnalysisMemo::forget()` drops every unpinned answer. `Runner::run()`, `RunnerForSource::run()` and
`ModelAttributeResolver::buildMorphTargetMap()` call it, so a pinned answer outlives a run in the same process.

## MethodAnalysis

[`MethodAnalysis`] carries a method's property rows plus the FQCN channels that imports and aliases are built from. Its
constructor docblock describes each channel.

### `addProperty()` is the only way a value becomes a property

`MethodAnalysis::addProperty()` turns a handler's `ValueExpressionResult` into one property row and routes every channel
the result carries. Every collector goes through it, so a channel added to `ValueExpressionResult` reaches all of them
at once. A collector that built rows by hand would miss a new channel silently. Nothing throws, the property still
types, and only the generated TypeScript shows the missing import or alias.

Never deduplicate the three inline queues, `inlineEnumFqcns`, `inlineModelFqcns` and `inlineEnumResourceFqcns`.
`addProperty()` appends to them, and `merge()` concatenates them per occurrence, for return branches and a spread parent
alike. `TsTypeString::aliasPropertyType()` walks each as a positional queue against the type's tokens, so a property
naming the same class twice needs two entries.

Branch merging takes every channel from `merge()`, and [ResourceAstAnalyzer § `mergeReturnBranches()`][merge-branches]
owns its rules.

### An attribute read carries the attribute's channels

A read that publishes a model attribute's type hands on the attribute's `classFqcns`, `enumFqcns` and `customImports`
through `ValueResult::withAttributeChannels()`. The file then imports every class, enum and `#[TsType]` name that type
spells. Every read position hands them on, from `$this->accessor` and relation chains to closure parameters, `pluck()`,
`whenHas()`, `whenAppended()` and a model spread's columns, unless `#[TsCasts]` overrides the name.
[AccessorBodyAnalyzer][accessor-reads] covers the model side.

A key the resource's own `only()` or `except()` selects keeps only part of these channels, and
[ResourceAstAnalyzer § A resource's imports follow its published types][resource-imports] covers the rest.

## Dropped union arms

A union arm the engine cannot type is left out, never widened to `unknown`, so `$cond ? <untypable> : null` publishes
`null`. `unknown` would be more honest but less specific, and no change may make a published type less specific. Close
the gap instead with a return type, a `@return` docblock or `#[TsCasts]`. [Known gaps][gap-union-arm] records what users
see. Return-branch merges outside a spread helper keep the strict rule, where one `unknown` branch makes the key
`unknown`, as [ResourceAstAnalyzer § A spread helper drops an untypable branch][spread-branches] explains.

[`DroppedUnionArms`] records the drops made at a fixed set of sites, and each entry names its site, so a site that goes
silent shows. The sites are `ValueResult::analyzeClosureUnion()`, `TernaryHandler`'s narrowed arm,
`KnownFunctionCallHandler`'s `data_get()` default and `CoalesceHandler`. `analyzeClosureUnion()` records because
`unionResults()` receives only resolved results and cannot name the expression it drops. `CoalesceHandler` builds its
own member list, because `??` never returns its left operand's `null`. It strips that arm with
`ValueResult::stripNullArm()`, which the ternary union would keep. `ConditionalMethodHandler::applyConditionalDefault()`
also leaves an `unknown` default arm out, but records nothing, so neither the drop count nor `DroppedUnionArmsAuditTest`
sees that drop.

Recording is off until a test calls `start()`, but the count always runs. `AccessorBodyAnalyzer` and `VariableHandler`
compare it around a read to tell a dropped arm's `null` from a literal one, and `AnalysisMemo` replays it for a reused
answer.

`DroppedUnionArmsAuditTest` fails on a workbench arm missing from
`tests/Unit/Ast/Fixtures/dropped-union-arms-baseline.php`, and on a baseline entry the corpus no longer drops. It also
proves each recording site still fires. The baseline only shrinks: teach a rule to type the shape, then delete its
entries. A recorded arm is a candidate gap, not a proven loss, because `ClosureHandler` and `VariableHandler` fall back
to a return-type annotation or to `null`. Read the published property before you call an entry a bug.

## Public API

`AstEngine::analyze()` and the [`AnalysisResult`] it returns are the engine's whole public surface. The
[Analyzer API](https://tolki.abe.dev/ts/analyzer-api.html) page documents them for users, including the shapes
`analyze()` cannot answer. Everything else under `src/Ast/` is `@internal`, and
`tests/Architecture/InternalBoundaryTest.php` enforces three rules:

- Every other class under `src/Ast/` carries the tag.
- No untagged class extends a tagged one.
- No untagged public signature names an internal type.

`AstEngine`'s other public methods each carry their own `@internal` tag, because each names `MethodAnalysis`,
`MethodContext` or `AnalysisScope`, and the signature rule skips a tagged method. [Known gaps][gap-non-goals] records
why the engine offers no extension point.

`analyze()` hands `analyzeMethod()`'s DTO to [`AnalysisComposer`], which aliases same-basename collisions, rewrites
`EnumResource` wraps to `AsEnum<typeof X>`, and imports only the names the rewritten types spell.

`AnalysisComposer` tests each name with `TsTypeString::typeNameOccursIn()`, which keeps the import of a name only a
string or a comment spells, unused at worst. See [known gaps][gap-spelled-name]. Where its identifier boundary departs
from TypeScript's, the test either keeps an import unused or misreads syntax TypeScript rejects anyway. A name right
after a numeric literal is one such case, and a name beside a character TypeScript refuses there, reported as TS1127, is
another. `TsTypeString`'s identifier-character constants record which Unicode tables the boundary follows.

`ResourceTransformer` applies the same test to a resource's own imports, as
[ResourceAstAnalyzer § A resource's imports follow its published types][resource-imports] describes.

Three consumers rewrite a `MethodAnalysis`'s channels before importing, so they call `analyzeMethod()` and
`AnalysisImports::build()` themselves: `InertiaSharedDataAnalyzer`, `ModelMetadataAnalyzer` and
`BroadcastEventTransformer`. [`AnalysisImports`] decides what to import but never aliases, so a caller whose file can
spell two same-named tokens runs `ImportNameRegistry` over the result. See
[ImportNameRegistry](import-name-registry.md).

A `$wrap = null` collection comes back from `analyze()` with all three fields empty, its singular resource's import
included. Its whole answer lives in `MethodAnalysis::$flatTypeAlias` and `$flatTypeAliasFqcn`, which neither
`AnalysisImports` nor `AnalysisComposer` reads.

`tests/Feature/AnalyzeApiProbeTest.php` renders `analyze()` results into
`workbench/resources/js/types/data/testing/analysis-probe/`, so the
[unimportable-token gate](../testing/type-inference-gates.md) type-checks the public API's output with `tsc`.

## Which entry each consumer uses

Each consumer enters the engine at the point that fits its subject:

| Consumer | Entry | Why that entry |
| --- | --- | --- |
| `ResourceTransformer` | `ResourceAstAnalyzer::analyze()` on the resource profile | A resource's `toArray()` is that profile's own subject |
| `BroadcastEventTransformer` | `analyzeMethod($event, 'broadcastWith')`, else `analyzePublicProperties()`, and `ReturnLiteralReader` for `broadcastAs()` | `hasMethod()` counts an inherited or trait `broadcastWith()`, as Laravel does |
| `InertiaPageAnalyzer` | The controller profile over `bindingsFor()`'s scope, and `analyzeMethod()` only for props delegated whole to a collaborator | Page props are expressions in an action, not a method's return |
| `InertiaSharedDataAnalyzer` | `analyzeMethod($middleware, 'share')` and `AnalysisImports::build()` | It rewrites channels before importing |
| `ModelMetadataAnalyzer` | `bindingsFor()` on the declaring class's `provide()`, then `ResourceAstAnalyzer` on the resource profile | `analyzeMethod()` seeds no bindings. See [Model metadata][metadata-consumer]. |
| `AccessorBodyAnalyzer` | `analyzeModelClosure()` on `forModelClosures()` | The model is the subject, so a trait's accessor reads `$this` as the model using it |
| `MethodReturnTypeResolver` | `analyzeMethod()` with `carriesImports: false` | It is the body fallback. See [Receiver types][body-fallback]. |

## Related

These pages cover the engine's neighbors:

- [Receiver types](receiver-types.md): how the engine names the class a `$x->m()` or `$x->prop` receiver holds.
- [ResourceAstAnalyzer](resource-ast-analyzer.md): the resource-specific rules the handlers implement.
- [AccessorBodyAnalyzer](accessor-body-analyzer.md) and [Model metadata](model-metadata.md): the two model-side
  consumers.
- [ImportNameRegistry](import-name-registry.md): aliasing two same-basename imports.
- [Known gaps § Handler ordering is pinned pairwise][gap-ordering]: why a green ordering suite is narrower than it
  looks.
- [Type inference gates](../testing/type-inference-gates.md): the CI checks that read the generated types.
- [ADR: freeze Laravel Surveyor/Ranger and exit in stages](../decisions/2026-08-31-surveyor-staged-exit.md): why every
  inference feature moved onto this engine.

[`AnalysisComposer`]: ../../src/Ast/AnalysisComposer.php
[`AnalysisImports`]: ../../src/Ast/AnalysisImports.php
[`AnalysisMemo`]: ../../src/Ast/AnalysisMemo.php
[`AnalysisResult`]: ../../src/Ast/AnalysisResult.php
[`AnalysisScope`]: ../../src/Ast/AnalysisScope.php
[`AstEngine`]: ../../src/Ast/AstEngine.php
[`AstParser`]: ../../src/Ast/AstParser.php
[`CollectsInstanceofGuards`]: ../../src/Ast/Concerns/CollectsInstanceofGuards.php
[`ControllerExpressionHandlers`]: ../../src/Ast/ControllerExpressionHandlers.php
[`DroppedUnionArms`]: ../../src/Ast/DroppedUnionArms.php
[`ExpressionDispatcher`]: ../../src/Ast/ExpressionDispatcher.php
[`ExpressionEngine`]: ../../src/Ast/Contracts/ExpressionEngine.php
[`ExpressionHandler`]: ../../src/Ast/Contracts/ExpressionHandler.php
[`MethodAnalysis`]: ../../src/Ast/MethodAnalysis.php
[`MethodLocator`]: ../../src/Ast/MethodLocator.php
[`ResourceAstAnalyzer`]: ../../src/Analyzers/ResourceAstAnalyzer.php
[`ResourceExpressionHandlers`]: ../../src/Ast/ResourceExpressionHandlers.php
[`ReturnLiteralReader`]: ../../src/Ast/ReturnLiteralReader.php
[`SubjectPropertyTypeResolver`]: ../../src/Ast/SubjectPropertyTypeResolver.php
[accessor-reads]: accessor-body-analyzer.md#a-getter-that-reads-another-models-accessor
[attribute-filters]: resource-ast-analyzer.md#attribute-filters-on-any-model-receiver
[body-fallback]: receiver-types.md#the-body-fallback-carries-no-fqcn-channel
[collection-pipelines]: resource-ast-analyzer.md#collection-pipelines
[gap-narrowing]: ../known-gaps.md#instanceof-narrowing-depends-on-the-spelling-in-four-different-ways
[gap-non-goals]: ../known-gaps.md#deliberate-non-goals
[gap-ordering]: ../known-gaps.md#handler-ordering-is-pinned-pairwise-corpus-bounded
[gap-spelled-name]: ../known-gaps.md#a-tscasts-value-that-spells-an-imported-name-inside-a-string-template-or-comment-keeps-the-import
[gap-union-arm]: ../known-gaps.md#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm
[merge-branches]: resource-ast-analyzer.md#mergereturnbranches-carries-every-methodanalysismerge-channel-plus-two-flat-scalars
[metadata-consumer]: model-metadata.md#body-inference-is-an-engine-consumer
[resource-imports]: resource-ast-analyzer.md#a-resources-imports-follow-its-published-types
[spread-branches]: resource-ast-analyzer.md#a-spread-helper-drops-an-untypable-branch
[ternary]: receiver-types.md#a-ternarys-instanceof-condition

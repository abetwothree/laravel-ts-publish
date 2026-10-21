# AST engine

[`AstEngine`] is the entry point to the package's one php-parser layer. It reads a PHP method body, or an accessor's
closure, and resolves its return shape into TypeScript property types plus the FQCN channels that imports are built
from. Every inference feature runs on it: API resources, broadcast events, Inertia page props and shared data, model
metadata and accessor bodies. Open [`AstEngine`] for the entry points, [`ResourceAstAnalyzer`] for the walk over a
body's return branches, and [`ExpressionDispatcher`] for how one expression reaches the handler that types it.

## Where things live

The `src/Ast/` root holds only the public surface, [`AstEngine`] and [`AnalysisResult`]. Every other class sits in a
folder by role, and a new class joins the folder whose role it shares:

| Folder | Holds |
| --- | --- |
| `Analysis/` | One analysis's state: the scope, the `MethodAnalysis` DTO, the run memo and the dropped-arm record |
| `Calls/` | Reading a call: its arguments, parameter binding, by-reference writes and macros |
| `Composition/` | Turning a finished analysis into published types and imports, `#[TsCasts]` included |
| `Concerns/`, `Contracts/` | Shared traits, and the handler and engine interfaces |
| `Dispatch/` | The dispatcher and its ordered handler profiles |
| `Handlers/` | One handler per expression family |
| `Members/` | Typing a class's own methods and properties |
| `Parsing/` | php-parser access: parsing, locating methods and render calls, matching call syntax, literal returns |
| `Receivers/` | Which class an expression holds, and the methods called on it |
| `Resources/` | API resource facts: backing model, collections, response body, JSON:API |
| `Values/` | Value results and the readers that type a value: constants, reflection, `@var` docblocks, aggregates |

These are the classes a change to the engine usually touches:

| Class | Owns |
| --- | --- |
| [`AstEngine`] | The public `analyze()`, and the `@internal` `analyzeMethod()`, `analyzeMethodValue()`, `analyzePublicProperties()`, `bindingsFor()` and `analyzeModelClosure()` |
| [`ResourceAstAnalyzer`] | The `ExpressionEngine`: walks a method's return branches and resolves each value through the dispatcher |
| [`ReadsReturnedVariables`] | The walk over an array built in a variable, for any analyzer that implements its abstract hooks |
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
| [`ReturnLiteralReader`] | The string, or list of strings, every return of a method spells, for `broadcastAs()`, `getAuthIdentifierName()` and `uniqueIds()` |

[Receiver types](receiver-types.md) covers `ReceiverClassResolver` and the handlers that type `$x->m()` and `$x->prop`.

## A handler declines what it cannot type

`ExpressionHandler::resolve()` returns `null` to decline. The dispatcher then tries the next handler that claims the
node class, and when none answers, the caller degrades to `unknown`. A handler that answers `unknown` pre-empts every
handler after it, so a handler declines a shape it cannot type unless that `unknown` is deliberate. The answer of
`RelationFilterHandler`'s map-proxy arm is one such case, as
[ResourceAstAnalyzer § Attribute filters][attribute-filters] records. `PropertyChainHandler`
tests for a decline with `TsTypeString::isUnknownOnly()`, which ignores `null` arms.

## Handler ordering

When more than one handler claims a node class, registration order decides which one answers. A reorder that changes the
winner changes published types, so treat it as a behavior change, not a refactor.
`ResourceExpressionHandlers::handlers()` is the one ordered list, and each profile filters it:

- **`make()`**: the resource profile, a `JsonResource` subject's.
- **`forNonResourceSubjects()`**: drops `ConditionalMethodHandler` alone, for every other subject
  `AstEngine::analyzeMethod()` reaches, such as a broadcast event, shared data, model metadata or a DTO.
- **`withoutResourceHandlers()`**: drops `ConditionalMethodHandler`, `ToResourceHandler` and `RelationFilterHandler`.
  Its one production caller is `ControllerExpressionHandlers::make()`.
- **`forModelClosures()`**: drops only the first two, for `AstEngine::analyzeModelClosure()`. `RelationFilterHandler`
  stays as the only handler that types a to-many relation's filter, a map proxy, a multi-model accessor's filter, or a
  filter on a collection-cast column or an `Eloquent\Collection` accessor.

`ResourceExpressionHandlers::forSubject()` gives a `JsonResource` subject `make()` and any other subject
`forNonResourceSubjects()`, and `ResourceAstAnalyzer::handlers()` calls it when no profile is injected. An Inertia
middleware takes `forNonResourceSubjects()` through `invokingCallables()`, with `InertiaResourcePropHandler` before
`ToResourceHandler` and `ModelFinderHandler` before `StaticCallHandler`, since Inertia calls every callable it shares
and sends a shared value as it sends a page prop.
Only `JsonResource::resolve()` drops the `MissingValue` the `when*()` family returns, so on any other subject
`$this->when()` is that class's own method and publishes `unknown`. Dropping `ToResourceHandler` and
`RelationFilterHandler` too was measured to lose typed keys (`Comment::relationSummary()`'s filters) and to turn an
event's `toResource()` into `unknown`, which the payload contradicts.

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
element array. Placed later, the two additions would be unreachable for those shapes. `InertiaResourcePropHandler` also
types a JSON:API resource's single and collection props as their `@tolki/types` documents.
`ControllerExpressionHandlersTest` pins the structure and the five orderings. `invokingCallables()` swaps in
`FirstClassCallableHandler(invoked: true)`: Inertia calls every callable prop, at any depth, so a first-class callable
there types as the call it stands for.

`InertiaPageAnalyzer` runs this profile over `AstEngine::bindingsFor()`'s scope, seeded from the action's locals and its
parameters, which bind through `ParameterBinder::bindCall()` as a helper's do: a route-bound model, a `Request` and
every other typed parameter. The seeded scope is why `compact()`, a route-bound `$post` and `$request->integer()` type
with no Inertia-specific handler code.

### The honest ordering inventory

This table lists every node class that more than one resource-profile handler claims. A pinned pair must keep its order.
Every other pair is inert: the two handlers never answer the same expression, or they answer it the same way.
[Known gaps][gap-ordering] explains why the pins cover only the divergences a corpus has found.

| Node class | Claimants, in registration order | Pinned, winner first | Why the other pairs are inert |
| --- | --- | --- | --- |
| `MethodCall` | `FirstClassCallableHandler`, `KnownFunctionCallHandler`, `ConditionalMethodHandler`, `ToResourceHandler`, `StaticCallHandler`, `RelationFilterHandler`, `RelationCollectionChainHandler`, `CollectionPipelineHandler`, `VariableHandler`, `ReceiverMethodCallHandler`, `KnownMethodRuleHandler` | `FirstClassCallableHandler` before `ConditionalMethodHandler` and before `ToResourceHandler`: the loser calls `getArgs()`, which asserts `!isFirstClassCallable()` and fatals. `FirstClassCallableHandler` before `KnownFunctionCallHandler`: otherwise `auth()->user(...)` gets the guard's model, a confident type for a `Closure`. | The matrix proves each pair on its corpus. See the notes below. |
| `NullsafeMethodCall` | `ToResourceHandler`, `RelationFilterHandler`, `ReceiverMethodCallHandler` | None | `ToResourceHandler` claims `?->toResource()` and `?->toResourceCollection()`, which the others left `unknown`. `ReceiverMethodCallHandler` answers every other method chain, and leaves `unknown` any chain a step of which holds no single model. `RelationFilterHandler` and `ReceiverMethodCallHandler` agree on a filter. No matrix runs this class. |
| `PropertyFetch` | `ThisPropertyHandler`, `PropertyChainHandler`, `VariableHandler`, `ReceiverPropertyFetchHandler` | `ThisPropertyHandler` before `PropertyChainHandler`: only `ThisPropertyHandler` threads a multi-model accessor's FQCNs out as `embeddedModelFqcns`, so the swap loses an arm's import | `VariableHandler` never reads a `$this` receiver, which the first two require. `ReceiverPropertyFetchHandler` declines every `$this->prop` leaf, and swapping it ahead of `PropertyChainHandler` or `VariableHandler` leaves the committed types unchanged. |
| `NullsafePropertyFetch` | `PropertyChainHandler`, `ReceiverPropertyFetchHandler` | None | `PropertyChainHandler` declines a chain that is only `unknown`, and the two agree on a chain both type |
| `StaticCall` | `FirstClassCallableHandler`, `InertiaWrapperHandler`, `StaticCallHandler`, `ReceiverMethodCallHandler` | `FirstClassCallableHandler` first: `StaticCallHandler` types `self::collection(...)` as the collection, though the value is a `Closure`. `InertiaWrapperHandler` before `StaticCallHandler`: `StaticCallHandler` never declines a call on a named class other than the subject, so it would reflect the wrapper and floor the prop at `unknown` | `StaticCallHandler` declines a class expression it cannot name, such as `$record::className()`, and a call PHP sends to the subject's `__call()` that it types nothing for, such as a resource collection's `static::total()`. Those are the shapes `ReceiverMethodCallHandler` reaches. The `Inertia` facade declares no wrapper method, so `ReceiverMethodCallHandler` declines `Inertia::always(...)`. |
| `FuncCall` | `FirstClassCallableHandler`, `ArrayMergeHandler`, `KnownFunctionCallHandler` | `FirstClassCallableHandler` first: `KnownFunctionCallHandler` types `strlen(...)` from the function's return, though the value is a `Closure` | `KnownFunctionCallHandler` rejects `array_merge()`'s reflected `unknown[]`. `ArrayMergeHandler` stays first in case that changes. |
| `BinaryOp\Coalesce` | `BinaryOpHandler`, which claims all of `BinaryOp`, and `CoalesceHandler` | None | `BinaryOpHandler` has no `Coalesce` branch |

`OffsetReadHandler`, a literal-key read such as `$data['a']` from each object-literal or tuple arm of its holder, sits
after `MatchHandler` and is the only claimant of `ArrayDimFetch`, so it is in no pair.

These `MethodCall` pairs are the ones most likely to break under an edit:

- **Attribute filters**: on a model-backed scope, `RelationCollectionChainHandler` and
  `VariableHandler`'s `$variable->method()` arm decline every `only()`/`except()`. `RelationFilterHandler` claims none
  on `$this->resource` itself. On a model-less scope, as in the controller profile, `RelationCollectionChainHandler`
  declines a filter on a property that holds a model, so the receiver rules answer `$this->post->only([...])` with
  `Pick<Post, …>` and `except($keys)` with `Record<string, unknown>`. The same arm declines a method
  `ReceiverMethodReturnResolver::typesByRule()` names, and one it would answer `unknown`, so `ReceiverMethodCallHandler`
  answers `getKey()` or an untyped method on a route-bound or closure-bound model.
- **`RelationFilterHandler` and `ReceiverMethodCallHandler`**: both claim a single-model relation filter, and they agree
  by construction, as [ResourceAstAnalyzer § Attribute filters][attribute-filters] explains. `ReceiverHandlersTest`'s
  "both claim … and answer it the same" cases pin the agreement.
- **`CollectionPipelineHandler` and `VariableHandler`**: both claim a trailing `values()` or `all()` on a `collect()`
  chain. They agree because the peel spells `values()` through `SpellsKeyedCollections::valuesList()` and peels only an
  `Enumerable` receiver or a `map()` it types itself. Typing the `collect()` argument needs a third handler, so
  `CollectionPipelineHandlerTest` pins this pair instead of the matrix.
- **The map arm**: `VariableHandler` claims a `map()` over any receiver but a bare `$this`, after
  `RelationCollectionChainHandler` and `CollectionPipelineHandler` decline it. It declines a receiver the resolver names
  outside a collection or a paginator, which runs its own `map()`, and a resource collection's items, and adds the
  `null` a `?->` in the receiver sends. Each map writer reads its callback through `ClosureHandler`, so the matrix gives
  every handler one; it claims no `MethodCall`, so it joins no pair.
- **`$this->method()`**: `SubjectMethodTypeResolver` declines when nothing in scope declares the method, or when the
  declaration PHP runs types nothing, so `RelationCollectionChainHandler` never floors `$this->when()` or `$this->can()`
  at `unknown`. It rejects `Model::getKey()`'s `mixed`, so only `ReceiverMethodCallHandler` answers a forwarded
  `$this->getKey()` or `$this->resource->getKey()`.

`VariableHandler` types `$x->member` only from an attribute of the model `varModelBindings` binds to `$x`, never from a
`whenLoaded()` closure's relation model. It declines anything else, a relation included, so
`ReceiverPropertyFetchHandler` types the relation from what `$x` holds.

The controller and middleware profiles add `ModelFinderHandler` to the `StaticCall` and `MethodCall` claimants, and
`InertiaResourcePropHandler` to the `StaticCall`, `New_`, `MethodCall` and `NullsafeMethodCall` claimants. The two claim
disjoint method names on a `MethodCall`, and on a `StaticCall` disjoint classes, a model and an API resource, since both
claim `::make()`. `ControllerExpressionHandlersTest` pins both before `StaticCallHandler`, the prop handler before
`NewResourceHandler` and the method-call claimants that leave `toResource()` and `toResourceCollection()` `unknown`, and
the matrix does not run these profiles.

## AnalysisScope

[`AnalysisScope`] is one mutable object shared by a whole analysis, not a value copied down the walk. Its field
docblocks say what each binding table holds. These seven facts span other classes:

- **`modelClass` is scoped, not fixed**: `NarrowsInstanceofSubjects::resolveNarrowed()` narrows it, with
  `forwardsUndeclaredMembersTo` and `provenProxy`, while a ternary's proven arm, or a value inside a block an `if`
  proves on `$this->resource`, resolves, for `TernaryHandler`, `ReceiverClassResolver` and `ResourceAstAnalyzer` alike,
  then restores all three. The narrowing happens below `AstEngine`'s `analysis:` memo key, so the key does not describe
  the model a nested resolution ran under. A subject method read as a value under a one-model proof is the exception:
  its `method-return:` key gains `@backing:<model>`.
- **`varGuardBindings` reserves `PROXY_GUARDS`**: the proofs on an API resource's `$this->resource` sit under the key
  `'$this->resource'`, which no variable spells, so no parameter claim or `copyBindings()` reaches them, and
  `enterSubjectMethod()` sets them aside with every name table. `narrowedReads`, the `$this->prop` paths a proven arm
  holds, is a stack, not a name table, so `nameBindings()` leaves it out. It and `provenProxy` reach a subject method's
  body, except a path the body writes whole or one that runs through such a path, and neither does once the body
  replaces `$this->resource` (`CollectsInstanceofGuards::rewrittenThisPaths()`).
- **The resource proxy has one home**: `hasResourceProxy()` says whether `$this->resource` is the proxy, true on every
  API resource, model or not, and nowhere else. `proxyTargetFor()` derives `forwardsUndeclaredMembersTo` for the
  constructor, `rebase()` and `ResourceAstAnalyzer`. Handlers ask through
  `InspectsAstNodes::isProxyFetch()` and `isModelMemberFetch()`.
- **`rebase()` moves a scope to another subject**: a builder calls it rather than writing `subjectReflection` or
  `modelClass`, so the proxy target and the subject's collection facts, `reindexesNumericKeys` and
  `collectedElement`, follow, as `AstEngine::analyzeModelClosure()` does. The narrowing changes only the backing,
  through `setBackingModel()`, since those facts are the runtime class's. `ResourceAstAnalyzer` seeds them from its
  runtime class on any scope, through `seedCollectionFacts()`, so a supplied scope or an ancestor's body reads a
  collection's items as the class Laravel builds does.
- **`requestVarNames` skips a resource's `Request` parameter**: `ParameterBinder::bindCall()` seeds it for any subject
  but a `JsonResource`. Typing the calls on a resource's `toArray(Request $request)` would move committed resource
  output.
- **`carriesImports` is false for the two readers that keep no FQCN channel**: the body fallback's literal shape, and a
  getter that `AstEngine::analyzeModelClosure()` reads without imports. See
  [Receiver types § The body fallback][body-fallback].
- **`varDeclaredBindings` holds a parameter's declared classes**: the floor every narrowing refines, so
  `ReceiverClassResolver` reads it after `varModelBindings`, and a guard narrows it, as `GuardWritePostResource` pins.

### Writing a scope binding

A writer that binds a name for one body, such as a closure, a loop or a spread, first snapshots what it changes. It then
mutates the scope, resolves the body, and restores the snapshot in a `finally`. Every mutation, and every seeding step
that can throw, sits inside that `try`.

The scope outlives the expression, so a binding an exception leaves behind raises nothing. The binding answers a later
property with a wrong but plausible type, far from the writer that leaked it. Restore the whole table, not the one key
you believe you wrote.

Closure writers and `ClosureHandler` snapshot with `AnalysisScope::nameBindings()` and restore with
`restoreNameBindings()`. `NarrowsInstanceofSubjects::resolveNarrowed()` hand-rolls its snapshot.
`ResolvesModelRelationTypes::bindForeachLoopVariables()` takes none, because a `foreach` binding holds for the whole
body by design, and `ClosureHandler` calls it inside its own snapshot. The closure writers' snapshots restore over it,
so a same-named closure parameter still resolves against its own binding, as `ClosureParamShadowResource` pins.

#### A closure parameter owns its name

Readers rank the name-keyed tables differently. `VariableHandler` reads `varDocBindings` first and `varValueBindings`
last, while `ReceiverClassResolver::fromVariable()` reads `varClassBindings` first and `varValueBindings` only for an
enum case. So an outer binding of a parameter's name, left in any table, outranks the parameter's own binding for some
reader. Nested in `map(fn (Comment $c) => …)`, a `collect(...)->map(fn ($c) => …)` would read its string element as a
`Comment`.

Every writer that binds a closure parameter therefore calls `AnalysisScope::claimParameters()` inside its `try`. The
claim drops each parameter name from every table, its binding end included, and marks the closure claimed. The writer
reads the value the call passes before it claims, since the claim can release a name that value shares, then binds the
parameter to it. `ClosureHandler` calls `releaseUnclaimedParameters()`, which makes the same drop for a closure no
writer claimed, such as a `whenNull()` value closure. It then binds each parameter whose native type names one class and
that no writer bound to a class, or to a value that names one or is `null`: a non-null concrete model in
`varModelBindings`, any other class in `varDeclaredBindings`, before it seeds the body, so a write that replaces the
parameter ends that binding.

Each writer binds the first parameter to what Laravel passes the closure:

| Writer | Laravel passes | First parameter bound to |
| --- | --- | --- |
| `BindsMapCallbacks::resolveMapCallback()`, for every `map()` | `($value, $key)` | The element's model, or a concrete model hint it does not extend, in `varModelBindings`; else the element's value. Never the key |
| `whenLoaded()` | The loaded relation | Its model, its collection in `varCollectionBindings`, or its `morphTo` targets |
| `whenHas()`, `whenExistsLoaded()` | The attribute, the `{relation}_exists` flag | That property read |
| `whenCounted()`, `whenAggregated(…, 'count')` | The count | `number` |
| `whenAggregated()`, other aggregates | The aggregate | The aggregate without its `null`, as the model's declaration or [`AggregateValueType`] types it; nothing when neither does |
| `transform()`'s callback | The filled value | A `$this->prop` read, a passed variable's bindings, or the value's type, a nullable model read as the model. The call proves the parameter non-null, so a bare read of it drops the `null` arm, like any read a guard proves non-null ([Narrowing](#narrowing)) |
| `transform()`'s default | The blank value | The value's full type, `null` included |
| `whenNotNull()`'s closure or first-class callable | Nothing, and always, since a closure is never `null` | Nothing beyond the unpassed-parameter rule below |
| `when()`, `unless()`, `merge()`, `mergeWhen()`, `mergeUnless()`, `whenAppended()`, other defaults | Nothing | Nothing beyond the unpassed-parameter rule below |

A first-class callable in a position where Laravel passes one value binds the same way:
`FirstClassCallableHandler::passingOneValue()` reads it as `fn ($v) => m($v)`.

These rules settle the cases the table leaves open:

- **A to-many `whenLoaded()` parameter holds the collection**: binding it to the element model would type a bare `$x` as
  a wrong singular type.
- **An unpassed parameter holds its default**: every conditional and merge writer calls
  `AnalysisScope::bindUnpassedParameters()`, which binds an optional one to its default's type, evaluated as PHP does,
  so `when($this->title, fn ($t = null) => $t)` publishes `null`, as Laravel returns. It binds a variadic one to
  `never[]`, a static closure or first-class callable default to `Record<string, never>`, the `{}` Laravel sends, and
  leaves one whose default it cannot type unbound. A readable `new X` default also binds the `new` in
  `closureParamExprBindings`, which the receiver resolver reads as an unsaved `X`. A map closure's parameters past the
  first, and a `whenNull()` value closure's, which never runs, stay unbound.
- **A variadic first parameter collects its one argument**: `whenLoaded('author', fn (...$a) => $a)` is `User[]`, and a
  `morphTo` `whenLoaded()` binds the list of its targets, its element never `null`. The map binder skips one, since
  `map()` passes two values of different types.
- **`when()` and `unless()` bind a required first parameter anyway**: they bind the condition's `$this->prop`, although
  Laravel passes nothing and the call throws `ArgumentCountError`. The workbench pins it with
  `ConditionalParamPrimitiveResource` and `ConditionalParamEnumResource`, so it stays, and `ts:publish` warns that the
  call throws.
- **`whenAggregated()` types its aggregate as the model declares it**: the model's own accessor or `@property` on
  `{relation}_{function}_{column}`, as declared, then its built-in cast, then, for `MIN()` and `MAX()`, a user's
  `custom_ts_mappings` entry for the related column's type, then what the connection's driver returns for the related
  column ([`AggregateValueType`]), else `number`. Every aggregate but a count or one whose function the call computes
  keeps `| null`, the SQL `NULL` over no rows, unless the model's declaration rules it out.

`ClosureHandlerTest` pins the release, and each writer's own tests pin its claim. `ShadowedClosureParamResource` stays
green with either mechanism alone, so it pins neither.

#### A called method's parameters

A method body read apart from its call, such as a spread helper, the body fallback, a method read as a value,
`SubjectHelperReturnResolver`'s helper or any analyzer that builds its own scope, binds its parameters with
[`ParameterBinder`]'s `bindCall()`, by the type they declare: the native type, else a whitelisted `@phpstan-param`,
`@psalm-param` or `@param` where the native type is vague. One concrete, non-null model goes in `varModelBindings`; any
other parameter puts its classes, when it names only classes, in `varDeclaredBindings` and its value in
`varValueBindings`. A `Request` goes in `requestVarNames`, except in an API resource, and a name already bound stays as
it is. `CollectsLocalVarBindings::seedBody()` then seeds the body's own bindings, ends and guards from one write scan,
in every body reader, a closure's included.

A parameter the body writes keeps its class binding, which ends at the write as [Declared locals](#declared-locals)
says, and a write holding fewer of a declared union's classes ends it as well. It gets no value binding, which would
outlive a write naming no class such as `$n = (string) $n`, so its local answers. The binding reads only the
declaration, so the `analysis:` and `method-return:` keys stay valid. A call, spread or read as a value, also binds each
parameter whose declaration binds nothing (no class, no type precise enough to publish, natively or by `@param`) to what
its argument binds, through `CallSite::passedTo()` and `bindCall()`, read before `AnalysisScope::enterSubjectMethod()`
sets the caller's tables aside. A value read keys its memo on what the call binds ([The run memo replays what it
recorded](#the-run-memo-replays-what-it-recorded)). A model no row backs yet binds as a `new` of its class, so its key
keeps `| null`, as a `transform()` callback's does. An argument holding narrower classes than a declared abstract class,
interface or union, such as a post passed to `Model $m`, replaces the declared binding the same way. A call binds
nothing for an argument that may hold a closure, whose value hangs on whether the method calls it: the closure itself,
an arm of `?:`, `??` or `match`, or a local the caller's scope binds to one (`InspectsAstNodes::mayHoldCallable()`).
Only a caller that calls every callable it reads, as Inertia does (`ExpressionEngine::invokesCallables()`), binds it. A
value read binds nothing for a first-class callable either; a spread binds it to the `Closure` it creates, which Laravel
sends as `{}`.

### Narrowing

Two mechanisms decide which class a variable, `$this->resource` or a `$this->prop` read holds at one point in a body,
and each restores its binding in a `finally`:

- **An `if` chain's tests**: [`CollectsInstanceofGuards`] reads every `if` in every block of the body, and binds what
  its `instanceof` tests prove in `varGuardBindings`, each proof with the offsets it holds between: a branch's block,
  where its test holds and every earlier one failed, and past the chain to the end of its block, where the tests of its
  leading branches that always leave failed. A branch leaves a whole body by a last `return`, `throw` or `exit()` no
  `break` or `continue` skips, and a nested block also by a last `break` or `continue`, whatever its level.
  `instanceofProofs()` reads a test through `!`, an `&&` that holds, an `||` that fails, or an `||` chain on one
  variable. An earlier `return` and a guard's own body read `$x` unnarrowed, and a proof inside another holds what both
  prove (`ReceiverClassResolver::provenClasses()`). On an API resource a test on `$this->resource` binds under
  `AnalysisScope::PROXY_GUARDS`, and `ResourceAstAnalyzer::analyzeValueExpression()` resolves a value inside one of its
  spans through `resolveNarrowed()`, as a ternary's proven arm.
- **A ternary's proven arm**: `TernaryHandler` resolves it under `NarrowsInstanceofSubjects::resolveNarrowed()`, which
  binds a variable in `varGuardBindings` past its test, or records `provenProxy` for `$this->resource`, setting
  `modelClass` too when one model is left, or pushes a chain of property reads on `$this` onto `narrowedReads`, which
  `ReceiverClassResolver::fromPropertyFetch()` answers for a read of the same property names. A test that rules out no
  class `$resource` is redeclared as, and no `null` it admits, records nothing, and an arm that writes its subject is
  not narrowed.

`AnalysisScope::guardAt()` gives the proof a read falls within, the one that starts latest. `fromVariable()` narrows
whatever binds the variable by it, so a `morphTo` parameter's targets narrow and a collection keeps its element, and a
model-bound variable read bare publishes the subclass a proof names. A parameter a call passes `$x` to takes the proof
in force at the call, for every read of the parameter.

A test binds `$x` only when no write to it can land between the test and the end of what it narrows; a write before
the test, such as the `foreach` assigning the value it tests before each pass, or past the block a nested proof ends
with, is fine; a by-reference `foreach` binds nothing. `GuardWritePostResource` pins both cases. Both see an
argument a call passes to a by-reference parameter of a callee the scan can name, but a built-in's that keeps the value
in some form ([By-reference calls](#by-reference-calls)); neither sees one on a receiver it cannot name.

A guard also proves reads non-null, in `AnalysisScope::$nonNullReads`: a `when()`, `unless()`, `mergeWhen()` or
`mergeUnless()` condition for its value, a ternary's for each arm, `transform()`'s filled value for its callback, an
`if` chain's for each block it runs, and the failed conditions of the chain's leading branches that always exit for the
rest of the block holding it. `ReadsNonNullGuards::nonNullReads()` lists the condition forms it reads. A bare read
the scope proves non-null drops its `null` arm, unless that would leave only `null`. A closure or a spread method that
writes a name drops the proofs of that name it came in with. A call between a guard and its read, such as a method
that assigns the read or `setRelation()`, is not seen as a write, so the proof survives it (#115).

`ClosureHandler` runs both passes over a `Closure`'s statements after unsetting every name the body writes from the
outer `localVarBindings` and `varDocBindings`. So a body-local shadows an outer one even when written twice. A write
inside a nested function reaches the body around it only through a `use (&$x)`: an arrow function or a by-value `use`
writes its own copy.

[Receiver types § A ternary's `instanceof` condition][ternary] covers how a proven arm's classes narrow, and
[known gaps][gap-narrowing] records what each spelling publishes.

### Declared locals

An inline `/** @var T $x */` on a top-level `$x = …;` records a `varDocBindings` span through
`CollectsLocalVarBindings::bindDeclaredType()`. The span runs from after that statement to the next top-level statement
that writes `$x`, and `T` resolves against `AnalysisScope::$declaringFileClass`, a trait's file for a trait's method.

A `$x = …;` nested in a block binds `$x` past its statement, to the end of that block or to the start of the block's
statement holding the next write (`bindBlockLocals()`), as a binding gap holding the value; a `@var` on it spans the
same reads. A block may not run and a loop runs it again, so a read before the write or past the block stays unbound,
as does a name a reference can write through (`=&`, `use (&$x)`, `foreach (… as &$x)`, `global`, `static`). A name a
parameter or a seeded binding holds gets no gap, since the rules below end that binding. A body drops the outer gaps of
each name it writes that holds no binding, so a closure's own `$x` never reads its enclosing block's, while a bound
name keeps its exit-block gap for the value a closure captures. `AnalysisScope::copyBindings()` passes a closure
parameter the value of the gap one write opened, a block local's or a parameter's.

Inside the span the assigned value's reading wins, and `T` fills in only where that reading is vague.
`VariableHandler::declaredLocalValue()` applies that to a value, and `ReceiverClassResolver::fromVariable()` to a
receiver. Each states its own test for vague. A parameter's own binding holds until the body's first write that replaces
it with a value naming another class. A collection binding also ends at a step that replaces its models with other
values, such as `pluck()`, `map()` or `groupBy()` (`ReceiverClassResolver::replacesItems()`), and holds through any
other collection, such as `collect()`'s or `merge()`'s. `AnalysisScope::$bindingEnds` records where, and past it every
read takes the body's own binding, a ternary narrowing it only past its test, as `ReassignedClosureParamResource` pins.
Past a top-level write that keeps a collection binding, such as `$c = $c->keyBy('id');`, a receiver reads the keys it
moved (`ReceiverClassResolver::rekeyedBefore()`). A write in an `if`, `elseif`, `else` or `case` block that always
leaves the function, outside a `try` whose `catch` or `finally` could resume past it, ends it only inside that block, in
`AnalysisScope::$bindingGaps`, where a read takes the value the block's own `$x = …;` assigned. Past a write nested
there in a block that falls through, a read takes that value or the old binding's. A span still outlives a write through
a by-reference parameter of a callee the scan cannot name.

`VarTypeWhitelist` lists the forms a tag may use. A callable inside a shape reads `unknown` by design, so
`extractVarTag()` and `extractParamTag()` accept a reading with as many `unknown` leaves as the tag has callables, and
no more.

### By-reference calls

`ByReferenceArguments::effect()` names what a known built-in leaves in the variable its by-reference parameter takes, as
a [`ByReferenceEffect`]: `ksort()` keeps any value, unless flags it cannot read or a string comparison can reorder int
keys; `sort()`, `array_pop()` and `array_shift()` a list; `asort()` and `uksort()` an object, and a list keyed out of
order as `Record<number, T>`, except where `AnalysisScope::$reindexesNumericKeys` says an API resource re-indexes it
back to the list it was, a tuple to the list of its members; a count out-parameter a number; `array_push()` and
`array_splice()` a list of the values they add; `array_walk()` or a by-reference `foreach` each element its writes stay
within, and `array_walk_recursive()` each leaf. A walk's callback values are read in the callback's own scope, its
parameters bound to the element and its key, and `.=` or an arithmetic operator as the operation it performs. A
`foreach` element written before the loop counts only when a loop around both can run it again. A call on a literal key
of a local, such as `sort($groups['x'])`, needs the member at that key to keep the form. `preg_match()` with no flags
and a literal pattern naming no group writes its `$matches` variable a list of strings, `WritesMatches`. A call may not
run, so the list replaces a held value only where that is an empty or string list, and a parameter's binding ends only
at a call that is a statement of the body. A name no other write reaches, the form's `sole`, holds the list past the
call wherever no binding still holds, a parameter's included: a skipped call leaves an unbound name undefined, which
Laravel throws on and `??` replaces. Every other callee, a flag, a named group or a computed key replaces the value.
`seedBody()` counts no keeping call as a write and records its form in `AnalysisScope::$byReferenceForms`, with the span
of reads it holds for, placed as a binding end or gap is: a copy taken before the call reads the value as it was.
`VariableHandler` reads the value the forms leave, or drops a read whose value lacks one. A class binding ends there,
since no form but `ksort()`'s is an object's. A reader that takes a local by its syntax, such as an array literal's
keys, reads `literalLocalBindings()`, which leaves such a local out.

### What deliberately stays unbound

These shapes stay unbound on purpose, so they resolve as `unknown` rather than as a guess:

- **A local written more than once**: the flat walk cannot tell which write is live at a given return. An early-exit
  guard still narrows one reassigned only before it, and an inline `@var` types it for its span.
- **A first-class callable**, such as `->map(...)`: there is no closure body to bind a parameter into.
- **A relation-chain `map()` whose argument is not a closure**, such as `'strtoupper'`: the same reason.
- **A parameter past a write that names another class**: its binding ends there. A write whose value names no class,
  or an operator such as `.=`, leaves the binding standing, since only the old binding is known; a `foreach`, `catch`
  or by-reference write ends it, but a call that keeps the value's form.

## Subject mode

Subject mode types `$this->prop` from the subject's own declaration. It always applies when `AnalysisScope::$modelClass`
is `null`, as for a broadcast event, a DTO or any plain class. On those `$this->resource` is the subject's own property,
since only an API resource has the proxy. On a model-backed subject, it applies to a property
`SubjectPropertyTypeResolver::declaresOwnProperty()` claims, which answers before the model's attributes and relations.
PHP reads a declared property before `JsonResource::__get()` forwards to the model, so a resource's own `$stats`
publishes that object, not a same-named attribute. A result naming a model the run does not publish declines rather
than emit a token nothing imports, and with no published set to read, an abstract or `Illuminate\` model is still
known to have no file. [ModelAttributeResolver § Models a run publishes][published-models] covers the set.

A name the framework declares is never the subject's own, however the subject redeclares it: `JsonResource`'s
`resource`, `with` and `additional`, `ResourceCollection`'s `collects` and `collection`, every `Model` property, and
every static property. The exclusion keeps `$this->resource` meaning the backing model. `preserveKeys` is deliberately
outside that set, because Laravel reads it with `property_exists()` rather than declaring it.

[`SubjectPropertyTypeResolver`] is the one home for how such a property types, for
`AstEngine::analyzePublicProperties()` and both handler arms. It reads the `@var` docblock, then the native type, then
an untyped property's default, typed by `ValueResolver::resolveConstantValue()` as a parameter default is. A
non-public property declared without a value publishes `null`; a public one left `null` declines, since code outside
the class fills it. The default counts only while no method the class runs writes the property, an ancestor's or a
trait's included. In application code a computed `$this->{$name}` write counts as one. So does an argument to a
by-reference parameter of a callee the scan can name ([`ByReferenceArguments`], which the local write scan shares), such
as `array_push($this->tags, 5)`, except the pointer functions `end()`, `next()`, `prev()` and `reset()`. The check
cannot see a subclass's write, including a child's that inherits `toArray()` and so publishes its parent's analysis
(#79), a by-reference parameter on a receiver it cannot resolve, or a write from outside the class.

The dispatcher never hands a chain's inner node to a handler, so there are two arms. `ThisPropertyHandler` types a leaf
such as `$this->teamId`. `PropertyChainHandler` receives `$this->post->title` whole and resolves its root itself. With
no backing model, only a `Model` root hands off to the relation walk. On a model-backed subject it declines a root
`declaresOwnProperty()` claims, so `ReceiverPropertyFetchHandler` follows `$this->stats?->views` through `PostStats`.

## Dependency recording policy

Every file an analyzer parses goes through `AstParser::parseFile()`, directly or through [`MethodLocator`]. It records
the file with `DependencyRecorder::record()` before it checks its AST cache, so a cache hit records the dependency too.
A read that bypasses it leaves the generation cache serving stale output when that file changes.
`AstParser::parseSource()` records nothing, so use it only for source that is not on disk.

A type read from reflection records the class it reads with `DependencyRecorder::recordClass()`, parents, traits and
interfaces included, so an override or a trait edit counts. Each reader records before it looks the member up, so a
member that does not exist yet still records its class. The readers are `ReceiverMethodReturnResolver::resolve()` and
`ReceiverPropertyFetchHandler` per receiver class, `ReceiverClassResolver::returnClasses()` and `memberProperty()`,
`LaravelTsPublish::toTsType()` and its three member readers, `StringSerialization::jsonStringType()`,
`ModelAttributeResolver`'s contexts and caster reads, `ModelAttributeResolver::subclassKeyTsType()`, which records every
published model, `ByReferenceArguments` for the class whose method it reads by-reference parameters from, and
`MacroCallResolver::reflect()`, which records the file that declares a closure or function macro. A cache in front of
such a read records before its lookup, or replays what it recorded, as `resolveAttributeClass()` and
`LaravelTsPublish::resolvePhpstanTypeAlias()`, which reads each class an alias import names, do. Recording never
deduplicates, because a memo frame keeps the paths recorded after its mark.

`MethodLocator` records nothing itself: it hands a method's file to `parseFileMethods()`, which reads it through
`parseFile()` and walks it for its methods once while it stays cached. `locate()` finds a method wherever it is
declared, matching the name case-insensitively as PHP dispatches. `locateOwn()` searches the class's own file only. A
method inherited from another file is a deliberate miss, the signal callers use to detect delegation, while a parent
declared in the same file is a hit. Both memoize misses. `locateDeclared()` searches the class's own file, then a trait
the class itself uses, composed of those two memoized lookups.

The body fallback records its dependencies the same way, because `MethodReturnTypeResolver` re-enters `analyzeMethod()`,
which reads the helper's file through `MethodLocator`, and `analyzeMethodValue()` locates the method through
`MethodLocator::locate()`. So a resource typed from a helper's body is invalidated when that helper changes.

### The run memo replays what it recorded

[`AnalysisMemo`] is a container singleton holding the engine's cycle guards and a per-run memo of four analyses:
`AstEngine::analyzeMethod()`, `MethodReturnTypeResolver::resolve()`, `AccessorBodyAnalyzer::analyze()` and
`ModelAttributeResolver`'s accessor waterfall. Every key but `method-return:`, whose answer does not depend on the
import mode, gains an `@importless` suffix for an analysis that carries no imports. It also holds the per-class reads
each resource prop repeats: `JsonApiResources::relationships()` and `includedResources()`,
`ResourceCollectionBody::sendsCollectedList()`, `ResourceResponseBody::holdsWrapKey()`, and the two model guesses in
`GuessesModelResources`.

`analysis:` and `method-return:` also key on what a value read's call binds: `CallSite::signature()` adds an `@args:`
suffix spelling the bindings its arguments make for the parameters whose declarations bind nothing, so two calls that
bind alike share one answer, and a call that binds nothing keeps the plain key. The arguments are read in the caller's
scope before the callee's frame opens, so what they record is the caller's. A subject method read under an `instanceof`
proof that holds `$this->resource` to one model adds `@backing:<model>` to its `method-return:` key, so the read with
the proof and the one without never share an answer. The `method-body:` cycle guard stays per
method, so a recursion that passes a new value each time stops at its first repeat.

A stored answer keeps the dependency paths and the dropped-arm count recorded while it was computed, and every reuse
records them again. The generation cache and the accessor `null` rule therefore see what a fresh computation would have
shown them. A cycle can cut an answer short, so each answer is stored with the guards it found held, and
`AnalysisMemo::reproducible()` reuses it only where nothing it read has changed and exactly those guards are held again.
No answer depends on which entry point read it first.

`AnalysisMemo::reset()` drops every answer. `BaseRunner::resetRunState()` calls it when a run starts, and
`ModelAttributeResolver::buildMorphTargetMap()` calls it too, so no answer outlives a run or a morph map. A guard
outside `AnalysisMemo`, such as an `AnalysisScope`'s `visitedSpreadMethods` or `resolvingLocalVars`, may span a
memoized call, since that call builds its own scope, but a memoized analysis must never read one, or the reuse rule
cannot see it.

## MethodAnalysis

[`MethodAnalysis`] carries a method's property rows plus the FQCN channels that imports and aliases are built from. Its
constructor docblock describes each channel.

### `addProperty()` is the only way a value becomes a property

`MethodAnalysis::addProperty()` turns a handler's `ValueExpressionResult` into one property row and routes every channel
the result carries. Every collector goes through it, so a channel added to `ValueExpressionResult` reaches all of them
at once. A collector that built rows by hand would miss a new channel silently. Nothing throws, the property still
types, and only the generated TypeScript shows the missing import or alias.

The row also keeps the result's `keepsKeys`: the value is an object, such as a `Collection`, whose keys `json_encode()`
writes as they are, so a resource's re-index skips it.

Never deduplicate the four inline queues, `inlineEnumFqcns`, `inlineModelFqcns`, `inlineResourceFqcns` and
`inlineEnumResourceFqcns`. `addProperty()` appends to them, and `merge()` concatenates them per occurrence, for return
branches and a spread parent alike. `TsTypeString::aliasPropertyType()`, or `aliasTypeofConst()` for
`inlineEnumResourceFqcns`, walks each as a positional queue against the type's tokens, so a property naming the same
class twice needs two entries.

`forgetChannels()` drops every import channel entry, and the `casts` entry, keyed by one property, when another value
takes the key over, such as a `#[TsCasts]` override or a child key over a spread parent's. A site that unset import
channels by hand would miss the next import channel added. `merge()` lets the later analysis's `casts` entry win, with
or without an import, and drops the entry where the later analysis sets the key without a cast.

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
see. Return-branch merges outside a spread helper or a merge call keep the strict rule, where one `unknown` branch
makes the key `unknown`, as [ResourceAstAnalyzer § A spread helper drops an untypable branch][spread-branches] explains.
A list element is not a union arm, since every response carries it: an element no rule types makes the list `unknown[]`.
An arm left out still makes the union optional when it can be a `MissingValue`.

[`DroppedUnionArms`] records the drops made at a fixed set of sites, and each entry names its site, so a site that goes
silent shows. The sites are `ValueResult::analyzeClosureUnion()`, `TernaryHandler`'s narrowed arm,
`KnownFunctionCallHandler`'s `data_get()` default, `CoalesceHandler`,
`ConditionalMethodHandler::applyConditionalDefault()` and `MatchHandler`'s arms. `analyzeClosureUnion()` records because
`unionResults()` receives only resolved results and cannot name the expression it drops. `CoalesceHandler` strips its
left operand's `null` with `ValueResult::stripNullArm()`, because `??` never returns that `null`, which the ternary
union would keep. It unions both operands through `unionResults()`, which leaves an untyped one out.
`applyConditionalDefault()` records a default only beside a value arm with a type. `ResourceAstAnalyzer::branchUnion()`
records nothing for a spread helper's branches or a merge call's sides, since it unions type strings and cannot name the
expression it drops. One arm is never left out: `$this->resource` read whole under its own `instanceof` proof, as the
proven class with no published type, leaves the ternary `unknown`.

Recording is off until a test calls `start()`, but the count always runs. `AccessorBodyAnalyzer` and `VariableHandler`
compare it around a read to tell a dropped arm's `null` from a literal one, and `AnalysisMemo` replays it for a reused
answer.

`DroppedUnionArmsAuditTest` fails on a workbench arm missing from
`tests/Unit/Ast/Fixtures/dropped-union-arms-baseline.php`, and on a baseline entry the corpus no longer drops. It also
proves each recording site still fires. A `DroppedUnionArms::record()` call whose site no baseline row names fails
`a new recording site cannot go unpinned`, so a new site brings its own `UnionHonestyResource` key and row. Apart from
those rows, the baseline only shrinks: teach a rule to type the shape, then delete its entries. A recorded arm is a
candidate gap, not a proven loss, because `ClosureHandler` and `VariableHandler` fall back to a return-type annotation
or to `null`. Read the published property before you call an entry a bug.

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

`analyze()` re-indexes an API resource's `toArray()` as `ts:publish` does, then hands `analyzeMethod()`'s DTO to
[`AnalysisComposer`], which aliases same-basename collisions, rewrites `EnumResource` wraps to `AsEnum<typeof X>`, and
imports only the names the rewritten types spell.

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

A body-less collection whose live wrap key is null or empty comes back from `analyze()` with all three fields empty,
its singular resource's import included. Its whole answer lives in `MethodAnalysis::$flatTypeAlias` and
`$flatTypeAliasFqcn`, which neither `AnalysisImports` nor `AnalysisComposer` reads.

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
| `ModelMetadataAnalyzer` | `bindingsFor()` on the declaring class's `provide()`, then `ResourceAstAnalyzer` on the non-resource profile | It locates `provide()` itself, and the analyzer reads the scope `bindingsFor()` seeded as it is. See [Model metadata][metadata-consumer]. |
| `AccessorBodyAnalyzer` | `analyzeModelClosure()` on `forModelClosures()` | The model is the subject, so a trait's accessor reads `$this` as the model using it |
| `MethodReturnTypeResolver` | `analyzeMethod()` with `carriesImports: false`, then `analyzeMethodValue()` | It is the body fallback: the literal shape, then the value read, which carries imports. See [Receiver types][body-fallback]. |

Every consumer that reaches `ResourceAstAnalyzer::analyze()` without a context locates through
`MethodLocator::locateDeclared()`, as both Inertia analyzers do for an action.

## Related

These pages cover the engine's neighbors:

- [Receiver types](receiver-types.md): how the engine names the class a `$x->m()` or `$x->prop` receiver holds.
- [ResourceAstAnalyzer](resource-ast-analyzer.md): the resource-specific rules the handlers implement.
- [AccessorBodyAnalyzer](accessor-body-analyzer.md) and [Model metadata](model-metadata.md): the two model-side
  consumers.
- [ImportNameRegistry](import-name-registry.md): aliasing two same-basename imports.
- [Known gaps § Handler ordering is pinned pairwise][gap-ordering]: why a green ordering suite is narrower than it
  looks.
- [Generation cache](generation-cache.md): how recorded dependencies decide a hit.
- [Type inference gates](../testing/type-inference-gates.md): the CI checks that read the generated types.
- [ADR: freeze Laravel Surveyor/Ranger and exit in stages](../decisions/2026-08-31-surveyor-staged-exit.md): why every
  inference feature moved onto this engine.

[`AggregateValueType`]: ../../src/Ast/Values/AggregateValueType.php
[`AnalysisComposer`]: ../../src/Ast/Composition/AnalysisComposer.php
[`AnalysisImports`]: ../../src/Ast/Composition/AnalysisImports.php
[`AnalysisMemo`]: ../../src/Ast/Analysis/AnalysisMemo.php
[`AnalysisResult`]: ../../src/Ast/AnalysisResult.php
[`AnalysisScope`]: ../../src/Ast/Analysis/AnalysisScope.php
[`AstEngine`]: ../../src/Ast/AstEngine.php
[`AstParser`]: ../../src/Ast/Parsing/AstParser.php
[`ByReferenceArguments`]: ../../src/Ast/Calls/ByReferenceArguments.php
[`ByReferenceEffect`]: ../../src/Ast/Calls/ByReferenceEffect.php
[`CollectsInstanceofGuards`]: ../../src/Ast/Concerns/CollectsInstanceofGuards.php
[`ControllerExpressionHandlers`]: ../../src/Ast/Dispatch/ControllerExpressionHandlers.php
[`DroppedUnionArms`]: ../../src/Ast/Analysis/DroppedUnionArms.php
[`ExpressionDispatcher`]: ../../src/Ast/Dispatch/ExpressionDispatcher.php
[`ExpressionEngine`]: ../../src/Ast/Contracts/ExpressionEngine.php
[`ExpressionHandler`]: ../../src/Ast/Contracts/ExpressionHandler.php
[`MethodAnalysis`]: ../../src/Ast/Analysis/MethodAnalysis.php
[`MethodLocator`]: ../../src/Ast/Parsing/MethodLocator.php
[`ParameterBinder`]: ../../src/Ast/Calls/ParameterBinder.php
[`ReadsReturnedVariables`]: ../../src/Ast/Concerns/ReadsReturnedVariables.php
[`ResourceAstAnalyzer`]: ../../src/Analyzers/ResourceAstAnalyzer.php
[`ResourceExpressionHandlers`]: ../../src/Ast/Dispatch/ResourceExpressionHandlers.php
[`ReturnLiteralReader`]: ../../src/Ast/Parsing/ReturnLiteralReader.php
[`SubjectPropertyTypeResolver`]: ../../src/Ast/Members/SubjectPropertyTypeResolver.php
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
[published-models]: model-attribute-resolver.md#models-a-run-publishes
[resource-imports]: resource-ast-analyzer.md#a-resources-imports-follow-its-published-types
[spread-branches]: resource-ast-analyzer.md#a-spread-helper-drops-an-untypable-branch
[ternary]: receiver-types.md#a-ternarys-instanceof-condition

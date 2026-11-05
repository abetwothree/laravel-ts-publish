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
| `Analysis/` | One analysis's state: the scope, the `MethodAnalysis` DTO, the run memo and the dropped-arm record; and the write and fill scans a body's seeding reads |
| `Calls/` | Reading a call: its arguments, parameter binding, by-reference writes and macros |
| `Composition/` | Turning a finished analysis into published types and imports, `#[TsCasts]` included |
| `Concerns/`, `Contracts/` | Shared traits, grouped by what they read (`Narrowing/`, `Collections/`, `Callbacks/`, `Models/`, `Resources/`, `Bodies/`), and the handler and engine interfaces |
| `Dispatch/` | The dispatcher and its ordered handler profiles |
| `Docblocks/` | Docblock tags read by family (`@return`, `@var`, `@param`, `@property`, `@extends`, `@mixin`, `@use`, `@template`) in PHPStan's order, and the forms an inline `@var` may bind |
| `Handlers/` | One handler per expression family, grouped as `Values/`, `Operators/`, `Calls/`, `Members/`, `Collections/`, `Resources/`, `Inertia/` |
| `Members/` | Typing a class's own methods and properties |
| `Parsing/` | php-parser access: parsing, locating methods and render calls, matching call syntax, literal returns |
| `Receivers/` | Which class an expression holds, and the methods called on it |
| `Resources/` | API resource facts: backing model, collections, response body and extras, resolved body, JSON:API |
| `Values/` | Value results and the readers that type a value: constants, reflection, aggregates |

These are the classes a change to the engine usually touches:

| Class | Owns |
| --- | --- |
| [`AstEngine`] | The public `analyze()`, and the `@internal` `analyzeMethod()`, `analyzeMethodValue()`, `analyzeEncodedValue()`, `analyzePublicProperties()`, `bindingsFor()` and `analyzeModelClosure()` |
| [`ResourceAstAnalyzer`] | The `ExpressionEngine`: walks a method's return branches and resolves each value through the dispatcher |
| [`ReadsReturnedVariables`] | The walk over an array built in a variable, for any analyzer that implements its abstract hooks |
| [`ExpressionDispatcher`] | Tries each handler that claims an expression's node class, in registration order, and keeps a result's held-object flags (`heldObject`, `mayHoldObject`, `heldProp`, `returnsCallable`) only from a `KeepsHeldObjects` handler; any other handler reads away from a closure's returned value (`AnalysisScope::$readsCallableReturn`) |
| [`ExpressionHandler`], [`ExpressionEngine`] | The handler contract, and the callback a handler uses for a sub-expression it does not own |
| [`ResourceExpressionHandlers`] | The ordered handler profiles |
| [`src/Ast/Handlers/`](../../src/Ast/Handlers) | One handler per expression family |
| [`AnalysisScope`] | The mutable state of one analysis: subject, backing model and the name-keyed binding tables |
| [`MethodAnalysis`] | The analysis DTO: property rows plus the FQCN channels |
| [`AnalysisComposer`], [`AnalysisImports`] | Turn a `MethodAnalysis` into import maps, and the composer also aliases and wraps |
| [`AstParser`], [`MethodLocator`] | Parse a file, cached and recorded as a cache dependency, with a call argument's docblock moved onto the closure it holds, and find one method's AST node, under a trait alias too |
| [`ParentCallChain`] | The classes whose declaration of a method a call returns the keys of, through `parent::` calls |
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
`ResourceExpressionHandlers::handlers()` is the one ordered list, and three lists filter it:

- **`make()`**: the resource profile, a `JsonResource` subject's.
- **`forNonResourceSubjects()`**: drops `ConditionalMethodHandler` alone, for every other subject
  `AstEngine::analyzeMethod()` reaches, such as a broadcast event, model metadata or a DTO.
- **`forModelClosures()`**: drops `ConditionalMethodHandler` and `ToResourceHandler`, for
  `AstEngine::analyzeModelClosure()`. `RelationFilterHandler` stays as the only handler that types a to-many relation's
  filter, a map proxy, a multi-model accessor's filter, or a filter on a collection-cast column or an
  `Eloquent\Collection` accessor.

`forInertia()` builds the [Inertia profile](#inertia-profile) over a list. `ResourceExpressionHandlers::forSubject()`
gives a `JsonResource` subject `make()` and any other subject `forNonResourceSubjects()`, each through `forInertia()`
where the analysis reads what Inertia sends. `ResourceAstAnalyzer::handlers()` calls it when no profile is injected.
Only `JsonResource::resolve()` drops the `MissingValue` the `when*()` family returns, so on any other subject
`$this->when()` is that class's own method and publishes `unknown`. Dropping `ToResourceHandler` and
`RelationFilterHandler` too was measured to lose typed keys (`Comment::relationSummary()`'s filters) and to turn an
event's `toResource()` into `unknown`, which the payload contradicts.

`ReceiverPropertyFetchHandler` and `ReceiverMethodCallHandler` sit last, with only `KnownMethodRuleHandler` after them,
so every specific handler answers first. `CollectionPipelineHandler` sits right after `RelationCollectionChainHandler`,
whose op table it shares. See [ResourceAstAnalyzer § Collection pipelines][collection-pipelines].

`ModelFinderHandler` sits before `StaticCallHandler`, whose last arm claims every static call on a named class and never
declines one, so a query chain rooted at a model types alike in every profile, as the paginator, model, list or column
it returns. It types a terminal against the query `ReceiverClassResolver` reads its receiver as, so a step that
provably leaves the query ends the chain, and a list step reads a list of models that receiver holds. For a terminal
every model-rooted chain was once typed by, a user-defined step the engine cannot prove either way, or a collection step
it does not follow that provably keeps the items' kind, reads as handing the query or models on
(`resolvePresumingSteps()`), and a builder a model's own static returns that Eloquent does not define, such as Scout's,
reads against the root model. `count()` on any `Enumerable` is a number, and a keyed list a step hands on gains the
object arm `json_encode()` writes. A factory's `make()` or `create()` is a list once a count step or argument sets one,
and a model's query with no terminal is the builder object `json_encode()` writes. A paginator's own setter, such as
`withQueryString()`, keeps the paginator it is called on, and `through()` is that paginator over what its callback
returns ([`PaginatorSteps`]). With no published set registered, it declines a result naming a framework or abstract
model, which has no file. With one, it keeps the model channel, so `MethodAnalysis::addProperty()` declines the key
holding a model the set leaves out whole, rather than a ternary or `??` keeping only its other arms.

`ResourceExpressionHandlersTest` pins `make()`'s sequence, the filtered profiles and the behavioral pins below.
`MethodCallOrderingMatrixTest` runs every pair of `MethodCall` claimants in both orders over a curated corpus. A pair in
its `METHOD_CALL_PINNED` map must answer as `handlers()` orders it, and every other pair must agree. When a new
expression shape makes an inert pair disagree, the matrix fails. Pin the pair in the direction `handlers()` lists it.
Its Inertia half does the same for each pair with a handler only the Inertia profile runs, against
`INERTIA_METHOD_CALL_PINNED`.

### Inertia profile

`ResourceExpressionHandlers::forInertia()` builds the profile every value Inertia sends runs, over the subject's own
profile, since Inertia sends a shared value as it sends a page prop. It inserts two handlers, and throws a
`LogicException` for a list that lacks an insertion point:

- `InertiaResourcePropHandler` before `ToResourceHandler`, which types a resource as the value a nested key flattens it
  to where Inertia sends its response, so a spelling the prop handler declines publishes on a page as in shared data.
  It also precedes `StaticCallHandler` and `NewResourceHandler`, which type a single resource as a nested key's value
  and a `ResourceCollection` as its element array.
- `InertiaTableHandler` before `StaticCallHandler`, which types an Inertia UI Table (`PostTable::make()`, `new PostTable`, or a chain on
  either) as the `TableResource<Post>` its `toArray()` sends, the model read from the table's `$resource` default or its
  `query()`, never by instantiating it. Each call of the chain must declare it returns the table (`static`, `self`, a
  table class, or with no native type a `@return` naming one, read against the file's use statements, or `$this`). A
  chain past the table's own `query()` reads as that chain on `query()` of the model it queries, and past any other
  call as the rest of the profile reads it. Where that is `unknown` on a value `InertiaPageAnalyzer` marks
  `TYPED_AS_TABLE` (a string-keyed item of the action's first render call's props literal, the one place the reader
  it replaced typed such a chain), the table type stands. The type is a custom import from the configured or
  installed table package. A table naming no model falls through, to `unknown`. So a table is one more prop: the
  render call's other props, the action's casts, a collaborator's props, shared data and a `->resolve()`d body's
  restated keys all read it.

`InertiaResourcePropHandler` also types a JSON:API resource's single and collection props as their `@tolki/types`
documents. `forInertia()` also swaps in `FirstClassCallableHandler(invoked: true)`: Inertia calls every callable prop,
at any depth, so a first-class callable there types as the call it stands for. `InertiaProfileTest` pins that a page and
a share middleware build this profile, and each ordering above, the method-call claimants that leave `toResource()` and
`toResourceCollection()` `unknown` included.

The [`Reading`] a body is read under, not the class that declares it, picks this profile: `Encoded` (as
`json_encode()` sends it, the default), `EncodedUnfiltered` (the same with no `filter()` over it), `EncodedFiltered`
(the same once a `JsonResource::filter()` ran), `Inertia`, or `InertiaFiltered` (Inertia's, once that filter ran).
`analyzeMethod()`, `analyzeMethodValue()` and `ResourceAstAnalyzer` take it, and `ExpressionEngine::reading()` reports
it; `Reading::encodes()` groups the three encoded ones. Either Inertia reading runs this profile, so
`ResourceAstAnalyzer` refuses an injected profile beside one.

- **Who sets it**: `InertiaPageAnalyzer`, on a page's analyzer and on the collaborator method a page's props come
  from, and `InertiaSharedDataAnalyzer`, on the middleware's `share()`, each `Reading::Inertia`.
- **Who passes it on**: every body read below one: a helper `MethodReturnTypeResolver` reads through the caller's
  `CallSite`, a nested analyzer such as `parent::share()`'s, and a spread resource's `toArray()`. So a share
  middleware's helper an API resource reads runs the resource's reading.
- **Who reads `Encoded`**: a read of what a class publishes for itself (`ResourceResponseBody`, `EmptyReceiverGuess`),
  a property's written value and a macro's body, each memoized under a key with no reading suffix, and an accessor
  body, read `EncodedFiltered` where `filter()` reaches its reader's value. `ResourceAstAnalyzer` marks its body, and
  each value it types, in `AnalysisMemo`, whose `filterReachesValue()` lets `ModelAttributeResolver` key an accessor by
  that reach: a model array a resource sends whole, spread or merged reaches `filter()` too.
  The keys a resource's response adds beside its body (below) reach Inertia already JSON and unfiltered, so they read
  `EncodedUnfiltered`, which `entering()` turns into `Encoded` where it enters a `JsonResource`'s `toArray()` whole.
- **The filtered readings**: `Reading::entering()` turns `Inertia` into `InertiaFiltered` where a read enters a
  `JsonResource`'s `toArray()` whole, which `resolve()` filters, and it holds below. `Reading::calledFrom()` hands a
  call's body `EncodedFiltered` where an encoded caller's filter reaches the call, since a helper on another class
  cannot tell that from its own subject; over a `JsonResource` that reading is `Encoded` (`Reading::over()`), so the
  two share memo entries. `Reading::filterReaches()` says whether `filter()` reaches a value, which
  `ReadsNullablePayloads` asks before a resource around a nullable payload takes that null: under either filtered
  reading in any body, under `Encoded` only in a `JsonResource` subject's own, and never inside a `map()` kept as a
  `Collection`, where a call's body reads unfiltered. An `EnumResource` wrap it does not reach, a page's, an event's
  or a kept map's, sends a null payload as `[]`, its `never[]` arm (`ResolvesEnumPropertyArgTypes::enumWrapValue()`);
  `ValueResult::filledArms()` and `isListShaped()` read past that arm where a union's enum arms are counted or shaped.
  `TernaryHandler`'s `?:` rule applies only under `Inertia`.
- **A `->resolve()`d resource**: `StaticCallHandler` publishes R, or R's list, with each key Inertia reads otherwise
  than R's own reading does restated over it, `Omit<R, 'k'> & { k: … }` ([`ResolvedResourceBody`]). A key a cast
  retypes keeps R's type, and a JSON:API resource keeps its own: its attributes reach Inertia as a `stdClass` its
  `PropsResolver` does not walk, so `json_encode()` sends the resources in them bare. The result's `resolvedFqcn` lets
  a spread take R's keys, as it takes a plain `R`.
- **A resource's response**: `InertiaResourcePropHandler` only reads the spelling. [`ResourceResponseType`] builds
  what it sends in Laravel's order (wrap, body, pagination, extras), so the key a body sits under is decided once.
- **A resource's response extras**: that builder sets the keys a resource's or named collection's
  `with()` and an `additional()` call add beside the body ([`ResponseExtras`]), joined in one step, as Laravel merges
  them once: merged into a `{ data: R }` literal, else intersected, with the key the body sits under `unknown` beside
  an `Omit<>`. A key both add merges as `array_merge_recursive()` does, and beside keys one side cannot read, every key
  of the other is `unknown`. `with()` is read under `EncodedUnfiltered`, and so is `additional()`'s argument, by the
  caller's analyzer (`AstEngine::analyzeEncodedValue()`), and an empty JSON object among them is the `never[]`
  Inertia sends once it decodes the response. So is a callable or an Inertia prop object a key or a list element holds,
  at any depth (`Reading::arrayValue()`). Under that reading `ConditionalMethodHandler` publishes a value the
  `when()` family leaves out as that `never[]` beside the value, not as an optional key, and a `merge()` or
  `mergeWhen()` merges no key, since Laravel sends each MergeValue whole under a positional key. Once one is sent, a
  body with no live wrap key nests under `data` (`ResourceWrapping::responseKey()`). A JSON:API resource or an
  `EnumResource` collection declines `additional()`.

The reading joins the memo keys ([The run memo replays what it recorded](#the-run-memo-replays-what-it-recorded)).

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
| `New_` | `InertiaWrapperHandler`, `NewResourceHandler` | `InertiaWrapperHandler` first where Inertia reads the value: `NewResourceHandler` reads a `new` of a class that is no resource, such as `new AlwaysProp(...)`, as `unknown`. Under `Encoded` the wrapper declines, since `json_encode()` sends the prop object as `{}` | None |

`OffsetReadHandler`, a literal-key read such as `$data['a']` from each object-literal or tuple arm of its holder, an
empty array arm holding none, sits after `MatchHandler` and is the only claimant of `ArrayDimFetch`, so it is in no
pair.

These `MethodCall` pairs are the ones most likely to break under an edit:

- **Attribute filters**: on a model-backed scope, `RelationCollectionChainHandler` and
  `VariableHandler`'s `$variable->method()` arm decline every `only()`/`except()`. `RelationFilterHandler` claims none
  on `$this->resource` itself. On a model-less scope, as on a page, `RelationCollectionChainHandler`
  declines a filter on a property that holds a model, so the receiver rules answer `$this->post->only([...])` with
  `Pick<Post, …>` and `except($keys)` with `Record<string, unknown>`. The same arm declines a method
  `ReceiverMethodReturnResolver::typesByRule()` names, and one it would answer `unknown`, so `ReceiverMethodCallHandler`
  answers `getKey()` or an untyped method on a route-bound or closure-bound model.
- **`RelationFilterHandler` and `ReceiverMethodCallHandler`**: both claim a single-model relation filter, and they agree
  by construction, as [ResourceAstAnalyzer § Attribute filters][attribute-filters] explains. `ReceiverHandlersTest`'s
  "both claim … and answer it the same" cases pin the agreement.
- **`CollectionPipelineHandler` and `VariableHandler`**: both claim a trailing `values()` or `all()` on a `collect()`
  chain. They agree because the peel spells `values()` through `ArrayArms::valuesList()` and peels only an
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

`VariableHandler` types `$x->member` only from an attribute of the model `varModelBindings` binds to `$x`. It declines
anything else, a relation included, so `ReceiverPropertyFetchHandler` types the relation from what `$x` holds.

The Inertia profile adds `InertiaResourcePropHandler` to the `StaticCall`, `New_`, `MethodCall` and
`NullsafeMethodCall` claimants, and `InertiaTableHandler` to the `StaticCall`, `New_` and `MethodCall` claimants, in the
places [§ Inertia profile](#inertia-profile) gives. The prop handler and `ModelFinderHandler`, which every profile runs,
claim disjoint method names on a `MethodCall`, and the three claim disjoint roots on a `StaticCall`, an API resource, a
model and a table, though each claims `::make()`. The matrix's resource half runs `ModelFinderHandler`'s pairs over
model-rooted query chains; its Inertia half runs each `MethodCall` pair either page-prop handler joins, over a page's
model-less scope, and pins only the prop handler before `ToResourceHandler`.

## AnalysisScope

[`AnalysisScope`] is one mutable object shared by a whole analysis, not a value copied down the walk. Its field
docblocks say what each binding table holds. These eight facts span other classes:

- **Three classes stand for the body under analysis**: `subjectReflection` is the subject; `runtime` holds the runtime
  class, the one `$this` and `static` are and whose model `$this` reads; `declaringClass` is the declaring class, the
  one `self` names and whose parent `parent` names, which is the using class for a trait's method, where
  `declaringFileClass` names the trait. The runtime class differs from the subject when `ResourceAstAnalyzer` reads an
  ancestor's body for a subclass, a helper for a caller's runtime class, or a method of an instance the body builds. A
  `$this->m()` call or a `$this->prop` read reaches its member on `thisMemberClass()` (`memberClassOf()`): the declaring
  class when it declares that member private, else the runtime class. A method on a built instance takes the same rule,
  its preserve check included, but an object whose class does not extend the declaring class runs its own, as one
  `OtherResource::make()` builds does. A static-syntax call reads `staticCallClass()`, which applies no private rule and
  names no class for a named one, a `$var::` or a `parent` the declaring class lacks, so each caller says what those
  reach. Every other `self`, `static` or `parent` reads `relativeClass()` (`relativeClassOf()`), while a call that
  forwards the runtime class hands it to its callee as `static` (`forwardsRuntimeClass()`). The write scans take the
  same two classes (`VariableWrites::of()`), through `detached()` for a body no analysis holds. Whether the runtime
  class is known is [its own rule](#known-or-assumed-runtime-class).
- **`modelClass` is scoped, not fixed**: `NarrowsInstanceofSubjects::resolveNarrowed()` narrows it, with
  `forwardsUndeclaredMembersTo` and `provenProxy`, while a ternary's proven arm, or a value inside a block an `if`
  proves on `$this->resource`, resolves, for `TernaryHandler`, `ReceiverClassResolver` and `ResourceAstAnalyzer` alike,
  then restores all three. The narrowing happens below `AstEngine`'s `analysis:` memo key, so the key does not describe
  the model a nested resolution ran under. A subject method read as a value under a one-model proof is the exception:
  its `method-return:` key gains `@backing:<model>`.
- **`varGuardBindings` keys each proof by its subject** (`proofKey()`): a variable's name; for an API resource's
  `$this->resource`, `PROXY_GUARDS`; for a chain of property reads on `$this`, `'$this->'` and its names. No variable
  spells the last two, so no parameter claim or `copyBindings()` reaches them. `enterMethod()` sets the proxy's aside
  with every name table, `provenProxy` carrying what holds. A chain's proofs (`thisPathProofs()`) reach a closure's
  body, and those a ternary's arm binds at no offsets a subject method's, except a path the body writes whole or one
  through it; with `provenProxy`, none does once the body replaces `$this->resource`
  (`CollectsInstanceofGuards::rewrittenThisPaths()`).
- **The resource proxy has one home**: `hasResourceProxy()` says whether `$this->resource` is the proxy, true on every
  API resource, model or not, and nowhere else. `proxyTargetFor()` derives `forwardsUndeclaredMembersTo` for the
  constructor, `rebase()` and `ResourceAstAnalyzer`. Handlers ask through
  `InspectsAstNodes::isProxyFetch()` and `isModelMemberFetch()`.
- **`rebase()` moves a scope to another subject**: a builder calls it rather than writing `subjectReflection` or
  `modelClass`, so the proxy target and the subject's collection facts, `reindexesNumericKeys` and
  `collectedElement`, follow, as `AstEngine::analyzeModelClosure()` does. The narrowing changes only the backing,
  through `setBackingModel()`, since those facts are the runtime class's. `ResourceAstAnalyzer` seeds them from its
  runtime class on any scope, through `seedCollectionFacts()`, so a supplied scope or an ancestor's body reads a
  collection's items as the class Laravel builds does. A built collection's method changes only `collectedElement`,
  through `seedBuiltElement()`; see [A method called on a new resource types its payload][built-payload].
- **`requestVarNames` skips a resource's `Request` parameter**: `ParameterBinder::bindCall()` seeds it for any subject
  but a `JsonResource`. Typing the calls on a resource's `toArray(Request $request)` would move committed resource
  output.
- **`carriesImports` is false only for a getter `AstEngine::analyzeModelClosure()` reads without imports**, for a reader
  that keeps no FQCN channel. **`casesAsLiterals` is true only for the body fallback's analysis and a getter it reads
  whole**, which type a plain enum's case as the literal it sends, except while a read that needs the case's enum, such
  as an `EnumResource` payload, is under way (`AnalysisMemo::readingCasesAsEnums()`). See
  [Receiver types § The body fallback][body-fallback].
- **`varDeclaredBindings` holds a parameter's declared classes**: the floor every narrowing refines, so
  `ReceiverClassResolver` reads it after `varModelBindings`, and a guard narrows it, as `GuardWritePostResource` pins.

### Known or assumed runtime class

The runtime class travels as one [`RuntimeClass`]: the class, and whether the analysis knows it or only assumes it.
`AstEngine`'s and `MethodReturnTypeResolver`'s entries take one, where null reads the class itself, known.
Only a known runtime class types a property write that names `static`, so `SubjectPropertyTypeResolver::resolve()`
reads a property for an assumed one as the subject's own. The runtime class is known:

- in the published class's own scope;
- in a helper that scope reaches through `$this->`, `$this::`, `self::`, `static::`, `parent::` or a non-static
  `Ancestor::m()` from an instance of it, and in a body run on what a `$this->` call declared `: static` returns: each
  is known where the caller's is (the forwarding calls hand on `$scope->runtime`; `AnalysisScope::calleeRuntime()` does
  so for the `: static` receiver);
- for a class the route or config names: a controller, an Inertia middleware, a metadata provider;
- for a `final` collaborator the container fills (`RuntimeClass::injected()`);
- for a static method called through a class's name, since PHP binds `static` to that class;
- for an exact construction, `new X` or `X::make()`, and a method a chain calls on it
  (`AnalysisScope::builtRuntime()`), except the class an assumed scope's `static` stands for, which stays assumed.

Anywhere else it is assumed (`RuntimeClass::assumed()`, or `held()` from a class name), since the object may be a
subclass: a receiver's declared class, the model `$this->resource` forwards to and a method macro's object, a
collaborator that is not final, and an instance method a class's name calls without forwarding, which runs on `$this`
wherever the object is one.

### Writing a scope binding

A writer that binds a name for one body, arm or value, such as a closure, a spread or a narrowing, has one primitive:
`AnalysisScope::within()`. Every change, every seeding step that can throw included, runs inside `within()`, in its
`$enter` or its read, and `within()` then puts back every name table and ambient field in a `finally`.

The scope outlives the expression, so a binding an exception leaves behind raises nothing. The binding answers a later
property with a wrong but plausible type, far from the writer that leaked it. So no writer restores only the key it
believes it wrote, and a new ambient field joins `within()`'s list, which `AnalysisScopeTest` enforces.

A closure a call passes values to, such as a conditional's value, a `map()` callback or a merge closure, is read through
`readClaimedClosure()`, which claims its parameters inside `within()`. A subject method's body is read with
`enterMethod()` as the `$enter`. `beforeGap()`, `resolvingBinding()` and `resolvingName()` stay apart, since each
restores one name's entry. State outside the scope saves and restores itself the same way: the analyzer's walk notes and
`readsKeylessAttributeNames`, `DroppedUnionArms::$held` and `AnalysisMemo`'s frames.
`ResolvesModelRelationTypes::bindForeachLoopVariables()` takes no snapshot, because a `foreach` binding holds for the
whole body by design, and `ClosureHandler` calls it inside its own `within()`. The closure writers' reads restore over
it, so a same-named closure parameter still resolves against its own binding, as `ClosureParamShadowResource` pins.

#### One lookup for the write a read sees

`AnalysisScope::writesSeenBy()` answers which writes a read of a local sees: its one top-level write, or the values the
gap it falls in holds, a block's write or a branch join's. It answers null where a parameter's binding may still hold,
or a gap's write assigns no value the scan reads; `soleWriteSeenBy()` answers a reader that takes one write.
`VariableHandler`, `ReceiverClassResolver`, `CallableForms::heldCallable()` and `ParentCallChain` read it.
`VariableHandler::joinedValue()` and `ReceiverClassResolver::fromGap()` type a gap's writes themselves: a parameter's
gap joins its old binding, and a write that reads the name lifts the gap (`beforeGap()`). A reader that takes a local by
its syntax goes through `InspectsAstNodes::spelledWrites()` or `soleSpelledWrite()`, which also decline past a
by-reference form or a write that reads what an earlier write left in the local: the `+` operand (one union literal per
write, joined, up to `MAX_SPELLED_LITERALS`), an `EnumResource` payload, a props local, an `array_merge()` argument and
`ResourceAstAnalyzer`'s written local. `HeldCallables::mayHold()` still reads a local's one top-level write.

#### A closure parameter owns its name

Readers rank the name-keyed tables differently. `VariableHandler` reads `varDocBindings` first and `varValueBindings`
last, while `ReceiverClassResolver::fromVariable()` reads `varClassBindings` first and `varValueBindings` only for an
enum case. So an outer binding of a parameter's name, left in any table, outranks the parameter's own binding for some
reader. Nested in `map(fn (Comment $c) => …)`, a `collect(...)->map(fn ($c) => …)` would read its string element as a
`Comment`.

Every writer that binds a closure parameter therefore reads the closure through `readClaimedClosure()`, whose
`claimParameters()` drops each parameter name from every table, its binding end included, and marks the closure claimed.
The writer reads the value the call passes before it claims, since the claim can release a name that value shares, then
binds the parameter to it. `ClosureHandler` calls `releaseUnclaimedParameters()`, which makes the same drop for a
closure no writer claimed, such as a `whenNull()` value closure. It then binds each parameter whose native type names
one class and that no writer bound to a class, or to a value that names one or is `null`: a non-null concrete model in
`varModelBindings`, any other class in `varDeclaredBindings`, before it seeds the body, so a write that replaces the
parameter ends that binding.

Each writer binds the first parameter to what Laravel passes the closure, through [`ParameterBinder`]:
`elementBinding()` names a collection element's binding, which `bindPassed()` makes, and `bindCallbackArgument()` binds
an expression or a value at a position. `whenLoaded()`'s relation classes and `transform()`'s passed variable and filled
model bind in `ConditionalMethodHandler`, whose value closures read through `ResolvesCallbackValues::callbackValue()`.

| Writer | Laravel passes | First parameter bound to |
| --- | --- | --- |
| `BindsMapCallbacks::resolveArrayCallback()`, for every `map()` and `array_map()` | `($value, $key)`, or one element of each array | The element's model, or a concrete model hint it does not extend, in `varModelBindings`; else the element's value, and nothing for an element nothing types. The second holds the key only where the caller passes one |
| `whenLoaded()` | The loaded relation | Its model, its collection in `varCollectionBindings`, or its `morphTo` targets |
| `whenHas()`, `whenExistsLoaded()` | The attribute, the `{relation}_exists` flag | That property read |
| `whenCounted()`, `whenAggregated(…, 'count')` | The count | `number` |
| `whenAggregated()`, other aggregates | The aggregate | The aggregate without its `null`, as the model's declaration or [`AggregateValueType`] types it; nothing when neither does |
| `transform()`'s callback | The filled value | A `$this->prop` read, a passed variable's bindings, or the value's type, a nullable model read as the model. The call proves the parameter non-null, so a bare read of it drops the `null` arm, like any read a guard proves non-null ([Narrowing](#narrowing)) |
| `transform()`'s default | The blank value | The value's full type, `null` included |
| `whenNotNull()`'s closure or first-class callable | Nothing, and always, since a closure is never `null` | Nothing beyond the unpassed-parameter rule below |
| `when()`, `unless()`, `merge()`, `mergeWhen()`, `mergeUnless()`, `whenAppended()`, other defaults | Nothing | Nothing beyond the unpassed-parameter rule below |

A first-class callable in a position where Laravel passes one value binds the same way:
`CallableForms::passingOneValue()` reads it as `fn ($v) => m($v)`. A `map()` or `array_map()` callback also reads a
function or `Class::m` string and a `[$object, 'm']` or `[Foo::class, 'm']` pair so (`CallableForms::asOneValueClosure()`).

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
it is. `bindClosure()` binds a closure's parameters from the declaration the AST holds, and differs in two ways: its
class goes through `bindClosureClass()`, which `ClosureHandler` shares, so a union of classes binds no class and a
`Request` binds as its class in `varDeclaredBindings`; and its `@param` is read only from a docblock attached to the
closure itself, where `AstParser` also moves one written before the call argument holding it. An untyped one binds
nothing, since no call is read. A closure's `self` and `parent` name the classes `relativeClass()` names, there and in
a `map()` callback's model (`ParameterBinder::elementBinding()`), and mark the name in `relativeParameters`, so its
receiver reads members as a spelled inline `@var`'s does.
`CollectsLocalVarBindings::seedBody()` then seeds the body's own bindings, ends and guards from one `VariableWrites`
scan, in every body reader, a closure's included.

A parameter the body writes keeps its class binding, which ends at the write as [Declared locals](#declared-locals)
says, and a write holding fewer of a declared union's classes ends it as well. Its value binding holds for a read
before its own `$x = …;` and gives way to the written value past it. The binding reads only the
declaration, so the `analysis:` and `method-return:` keys stay valid. A call, spread or read as a value, also binds each
parameter whose declaration binds nothing (no class, no type precise enough to publish, natively or by `@param`) to what
its argument binds, through `CallSite::passedTo()` and `bindCall()`, read before `AnalysisScope::enterMethod()`
sets the caller's tables aside. A value read keys its memo on what the call binds ([The run memo replays what it
recorded](#the-run-memo-replays-what-it-recorded)). A model no row backs yet binds as a `new` of its class, so its key
keeps `| null`, as a `transform()` callback's does. An argument holding narrower classes than a declared abstract class,
interface or union, such as a post passed to `Model $m`, replaces the declared binding the same way. A call binds
nothing for an argument that may hold a closure, whose value hangs on whether the method calls it: the closure itself,
an arm of `?:`, `??` or `match`, or a local the caller's scope binds to one (`HeldCallables::mayHold()`).
Only a caller that calls every callable it reads, as Inertia does (`ExpressionEngine::reading()`), binds it. A
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
  `instanceofProofs()` reads a test on a variable or a chain of property reads on `$this` through `!`, an `&&` that
  holds, an `||` that fails, or an `||` chain on one subject. An earlier `return` and a guard's own body read `$x`
  unnarrowed, and a proof inside another holds what both prove (`InstanceofNarrowing::proven()`). A chain's proof is
  read as a ternary's is. On an API resource a test on `$this->resource` binds under
  `AnalysisScope::PROXY_GUARDS`, and `NarrowsInstanceofSubjects::resolveUnderProxyProof()` resolves a value inside one
  of its spans through `resolveNarrowed()`, as a ternary's proven arm, and a value nested in it reads that proof as
  applied.
- **A ternary's proven arm**: `TernaryHandler` resolves it under `NarrowsInstanceofSubjects::resolveNarrowed()`, which
  binds a variable in `varGuardBindings` past its test, or records `provenProxy` for `$this->resource`, setting
  `modelClass` too when one model is left, or binds a chain of property reads on `$this` under its key at every offset
  while the arm resolves. `ReceiverClassResolver::fromPropertyFetch()` reads a chain under the latest proof in force as
  what it holds with that proof set aside, narrowed by the proof's classes, so every proof around it applies too. A
  test that rules out no class `$resource` is redeclared as, and no `null` it admits, records nothing, and an arm that
  writes its subject is not narrowed.

`AnalysisScope::guardAt()` gives the proof a read falls within, the one that starts latest. `fromVariable()` narrows
whatever binds the variable by it, so a `morphTo` parameter's targets narrow and a collection keeps its element, and a
model-bound variable read bare publishes the subclass a proof names. A parameter a call passes `$x` to takes the proof
in force at the call, for every read of the parameter.

A test binds `$x` only when no write to it can land between the test and the end of what it narrows; a write before
the test, such as the `foreach` assigning the value it tests before each pass, or past the block a nested proof ends
with, is fine; a by-reference `foreach` binds nothing. `GuardWritePostResource` pins both cases. Both see an
argument a call passes to a by-reference parameter of a callee the scan can name, but a built-in's that keeps the value
in some form ([By-reference calls](#by-reference-calls)); neither sees one on a receiver it cannot name. An assignment
through a member or key of `$x` leaves its class, so `keepsProvenClass()` keeps a class proof past one that stores a
value the member's column holds, as `ModelFills::keepsModelThrough()` decides, or names a member no column or relation
of the proven models types; a write of `$this->resource` itself always ends the proxy's proof, and a non-null proof
ends at each. [`ThisPathWrites`] answers for a chain on `$this`: an assignment to it or to a chain it reads through,
`unset()` or a by-reference argument of either, and a call on `$this` or on a chain it reads through end its proof,
the conditional helpers `JsonResource` declares aside; on an API resource a chain the subject does not declare reads
through `$this->resource`. Only a write through the chain itself keeps it, by `keepsProvenClass()`'s rule.

A guard also proves reads non-null, in `AnalysisScope::$nonNullReads`: a `when()`, `unless()`, `mergeWhen()` or
`mergeUnless()` condition for its value, a ternary's for each arm, `transform()`'s filled value for its callback, an
`if` chain's for each block it runs, and the failed conditions of the chain's leading branches that always exit for the
rest of the block holding it. `ReadsNonNullGuards::nonNullReads()` lists the condition forms it reads. A bare read
the scope proves non-null drops its `null` arm, unless that would leave only `null`. A closure or a spread method that
writes a name drops the proofs of that name it came in with. A call between a guard and its read, such as a method
that assigns the read or `setRelation()`, is not seen as a write, so the proof survives it (#115).

`ClosureHandler` runs both passes over a `Closure`'s statements after unsetting every name the body writes from the
outer `localVarBindings`, `varDocBindings`, `byReferenceForms` and `keyBuiltLocals`, and from `bindingGaps` where no
parameter or seeded binding holds it (`AnalysisScope::bindsName()`). So a body-local shadows an outer one even when
written twice. Any other outer binding of a name the body assigns, and neither declares nor imports by reference, ends
where the body first assigns it, as do that name's outer gaps and guards: past that `$x = …;` when it is a statement of
the body, else before the statement holding it. A write inside a nested function reaches the body around it only through
a `use (&$x)`: an arrow function or a by-value `use` writes its own copy.

[Receiver types § A ternary's `instanceof` condition][ternary] covers how a proven arm's classes narrow, and
[known gaps][gap-narrowing] records what each spelling publishes.

### Declared locals

An inline `/** @var T $x */` on a top-level `$x = …;` records a `varDocBindings` span through
`CollectsLocalVarBindings::bindDeclaredType()`. The span runs from after that statement to the next top-level statement
that writes `$x`, and `T` resolves against `AnalysisScope::$declaringFileClass`, a trait's file for a trait's method.
Before it does, each `self`, `static` and `parent` in it is spelled as the class `relativeClass()` names. `self` and
`parent` are the declaring class, the using class in a trait, and its parent; `static` is the runtime class the body is
seeded for, which `AstEngine::bindingsFor()` takes from a value read. The receiver such a tag types, and every value a
member call or property read on it returns, reads a member a subclass can override by its declaration alone
([Receiver types § `self` and `parent`][receiver-self]): a declared type stands even where the runtime class narrows
it, and an untyped member stays `unknown`.

A `$x = …;` nested in a block binds `$x` past its statement, to the end of that block or to the start of the block's
statement holding the next write (`bindBlockLocals()`), as a binding gap holding the value; a `@var` on it spans the
same reads, and types that value where a join carries it past the block (`AnalysisScope::declaredWrite()`), a bound
name's only where a statement of its block follows the write. A block may not run and a loop runs it again, so a read
before the write or past the block stays unbound, as does a name a reference can write through (`=&`, `use (&$x)`,
`foreach (… as &$x)`, `global`, `static`). Past an `if` that assigns a local on every path, the join below binds it. A
name a parameter or a seeded binding holds takes its gaps from the join below instead. A body drops the outer gaps of
each name it writes that holds no binding, so a closure's own `$x` never reads its enclosing block's, while a bound name
keeps its exit-block gap for the value a closure captures, up to the closure's own first `$x = …` of it.
`AnalysisScope::copyBindings()` passes a closure parameter the value of the gap one write opened, a block local's or a
parameter's, and in a gap the old binding may still hold past, that binding joined with the gap's writes.

A name with no binding that the body writes more than once is joined statement by statement (`bindJoinedLocals()`), each
span a binding gap: past a top-level `$x = …;` a read takes that value, past a statement whose writes are all nested
`$x = …` the values before it and theirs, and past a `try` what its body or a catch leaves, then its finally. Past an
`if` whose every branch, `else` included, ends with its own `$x = …;` or such an `if`, or always leaves the function, a
read takes only those branches' values, a local written once there included (`branchValues()`); a value that may be a
callable (`HeldCallables::mayHold()`, a variable it cannot follow counted) leaves it unbound, since a returned Closure
object reads apart. A member read leaves out a joined `null`, as it does a ternary's `null` arm. A write in a block that
always exits reaches no read past it. Any other write leaves the name unbound until its next top-level `$x = …;`, and a
name a reference can write through is never joined. A parameter takes the same walk from its own binding, for values
only, and walks each block a statement holds too, so a read there past the block's own `$x = …;` takes that value: an
`if` branch starts from what the name held before the statement, a loop body or a `switch` case also from every value
the statement writes. Its own `$x = …;` replaces the value it binds, alone or beside a class, and a model's where the
write is a typed value naming no class, so `$t = json_decode($t)` reads `unknown` past it while a model's untyped write
keeps the model (O11); any other class binding alone, or any other write, stops the walk, though in a block a write that
leaves such a binding standing joins it, as past the block. An expression binding the receiver resolver names a class
for, such as a relation read, holds that class beside its value. Its gaps carry `receiversKeep`: past a write naming no
class a member read keeps the old binding, until the write `endBindingsAtWrites()` returns as ending it, but past a
model's own write of a value the scan types, such as a column read, it does not. A value read leaves out a nested write
the engine cannot type wherever an earlier value, the binding's or its own `$x = …;`'s, still reaches the read, and an
old value it cannot type beside writes that all type. A closure parameter nothing binds, as `transform($this->opaque(),
…)` leaves its callback's, walks from the value its call passed, which has no type, once a nested write needs the join;
`AnalysisScope::$passedValues` keeps that argument, which no reader binds from, for the join to name. `VariableWrites`
classifies each write once, as its scan records it. The cycle guard, `AnalysisScope::resolvingBinding()`, keys a binding
by its name and the bound expression, so `$s = $s . 'x'` reads the value `$s` held before.

Inside the span the assigned value's reading wins, and `T` fills in only where that reading is vague.
`VariableHandler::declaredLocalValue()` applies that to a value, and `ReceiverClassResolver::fromVariable()` to a
receiver. Each states its own test for vague. A parameter's own binding holds until the body's first write that replaces
it with a value naming another class. A collection binding also ends at a step that replaces its models with other
values, such as `pluck()`, `map()` or `groupBy()` (`CollectionSteps::replacesItems()`), and holds through any
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
of a local, such as `sort($groups['x'])`, needs the member at that key to keep the form. `preg_match()` and
`preg_match_all()` with a literal pattern and constant flags write their `$matches` variable the type
[`PregMatches`](../../src/Ast/Calls/PregMatches.php) reads, `WritesMatches`, carried as the form's `matches`: PCRE
itself names the pattern's groups, from a probe that wraps the pattern in a `(?(DEFINE)…)` group PCRE never enters. The
probe drops the leading `(*NOTEMPTY)` and `(*LIMIT_…)` options, which PCRE's JIT and interpreter enforce apart. A verb
that names a mark, told from its text by renaming it to one PCRE rejects, adds `MARK?: string`, merged into a group
named `MARK`, and declines pattern order, which keys the marks by match. A call may not run, so the matches replace a
held value only where that is an empty list or a value they stand for, and a parameter's binding ends only at a call
that is a statement of the body. A name no other write reaches, the form's `sole`, holds the matches past the call
wherever no binding still holds, a parameter's included: a skipped call leaves an unbound name undefined, which Laravel
throws on and `??` replaces. The matches carry `absentKeys`, so `$m['name']` reads a group PHP may leave out as
required: its bare read throws, and `??` replaces it. Every other callee, a computed pattern or flag, or a computed key
replaces the value. `seedBody()` counts no keeping call as a write and records its form in
`AnalysisScope::$byReferenceForms`, with the span of reads it holds for, placed as a binding end or gap is: a copy taken
before the call reads the value as it was. `VariableHandler` reads the value the forms leave, or drops a read whose
value lacks one. A class binding ends there, since no form but `ksort()`'s is an object's. A reader that takes a local
by its syntax, such as an array literal's keys, reads `InspectsAstNodes::spelledWrites()`, which declines a read past
such a call.

### What deliberately stays unbound

These shapes stay unbound on purpose, so they resolve as `unknown` rather than as a guess:

- **A local past a write the join cannot read**: a `foreach`, `unset($x)`, `.=` or a by-reference call other than a
  keeping one leaves it unbound until its next top-level `$x = …;`. A key write, such as `$data['k'] = …`,
  `unset($data['k'])` or `+=`, does too; a returned variable's walk reads one, and so does a value read of a local the
  body assigns whole (`AnalysisScope::$keyBuiltLocals`), over the statements before the one holding the read, unless
  that one writes it too. A local a reference can write through, made by `=&`, `use (&$x)`, `global`, `static` or
  `foreach (… as &$x)`, stays unbound past every write. A member or key write on a local holding only models, such as
  `$p->title = 'x'`, is no write of it, since Laravel stores the value in that model, where the scan shows the column
  holds it (a constant, `.=`, `+=` or `++`) and no key write lands on a local that may be `null`. Neither is the key a
  variable reads, `$k` in `$a[$k] = …`, nor a variable variable's write.
- **A first-class callable**, such as `->map(...)`: there is no closure body to bind a parameter into.
- **A `map()` whose argument is no callable form**, such as a variable holding a closure: the same reason.
- **A parameter past a write that names another class**: its binding ends there. A write whose value names no class,
  or an operator such as `.=`, leaves a class binding standing, since only the old binding is known, though a value
  read takes what its own `$x = …;` writes where it binds a value, or a model the write gives a typed value, and past
  a write nested in a block the old value or the one written; a `foreach`, `catch` or by-reference write ends it, but
  a call that keeps the value's form.

## Subject mode

Subject mode types `$this->prop` from the declaration `thisMemberClass()` reaches, the runtime class's or the declaring
class's private one ([§ AnalysisScope](#analysisscope)). It always applies when `AnalysisScope::$modelClass` is `null`,
as for a broadcast event, a DTO or any plain class. On those `$this->resource` is the subject's own property,
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
`AstEngine::analyzePublicProperties()` and both handler arms:

- It reads the `@var` docblock, then the native type, then an untyped property's default, typed by
  `ValueResolver::resolveConstantValue()` as a parameter default is.
- A non-public property declared without a value publishes `null`; a public one left `null` declines, since code
  outside the class fills it.
- The default joins the type of each value a method the class runs assigns the property whole or by a compound
  operator, an ancestor's, a trait's or, through the runtime class, a subclass's included, each write found by
  [`PropertyWriteSites`] once a run per class and property.
- A write counts only where the class runs its body: a trait's method the class overrides is skipped, unless an alias,
  the class's or a used trait's, still runs it, typed then under that alias in the trait method's own scope. An
  ancestor's method a subclass overrides still counts, since `parent::` may run it.
- A constant expression is evaluated as one; any other value is read in the writing method's scope
  (`AstEngine::analyzeExpressionValue()`), where a read of the property itself takes the default and constant writes
  under an `AnalysisMemo` guard.
- A write inside a nested class, a named function or a static closure is skipped, since `$this` there is never the
  object.
- A write a closure or arrow function in the method holds joins even where the closure may never run, read in that
  closure's scope: its declared parameters through `ParameterBinder::bindClosure()`, its body's locals and, for a
  closure, only its imports, as the method held them there, which leaves a by-reference import `unknown`.
- That closure read declines where it leaves out a union arm it cannot type, as `$n ?? 'z'` over an untyped parameter
  does, or reads an import that a closure it passes through reassigns anywhere in its body, which the scope reads as
  that assignment's value wherever the read sits.
- Any other write declines: a value the engine cannot type, a whole `unset()`, a computed `$this->{$name}` write in
  application code, or an argument to a by-reference parameter of a callee the scan can name ([`ByReferenceArguments`],
  which the local write scan shares), such as `array_push($this->tags, 5)`, except the pointer functions `end()`,
  `next()`, `prev()` and `reset()`.
- A value naming `static` is typed against the scope's runtime class where it is known, and declines where the scope
  only assumes it, on a receiver or in its method's body.
- The check cannot see a by-reference parameter on a receiver it cannot resolve, a write by a subclass of a receiver's
  declared class (`$this->holder()->views`), or a write from outside the class.

A helper the value calls while it is already being read is read again under `AnalysisMemo::reenterOnce()`, so its own
read of the property takes the default and constant writes too. Where reading the property as the type that joins, under
`AnalysisMemo::seeding()`, would join an arm that type lacks, as a write nesting what the helper returns does, each such
write reads the helper cut instead. Only the outermost property typed reads a helper again: one typed inside it reads
its writes under `AnalysisMemo::cut()`, as before the re-entry. The work is the cut read's plus one read per call and
property the outermost writes reach for the check and one for the fallback; a property typed inside adds none.

An element write, an element `unset()` and a by-reference `foreach`, whose values
`VariableWrites::foreachElementValues()` reads, apply to each list or object arm of the joined type in any order until
none changes, and decline on any other arm or on a path to a member an arm may lack ([`ArrayArm`]). An object the class
may send as a list also publishes that list unless its members admit it, which in a re-indexing resource
(`ResourceCollections::reindexesNumericKeys()`) needs one type or the array's order, and order is known only for the
default and constant writes.

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
`ReceiverMemberReader::value()` per receiver class, `DeclaredClasses::returnClasses()`,
`ReceiverMemberReader::classes()`, `LaravelTsPublish::toTsType()` and its three member readers,
`StringSerialization::jsonStringType()`,
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
`ModelAttributeResolver`'s accessor waterfall. The two accessor keys gain an `@importless` suffix for a read that
carries no imports, and all three gain `@casesAsLiterals` for the body fallback's reading. Every key gains
`@casesAsEnums` while a read that needs each case's enum is under way (`readingCasesAsEnums()`), so its answers stay
apart. It also holds the per-class reads each resource prop repeats:
`JsonApiResources::relationships()` and `includedResources()`, `ResourceCollectionBody::sendsCollectedList()`,
`ResourceResponseBody::holdsWrapKey()`, and the two model guesses in `GuessesModelResources`.

`analysis:` and `method-return:` also key on what a value read's call binds: `CallSite::signature()` adds an `@args:`
suffix spelling the bindings its arguments make for the parameters whose declarations bind nothing, so two calls that
bind alike share one answer, and a call that binds nothing keeps the plain key. The arguments are read in the caller's
scope before the callee's frame opens, so what they record is the caller's. A subject method read under an `instanceof`
proof that holds `$this->resource` to one model adds `@backing:<model>` to its `method-return:` key, so the read with
the proof and the one without never share an answer. A body read for a caller whose runtime class is not the method's
class, as a private `$this->m()` or a `self::m()` is, adds `@runtime:<class>` to its `method-return:` and `analysis:`
keys, so two siblings never share an inherited helper's `static`. A `macro:` key names the class the call reaches,
which decides whether `__call()` runs, and adds the runtime class, whose macros decide what runs, the same way. A read
that only assumes its runtime class adds `@assumed` to them and to `macro:`, so a receiver's read of a body never
shares one that knows the class. `RuntimeClass::memoKey()` spells both suffixes for all three keys. The `method-body:`
cycle guard stays per method, so a recursion that passes a new value each time stops at its first repeat.
A body read for Inertia adds `Reading::memoKey()`'s `@inertia` to its `analysis:` and `method-return:` keys, and
`@filtered` after it under a resource's filter; a response's extras read adds `@unfiltered`. So a helper read for a page
never shares the answer a read for anything else gets ([Inertia profile](#inertia-profile)).

A stored answer keeps the dependency paths, the dropped-arm count and the `UnreadSpreads` count recorded while it was
computed, and every reuse records them again. The generation cache, the accessor `null` rule and a `parent::` spread's
read therefore see what a fresh computation would have shown them. A cycle can cut an answer short, so each answer is
stored with the guards it found held, and `AnalysisMemo::reproducible()` reuses it only where nothing it read has
changed and exactly those guards are held again. No answer depends on which entry point read it first.
`AnalysisMemo::reenterOnce()` lifts each guard entered before the one its caller holds, so a property write's value
enters a method already under way once more, and the `typing-property:` guard bounds the cycle. It never nests, and
throws if a call would: `cut()` holds the lifted guards again for a property typed inside, and records a marker for
each, so an answer it shaped is reused only where the same guards are lifted. A lifted guard counts as not held, so an
answer computed under the lift is never reused where that guard is held. `reentries()` counts each entry into a lifted
guard, and each reuse of an answer that entered one, so a caller can tell a read that went through an analysis under way
whatever was read first. `seeding()` marks a guard that answers a seed in place of its cut: no answer that met it
outside the check is reused there, and one computed there is reused only until the check ends. `computations()` counts
the answers a run computes, which a test bounds.

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
An arm left out still makes the union optional when it can be a `MissingValue`. `ValueResult::unionResults()` folds an
empty `[]`'s `never[]` into a list member beside it, which already admits the empty array: `$rows ?? []` over a list
publishes the list.

[`DroppedUnionArms`] records the drops made at a fixed set of sites, and each entry names its site, so a site that goes
silent shows. The sites are `ValueResult::analyzeClosureUnion()`, `TernaryHandler`'s narrowed arm,
`KnownFunctionCallHandler`'s `data_get()` default, `CoalesceHandler`,
`ConditionalMethodHandler::applyConditionalDefault()`, `MatchHandler`'s arms, `VariableHandler`'s join of a parameter
past its block (`binding-join`: an old value no rule types beside writes that all type, or a nested write no rule types
where an earlier value still reaches the read), the walk's branch join (`walk-branch-join`, naming the last key write or
array item the branch saw, never a sibling's, that it reads as `unknown`) and the join of a merge call's sides or a
spread helper's returns (`merge-branch-join`, naming the same for each walked, literal or joined branch).
`analyzeClosureUnion()` records because `unionResults()` receives only resolved results and cannot name the expression
it drops. `CoalesceHandler` strips its left operand's `null` with `TsTypeString::withoutNullArm()`, because `??` never
returns that `null`, which the ternary union would keep. Under an Inertia reading a left with held-object flags is the
exception: `??` returns a closure or prop object whole, so the `null` its call resolves to stays, and only a plain
arm's `null` is replaced. In what a closure returns (`AnalysisScope::$readsCallableReturn`) only a prop object
(`heldProp`) is, since Inertia sends a callable there as `{}`, uncalled. A left the receiver resolver reads as a
collection, its type holding no `null`, is returned whole where it is read from `$this`, an API resource's
`$this->resource` or a call through other collections alone: `??` tests the whole read, so a relation of a to-one relation or of `first()` is null
wherever that model is. Otherwise it unions both operands through `unionResults()`, which leaves an untyped one out.
`applyConditionalDefault()` records a default only beside a value arm with a type.
`BranchJoin::branchUnion()` unions type strings and cannot name the expression it drops, so the two branch
joins name one through the untyped writes `ReadsReturnedVariables` notes, which a merge call's or a spread helper's
walked, literal or joined branch keeps beside its analysis. One arm is never left out: `$this->resource` read whole
under its own `instanceof` proof, as the proven class with no published type, leaves the ternary `unknown`.

Recording is off until a test calls `start()`, but the count always runs. `AccessorBodyAnalyzer` and `VariableHandler`
compare it around a read to tell a dropped arm's `null` from a literal one, and `AnalysisMemo` replays it for a reused
answer.

Each site also passes its union to `DroppedUnionArms::warnOfPlaceholder()`. Where a drop leaves the union only `null`
and/or `never[]`, it adds one run-output warning per arm left out, naming its line and expression, and returns at once
when nothing was dropped. The three join sites call `warnOfJoinDrop()` instead: a value a join leaves out still reaches
the read, so beside a typed arm it warns that the published type may not admit every value the read sends. It covers
only a value a join leaves out: past a write naming no class, a member read keeps the old class (`receiversKeep`) and
warns of nothing.
`replaceable()` holds the warnings of a read whose placeholder its caller may replace, a declared local's `@var` or a
getter body that gives way to the model's declared type, and lets them through only where nothing replaces the reading.
`provisional()` does the same and, for a read thrown away, as a property's fixpoint check is, restores the count and the
audit entries too. A class the generation cache serves prints none, since the cache stores no analysis warnings, until
`--fresh` analyzes it again. The warning names a return type or a `@return` as the remedy, not `#[TsCasts]`, because a
cast on the key leaves the reading, and so the warning, as it is.

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
`MethodContext`, `AnalysisScope` or `RuntimeClass`, and the signature rule skips a tagged method.
[Known gaps][gap-non-goals] records why the engine offers no extension point.

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
| `InertiaPageAnalyzer` | The Inertia profile over `bindingsFor()`'s scope, a props local read as the literals its read sees or walked from its writes before the render call (`variableAnalysis()`), and `analyzeMethod()` only for props delegated whole to a collaborator, which assumes its runtime class unless that class is final | Page props are expressions in an action, not a method's return; the container may inject a subclass |
| `InertiaSharedDataAnalyzer` | `analyzeMethod($middleware, 'share')` and `AnalysisImports::build()` | It rewrites channels before importing |
| `ModelMetadataAnalyzer` | `bindingsFor()` on the provider's `provide()`, then `ResourceAstAnalyzer` on the non-resource profile | It locates `provide()` itself, and the analyzer reads the scope `bindingsFor()` seeded as it is. See [Model metadata][metadata-consumer]. |
| `AccessorBodyAnalyzer` | `analyzeModelClosure()` on `forModelClosures()` | The model is the subject, so a trait's accessor reads `$this` as the model using it |
| `MethodReturnTypeResolver` | `analyzeMethod()`, then `analyzeMethodValue()`, both with `casesAsLiterals` unless `AnalysisMemo::readsCasesAsEnums()` | It is the body fallback: the literal shape, then the value read, both carrying imports. See [Receiver types][body-fallback]. |

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
[`ArrayArm`]: ../../src/Ast/Members/ArrayArm.php
[`AstEngine`]: ../../src/Ast/AstEngine.php
[`AstParser`]: ../../src/Ast/Parsing/AstParser.php
[`ByReferenceArguments`]: ../../src/Ast/Calls/ByReferenceArguments.php
[`ByReferenceEffect`]: ../../src/Ast/Calls/ByReferenceEffect.php
[`CollectsInstanceofGuards`]: ../../src/Ast/Concerns/Narrowing/CollectsInstanceofGuards.php
[`DroppedUnionArms`]: ../../src/Ast/Analysis/DroppedUnionArms.php
[`ExpressionDispatcher`]: ../../src/Ast/Dispatch/ExpressionDispatcher.php
[`ExpressionEngine`]: ../../src/Ast/Contracts/ExpressionEngine.php
[`ExpressionHandler`]: ../../src/Ast/Contracts/ExpressionHandler.php
[`MethodAnalysis`]: ../../src/Ast/Analysis/MethodAnalysis.php
[`MethodLocator`]: ../../src/Ast/Parsing/MethodLocator.php
[`ParameterBinder`]: ../../src/Ast/Calls/ParameterBinder.php
[`PaginatorSteps`]: ../../src/Ast/Receivers/PaginatorSteps.php
[`ParentCallChain`]: ../../src/Ast/Parsing/ParentCallChain.php
[`PropertyWriteSites`]: ../../src/Ast/Members/PropertyWriteSites.php
[`Reading`]: ../../src/Ast/Analysis/Reading.php
[`ReadsReturnedVariables`]: ../../src/Ast/Concerns/Bodies/ReadsReturnedVariables.php
[`ResolvedResourceBody`]: ../../src/Ast/Resources/ResolvedResourceBody.php
[`ResourceAstAnalyzer`]: ../../src/Analyzers/ResourceAstAnalyzer.php
[`ResourceExpressionHandlers`]: ../../src/Ast/Dispatch/ResourceExpressionHandlers.php
[`ResourceResponseType`]: ../../src/Ast/Resources/ResourceResponseType.php
[`ResponseExtras`]: ../../src/Ast/Resources/ResponseExtras.php
[`ReturnLiteralReader`]: ../../src/Ast/Parsing/ReturnLiteralReader.php
[`RuntimeClass`]: ../../src/Ast/Analysis/RuntimeClass.php
[`SubjectPropertyTypeResolver`]: ../../src/Ast/Members/SubjectPropertyTypeResolver.php
[`ThisPathWrites`]: ../../src/Ast/Analysis/ThisPathWrites.php
[accessor-reads]: accessor-body-analyzer.md#a-getter-that-reads-another-models-accessor
[attribute-filters]: resource-ast-analyzer.md#attribute-filters-on-any-model-receiver
[body-fallback]: receiver-types.md#the-body-fallback-carries-channels-as-an-inline-literal-does
[built-payload]: resource-ast-analyzer.md#a-method-called-on-a-new-resource-types-its-payload
[collection-pipelines]: resource-ast-analyzer.md#collection-pipelines
[gap-narrowing]: ../known-gaps.md#instanceof-narrowing-depends-on-the-spelling-in-four-different-ways
[gap-non-goals]: ../known-gaps.md#deliberate-non-goals
[gap-ordering]: ../known-gaps.md#handler-ordering-is-pinned-pairwise-corpus-bounded
[gap-spelled-name]: ../known-gaps.md#a-tscasts-value-that-spells-an-imported-name-inside-a-string-template-or-comment-keeps-the-import
[gap-union-arm]: ../known-gaps.md#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm
[merge-branches]: resource-ast-analyzer.md#mergereturnbranches-carries-every-methodanalysismerge-channel-plus-two-flat-scalars
[metadata-consumer]: model-metadata.md#body-inference-is-an-engine-consumer
[published-models]: model-attribute-resolver.md#models-a-run-publishes
[receiver-self]: receiver-types.md#self-and-parent
[resource-imports]: resource-ast-analyzer.md#a-resources-imports-follow-its-published-types
[spread-branches]: resource-ast-analyzer.md#a-spread-helper-drops-an-untypable-branch
[ternary]: receiver-types.md#a-ternarys-instanceof-condition

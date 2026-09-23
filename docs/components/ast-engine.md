# AST Engine

`AbeTwoThree\LaravelTsPublish\Ast\AstEngine` is the public entry point onto the package's php-parser
layer — the only one it has: hand it a class and a method name, get back a `MethodAnalysis` DTO of
TypeScript properties plus resource, model, and enum FQCN channels and import maps. It replaces the
duplication that used to sit inside six separate files, each carrying its own nikic/php-parser logic —
`ResourceAstAnalyzer`, `ControllerPaginatorAnalyzer`, `InertiaTableAnalyzer`, and the traits
`InspectsAstNodes`, `FiltersModelAttributes`, `ResolvesClassNames` — with the shared primitives
documented below: `AstParser`, `MethodLocator`, `ExpressionDispatcher`, and the two handler profiles.

Every feature that infers a type runs on it. Resources, broadcast events, Inertia shared data and
Inertia page props all go through `AstEngine`, and `laravel/surveyor` + `laravel/ranger` — the second,
foreign AST engine that used to type events and both Inertia features — are gone from `composer.json`.
`ControllerPaginatorAnalyzer` went with them: the handlers resolve a paginator from the prop expression
itself, so its three prop-key maps had no caller left. The tracked
[ADR: freeze Laravel Surveyor/Ranger and exit in stages](../decisions/2026-08-31-surveyor-staged-exit.md)
records the staged exit and what each stage had to show before it landed.

## Dispatch semantics

`ExpressionHandler::resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array`
returning `null` means **decline**: the dispatcher tries the next candidate handler, and if none
resolve it, the caller degrades to `ValueResult::unknown()`. This is the decline-and-fall-through
contract every handler implements — it is what lets 27 independently-written handlers reproduce one
ordered guard chain's behavior without any handler knowing about the others.

`ExpressionDispatcher::dispatch()` does the trying. For an expression's *concrete* node class, it
builds the candidate list once — every handler whose `nodeClasses()` claims that class via `is_a()`,
so a handler can claim an abstract type (e.g. `Cast::class`) and match every concrete subclass — and
memoizes it per class in `$candidatesByClass`. A class no handler claims still gets a cached empty
list, so a repeated dispatch of an unclaimed node class never re-scans every handler's `nodeClasses()`
again. Handlers run in registration order within that candidate list; the first non-null `resolve()`
wins. This is PHPStan's `ExprHandlerRegistry` and Rector's `NodeNameResolver` memoization pattern,
scaled down: no DI container, no attribute-driven autodiscovery, just a plain constructor array — at
27 handlers that is enough.

`ExpressionEngine` has exactly three methods, all implemented today by `ResourceAstAnalyzer`:

- `resolve(Expr $expr): array` — full expression resolution; dispatches to the first handler that
  claims the node, or degrades to `unknown` if none do. Handlers call back into this for a
  sub-expression they don't own themselves.
- `spreadAnalysis(string $methodName): ?MethodAnalysis` — re-enters the analyzer on a named method's
  own return, for a handler that resolves a self-returning chain onto a non-preserving method body
  (`StaticCallHandler`'s `new self($x)->method()` handling) instead of degrading to `unknown`.
- `returnArrayAnalysis(Array_ $array): MethodAnalysis` — the same return-position array machinery used
  for a nested inline array literal, reused by `InlineArrayHandler`.

## Handler ordering

`ExpressionDispatcher::dispatch()` tries registered handlers, in registration order, for an
expression's concrete node class, and returns the first non-null result —
`ExpressionHandler::resolve()` returning `null` means DECLINE, fall through to the next candidate.
Order is load-bearing wherever a node class is claimed by more than one handler: a reordering that
changes which handler wins for a shared class is a silent behavior regression, not a refactor.

`ResourceExpressionHandlers::make()` builds the resource profile — the 22 handlers extracted from
the legacy `analyzeValueExpression()` guard chain, in the exact order the chain checked them, plus
`ArrayMergeHandler`, `InertiaWrapperHandler`, `CollectionPipelineHandler`,
`ReceiverPropertyFetchHandler`, and `ReceiverMethodCallHandler`. `CollectionPipelineHandler` sits
directly after `RelationCollectionChainHandler`, the handler it shares its op table with — see
[ResourceAstAnalyzer § Collection pipelines](resource-ast-analyzer.md#collection-pipelines).
`ReceiverMethodCallHandler`
sits last before the convention rules in `KnownMethodRuleHandler`, so every specific handler keeps priority;
it follows a method's return type through any receiver class, see
[Receiver types](./receiver-types.md#following-a-methods-return-type).
`ReceiverPropertyFetchHandler` sits immediately before it and does the same for a property read, see
[Property access on a receiver](./receiver-types.md#property-access-on-a-receiver).
`ResourceExpressionHandlers::withoutResourceHandlers()` is that same list minus the three
resource-only handlers (`ConditionalMethodHandler`, `ToResourceHandler`, `RelationFilterHandler`)
— every other handler is class-agnostic and safe to reuse outside a resource's `toArray()`.

`ResourceExpressionHandlers::forModelClosures()` is the list minus only `ConditionalMethodHandler` and
`ToResourceHandler`, and `AstEngine::analyzeModelClosure()` is its one caller. A getter body reads the model's own
relations, and `RelationFilterHandler` is the only handler that types a to-many relation's filter, a map proxy, a
multi-model accessor's filter, a filter on a column cast to a `Support\Collection` (`'collection'`, `AsCollection`
and their encrypted forms), or one on an accessor holding an `Eloquent\Collection` there: without it those published
`unknown`, since the generic reflectors decline every filter. A single relation's filter, and one on an accessor
returning a `Support\Collection`, do not depend on it, because `ReceiverMethodCallHandler` gives the same answer.

**But being safe to reuse is not the same as being used.** `withoutResourceHandlers()` has exactly one production
caller, `ControllerExpressionHandlers::make()`. Every other non-resource subject apart from a model's getter body — a
broadcast event, model metadata, any DTO reaching `AstEngine::analyzeMethod()` — runs the **resource** profile, so
`ConditionalMethodHandler`, `ToResourceHandler` and `RelationFilterHandler` are live inside event and
metadata bodies today. The method is named for what it drops rather than for who may use it, because its
former name (`generic()`) promised a layering nothing wires. Pointing `analyzeMethod()` at it would move
published broadcast-event and model-metadata types, which makes that a typed-output decision rather than
a cleanup: measure the diff both ways and pin whichever answer is right before changing it.

The executable ordering contract lives in `tests/Unit/Ast/ResourceExpressionHandlersTest.php`:

- One test asserts `make()`'s exact class-name sequence, so an accidental reorder fails a test
  instead of silently changing generated output.
- One test asserts `withoutResourceHandlers()`'s exclusion set and relative order, and one asserts
  `forModelClosures()`'s.
- Five tests pin the *behavioral* precedence between handlers that both really claim a shared node
  class — proven by mutation: swap the pinned pair, watch the pinned test fail, revert.
  - `FirstClassCallableHandler` before `ConditionalMethodHandler` for a first-class-callable
    `$this->when(...)`. `ConditionalMethodHandler::isThisMethodCall()` matches on method name
    alone, ignoring arguments, so it also claims this shape; if it ran first it would call
    `MethodCall::getArgs()`, which asserts `!isFirstClassCallable()` and fatals.
  - `ThisPropertyHandler` before `PropertyChainHandler` for `$this->{multi-FQCN accessor}`.
    `ThisPropertyHandler` threads a multi-model accessor's FQCNs out as `embeddedModelFqcns`, used
    downstream to alias same-basename union arms apart; `PropertyChainHandler`'s last-step branch has
    no equivalent, so swapping the two loses one arm's FQCN entirely rather than merely reordering.
  - `InertiaWrapperHandler` before `StaticCallHandler` for `Inertia::always(...)`. `StaticCallHandler`'s
    last arm claims every `StaticCall` on a named class and never declines one, so if it ran first it would
    reflect the wrapper as an ordinary static method and floor the prop at `unknown` instead of the wrapped value.
  - `FirstClassCallableHandler` before `KnownFunctionCallHandler` for a first-class-callable
    `auth()->user(...)`. `KnownFunctionCallHandler` gates only the inner `auth()` call on
    `isFirstClassCallable()`, never the outer `MethodCall`, so if it ran first it would answer with
    the guard's model — a confident type for what is actually a `Closure`.
  - `FirstClassCallableHandler` before `ToResourceHandler` for a first-class-callable
    `$this->post->toResource(...)`. `ToResourceHandler` matches on the method name alone and then
    calls `getArgs()`, which asserts `!isFirstClassCallable()` and fatals without the guard ahead of it.
- One former pin now proves the order does not matter: `RelationFilterHandler` and `MethodChainHandler` answer
  `$this->relation?->only([...])` and `$this->resource->relation?->only([...])` with the same `Pick<>` in either
  order. `MethodChainHandler` used to reflect `only()` on the related model and degrade that reference when it
  ran first; it now declines every `only()`/`except()`, and the test runs both orders.

`MethodCall` gets a further, exhaustive layer on top of the five pins above:
`tests/Unit/Ast/MethodCallOrderingMatrixTest.php` runs every one of its eleven claimants' 55 unordered
pairs in both orders over a curated corpus, the same mutate/watch-fail/revert method proves each pin
with, rather than trusting a hand-picked example per pair — see that node class's inventory row below.

### Controller profile

`ControllerExpressionHandlers::make()` is the profile `InertiaPageAnalyzer` runs an
`Inertia::render()` props expression through. It is `ResourceExpressionHandlers::withoutResourceHandlers()` with two
handlers inserted **immediately before `StaticCallHandler`**:

- `ModelFinderHandler` — a chain rooted at a `Model` static call, typed by its terminal:
  `find`/`first`/`firstWhere` → `{Model} | null`; `sole`/`firstOrFail`/`findOrFail`/`firstOrCreate`/
  `firstOrNew`/`create`/`make`/`updateOrCreate` → `{Model}`; `all`/`get` → `{Model}[]`;
  `paginate`/`simplePaginate`/`cursorPaginate` → the matching `TolkiTypes::MAP` name generic over the
  model, with the `@tolki/types` external import; `count`/`exists` → `number`/`boolean`.
- `InertiaResourcePropHandler` — a resource or resource collection in prop position, resolved to what
  it wraps, including the preserve-keys `Omit<…, 'data'> & { data: Record<string, R> }` shape.

That position is load-bearing in both directions. `StaticCallHandler`'s final arm claims every
`StaticCall` on a named class and never declines one, so anything registered after it never sees one; and `NewResourceHandler`
sits directly after `StaticCallHandler` and resolves a `ResourceCollection` to its collected element
array, so a `New_` handler has to precede that too. `tests/Unit/Ast/ControllerExpressionHandlersTest.php`
pins the structure (the profile equals `withoutResourceHandlers()` with exactly those two inserted at that point) plus
three behavioural ordering pins, each proven by mutation.

`InertiaPageAnalyzer` pairs the profile with `AstEngine::bindingsFor()`, which seeds the scope from the
action's own signature: route-bound `Model` parameters, `Request` parameter names, and the local
variable bindings collected from the method body. That is what lets `compact('post', 'comments')`,
`$post` from `show(Post $post)`, and `$request->integer('page')` all type without any Inertia-specific
special case in the handlers.

### The honest ordering inventory

Not every node class more than one handler claims has a pin. The table below is the full inventory of
contested node classes — pinned pairs, and pairs explicitly proven inert (the two arms never both
actually claim the same expression, so their relative order cannot change output).

| Node class | Claimants | Status |
| --- | --- | --- |
| `MethodCall` | `FirstClassCallableHandler`, `KnownFunctionCallHandler`, `ConditionalMethodHandler`, `ToResourceHandler`, `StaticCallHandler`, `RelationFilterHandler`, `RelationCollectionChainHandler`, `CollectionPipelineHandler`, `VariableHandler`, `ReceiverMethodCallHandler`, `KnownMethodRuleHandler` (11) | Three of the 55 unordered pairs are pinned: `FirstClassCallableHandler` before `ConditionalMethodHandler` and before `ToResourceHandler` (both crash-level — the loser calls `getArgs()`, which asserts `!isFirstClassCallable()`); and `FirstClassCallableHandler` before `KnownFunctionCallHandler` (a silent divergence: `auth()->user(...)` as a first-class callable resolves to the guard's model instead of `unknown`). **On this model-backed corpus, no pair is contested for an `only()`/`except()` filter.** Two former pins are gone because `RelationCollectionChainHandler` now declines every filter on a model-backed scope. That decline does not reach a model-less scope: in the controller profile `RelationCollectionChainHandler` still reflects `$this->post->except($keys)` to `unknown[]` ahead of `ReceiverMethodCallHandler`'s `Record<string, unknown>`, and `$this->post->only(['id', 'title'])` to `Record<string, unknown>` ahead of its `Pick<Post, 'id' \| 'title'>`. The matrix runs only the `Comment` resource scope, so that disagreement is unpinned. `RelationFilterHandler` before `RelationCollectionChainHandler` had kept the chain handler's reflected `Record<string, unknown>` off `$this->post->only(['id', 'title'])`, and `RelationCollectionChainHandler` before `ReceiverMethodCallHandler` had held that same inferior `Record<string, unknown>` over the receiver rules' `Pick<Post, 'id' \| 'title'>`, and `unknown[]` over their answer for `except()`, in a two-handler profile. The corpus runs `$this->post->only()`/`except()` with literal and runtime key lists, `$this->resource->only([...])`, `$this->resource->except($fields)`, `$this->resource->post->only([...])` and the many-relation `$this->replies->only([1])`, and every pair agrees on each. The former `ToResourceHandler`-before-`RelationCollectionChainHandler` pin is gone: that branch now declines where it used to floor at `unknown`, so `$this->post->toResource()` reaches `ToResourceHandler` in either order. `CollectionPipelineHandler` brings ten new pairs and needs none of them pinned: it claims only a chain rooted at `collect(...)`, which `RelationCollectionChainHandler` declines for want of a `$this->prop` root, so the two never answer the same expression. It does overlap `VariableHandler`, whose trailing-`values()`/`all()` peel claims the same outermost node, and that agreement is **earned, not structural**: the peel resolves a receiver carrying one op *fewer*, so on `collect($x)->filter()->values()` it would hand back the `X[] \| Record<string, X>` its receiver really has, where the pipeline handler gives `X[]` — a keyed arm `values()` cannot leave behind. The peel therefore spells `values()` through `SpellsKeyedCollections::valuesList()`, which turns each `Record<string, X>` arm into `X[]`, and keeps the receiver's type for `all()`, since `all()` returns the underlying array with its keys intact. It peels a receiver that `ReceiverClassResolver` resolves to classes that are all `Illuminate\Support\Enumerable`, and, where the resolver names nothing, only the `$variable->map(callback)` its own map arm types as a Collection map: on any class the resolver names that is not a collection, `values()` and `all()` are that class's own methods, so it declines and the receiver rules reflect them. Note the matrix cannot pin this particular pair: typing the `collect()` argument needs a third cooperating handler, and its profiles hold two. `CollectionPipelineHandlerTest` pins both halves directly instead, in the `ReceiverHandlersTest` "both claim it and answer it the same" shape. The corpus runs a relation-rooted `map()->values()->all()`, `concat()` of the receiver's own relation, and two `collect()` roots whose argument `RelationCollectionChainHandler` alone can type — the last of these being the only shape where the pipeline handler actually claims anything inside a two-handler profile. `ReceiverMethodCallHandler` still declines a receiver holding a `Request`, and a bare `$this->method()` the resource declares itself, for which `ReceiverClassResolver::forwardedThisReceiver()` names no class — and it no longer declines `only()`'s vague `Record<string, unknown>`. On a model subject (a model's own method or accessor body) it takes a bare `$this` as the model through `ReceiverClassResolver::modelSubject()`, for an `only()`/`except()` only. `ReceiverMethodReturnResolver::attributeFilterRule()` now answers `only()`/`except()` on a receiver holding exactly one model class, building the same `Pick<Model, …>` (or the same inline shape) `RelationFilterHandler` builds for a relation to that model, and the same `Record<string, unknown>` from `ResolvesFilteredRelationTypes::attributeRecordResult()` when the key list is not literal. Both skip a model whose filter override has a return reflection types, through `ReceiverMethodReturnResolver::typesAsModelFilter()`, and both read `AnalysisScope::$carriesImports` the same way. Against `RelationFilterHandler` that leaves the pair inert **for a new reason** — the two now agree, where one used to decline — and `ReceiverHandlersTest`'s `both claim $this->relation->only([...]) and answer it the same` proves it directly, channels included, in both the `->` and `?->` spellings and for a runtime key list. A many-relation filter is not shared: `RelationFilterHandler` publishes the relation read, and the receiver rules decline an Eloquent collection, so the two cannot disagree on it. A filter on an accessor holding a `Support\Collection` is shared and agrees by construction, whatever the collection's elements: `RelationFilterHandler` asks `FiltersAttributeKeys::runsCollectionFilter()` before reading any element model the accessor names, the receiver rules ask the same check, and both publish `attributeRecordResult()`. An accessor holding an `Eloquent\Collection` is not shared: `RelationFilterHandler` publishes a list of its models, and the receiver rules decline an `Eloquent\Collection`. `ReceiverHandlersTest`'s `agree on a filter on an accessor holding a collection of models` runs both handlers on `Attribute<Collection<int, User>, never>` and `Attribute<Eloquent\Collection<int, Comment>, never>`. A bare `$this->getKey()` the resource forwards to its model is in the corpus: `SubjectMethodTypeResolver` rejects `Model::getKey()`'s `mixed`, so only `ReceiverMethodCallHandler` answers it. `SubjectMethodTypeResolver::resolve()` declines when nothing in scope declares the method, so `RelationCollectionChainHandler` no longer floors every `$this->method()` at `unknown`; `ConditionalMethodHandler` and `KnownMethodRuleHandler` therefore answer `$this->when()`/`whenLoaded()` and `can()`/`cannot()`/`canAny()` in either order. The decline is not ordering alone: a model that declares `can()` with a return type `ReflectedTypeAcceptor` rejects — `can(): void` — falls through the same way, so it too lands on `KnownMethodRuleHandler`'s `boolean` where it used to floor at `unknown`. Every unordered pair is run in both orders by `tests/Unit/Ast/MethodCallOrderingMatrixTest.php` over a curated corpus: the pairs in its `METHOD_CALL_PINNED` map disagree and are held in the direction `handlers()` lists them; every other pair is proven inert on that corpus (a new expression shape that makes an inert pair disagree fails the matrix, which is the signal to pin it). In the controller profile, `withoutResourceHandlers()` drops `ConditionalMethodHandler`, `ToResourceHandler`, and `RelationFilterHandler`, and `ControllerExpressionHandlers` splices `ModelFinderHandler` (`StaticCall` + `MethodCall`) ahead of `StaticCallHandler`, making nine claimants there. |
| `NullsafeMethodCall` | `RelationFilterHandler`, `MethodChainHandler`, `ReceiverMethodCallHandler` (3) | **No pair is contested for an `only()`/`except()` filter**, because `MethodChainHandler` declines every one. `RelationFilterHandler` before `MethodChainHandler` used to be pinned: `MethodChainHandler` reflected `only()` on the related model, degrading `$this->relation?->only([...])` to `Record<string, unknown> \| null` and a runtime-key `except()` to `unknown[] \| null` whenever it answered first. `ResourceExpressionHandlersTest`'s `answers $this->relation?->only([...]) with the Pick<> whichever of RelationFilterHandler and MethodChainHandler runs first` now runs both orders, in the `$this->post?->` and `$this->resource->post?->` spellings. `RelationFilterHandler` vs. `ReceiverMethodCallHandler` is **inert**, but for the opposite reason to the one recorded here before: `ReceiverMethodCallHandler` used to decline the vague `Record<string, unknown>` that `only()` reflects to, and now **answers** `$this->relation?->only([...])` with the very same `Pick<Post, 'id' \| 'title'> \| null`, `modelFqcn` channel included, and a runtime-key filter with the same `Record<string, unknown> \| null`. `ReceiverHandlersTest` pins that agreement directly in both spellings rather than leaving it to a decline. `MethodChainHandler` vs. `ReceiverMethodCallHandler` used to disagree on `$this->author?->fresh()`: `MethodChainHandler` reflected the `static\|null` docblock to `unknown \| null` and answered first, while `ReceiverMethodCallHandler` gives `User \| null`, the type `$this->author->fresh()` already had. `MethodChainHandler` now declines a type that is only `unknown` once its `null` arms are removed, and `ResourceExpressionHandlersTest`'s `lets MethodChainHandler decline an unknown-only $this->relation?->fresh()` test pins that decline: restoring the floor fails it. The test does not pin the order. With the decline in place both orders give `User \| null`, and a both-orders probe of 50 model methods across five `$this->relation?->m()` chains found no disagreement. That is probe-level evidence only, since no matrix runs this node class. |
| `PropertyFetch` | `ThisPropertyHandler`, `PropertyChainHandler`, `VariableHandler`, `ReceiverPropertyFetchHandler` (4) | One of the six pairs is pinned (`ThisPropertyHandler` before `PropertyChainHandler`). Three are **inert by construction**: `ThisPropertyHandler` vs. `VariableHandler` never both claim the same expression (`isThisPropertyFetch()` requires a `$this` receiver; `VariableHandler`'s property branch requires the receiver not be `$this`); `PropertyChainHandler` vs. `VariableHandler` likewise — `PropertyChainHandler`'s fallback declines any chain not rooted at `$this`, which is exactly `VariableHandler`'s territory; and `ThisPropertyHandler` vs. `ReceiverPropertyFetchHandler`, since the receiver handler declines every `$this->prop` leaf outright. The remaining two are **inert by mutation**: registering `ReceiverPropertyFetchHandler` ahead of `PropertyChainHandler`, and then ahead of `VariableHandler`, each regenerated the committed trees byte-identically, with only the two registration-order tests failing. Neither is vacuous by accident — `ReceiverHandlersTest`'s `both claim $this->relation?->attr and answer it the same` shows the `PropertyChainHandler` pair genuinely overlapping and agreeing. The `VariableHandler` pair has a shape where the two *would* differ and the corpus is silent: for a variable bound in `varModelBindings`, `VariableHandler::analyzeRelatedModelProperty()` answers `unknown` rather than declining when the member is a relation rather than an attribute, and the receiver handler would type that relation. No fixture writes `$x->relation` inside a `whenLoaded` closure today, which is why the swap moved nothing; read this row's inertness the way the note below this table asks. |
| `NullsafePropertyFetch` | `PropertyChainHandler`, `ReceiverPropertyFetchHandler` (2) | **Inert by the same mutation** as the `PropertyFetch` row. `PropertyChainHandler` still answers a `$this`-rooted chain it can type, and now declines one whose answer is only `unknown` once its `null` arms are removed — `TsTypeString::isUnknownOnly()`, the same test `MethodChainHandler` applies to a nullsafe call chain. Its `NullsafePropertyFetch` arm used to return that floor unconditionally, which is what kept `$post?->title` at `unknown` instead of letting the receiver rules type it. `ReceiverHandlersTest` pins both halves: the decline, and the agreement on a chain both claim. |
| `BinaryOp\Coalesce` | `BinaryOpHandler`, `CoalesceHandler` (2) | Inert-proven — `BinaryOpHandler::resolve()` has no branch matching `BinaryOp\Coalesce`, so it always declines regardless of registration position. |
| `StaticCall` | `InertiaWrapperHandler`, `StaticCallHandler`, `ReceiverMethodCallHandler` (3) | `InertiaWrapperHandler` before `StaticCallHandler` is pinned. `StaticCallHandler` now declines a static call whose class is an expression it cannot name, such as `$record::className()`, and that is the only static-call shape `ReceiverMethodCallHandler` reaches: every call on a named class (`X::m()`, `self::m()`, `static::m()`) is still answered by `StaticCallHandler`, which never declines one. `InertiaWrapperHandler` vs. `ReceiverMethodCallHandler` is **inert by construction**: `Inertia\Inertia` is a facade that declares none of the wrapper methods, so `ReceiverMethodCallHandler` declines every `Inertia::always(...)`-style call in either order. |
| `FuncCall` | `ArrayMergeHandler`, `KnownFunctionCallHandler` (2) | Inert-proven — `KnownFunctionCallHandler` declines `array_merge`: its reflected return type is `unknown[]`, and `resolveKnownFunctionCallType()` rejects any type containing `unknown`. `ArrayMergeHandler` is still registered first, so the specific handler keeps winning if that ever changes. |

`MethodCall` is now the most thoroughly verified row in this table: every one of its 55 unordered
pairs is run in both orders, not merely enumerated by inspection. Its residual limit is the matrix's
own corpus — an expression shape the corpus never constructs cannot disagree there, however plausible
it looks by inspection. Read the pin counts elsewhere in this table the way this file always has: as
the divergences someone has actually gone and found, not as the only divergences that exist.

## AnalysisScope

`AnalysisScope` is the mutable state threaded through one `ResourceAstAnalyzer::analyze()` call: the
subject under analysis and its backing model, plus the closure/spread bookkeeping that makes local
variables, `whenLoaded` relations, and recursive spreads resolve correctly as traversal descends. A
handler reaches it as the `$scope` parameter `ExpressionHandler::resolve()` receives; the analyzer
itself reaches the same instance as `$this->scope`.

Eight of its fields are **name-keyed tables**, each mapping a variable name to what that variable is bound to:
`closureParamExprBindings`, `varClassBindings`, `varGuardBindings`, `varModelBindings`, `varCollectionBindings`,
`varValueBindings`, `localVarBindings` and `requestVarNames`. `AnalysisScope::nameBindings()` captures exactly those
eight, with `claimedClosures`, and `restoreNameBindings()` puts them back.

| Field | Type | Holds |
| --- | --- | --- |
| `subjectReflection` | `ReflectionClass<object>` | The resource (or other AST subject) under analysis. Constructor argument. |
| `modelClass` | `class-string<Model>\|null` | The subject's resolved backing model, if any. Constructor argument. **Scoped, not fixed:** `TernaryHandler` narrows it to the guarded class while an `instanceof` true arm resolves, then restores it — see [Narrowing](#narrowing) rule 3. That mutation happens *below* `AstEngine`'s `analysis:class@method@modelClass` memo key, so the key does not describe the value a nested resolution actually ran under. |
| `instanceOfWrappedClass` | `class-string\|null` | Wrapped class from an `instanceof` guard in `toArray()`; fallback when `resolveClassOnProperty()` returns `null`. |
| `forwardsUndeclaredMembersTo` | `class-string\|null` | The class an undeclared `$this->member` read or call forwards to — a `JsonResource` proxies both to `$this->resource`. Derived in the constructor from the subject, so every scope carries it without its builder having to remember; `ResourceAstAnalyzer` re-derives it once an `instanceof` guard supplies a backing the constructor lacked. `ReceiverClassResolver` reads this instead of testing for `JsonResource` itself. Scoped: `TernaryHandler` narrows and restores it alongside `modelClass`. |
| `carriesImports` | `bool` | False while a reader that carries no import analyzes a body: `MethodReturnTypeResolver`'s body fallback, which flattens a shape into a type string with no FQCN channel, and an accessor getter read without imports through `AstEngine::analyzeModelClosure()`. Filter code reads it to publish the most specific answer that names no token. Seeded by `ResourceAstAnalyzer`'s constructor; a supplied scope keeps its own. |
| `closureRelationModelClass` | `class-string<Model>\|null` | Related model set while analyzing a `whenLoaded` closure, so `$variable->prop`/`->method()` inside it resolve. |
| `closureParamExprBindings` | `array<string, Expr>` | Closure parameter names bound to an expression: the `$this->prop` in a surrounding `when()` condition, for a required first parameter, so `EnumResource::make($status)` resolves like `EnumResource::make($this->status)`; the `$this->prop` passed to `transform()`; the attribute read `whenHas()` or `whenExistsLoaded()` passes; and, through `AnalysisScope::copyBindings()`, the entry a variable passed to `transform()` held. |
| `varClassBindings` | `array<string, non-empty-list<class-string>>` | Variables a ternary's `instanceof` test has proven to hold a class, and a `morphTo` `whenLoaded()` closure parameter bound to every target. Read **first** in `ReceiverClassResolver::fromVariable()`. What that ordering actually buys today is precedence over the `closureParamExprBindings ?? localVarBindings` fallback, since a narrowed variable is normally bound by a plain local assignment; sitting above `varModelBindings` is the same concern for a narrowed closure param or loop variable, and is motivating rather than currently proven. Scoped: `ClosureHandler`, `TernaryHandler` and `ConditionalMethodHandler` save and restore it around the body they bind it for. See [Narrowing](#narrowing). |
| `varGuardBindings` | `array<string, array{classes: non-empty-list<class-string>, after: int}>` | Variables an early-exit `instanceof` guard proves to hold a class, each with the file offset the guard's `if` ends at. `ReceiverClassResolver::fromVariable()` reads it right after `varClassBindings`, and only for a read that starts past that offset, so a `return` before the guard, or the guard's own body, still reads the variable unnarrowed. Written by `CollectsInstanceofGuards`. Scoped: every writer that captures the name-keyed tables restores it, and `analyzeThisMethodSpread()` clears it for a spread and restores it after. See [Narrowing](#narrowing). |
| `varModelBindings` | `array<string, class-string<Model>>` | Closure params / loop vars bound to a model class (to-one `whenLoaded` params, `map()` params on a relation chain or a variable, a `transform()` callback param passed a model, the entry a variable passed to `transform()` held, `foreach` over a many-relation), so `$var`, `$var->prop`, `$var->method()` resolve against that model. Scoped: every closure writer saves and restores it around the body; the `foreach` binding is method-wide. Also seeded, via `AstEngine::bindingsFor()`, from every `Model`-typed parameter of the located method — a route-bound `Post $post`, a metadata provider's `Model $model` — bound to the parameter's **declared** type. [How it gets populated](#how-varmodelbindings-gets-populated-and-how-scoping-holds) lists every writer. |
| `varCollectionBindings` | `array<string, array{type: string, modelFqcn: class-string<Model>}>` | Closure params bound to a whole relation collection rather than one element — a to-many `whenLoaded` param. Read for a bare return of the param, and as the element-model fallback for an untyped `->map()` closure param. |
| `varValueBindings` | `array<string, ValueExpressionResult>` | Closure params bound to an already-resolved *value* rather than to a class. Six writers: `CollectionPipelineHandler::resolveMapBody()`, for a `collect(...)->map()` param, whose element type the pipeline read off the `collect()` argument before descending; `ConditionalMethodHandler::bindPassedValue()`, for a `transform()` callback param passed a value that is not a model, and for `transform()`'s default param, passed the value with its `null` arm; `ConditionalMethodHandler::bindVariadicList()`, for a variadic param, bound to the list its one argument collects into; `ConditionalMethodHandler::resolveValueArgument()`, for a `whenCounted()` or `whenAggregated(…, 'count')` value closure's param, bound to `number`; `AnalysisScope::bindUnpassedParameters()`, for a param the call passes nothing, bound to the type of the value its default evaluates to or, variadic, to `never[]`; and `AnalysisScope::copyBindings()`, for the entry a variable passed to `transform()` held. A bare read of the param resolves straight to it, checked after `varModelBindings` and `varCollectionBindings`, which name a model instead; the writer first releases the param's name from those tables, so an outer binding of it cannot win. Scoped: every writer runs inside a claim that saves and restores every name-keyed table around the body. |
| `localVarBindings` | `array<string, Expr>` | Top-level `$var = expr;` bindings for the method last analyzed, so a bare `Variable` value expression resolves through its bound expression instead of degrading to `unknown`. Only variables written exactly once are recorded; `analyzeThisMethodSpread()` saves and restores this per method. |
| `resolvingLocalVars` | `array<string, true>` | Re-entrancy guard: variable names currently mid-resolution, so a self- or mutually-referential binding (`$a = $b; $b = $a;`) resolves as `unknown` instead of recursing forever. |
| `visitedSpreadMethods` | `array<string, true>` | Spread methods currently on the analysis stack, so a method that spreads itself — directly or through a cycle — degrades to an empty analysis instead of recursing until memory runs out. |
| `requestVarNames` | `array<string, class-string<Request>>` | Variable names holding an `Illuminate\Http\Request`, mapped to the bound class, so `KnownMethodRuleHandler`'s reflected Request rule (`url()`, `ip()`, `integer()`, …) fires on `$request->ip()` and stays off an unrelated receiver sharing a method name; the bound class is what `validated()` is resolved against, reading the `FormRequest` subclass's own `rules()`. `user()` is answered ahead of reflection, from the configured auth model. Seeded in `AstEngine::bindingsFor()` from a located method's `Request`-typed parameters, and in `ResourceAstAnalyzer::resolveRequestVarNames()` for a directly-constructed resource analysis — **except for a `JsonResource` subject**, whose `toArray(Request $request)` would otherwise start typing request calls and move committed resource output. |
| `claimedClosures` | `array<int, true>` | Closures, keyed by `spl_object_id()`, whose parameters a writer has claimed: released from every name-keyed table, then bound to what the call passes, or to what a parameter the call passes nothing holds — except a *required* first parameter of a `when()`/`unless()` value closure, bound to the condition's `$this->prop` although that call throws ([below](#a-closure-parameter-owns-its-name)). `ClosureHandler` leaves a claimed closure's names alone. Captured and restored with the name-keyed tables, so a mark never outlives the writer that set it. |

**Snapshot/restore, not immutable copies.** `AnalysisScope` is one mutable object shared for the whole
`analyze()` call, not a value threaded through with `mergeWith()`-style copying. A writer that needs a
binding to hold only for one nested body — a closure, a loop, a spread — saves the field (or the one
key it is about to overwrite), mutates it, analyzes the body, then restores the snapshot, typically in
a `finally` so an exception path restores it too. This mirrors the analyzer's own pre-refactor save/
restore discipline and is why the field inventory above calls out scoping per field rather than once.

### Writing a scope binding

Most writers hand-roll this. Every writer that binds a closure parameter, and `ClosureHandler`, share
`AnalysisScope::nameBindings()` and `restoreNameBindings()`, and free the parameter names through `claimParameters()`
or `releaseUnclaimedParameters()`, for the reason [below](#a-closure-parameter-owns-its-name). The current set:

| Writer | Fields it scopes |
| --- | --- |
| `ResourceAstAnalyzer::analyzeThisMethodSpread()` | `localVarBindings`, `resolvingLocalVars`, `varModelBindings`, `varClassBindings`, `varGuardBindings`, `requestVarNames`, and the `visitedSpreadMethods` entry |
| `ClosureHandler::resolve()` | every name-keyed table, releasing the parameter names of a closure no writer claimed |
| `ConditionalMethodHandler` | `closureRelationModelClass` and every name-keyed table around a `whenLoaded` closure; every name-keyed table around a `when()`/`unless()` value closure, `transform()`'s callback, `resolveValueArgument()`'s value closure, and `resolveConditionalDefault()`'s default closure — claiming the closure's parameters at each, then binding them as the [corollary](#a-closure-parameter-owns-its-name) lists |
| `ResourceAstAnalyzer::resolveArrayOrClosureToProperties()` | every name-keyed table around a `merge()`, `mergeWhen()` or `mergeUnless()` closure, claiming its parameters and binding each to what it holds, since the call passes nothing |
| `TernaryHandler::narrowedArmResult()` | `varClassBindings` for a narrowed variable; `modelClass` + `forwardsUndeclaredMembersTo` for a narrowed `$this->resource` |
| `RelationCollectionChainHandler` | `closureRelationModelClass` around `pluck()`; that plus every name-keyed table around a `map()` closure, whose parameter it binds in `varModelBindings` |
| `CollectionPipelineHandler::resolveMapBody()` | every name-keyed table around a `collect(...)->map()` closure, whose parameter it binds in `varValueBindings` |
| `VariableHandler::analyzeVariableMapCall()` | `closureRelationModelClass` plus every name-keyed table around a `$var->map()` closure, whose parameter it binds in `varModelBindings` |
| `VariableHandler`, `ReceiverClassResolver::fromVariable()` | the `resolvingLocalVars` re-entrancy guard |

**The rule: every mutation must sit inside the `try` whose `finally` restores it.** Reviews during the
receiver phase caught the opposite shape twice — `ConditionalMethodHandler::analyzeWhen()` and
`::analyzeTransform()` bound a closure parameter, resolved, and restored on the **success path only**,
with no `try` at all — and it is worth stating why that is worse than it looks. The scope outlives the
expression being resolved, so a binding left in force by an escaping path raises nothing; it silently
answers some *later* property with a wrong-but-plausible type, arbitrarily far from the writer that
leaked it.

Every writer in the table above now restores through a `finally`, and every seeding step that can throw
sits inside its `try` — including `analyzeWhenLoaded()`'s relation lookup, which resolves and reflects.
What still sits between a snapshot and its `try` is plain assignment, plus one guard worth naming rather
than glossing: `TernaryHandler` narrows the forwarding target behind
`$scope->subjectReflection->isSubclassOf(JsonResource::class)`. That is a reflection call, not an
assignment, and `isSubclassOf()` *does* throw `ReflectionException` when its argument names a class that
cannot be loaded. It is safe there on a precondition rather than by its shape: `JsonResource` is an
ancestor of the very subject being reflected, so it is necessarily already loaded by the time the guard
runs. Read that as the exception that proves the line to hold — the moment seeding needs to resolve,
reflect, or call back into the engine on anything whose loading is not already guaranteed, it belongs
inside the `try`. Prefer restoring the whole map over unsetting the single key you believe you wrote.

#### A closure parameter owns its name

The readers rank the name-keyed tables differently. For a bare variable, `VariableHandler` reads
`varModelBindings`, then `varCollectionBindings`, then `varValueBindings`; `ReceiverClassResolver::fromVariable()`
reads `varClassBindings` first, then `varGuardBindings`, and never reads `varValueBindings`. So an outer binding of a closure parameter's name,
left in any table, outranks the parameter's own binding for some reader: nested in a `map(fn (Comment $c) => …)`, a
`collect(...)->map(fn ($c) => …)` would read its string element as a `Comment`, and so would
`$this->transform($this->title, fn ($c) => $c)`.

Every writer that binds a closure parameter therefore calls `AnalysisScope::claimParameters()` inside its `try`. It
drops every parameter name of the closure from every name-keyed table and marks the closure claimed; the writer then
seeds its own binding, and `restoreNameBindings()` puts every table and the mark back in the `finally`.
`ClosureHandler` calls `releaseUnclaimedParameters()`, which makes the same drop for a closure no writer claimed, such
as a `whenNotNull()` value closure or a closure written as a plain value, and leaves a claimed closure's bindings in
place. A conditional's default closure is claimed, by `ConditionalMethodHandler::resolveConditionalDefault()`.

**The corollary: a writer that claims a parameter binds it to what the call passes.** The claim frees the name, so
the writer reads the passed value first, before the claim can release a name the value shares, then binds the
parameter to it:

| Writer | What Laravel passes the closure | What the first parameter is bound to |
| --- | --- | --- |
| The three map writers | `($value, $key)` | the element; the key stays unbound |
| `whenLoaded()` | the loaded relation | its model, its collection, or its `morphTo` targets |
| `whenHas()`, `whenExistsLoaded()` | `$this->resource->{$attribute}`, the `{relation}_exists` flag | that property read |
| `whenCounted()` | the count | `number` |
| `whenAggregated()` | the aggregate | `number` for a `count`, which is always an integer; nothing for any other aggregate, whose type depends on its column, function and driver |
| `transform()`'s callback | the value | a `$this->prop` value's read; every binding a passed variable held before the claim; otherwise the value's resolved type, a nullable model read as the model, since the callback runs only for a filled value |
| `transform()`'s default | the value, only when it is blank | the value's full type, `null` arm included |
| `when()`, `unless()` | nothing: `value($value)` | a *required* one: the `$this->prop` its condition tests, if any, although the call throws (below); an optional or variadic one: nothing beyond what it holds (below) |
| `whenAppended()`, `merge()`, `mergeWhen()`, `mergeUnless()` and every other conditional default | nothing: `value($value)`, `value($default)` | nothing, beyond what an optional or variadic parameter holds (below) |

**`whenAggregated()` publishes its aggregate as `number`,** the package's convention, when it has no value closure or
the closure's result cannot be typed. A driver can return an aggregate that is not a count as a numeric or date
string, a MySQL `SUM()` for one, which that `number` does not describe; the package does not yet narrow the type by
driver and column cast.

**A parameter the call passes nothing holds its default,** and a variadic one holds an empty list:
`AnalysisScope::bindUnpassedParameters()` binds each parameter past the passed arguments to the type of the value its
default evaluates to, or to `never[]`. A default is a constant expression, so
`ValueResolver::evaluateConstantExpression()` evaluates it as PHP does. It reads a class constant or enum case through
reflection, a global constant as it is defined where the types are published, and an enum case's `->name` or
`->value`. It never reads a magic constant, whose value depends on where it appears: a default that is only a magic
constant binds `string`, or `number` for `__LINE__`, and one that needs the value, such as `[__FILE__ => 1]`, falls
back as below and binds nothing. `ValueResolver::resolveConstantValue()` then types the value as a class constant's
value is typed: a list literal is a list, `fn ($t = [1, 2])` holding `number[]`. An array whose keys all pass `is_numeric()` but which is not a list binds nothing: a resource's
`removeMissingValues()` re-indexes it into a list at any depth, so `['1.5' => 'x']` reaches JSON as `["x"]`.

A default the evaluator cannot read, and one whose evaluation errors, such as `1 / 0`, falls back in two steps:
- **A `new` of a class `json_encode()` writes as a string** types as that string, whatever name the package
  publishes the class under: `string` for a class whose `jsonSerialize()` declares `string`, `string | null` for one
  that declares `?string` or `string|null`, and `string` for a date that keeps Carbon's own `jsonSerialize()`, the ISO
  string. `timestamps_as_date` publishes a Carbon attribute as `Date`, but the value a default holds reaches JSON as
  that string. `DateTime`, and a date whose own `jsonSerialize()` does not declare `string`, are not one.
- **Anything else is the engine's to type,** with three exceptions that bind nothing: an array literal not keyed
  wholly by string literals that fail `is_numeric()`, which the engine would type as a record; a closure or
  first-class callable, which the engine types by what it returns though `json_encode()` writes a `Closure` as `{}`;
  and a variable, which no constant expression may read.

A default whose value cannot be typed leaves its parameter unbound. So `when($this->title, fn ($t = null) => $t)`
publishes `null`, which is what Laravel returns. Every writer that claims a conditional or merge closure calls
`bindUnpassedParameters()`. Two kinds of closure do not get it: a `whenNotNull()`/`whenNull()` value closure, which no
writer claims, and a map closure, whose writers bind only its first parameter, so a parameter past `$key` stays unbound
although `map()` passes it nothing.

**A variadic parameter collects its arguments into a list.** Where the call passes one argument, the writer binds a
variadic first parameter to that argument's type as a list: `whenLoaded('author', fn (...$a) => $a)` is `User[]`, and
`whenHas('title', fn (...$t) => $t)` is `string[]`. There are two exceptions:
- **The three map writers.** `map()` passes `($value, $key)`, two values of different types, so they skip a variadic
  first parameter and analyze no body for it.
- **A `morphTo` `whenLoaded()`.** Its variadic parameter binds nothing, so the key stays `unknown`. That admits the
  `null` `whenLoaded()` returns, before it calls the closure, for a relation loaded as null. No `whenLoaded()` value
  closure's key publishes that `null` itself: a to-one relation's variadic `User[]` omits it too.

**One writer still binds what the call does not pass.** `when()` and `unless()` bind a *required* first parameter
to the condition's `$this->prop`, although Laravel calls their value closure with no argument. That call throws
`ArgumentCountError`, so no successful response carries the key; the binding predates this rule and the workbench
pins it (`ConditionalParamPrimitiveResource`, `ConditionalParamEnumResource`), so it stays. An optional or variadic
parameter there holds what the rules above say.

**Known exceptions.** None remain among the conditional writers. `ResourceAstAnalyzer::resolveArrayOrClosureToProperties()`
reads a `merge()`, `mergeWhen()` or `mergeUnless()` closure's return arrays directly rather than through
`ClosureHandler`, and `VariableHandler::analyzeVariableMapCall()` reads its closure's body directly too, but both
claim the closure first.

**Which tests pin each path.** Removing `ClosureHandler`'s release fails `ClosureHandlerTest`'s
`suppresses a closure param that shadows a populated localVarBindings entry, then restores it` and
`releases an unclaimed parameter from every name-keyed table, then restores every table`. Restoring only the two
tables the handler once wrote fails the second of those. Removing a writer's claim fails that writer's own cases:
`when()`'s fails `ConditionalParamEnumResource`'s `when() param` tests and the `when() on a property` case;
`resolveValueArgument()`'s fails `ConditionalMethodHandlerTest`'s `binds a value closure parameter to the attribute
whenHas() and whenExistsLoaded() pass`; `resolveConditionalDefault()`'s fails its `… default, optional null` and
`transform() default, passed the blank value` cases; and a merge closure's fails `ResourceAstAnalyzerTest`'s
`a merge closure parameter owns its name and holds its default`. Dropping the `finally` restore after a default
closure fails `restores the outer binding after a conditional default binds its parameter`, and after a merge closure
that same merge test, whose `title_after` reads the outer local again.

### How `varModelBindings` gets populated, and how scoping holds

`varModelBindings` is populated from six sources. Four bind a closure parameter for that closure's body:

- **`whenLoaded('relation', fn ($x) => ...)`** (`ConditionalMethodHandler::analyzeWhenLoaded()`) —
  when `relation` resolves to a *single*-model relation, `$x` is bound to that model for the closure
  body. A to-many relation's closure param is deliberately **not** bound this way: the param holds the
  whole collection, not one element, so binding it to the element model would resolve a bare `$x` to a
  wrong-but-plausible singular type (e.g. `OrderItem` instead of `OrderItem[]`). Neither `$x->pluck(...)`
  nor `$x->map(...)` needs that binding: the first resolves through `AnalysisScope::$closureRelationModelClass`,
  and the second binds its own parameter to its type hint's model or, untyped, to the element model the
  `varCollectionBindings` entry names (the variable-receiver `map()` below).
- **A relation-chain `map()`** (`$this->{manyRelation}->take(5)->map(fn ($m) => ...)`, handled in
  `RelationCollectionChainHandler`) — `$m` is bound to the relation's element model for the map
  closure's body.
- **A variable-receiver `map()`** (`$rows->map(fn (Comment $c) => ...)`, handled in
  `VariableHandler::analyzeVariableMapCall()`) — `$c` is bound to its type hint's model or, untyped, to the
  element model of the receiver's to-many `whenLoaded` binding. `ReceiverClassResolver::fromVariable()` reads
  `varModelBindings`, never `closureRelationModelClass`, so without this entry a chain such as `$c->user?->name`
  would find no receiver. `PostCommentAuthorsResource` pins it.
- **A `transform()` callback or default** (`ConditionalMethodHandler::bindPassedValue()`) — a callback's `$v` is
  bound to the model a model-typed value names, a nullable read included, since the callback runs only for a filled
  value. A variable passed to either binds `$v` to every entry that variable held, through
  `AnalysisScope::copyBindings()`, a `varModelBindings` entry among them.

The other two bind a name for the rest of the method:

- **A top-level `foreach ($this->{manyRelation} as $item) { ... }`** (`ResourceAstAnalyzer::
  bindForeachLoopVariables()`) — `$item` is bound to the relation's element model for the rest of the
  method's analysis (mirrors `localVarBindings`' method-wide scope, restored around a
  `...$this->method()` spread the same way).
- **A located method's `Model`-typed parameter** (`AstEngine::bindingsFor()`) — seeded before the analysis starts,
  bound to the parameter's declared type.

The four closure writers follow the save/restore discipline described above: each captures every name-keyed table with
`nameBindings()` before its `try`, claims the closure and binds inside it, and restores the capture in its `finally`.
The `foreach` writer does not — `bindForeachLoopVariables()` assigns `varModelBindings[$stmt->valueVar->name]`
outright, with no snapshot and no restore, because its binding is method-wide by design. The shadowing guarantee
survives that exception: a closure parameter that shadows an outer variable of the same name still resolves against
its **own** binding and can never leak into, or be leaked into by, the outer scope, because it is the closure writers'
own snapshots that restore over whatever the `foreach` binding left behind. `ClosureParamShadowResource` in the
workbench pins this: a top-level `$member` and a `map(fn ($member) => $member)` closure param share a name, and each
site resolves independently.

### `localVarBindings` and closure descent

A closure parameter that shares a name with an outer local must not resolve through that local. Two mechanisms
see to it, both described in [A closure parameter owns its name](#a-closure-parameter-owns-its-name):

- **A writer's claim.** Every writer that binds a closure parameter claims the closure first, which drops the
  parameter's name from every name-keyed table, `localVarBindings` included. `when()` is such a writer, so a
  `when()` closure is protected by `ConditionalMethodHandler::analyzeWhen()`'s claim whether or not its condition
  is a `$this->prop` test.
- **`ClosureHandler`'s release.** `ClosureHandler::resolve()`, the generic closure and arrow-function handler,
  saves every name-keyed table, releases the parameter names of a closure no writer claimed, analyzes the body,
  and restores every table in a `finally`. A `whenNotNull()` value closure, or a closure written as a plain value,
  is protected this way.

`ShadowedClosureParamResource` in the workbench checks the end result: after `$slug = $this->slug;`, a `when()`
closure whose parameter is also `$slug`, under a condition that is not a `$this->prop` test, must resolve to
`unknown` rather than to the outer `$slug`'s type. Either mechanism alone keeps it green, so it pins neither; the
tests that do are listed at the end of that section.

### Narrowing

`CollectsInstanceofGuards` (`src/Ast/Concerns/`) and `TernaryHandler` together answer which class a
variable holds *at one point in a body*. The guard pass writes `AnalysisScope::$varGuardBindings`, and
`TernaryHandler` writes `$varClassBindings`. Three rules, each restored in a `finally`:

1. **An early-exit guard.** A top-level `if` with no `elseif` and no `else`, whose body's last statement
   is a `return` or a `throw`, and whose condition is `! $x instanceof C` — or an `||` chain containing
   one — binds `$x` to `C` for the reads after it: the binding records the file offset the `if` ends at, and
   `ReceiverClassResolver::fromVariable()` applies it only to a read that starts past that offset.
   `NarrowedParentResource` is the fixture: after
   `if (! $parent || ! $parent instanceof Post) { return null; }`, `$parent->title` types as `string`
   even though `attachable` is a `morphTo` holding a union.
2. **A ternary's true arm.** `$x instanceof C ? A : B` binds `$x` to `C` while `A` resolves, and only `A`.
3. **A ternary on `$this->resource`.** `$this->resource instanceof C ? A : B`, with `C` a `Model`, sets
   `$scope->modelClass = C` while `A` resolves, so every `$this->prop` read in that arm resolves against
   the narrowed model. `TeamSubscriberResource` pins it: `$this->resource->subscriber` is a relation only
   the `SubscribedTeam` subclass declares.

One more rule writes no binding. `ReceiverClassResolver::testedClasses()` reads a ternary's `instanceof`
test, or `||` chain of tests, when every operand tests the true arm's own read path. `narrowed()` then checks
each class the arm resolves to against those tests, keeping it, replacing it with the tested subclasses, or
dropping it, and never widening it. So a receiver read later through a variable bound to that ternary is
narrowed too, which rule 2 cannot do. Rule 1 and this rule read the `||` chain through the same
`ReadsInstanceofChains` grammar. See
[Receiver types § A ternary's `instanceof` condition](receiver-types.md#a-ternarys-instanceof-condition).

**No write after the guard.** Rule 1 binds `$x` only when no write to it can land once the guard has tested
it. Every write `CollectsLocalVarBindings::collectVariableWrites()` finds must end before the tested operand
starts, or sit in the guard's own body, which always exits. A write before the guard does not matter, since the
guard tests the value it leaves, so a parameter nothing writes and a variable reassigned only before the guard both
bind. A write after the guard, or later in its condition, leaves `$x` unnarrowed rather than typed as a class it
may no longer hold. `GuardWritePostResource` (a test fixture) pins the three cases. The pass does not see a write
through a by-reference argument, or through a reference taken before the guard (`$r = &$x;`, a closure's
`use (&$x)`).

**A guard narrows by position.** The walk is flat and holds one binding per method, so the binding is in
force while the whole body is analyzed: the guard's *own* body, the branch that proves `$x` is **not** a `C`,
and any `return` placed before the guard. Both are live to this engine: `ClosureHandler` unions every return,
and `ResourceAstAnalyzer::analyzeThisMethodSpread()` reads every branch when it can classify them all. The
recorded offset keeps those reads unnarrowed, since each starts before the guard's `if` ends.
`NarrowingGuardBodyResource` (a test fixture, not a workbench one) pins the guard's own body: `dirty_label`,
read there, drops as an untypable branch instead of narrowing, while a sibling guard that exits by `throw`
still narrows what follows. `GuardOrderResource` pins a `return` before the guard, in a method body and in a
closure body.

**A positive `if ($x instanceof C) { … }` body is not narrowed.** Its returns are analyzed without any
per-branch scope, so a binding made for that body would still be in force for the statements *after* it,
where `$x` is exactly what the guard excluded. Only the early-exit shape, whose narrowing genuinely holds
for everything that follows, is safe to bind from a flat walk.

**Closure bodies bind their own locals.** `ClosureHandler` runs `collectLocalVarBindings()` and
`collectInstanceofGuards()` over a `Closure`'s statements, so a body-local resolves inside the closure the
way a top-level local does in `toArray()`. Every name the body writes is unset from the outer
`localVarBindings` first, so a body-local shadows an outer one of the same name — including one written
twice, which binds nothing and must not fall through to the outer binding instead. An `ArrowFunction` has
a single expression and no statement list, so only the parameter suppression above applies to it.

### What deliberately stays unbound

- **A reassigned local** (written more than once in the method) — `localVarBindings` already skips
  these; `varModelBindings` has no reassignment analog since it only ever binds closure params and
  loop variables, each written exactly once by construction. An early-exit guard still narrows one
  reassigned only before it, as § Narrowing says.
- **First-class callables** (`->map(...)`, `->pluck(...)`) — there is no closure body to bind a
  param into, so these are rejected before any binding is attempted.
- **A relation-chain `map()` whose argument isn't a `Closure`/`ArrowFunction`** (a string callable
  like `'strtoupper'`, or an array callable like `[$this, 'method']`) — same reasoning.

See [ResourceAstAnalyzer § Multi-model accessor unions](resource-ast-analyzer.md#multi-model-accessor-unions-reference-each-arms-own-model)
and the fixtures named above for how these bindings surface in emitted output.

## Subject mode

Subject mode is how `$this->prop` resolves when `AnalysisScope::$modelClass` is `null` — a class the
engine is pointed at that has no backing Eloquent model, which is every non-resource subject
`AstEngine::analyzeMethod()` accepts (a broadcast event, a DTO, a plain class). Both arms below live
strictly inside that `null` branch.

A model-backed subject now reaches the same resolver through a different door.
`SubjectPropertyTypeResolver::declaresOwnProperty()` asks whether the subject declares the property
itself, and when it does, that declaration answers `$this->prop` **before** the model's attributes and
relations are consulted. This matches what runs: PHP reads a declared property before
`JsonResource::__get()` ever forwards to the model, so a resource carrying its own `$stats` publishes
that value object, not a same-named model attribute. A result naming an abstract or `Illuminate\`
model still declines, through `ValueResult::namesOnlyPublishedModels()`, rather than emitting a token
nothing imports.

A name the framework declares is never the subject's own, however the subject redeclares it:
`resource`, `with` and `additional` on `JsonResource`, `collects` and `collection` on
`ResourceCollection`, every `Model` property, and any static property. Excluding them is what keeps
`$this->resource` meaning the backing model. `preserveKeys` is deliberately *not* in that set —
Laravel reads it with `property_exists()` rather than declaring it, so it belongs to the subject.

Resolution order for the property itself is **`@var` docblock first, native declared type second,
untyped default literal third** — `PropertyDocblockTypeReader::read()`, then
`LaravelTsPublish::propertyTypes()` accepted through `ReflectedTypeAcceptor`, so a token that has no
importable published file rejects the whole result rather than shipping a name nothing imports, and
last the literal an untyped property defaults to. That final rule types `protected $extensions =
['png', 'jpg']` as `string[]` and `protected $limit = 10` as `number`, while a mixed list, a non-list
array, or a `null` default yields nothing — which is why an untyped property with no explicit default
still resolves to nothing at all. The default types the property only while no method the class runs writes
it: `SubjectPropertyTypeResolver` reads the class, each ancestor and every trait each uses, transitively, for a
write of `$this->prop` or an element of it (an assignment, compound assignment, reference, `++`/`--`,
`foreach` target, by-reference `foreach` over it, `unset()` or destructuring) and, in application code, for
any computed-name write such as `$this->{$name} = …`, since Laravel's own computed writes name columns. It does
not see a write a subclass makes, since it reads only the class and what the class inherits, or a write through a
by-reference argument such as `array_push($this->tags, 5)`: either leaves the default's type standing for a value
the property may no longer hold. An internal class has no file and is skipped, since its code cannot name a
property its user-land subclass declares; a parsed file that does not declare the class counts as writing. A
default naming a constant this process cannot load declines rather than aborting the run.
`SubjectPropertyTypeResolver` is the one home for all three; `AstEngine::analyzePublicProperties()` and both
handler arms call it.

There are two arms because the dispatcher never hands the inner node of a chain to a handler:

- **Leaf** (`ThisPropertyHandler`): `$this->teamId` — after the model attribute and relation lookups
  both miss (they always do with no model), the subject's own property supplies the type and its FQCN
  channels.
- **Chain root** (`PropertyChainHandler::analyzePropertyChain()`): `$this->post->title` arrives as one
  `PropertyFetch` whose outermost node is `title`, so `ThisPropertyHandler`'s resolution of the inner
  `$this->post` is never consulted. The chain handler resolves its own first segment the same way, and
  **only a `Model` subclass hands off**: that model becomes the walk's starting point and the existing
  relation/attribute traversal runs unchanged over the remaining steps. Any other type declines, and
  the expression degrades to `unknown` exactly as before. On a *model-backed* subject the same arm
  declines outright when `declaresOwnProperty()` claims the chain's root, because the walk would
  otherwise read the model: declining hands the chain to `ReceiverPropertyFetchHandler`, which types it
  from that property's own class, so `$this->stats?->views` follows `PostStats`, not the model.

[Receiver types](receiver-types.md) documents `ReceiverClassResolver`, which names the PHP class an expression holds.

## Dependency recording policy

Every analyzer file-read flows through `AstParser`, directly or via `MethodLocator`. Skipping it means
the cache serves stale output when the underlying file changes — a live staleness bug, not a
theoretical one: the controller paginator analysis parsed controller files without recording a single
dependency until it was moved onto `AstParser`.

`AstParser::parseFile(string $path): array<Node>` calls `DependencyRecorder::record($path)`
unconditionally, before it ever checks the cache — so a cache *hit* still records the dependency, not
only a cache miss. The parsed AST is cached per path in `$fileAsts`, name-resolved (`NameResolver`
runs over every file), bounded at `MAX_CACHED_FILES = 128` with FIFO eviction (`array_shift()`) once
that cap is hit — spread analysis re-reads the same file many times per run, and the cap keeps a large
app's file set from ballooning memory. `AstParser::parseSource(string $source): array<Node>` parses raw
PHP text with no caching and no dependency recording; prefer `parseFile()` for anything on disk.

`MethodLocator` finds one method's `ClassMethod` AST node, and its two entry points differ in exactly
what "found" means:

| Method | Searches | Name match | Miss means |
| --- | --- | --- | --- |
| `locateOwn(class, method)` | the class's **own** file only | exact (case-sensitive) | the method is inherited, not declared here — the deliberate signal callers use to detect delegation |
| `locate(class, method)` | wherever the method is **declared** (class, trait, or parent) | case-insensitive, mirroring PHP's own dispatch | the method genuinely doesn't exist anywhere in the hierarchy |

Both memoize hits *and* misses — `memo()` checks `array_key_exists()`, not a truthy/falsy test, so a
`null` result is cached exactly like a real one and a repeated lookup never re-parses. Both resolve
their target file and hand it to `AstParser::parseFile()`, which is where the actual dependency
recording happens; neither method records anything itself.

The body fallback records dependencies for free, for the same reason. When a reflected return type is too
vague to publish, `MethodReturnTypeResolver::resolve()` re-enters `AstEngine::analyzeMethod()` for that class
and method, which reads the method's file through `MethodLocator` and therefore `AstParser` — so a resource
whose type came from a helper's *body* is invalidated when that helper changes, not only when the resource
does.

### The run memo replays what it recorded

`AnalysisMemo`, a container singleton, holds the engine's cycle guards and a memo of analyses. A run starts by
calling its `forget()`, which keeps the pinned outermost answers, so those outlive a run in the same process. Four
analyses are memoized in it: `AstEngine::analyzeMethod()` per `analysis:class@method@model`,
`MethodReturnTypeResolver::resolve()` per `method-return:class@method`, `AccessorBodyAnalyzer::analyze()` per
`accessor-body:model@attribute`, and `ModelAttributeResolver`'s accessor waterfall per
`accessor-type:model@attribute`. Every key but `method-return:`, whose answer does not depend on the import mode,
gains an `@importless` suffix for an analysis that carries no imports. So a helper that many keys read is analyzed
once per run.

An answer stores the dependency paths `DependencyRecorder` recorded while it was computed, and every reuse
records them again, so the generation cache sees what a fresh computation would have shown it. An unpinned
answer is reused only where computing it again could not differ: no cycle guard it passed through is on the
stack, no analysis it read has been pinned to a fresh answer since, the published resource set is unchanged,
and it was computed while dependencies were recorded if they are being recorded now. `analyzeMethod()`'s
outermost call in its chain is pinned instead: stored even when a cycle cut it short, and reused whatever is on
the stack. A pinned answer computed while dependencies were not recorded has none to replay. `Runner::run()`,
`RunnerForSource::run()` and `ModelAttributeResolver::buildMorphTargetMap()` call `AnalysisMemo::forget()`,
which drops every unpinned answer.

## MethodAnalysis

`MethodAnalysis` (`src/Ast/MethodAnalysis.php`) is the unified analysis DTO — the generalized
`ResourceAnalysis`, which now `extends MethodAnalysis {}` with an empty body. Its thirteen constructor
properties are the whole surface a `toArray()`-style method analysis carries:

| Field | Shape | Carries |
| --- | --- | --- |
| `properties` | `list<{name, type, optional, description}>` | The property list itself. |
| `enumResources` | `array<string, class-string>` | Property name => enum FQCN, via `EnumResource::make()`. |
| `nestedResources` | `array<string, class-string>` | Property name => resource FQCN. |
| `customImports` | `TypesImportMap` | Import path => list of type names, from `#[TsType(import:)]`-annotated classes. |
| `directEnumFqcns` | `array<string, class-string>` | Property name => FQCN for direct access; FQCN => FQCN for embedded enums. |
| `modelFqcns` | `array<string, class-string>` | Property name => model FQCN, from a bare `whenLoaded`. |
| `inlineEnumFqcns` | `array<string, list<class-string>>` | Property name => enum FQCNs embedded in an inline object type string. |
| `inlineModelFqcns` | `array<string, list<class-string>>` | Property name => model FQCNs embedded in an inline object type string. |
| `multiEnumResourceFqcns` | `array<string, list<class-string>>` | Property name => ordered enum FQCNs, for a multi-`EnumResource` ternary/union branch (feeds the `AsEnum` rewrite). |
| `inlineEnumResourceFqcns` | `array<string, list<class-string>>` | Property name => enum FQCNs embedded via `EnumResource` inside an inline object type string (value-import channel). |
| `enumResourceArmShapes` | `array<string, {wrapIsCollection, directIsArray}>` | Property name => each arm's own array shape, for a mixed `EnumResource`/direct-access ternary whose merged type string already collapsed which arm was the collection. |
| `flatTypeAlias` | `string\|null` | When set, the collection emits `export type X = SingularResource[]` instead of an interface. |
| `flatTypeAliasFqcn` | `class-string<JsonResource>\|null` | FQCN of the singular resource for the flat type alias. |

### `addProperty()` is the only way a value becomes a property

`addProperty(string $name, array $result, bool $optional = false, string $description = '')` takes a
handler's `ValueExpressionResult` and turns it into one property row plus every FQCN channel that
result carries — the single-value maps and `enumResourceArmShapes` through
`DispatchesFqcnResults::dispatchFqcnResults()`, then the three inline queues and `customImports`
directly.

**Why one entry point rather than each collector doing it.** The collectors used to assemble the DTO
by hand, at eight sites across `ResourceAstAnalyzer`, `ResolvesModelTypes`, `ThisPropertyHandler` and
`AstEngine`. Adding a channel meant finding and updating every one, and the failure mode when one was
missed is the expensive kind: nothing throws, the property still gets a plausible type, and only the
generated TypeScript says the import or the alias went missing. Routing every collector through
`addProperty()` means a channel added to `ValueExpressionResult` reaches all of them at once.

Widening `analyzePublicProperties()` onto it is the one place behaviour changed. It dispatched the
single-value channels and queued no inline FQCNs at all, so a broadcast event property whose `@var`
unions two same-basename models rendered `User | User` against imports already aliased apart.
`SameBasenameModelEvent::$actor` now emits `AppUser | CrmUser`.

**Why the three inline queues append instead of assigning or deduping.** `inlineEnumFqcns`,
`inlineModelFqcns` and `inlineEnumResourceFqcns` reach
`TsTypeString::aliasPropertyType()` as positional queues, walked against the type-name tokens in
the rendered type string. A property whose inline object names the same FQCN twice needs two entries
or the second token draws the first token's alias, so a repeat has to survive as a repeat.

### Merge rules

`merge(self $source)` folds another analysis into this one, field by field, and each field's merge
rule differs on purpose:

- `properties` **appends**.
- `enumResources`, `nestedResources`, `directEnumFqcns`, `modelFqcns`, `multiEnumResourceFqcns` and
  `enumResourceArmShapes` are single-value maps: spread-merged, source wins on a colliding key.
- `customImports` merges per import path, concatenating each path's type-name list.
- `inlineEnumFqcns`, `inlineModelFqcns` and `inlineEnumResourceFqcns` concatenate per property key
  **without** deduping, for the positional-queue reason above. `merge()`'s own docblock states the
  invariant directly.

`mergeReturnBranches()` stays on `ResourceAstAnalyzer` rather than moving onto `MethodAnalysis`: it
needs a per-branch `propertyMap` to union-type a property name several branches set, which a
field-by-field merge cannot express. It now unions only that `propertyMap` itself and accumulates
every channel by calling `merge()` on a scratch analysis, so the two can no longer drift apart. It
still resolves the two `flatTypeAlias*` scalars `merge()` never touches (first non-null branch wins).
See
[ResourceAstAnalyzer § `mergeReturnBranches()` carries every `MethodAnalysis::merge()` channel](resource-ast-analyzer.md#mergereturnbranches-carries-every-methodanalysismerge-channel-plus-two-flat-scalars)
for the corpus evidence behind the per-occurrence rule.

## Dropped union arms

A union arm the engine cannot type is **left out of the union**, never widened to `unknown`. So
`$cond ? <untypable> : null` publishes `null`, and so does `$this->opaque() ?: null`.

That is deliberate and it stays. `unknown` would be more honest but strictly less specific, and no change
may make a published type less specific. A dropped arm is a gap to *close* — give the arm a return type, a
`@return` docblock, or `#[TsCasts]` — not a reason to widen the type around it. The engine did not start
publishing `unknown` where it used to publish `null`; what it gained is a record of what it dropped.

`ValueResult::unionResults()` is where the arm is skipped, but it receives already-resolved results and so
cannot name the expression it dropped. `ValueResult::analyzeClosureUnion()` therefore pairs each `Expr`
with its own result before delegating, and records there. Two callers reach `unionResults()` directly and
record their own drops: `TernaryHandler`'s `instanceof`-narrowed arm, and `KnownFunctionCallHandler`'s
`data_get()` default. `CoalesceHandler` records too: it deliberately does not delegate to
`analyzeClosureUnion()` — that would leave `null` in the union twice — and computes its own member list, so
it records whichever operand of `??` it drops. Each entry names the site that recorded it, so a site going
silent is visible rather than merely absent.

`DroppedUnionArms` is the recorder — `start()`, `stop()`, `record()`, `dropped()`, `replay()`. Recording is off
until a test calls `start()`, so a publish run pays one count and one null check per dropped arm; `stop()` returns
each distinct `{subject, line, expression}` once. The count is always kept: `AccessorBodyAnalyzer::analyze()` reads
`dropped()` before and after a getter body, and declines a body left as only `null` when an arm was dropped in
between, so an idiom such as `$attributes['title'] ?? null` publishes `unknown` rather than `null`. When
`AnalysisMemo` reuses an unpinned answer it adds the arms that answer dropped through `replay()`, so the getter
sees the count a fresh analysis would have left. The `start()` audit list is not replayed; a reuse repeats only
arms its first computation already recorded.

`tests/Unit/Ast/DroppedUnionArmsAuditTest.php` runs every non-abstract resource in the workbench corpus
through `analyzeMethod()` and fails on any arm missing from
`tests/Unit/Ast/Fixtures/dropped-union-arms-baseline.php` — and on any baseline entry the corpus no longer
drops. **The baseline may only shrink:** teach a rule to type a shape, then delete its entries. Every entry
carries a comment naming why it is pinned: most are fixtures whose arm is deliberately untypable, and one
is an incidental drop whose property the surviving `??` operand still types. `UnionHonestyResource` carries
one key per recording site, so the audit also proves each site still fires.

**A recorded arm is a candidate gap, not a proven published loss.** `ClosureHandler` and `VariableHandler`
both discard an `unknown` union result and fall back to a return-type annotation or to `null`, so an entry
can name an arm whose property is ultimately typed correctly by another rule. Read the published property
before treating an entry as a bug.

## Public API

```php
AstEngine::analyze(string $class, string $method = 'toArray', ?string $modelClass = null, string $fromNamespacePath = ''): AnalysisResult
```

That signature and the `AnalysisResult` it returns are the engine's whole public surface. Everything
else here — `analyzeMethod()`, `analyzePublicProperties()`, `bindingsFor()`, `AnalysisImports`,
`AnalysisComposer`, every handler and DTO — is `@internal`, checked by
`tests/Architecture/InternalBoundaryTest.php`; the three rules it enforces are in
[known gaps](../known-gaps.md). The rest of this section documents those internals for people working
*on* the engine, not for consumers of it.

The `@internal` tag on `analyzeModelClosure()` and `bindingsFor()` is load-bearing, not decorative:
both name `MethodContext` — an `@internal` class — in a public method of the un-tagged `AstEngine`,
which only clears the boundary test's "never names an internal type in a public signature" rule
because that rule skips a method carrying its own `@internal` tag. Removing either tag as cleanup
would silently widen the engine's public API without the test ever failing to say so.

`analyze()` runs `analyzeMethod()` for the raw DTO and hands it to `AnalysisComposer`, which is what
makes the three fields agree with each other:

1. **Index the properties by name.** `MethodAnalysis::$properties` is an append-only list, so a model
   spread that repeats a key it already carries appears twice; rendered as-is those are duplicate
   interface members. `ApiPostResource` measures 30 raw properties against 27 composed ones.
2. **Resolve name collisions and rewrite the types.** Two `ImportNameRegistry` instances — one for type
   names, a sibling for enum const names — over the enum, resource and model maps, then
   `aliasPropertyType()` per property against its positional FQCN queue. This is the half
   `AnalysisImports` deliberately leaves out; without it `ImageDelegatedResource` returns
   `reviewable: User | User | null` beside two `User` imports.
3. **Rewrite the `EnumResource` wraps** to `AsEnum<typeof Const>` — the same three cases
   `ResourceTransformer::rewriteEnumResourceTypes()` handles: the plain substitution, the mixed
   ternary whose arms are synthesized from `enumResourceArmShapes`, and the multi-enum ternary
   replaced branch by branch. Gated on `ts-publish.enums.use_tolki_package`; with it off the bare
   enum type name is already the right answer and its type import survives.
4. **Import every name the rewritten types spell as a token.** `TsTypeString::typeNameOccursIn()` is a plain
   token match: a name counts wherever its token stands, inside a string, a template literal or a comment as
   much as outside, so a name only a literal spells keeps its import unused rather than a hidden one dropping
   an import the file needs (the accepted cost, in
   [Receiver types](./receiver-types.md#the-body-fallback-carries-no-fqcn-channel)). One rule replaces
   two special cases: `AnalysisImports::asEnumWrappedOnlyFqcns()`'s wrapped-only GC, and
   `ResourceTransformer::pruneOverriddenEnumImports()`'s override GC. An enum the wrap replaced and
   an enum a `#[TsCasts]` override displaced are both simply unspelled, so neither is imported. The
   transformer runs the same predicate over the model and `#[TsType]` imports its analysis carried, in
   `ResourceTransformer::pruneOverriddenAnalysisImports()`, with two differences. It tests each model's
   class basename before `resolveImportConflicts()` aliases it, where `pruneUnspelledImports()` tests each
   import's local name against the aliased types, so of two models sharing a basename neither is dropped
   while either is spelled, and one can stay imported unused under its alias. Both transformer prunes also
   search the resource's extends clauses, which an analysis never has.

`$fromNamespacePath` is the generated file's own namespace path, so relative import paths resolve from
where the file will live; `''` means the output root.

`tests/Feature/AnalyzeApiProbeTest.php` renders the result of eight of these analyses into real `.ts`
modules under `workbench/resources/js/types/data/testing/analysis-probe/`, so the
[unimportable-token gate](../testing/type-inference-gates.md) type-checks them with `tsc`. Before the
composer those eight files produced 11 diagnostics — TS2304, TS6133/TS6192, TS2300 and TS2344.

`AnalysisResult` carries only those three fields, so a `$wrap = null` collection — whose entire answer
lives in `MethodAnalysis`'s `flatTypeAlias`/`flatTypeAliasFqcn`, a channel neither `AnalysisImports`
nor `AnalysisComposer` reads — comes back with all three empty, losing even the singular resource's
type import. `PostFlatCollection` measures as `properties: []`, `typeImports: []`, `valueImports: []`
against a `flatTypeAlias` of `PostResource[]`. There is no public answer for that shape.

Two more shapes `analyze()` does not answer, both recorded in the README's own capability list: a
`morphTo` union needs the morph target map `BaseRunner::run()` builds by scanning every model class,
so outside a publish run `resolveModelRelationTypeInfo()` types it `unknown` and the property is
dropped altogether — `ImageDelegatedResource::imageable` is exactly that. And a form request's
published interface comes from `FormRequestRulesAnalyzer` calling `rules()` at runtime, not from the
engine, so `analyze($request, 'rules')` types the rules array itself: one
`Record<string, unknown>` per rule key, dotted paths and all.

```php
AstEngine::analyzeMethod(string $class, string $method = 'toArray', ?string $modelClass = null): MethodAnalysis
AstEngine::analyzePublicProperties(string $class): MethodAnalysis
```

The other boundary is deliberate: in-package consumers that rewrite a `MethodAnalysis`'s FQCN channels
before importing must build from the mutated DTO, so they keep calling `analyzeMethod()` and
`AnalysisImports::build()` themselves rather than `analyze()`. There are three:

- `InertiaSharedDataAnalyzer::buildInferredImports()` filters against an analysis it has already run
  `forgetOverriddenChannels()` over.
- `ModelMetadataAnalyzer` prunes the same channels inline before building.
- `BroadcastEventTransformer::transformProperties()` unsets eight channels for each `#[TsCasts]`
  override, needs the `MethodAnalysis` object itself for `resolveProperties()`, and reaches
  `analyzePublicProperties()` instead when the event has no `broadcastWith()`.

`analyzeMethod()` analyzes one method body's return shape. `$method` defaults to `'toArray'`, the
resource case, but any class/method pair works identically. When
`$modelClass` is omitted and `$class` is a `JsonResource` subclass, it resolves the backing model via
`ModelClassResolver::resolve()` (the same precedence `ResourceTransformer` uses, so the two never
disagree about which model a resource wraps) before constructing
`new ResourceAstAnalyzer($reflection, $modelClass, $method)` and calling `analyze()`.

`analyzePublicProperties()` analyzes a class's public properties instead of a method body — promoted
constructor parameters *and* class-body declarations, `@var` docblock first, native reflected type
second. It skips any property a used trait declares (transitively), so a `#[TsExtends]` trait's own
fields aren't emitted twice by the class that uses it. A property that is neither promoted nor
defaulted is marked `optional`, because `json_encode()` omits it when it was never assigned;
nullability stays separate, expressed as `| null` in the type. Reflection cannot see a constructor
assignment, so a property a hand-written constructor always assigns still renders `?:` —
`DeclaredPropsEvent::$label` is exactly that case.

`ReturnLiteralReader::stringLiteral(string $class, string $method): ?string` returns the one string
literal a method returns, and `null` for anything else — several returns, no return, or an expression
that merely *starts* with a literal. `'order.'.$this->kind` reads `null`, not `"order."` — folding a
concatenation to its prefix and shipping that as a broadcast name is worse than emitting no key at all,
because with no key the caller falls back to a convention it controls. The return count stops at
every nested `FunctionLike`, so a closure's own `return` neither counts toward the total nor stands in
for the method's — a plain `NodeFinder` sweep has no such boundary and over-rejects on it.

`AnalysisImports::build(MethodAnalysis $analysis, string $fromNamespacePath): array{typeImports:
TypesImportMap, valueImports: TypesImportMap}` turns a `MethodAnalysis`'s FQCN channels into resolved
import maps for one generated file. What it does:

- **Merges** colliding import paths. Two of its three FQCN maps (enum, resource, model) can resolve to
  the same path — an app that keeps enums, models, and resources in one namespace — and `build()`
  folds their name lists together rather than letting the second map's names replace the first's. This
  is a deliberate divergence from `ResourceTransformer::buildResolvedImports()`, which spreads the
  three maps together and so silently drops a whole path's names when two of them collide.
- **Prunes** the type import for an enum reachable only through an `EnumResource`/`AsEnum` wrap (gated
  on `ts-publish.enums.use_tolki_package`) — mirroring `ResourceTransformer::rewriteEnumResourceTypes()`'s
  own import garbage collection, so a wrapped-only enum never emits a dead `import type` that trips a
  consumer's `noUnusedLocals`.

What it does **not** do: alias-conflict resolution. Every name it emits is the plain type/const name,
unaliased — a caller whose file can emit two same-named tokens (the same-basename-across-namespaces
case `ImportNameRegistry` exists for) runs `Support\ImportNameRegistry` over the result itself; that
collision handling is deliberately kept out of `AnalysisImports`, which only resolves *what* to import,
not what to *call* it once two imports collide. `AnalysisComposer` is the one caller that does run
`ImportNameRegistry` over the result, which is why `analyze()` needs it and the three channel-rewriting
consumers above do not. See [ImportNameRegistry](import-name-registry.md) for that half, and the
[Analyzer API](https://tolki.abe.dev/ts/analyzer-api.html) page for the user-facing walkthrough of
calling `AstEngine::analyze()`.

## Consumers

`BroadcastEventTransformer` is the first feature on this API, and shows the intended shape of a
consumer: `analyzeMethod($fqcn, 'broadcastWith')` when the event has one — `hasMethod()`, so an
inherited or trait-supplied `broadcastWith()` counts, matching what Laravel itself calls — and
`analyzePublicProperties($fqcn)` otherwise, `ReturnLiteralReader` for the Echo name, then
`AnalysisImports::build()` for every channel its own FQCN maps do not already own. Those two maps —
enums and models — stay transformer-side because only the transformer knows the `Partial<Model>`
presentation and the `ImportNameRegistry` aliases a same-basename collision forces; `buildTypeImports()`
skips any `AnalysisImports` name they already emitted, so an alias is never shadowed by a bare
duplicate.

**Broadcast events now honour `@return array{…}`.** Events reach `ReturnShapeRefiner` through
`analyzeMethod()` exactly as resources do, so a `broadcastWith()` whose body types nothing still
publishes whatever its own return shape declares, and a key the shape writes `key?:` publishes
optional. `DocblockShapedEvent` pins it: `broadcastWith()` returns one value from an untyped private
helper, and `published_at` publishes `string | null` from the docblock rather than `unknown`. An
event whose `broadcastWith()` carries no shape is unaffected — the refiner only ever fills a property
the body left untyped (`unknown`, or an interpolated key's `unknown | undefined`), so `TeamMessageSent`'s
reflected `teamId`/`content` keep the types their bodies already resolved.

`InertiaPageAnalyzer` is the other shape a consumer can take: instead of one method's return shape it
resolves *expressions* — every `Inertia::render()` props argument in a controller action — through the
[controller profile](#controller-profile) over a scope built by `AstEngine::bindingsFor()`, merging
same-component branches with `mergeReturnBranches()` so a key present in only one branch becomes
optional, then reconciling each component's index signatures with `IndexSignatureReconciler`, counting
the keys the controller method's `#[TsCasts]` adds or retypes. It reaches for `AstEngine::analyzeMethod()`
directly only when the props are delegated whole to a collaborator
(`Inertia::render('X', $this->service->build())`).

`ModelMetadataAnalyzer` (`src/Analyzers/Metadata/`) is the second `bindingsFor()` caller and the third
shape: it locates a metadata provider's `provide()` on its **declaring** class, seeds the scope so the
`Model $model` parameter's method calls reflect Laravel's own docblocks, and runs `ResourceAstAnalyzer` on
the default resource profile with that scope — not through `analyzeMethod()`, which seeds no bindings and
would drop them on an inherited body. Docblock and method-level `#[TsCasts]` overrides layer on top, the
FQCN channels of overridden or unreturned keys are forgotten, `modelFqcns` / `nestedResources` are cleared
(a runtime metadata array need not satisfy a model interface), and `AnalysisImports::build()` supplies the
enum imports the surviving inferred types spell. It strips the `customImports` that
`applyTsCastsFromMethod()` already appended for the method's own `#[TsCasts]`, whose imports
`TsCastsImportResolver` owns. See [model-metadata.md](model-metadata.md#body-inference-is-an-engine-consumer).

`AccessorBodyAnalyzer` (`src/Analyzers/Model/`) is the fourth shape, and the first to resolve a
**closure** rather than a method: `analyzeModelClosure()` takes an accessor's `get` closure (or an old-style
accessor body wrapped as one), seeds the scope with `bindingsFor()`, then overwrites the subject with
the model class so a trait-declared accessor still reads `$this` as the model that uses the trait, and
runs `ResourceAstAnalyzer::resolve()` on `ResourceExpressionHandlers::forModelClosures()` — an accessor body is not
a resource `toArray()`, so `ConditionalMethodHandler` and `ToResourceHandler` are dropped, while
`RelationFilterHandler` stays to type the model's own relation filters. Its result is one value, not a property
map, so it returns `ValueExpressionResult` and the analyzer carries the FQCN channels across into the
model engine's own `TypeScriptTypeInfo`. A reader that carries no import passes `carriesImports: false`, which it sets
on the getter's scope. See
[accessor-body-analyzer.md](accessor-body-analyzer.md).

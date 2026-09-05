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
contract every handler implements — it is what lets 24 independently-written handlers reproduce one
ordered guard chain's behavior without any handler knowing about the others.

`ExpressionDispatcher::dispatch()` does the trying. For an expression's *concrete* node class, it
builds the candidate list once — every handler whose `nodeClasses()` claims that class via `is_a()`,
so a handler can claim an abstract type (e.g. `Cast::class`) and match every concrete subclass — and
memoizes it per class in `$candidatesByClass`. A class no handler claims still gets a cached empty
list, so a repeated dispatch of an unclaimed node class never re-scans every handler's `nodeClasses()`
again. Handlers run in registration order within that candidate list; the first non-null `resolve()`
wins. This is PHPStan's `ExprHandlerRegistry` and Rector's `NodeNameResolver` memoization pattern,
scaled down: no DI container, no attribute-driven autodiscovery, just a plain constructor array — at
24 handlers that is enough.

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
`ArrayMergeHandler` and `InertiaWrapperHandler`.
`ResourceExpressionHandlers::generic()` is that same list minus the three
resource-only handlers (`ConditionalMethodHandler`, `ToResourceHandler`, `RelationFilterHandler`)
— every other handler is class-agnostic and safe to reuse outside a resource's `toArray()`.

The executable ordering contract lives in `tests/Unit/Ast/ResourceExpressionHandlersTest.php`:

- One test asserts `make()`'s exact class-name sequence, so an accidental reorder fails a test
  instead of silently changing generated output.
- One test asserts `generic()`'s exclusion set and relative order.
- Four tests pin the *behavioral* precedence between handlers that both really claim a shared node
  class — proven by mutation: swap the pinned pair, watch the pinned test fail, revert.
  - `FirstClassCallableHandler` before `ConditionalMethodHandler` for a first-class-callable
    `$this->when(...)`. `ConditionalMethodHandler::isThisMethodCall()` matches on method name
    alone, ignoring arguments, so it also claims this shape; if it ran first it would call
    `MethodCall::getArgs()`, which asserts `!isFirstClassCallable()` and fatals.
  - `RelationFilterHandler` before `MethodChainHandler` for `$this->relation?->only([...])`.
    `MethodChainHandler`'s floor is `ValueResult::unknown()`, never `null`, so it always claims
    every `NullsafeMethodCall` — if it ran first it would win this one too, degrading a `Pick<>`
    reference to a plain reflected type.
  - `ThisPropertyHandler` before `PropertyChainHandler` for `$this->{multi-FQCN accessor}`.
    `ThisPropertyHandler` threads a multi-model accessor's FQCNs out as `embeddedModelFqcns`, used
    downstream to alias same-basename union arms apart; `PropertyChainHandler`'s last-step branch has
    no equivalent, so swapping the two loses one arm's FQCN entirely rather than merely reordering.
  - `InertiaWrapperHandler` before `StaticCallHandler` for `Inertia::always(...)`. `StaticCallHandler`'s
    last arm claims every `StaticCall` and never declines, so if it ran first it would reflect the
    wrapper as an ordinary static method and floor the prop at `unknown` instead of the wrapped value.

### Controller profile

`ControllerExpressionHandlers::make()` is the profile `InertiaPageAnalyzer` runs an
`Inertia::render()` props expression through. It is `ResourceExpressionHandlers::generic()` with two
handlers inserted **immediately before `StaticCallHandler`**:

- `ModelFinderHandler` — a chain rooted at a `Model` static call, typed by its terminal:
  `find`/`first`/`firstWhere` → `{Model} | null`; `sole`/`firstOrFail`/`findOrFail`/`firstOrCreate`/
  `firstOrNew`/`create`/`make`/`updateOrCreate` → `{Model}`; `all`/`get` → `{Model}[]`;
  `paginate`/`simplePaginate`/`cursorPaginate` → the matching `TolkiTypes::MAP` name generic over the
  model, with the `@tolki/types` external import; `count`/`exists` → `number`/`boolean`.
- `InertiaResourcePropHandler` — a resource or resource collection in prop position, resolved to what
  it wraps, including the preserve-keys `Omit<…, 'data'> & { data: Record<string, R> }` shape.

That position is load-bearing in both directions. `StaticCallHandler`'s final arm claims every
`StaticCall` and never declines, so anything registered after it never sees one; and `NewResourceHandler`
sits directly after `StaticCallHandler` and resolves a `ResourceCollection` to its collected element
array, so a `New_` handler has to precede that too. `tests/Unit/Ast/ControllerExpressionHandlersTest.php`
pins the structure (the profile equals `generic()` with exactly those two inserted at that point) plus
three behavioural ordering pins, each proven by mutation.

`InertiaPageAnalyzer` pairs the profile with `AstEngine::bindingsFor()`, which seeds the scope from the
action's own signature: route-bound `Model` parameters, `Request` parameter names, and the local
variable bindings collected from the method body. That is what lets `compact('post', 'comments')`,
`$post` from `show(Post $post)`, and `$request->integer('page')` all type without any Inertia-specific
special case in the handlers.

### The honest ordering inventory

Not every node class more than one handler claims has a pin. The table below is the full inventory of
contested node classes — pinned pairs, and pairs explicitly proven inert (the two arms
never both actually claim the same expression, so their relative order cannot change output), and, for
one class, pairs neither pinned nor proven — flagged as a gap rather than glossed over.

| Node class | Claimants | Status |
| --- | --- | --- |
| `MethodCall` | `FirstClassCallableHandler`, `KnownFunctionCallHandler`, `ConditionalMethodHandler`, `ToResourceHandler`, `StaticCallHandler`, `RelationFilterHandler`, `RelationCollectionChainHandler`, `VariableHandler`, `KnownMethodRuleHandler` (9) | Three pairs pinned, all with `FirstClassCallableHandler` as the winner: before `ConditionalMethodHandler` and before `ToResourceHandler` (both crash-level — the loser calls `getArgs()`, which asserts `!isFirstClassCallable()`), and before `KnownFunctionCallHandler` (a silent divergence: `auth()->user(...)` as a first-class callable resolves to the guard's model instead of `unknown`). `KnownFunctionCallHandler` claims this node class for `auth()->user()`/`auth()->id()` only, and declines every other receiver. The remaining pairwise interactions within this candidate list are **live and unpinned** — untraced, unverified, could be inert or could silently change output on a reorder; see [Known gaps](../known-gaps.md#handler-ordering-is-pinned-by-example-not-by-the-suite). |
| `NullsafeMethodCall` | `RelationFilterHandler`, `MethodChainHandler` (2) | Pinned — the whole candidate list, full coverage. |
| `PropertyFetch` | `ThisPropertyHandler`, `PropertyChainHandler`, `VariableHandler` (3) | One pair pinned (`ThisPropertyHandler` before `PropertyChainHandler`). The other two pairs are **inert-proven**: `ThisPropertyHandler` vs. `VariableHandler` never both claim the same expression (`isThisPropertyFetch()` requires a `$this` receiver; `VariableHandler`'s property branch requires the receiver not be `$this`); `PropertyChainHandler` vs. `VariableHandler` likewise — `PropertyChainHandler`'s fallback declines any chain not rooted at `$this`, which is exactly `VariableHandler`'s territory. |
| `BinaryOp\Coalesce` | `BinaryOpHandler`, `CoalesceHandler` (2) | Inert-proven — `BinaryOpHandler::resolve()` has no branch matching `BinaryOp\Coalesce`, so it always declines regardless of registration position. |
| `StaticCall` | `InertiaWrapperHandler`, `StaticCallHandler` (2) | Pinned — the whole candidate list, full coverage. |
| `FuncCall` | `ArrayMergeHandler`, `KnownFunctionCallHandler` (2) | Inert-proven — `KnownFunctionCallHandler` declines `array_merge`: its reflected return type is `unknown[]`, and `resolveKnownFunctionCallType()` rejects any type containing `unknown`. `ArrayMergeHandler` is still registered first, so the specific handler keeps winning if that ever changes. |

`MethodCall`'s unpinned handlers are the open item: their pairwise interactions were enumerated
(by tracing the dispatcher's own candidate list against the current registration order) but not
individually fixture-verified. Read the pin count as "the divergences someone has actually gone and
found," not "the only divergences that exist."

## AnalysisScope

`AnalysisScope` is the mutable state threaded through one `ResourceAstAnalyzer::analyze()` call: the
subject under analysis and its backing model, plus the closure/spread bookkeeping that makes local
variables, `whenLoaded` relations, and recursive spreads resolve correctly as traversal descends. A
handler reaches it as the `$scope` parameter `ExpressionHandler::resolve()` receives; the analyzer
itself reaches the same instance as `$this->scope`.

| Field | Type | Holds |
| --- | --- | --- |
| `subjectReflection` | `ReflectionClass<object>` | The resource (or other AST subject) under analysis. Constructor argument. |
| `modelClass` | `class-string<Model>\|null` | The subject's resolved backing model, if any. Constructor argument. |
| `instanceOfWrappedClass` | `class-string\|null` | Wrapped class from an `instanceof` guard in `toArray()`; fallback when `resolveClassOnProperty()` returns `null`. |
| `closureRelationModelClass` | `class-string<Model>\|null` | Related model set while analyzing a `whenLoaded` closure, so `$variable->prop`/`->method()` inside it resolve. |
| `closureParamExprBindings` | `array<string, Expr>` | Closure parameter names bound to the `$this->prop` expression found in the surrounding `when()` condition, so `EnumResource::make($status)` resolves like `EnumResource::make($this->status)`. |
| `varModelBindings` | `array<string, class-string<Model>>` | Closure params / loop vars bound to a model class (`whenLoaded` params, relation-chain `map()` params, `foreach` over a many-relation), so `$var`, `$var->prop`, `$var->method()` resolve against that model. Scoped: writers save and restore around the body. Also seeded, via `AstEngine::bindingsFor()`, from every `Model`-typed parameter of the located method — a route-bound `Post $post`, a metadata provider's `Model $model` — bound to the parameter's **declared** type. |
| `varCollectionBindings` | `array<string, array{type: string, modelFqcn: class-string<Model>}>` | Closure params bound to a whole relation collection rather than one element — a to-many `whenLoaded` param. Read for a bare return of the param, and as the element-model fallback for an untyped `->map()` closure param. |
| `localVarBindings` | `array<string, Expr>` | Top-level `$var = expr;` bindings for the method last analyzed, so a bare `Variable` value expression resolves through its bound expression instead of degrading to `unknown`. Only variables written exactly once are recorded; `analyzeThisMethodSpread()` saves and restores this per method. |
| `resolvingLocalVars` | `array<string, true>` | Re-entrancy guard: variable names currently mid-resolution, so a self- or mutually-referential binding (`$a = $b; $b = $a;`) resolves as `unknown` instead of recursing forever. |
| `visitedSpreadMethods` | `array<string, true>` | Spread methods currently on the analysis stack, so a method that spreads itself — directly or through a cycle — degrades to an empty analysis instead of recursing until memory runs out. |
| `requestVarNames` | `array<string, true>` | Variable names holding an `Illuminate\Http\Request`, so `KnownMethodRuleHandler`'s reflected Request rule (`url()`, `user()`, `integer()`, …) fires on `$request->user()` and stays off an unrelated receiver sharing a method name. Seeded in `ResourceAstAnalyzer::__construct()` from the analyzed method's `Request`-typed parameters — **except for a `JsonResource` subject**, whose `toArray(Request $request)` would otherwise start typing request calls and move committed resource output. |

**Snapshot/restore, not immutable copies.** `AnalysisScope` is one mutable object shared for the whole
`analyze()` call, not a value threaded through with `mergeWith()`-style copying. A writer that needs a
binding to hold only for one nested body — a closure, a loop, a spread — saves the field (or the one
key it is about to overwrite), mutates it, analyzes the body, then restores the snapshot, typically in
a `finally` so an exception path restores it too. This mirrors the analyzer's own pre-refactor save/
restore discipline and is why the field inventory above calls out scoping per field rather than once.

### How `varModelBindings` gets populated, and how scoping holds

`varModelBindings` is populated from three sources, each scoped to the body it binds:

- **`whenLoaded('relation', fn ($x) => ...)`** (`ConditionalMethodHandler::analyzeWhenLoaded()`) —
  when `relation` resolves to a *single*-model relation, `$x` is bound to that model for the closure
  body. A to-many relation's closure param is deliberately **not** bound this way: the param holds the
  whole collection, not one element, so binding it to the element model would resolve a bare `$x` to a
  wrong-but-plausible singular type (e.g. `OrderItem` instead of `OrderItem[]`) —
  `$x->pluck(...)`/`$x->map(...)` already resolve via `AnalysisScope::$closureRelationModelClass`,
  unaffected by this guard.
- **A relation-chain `map()`** (`$this->{manyRelation}->take(5)->map(fn ($m) => ...)`, handled in
  `RelationCollectionChainHandler`) — `$m` is bound to the relation's element model for the map
  closure's body.
- **A top-level `foreach ($this->{manyRelation} as $item) { ... }`** (`ResourceAstAnalyzer::
  bindForeachLoopVariables()`) — `$item` is bound to the relation's element model for the rest of the
  method's analysis (mirrors `localVarBindings`' method-wide scope, restored around a
  `...$this->method()` spread the same way).

The two closure writers follow the save/restore discipline described above: snapshot the map (or the
one key being overwritten), mutate it for the nested body's analysis, then restore the snapshot. The
third writer does not — `bindForeachLoopVariables()` assigns `varModelBindings[$stmt->valueVar->name]`
outright, with no snapshot and no restore, because its binding is method-wide by design. The shadowing
guarantee survives that exception: a closure parameter that shadows an outer variable of the same name
still resolves against its **own** binding and can never leak into, or be leaked into by, the outer
scope, because it is the closure writers' own snapshots that restore over whatever the `foreach`
binding left behind. `ClosureParamShadowResource` in the workbench pins this: a top-level `$member` and
a `map(fn ($member) => $member)` closure param share a name, and each site resolves independently.

### `localVarBindings` and closure descent

`ClosureHandler::resolve()` — the generic closure/arrow-function handler every dispatch reaches —
saves `$scope->localVarBindings`, unsets any entry whose name matches one of the closure's own
parameters, analyzes the body, and restores the snapshot in a `finally`. Without that suppression, a
closure parameter shadowing an outer local, inside a construct with no scoped binding of its own (none
of the three `varModelBindings` sources above — e.g. `when()`'s condition isn't a `$this->prop` test),
would resolve through the outer `localVarBindings` entry when analyzing the closure body, turning an
honest `unknown` into a confidently wrong type. `ShadowedClosureParamResource` in the workbench pins
this: its `$slug = $this->slug;` followed by a `when()` call whose closure param is also named `$slug`,
with a condition that isn't a `$this->prop` test, must resolve to `unknown` rather than leaking the
outer `$slug`'s type.

### What deliberately stays unbound

- **A reassigned local** (written more than once in the method) — `localVarBindings` already skips
  these; `varModelBindings` has no reassignment analog since it only ever binds closure params and
  loop variables, each written exactly once by construction.
- **First-class callables** (`->map(...)`, `->pluck(...)`) — there is no closure body to bind a
  param into, so these are rejected before any binding is attempted.
- **A relation-chain `map()` whose argument isn't a `Closure`/`ArrowFunction`** (a string callable
  like `'strtoupper'`, or an array callable like `[$this, 'method']`) — same reasoning.

See [ResourceAstAnalyzer § Multi-model accessor unions](resource-ast-analyzer.md#multi-model-accessor-unions-reference-each-arms-own-model)
and the fixtures named above for how these bindings surface in emitted output.

## Subject mode

Subject mode is how `$this->prop` resolves when `AnalysisScope::$modelClass` is `null` — a class the
engine is pointed at that has no backing Eloquent model, which is every non-resource subject
`AstEngine::analyzeMethod()` accepts (a broadcast event, a DTO, a plain class). A resource always has
a model or the pipeline could not type it at all, so nothing on the resource path enters this mode;
both arms below live strictly inside the `null` branch that previously returned `unknown`.

Resolution order for the property itself is **`@var` docblock first, native declared type second** —
`PropertyDocblockTypeReader::read()`, then `LaravelTsPublish::propertyTypes()` — with the result
accepted through `ReflectedTypeAcceptor`, so a token that has no importable published file rejects
the whole result rather than shipping a name nothing imports. `SubjectPropertyTypeResolver` is the one
home for that pair; `AstEngine::analyzePublicProperties()` and both handler arms call it.

There are two arms because the dispatcher never hands the inner node of a chain to a handler:

- **Leaf** (`ThisPropertyHandler`): `$this->teamId` — after the model attribute and relation lookups
  both miss (they always do with no model), the subject's own property supplies the type and its FQCN
  channels.
- **Chain root** (`PropertyChainHandler::analyzePropertyChain()`): `$this->post->title` arrives as one
  `PropertyFetch` whose outermost node is `title`, so `ThisPropertyHandler`'s resolution of the inner
  `$this->post` is never consulted. The chain handler resolves its own first segment the same way, and
  **only a `Model` subclass hands off**: that model becomes the walk's starting point and the existing
  relation/attribute traversal runs unchanged over the remaining steps. Any other type declines, and
  the expression degrades to `unknown` exactly as before.

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

## MethodAnalysis

`MethodAnalysis` (`src/Ast/MethodAnalysis.php`) is the unified analysis DTO — the generalized
`ResourceAnalysis`, which now `extends MethodAnalysis {}` with an empty body. Its twelve constructor
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
| `flatTypeAlias` | `string\|null` | When set, the collection emits `export type X = SingularResource[]` instead of an interface. |
| `flatTypeAliasFqcn` | `class-string<JsonResource>\|null` | FQCN of the singular resource for the flat type alias. |

`merge(self $source)` folds another analysis into this one, field by field, and each field's merge
rule differs on purpose:

- `properties` **appends**.
- `enumResources`, `nestedResources`, `directEnumFqcns`, `modelFqcns`, `multiEnumResourceFqcns` are
  single-value class maps: spread-merged, source wins on a colliding key.
- `customImports` merges per import path, concatenating each path's type-name list.
- `inlineEnumFqcns` and `inlineEnumResourceFqcns` union per property key **with `array_unique`**.
- `inlineModelFqcns` unions per property key **without** deduping — the one field that deliberately
  never collapses a repeat. Its own docblock states the invariant directly: `aliasPropertyType()`
  consumes it as a positional queue against the rendered type string, so a real repeated FQCN
  occurrence has to survive as a repeat or a later occurrence gets the wrong alias.

`mergeReturnBranches()` (still on `ResourceAstAnalyzer`, not `MethodAnalysis` itself — it needs a
per-branch `propertyMap` for union-typing shared property names, which a plain field-by-field merge
can't do) carries the identical ten channels `merge()` does, plus the two `flatTypeAlias*` scalars
`merge()` never touches (first non-null branch wins there instead). See
[ResourceAstAnalyzer § `mergeReturnBranches()` carries every `MethodAnalysis::merge()` channel](resource-ast-analyzer.md#mergereturnbranches-carries-every-methodanalysismerge-channel-plus-two-flat-scalars)
for the corpus evidence behind the dedupe rules above.

## Public API

```php
AstEngine::analyzeMethod(string $class, string $method = 'toArray', ?string $modelClass = null): MethodAnalysis
AstEngine::analyzePublicProperties(string $class): MethodAnalysis
```

`analyzeMethod()` analyzes one method body's return shape. `$method` defaults to `'toArray'`, the
resource case, but any class/method pair works identically. When
`$modelClass` is omitted and `$class` is a `JsonResource` subclass, it resolves the backing model via
`ModelClassResolver::resolve()` (the same precedence `ResourceTransformer` uses, so the two never
disagree about which model a resource wraps) before constructing
`new ResourceAstAnalyzer($reflection, $modelClass, $method)` and calling `analyze()`.

`analyzePublicProperties()` analyzes a class's public properties instead of a method body — promoted
constructor parameters *and* class-body declarations, `@var` docblock first, native reflected type
second. It skips any property a used trait declares (transitively), so a `#[TsExtends]` trait's own
fields aren't emitted twice by the class that uses it. It never marks a property `optional`:
nullability is expressed as `| null` in the type; whether the key is present at all is a `#[TsCasts]`
concern, not something this method decides.

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
not what to *call* it once two imports collide. See
[ImportNameRegistry](import-name-registry.md) for that half, and the
[Analyzer API](https://tolki.abe.dev/ts/analyzer-api.html) page for the user-facing walkthrough of
calling `AstEngine` directly.

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

`InertiaPageAnalyzer` is the other shape a consumer can take: instead of one method's return shape it
resolves *expressions* — every `Inertia::render()` props argument in a controller action — through the
[controller profile](#controller-profile) over a scope built by `AstEngine::bindingsFor()`, merging
same-component branches with `mergeReturnBranches()` so a key present in only one branch becomes
optional. It reaches for `AstEngine::analyzeMethod()` directly only when the props are delegated whole
to a collaborator (`Inertia::render('X', $this->service->build())`).

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

# Known gaps

This file holds the accepted limits a fresh clone cannot show you. You have the code and the tests, but not the notes
of whoever deferred the work. An entry belongs here when it passes one of two tests:

- **It changes what you get out of the package**: a type the generator leaves `unknown`, `null` or an empty interface,
  gets wrong, or emits in a file that fails to compile, which a user would otherwise file as a bug.
- **It means a green signal is narrower than it looks**: a gate that passes without checking what you would assume
  it checks.

Every entry is a limit someone understood and accepted, not a regression.

Internal refactor debt, test-suite quality notes and release chores do not belong here. They go in the follow-ups
ledger of the plan that deferred them, and work meant for a future plan also gets a `next-plan` GitHub issue, as
[AGENTS.md](../AGENTS.md#known-gaps) says. When you decline something a reviewer raised, add it here only if it passes
one of the two tests.

## Types the generator will not give you

### A union arm the engine cannot type is left out, so the union publishes the other arm

`$cond ? $untypable : null` and `$this->opaque() ?: null` publish `null`, because the engine drops the arm it cannot
type ([AST engine § Dropped union arms](./components/ast-engine.md#dropped-union-arms) lists the sites that record a
drop). Widening the union to `unknown` would be more honest but less specific. A spread helper's key or a model
accessor's getter body left with only that `null` publishes `unknown` instead, while a getter that only ever returns
`null` still publishes `null`. Type the arm with a return type, a `@return` docblock or `#[TsCasts]`, and it comes
back.

### `config()` on an absent key with no default types as null

`config('unset.key')` with no default publishes `null`, the value on the machine that ran the publish. A
`config('key')` or `config()->get('key')` read follows that machine's configuration, so a `.env` that differs from
production changes the type. The package runs inside the booted app, where the live value is the only answer
([`KnownFunctionCallHandler`](../src/Ast/Handlers/KnownFunctionCallHandler.php)). Only an absent key types from its
default. A key set to `null`, such as an `env()` value unset on that machine, types as `null` whatever the default. A
typed accessor such as `config()->string('key')` types from its declared return instead.

### On Laravel 12, `#[Collects]` cannot be resolved, so use the `$collects` property

On Laravel 12 the `#[Collects]` class does not exist, so a `ResourceCollection` that names its resource only that way
publishes an empty interface instead of `PostResource[]`. An empty interface accepts any value except `null` and
`undefined`, so it looks specific and checks almost nothing. Laravel's own `collects()` cannot read the attribute on
12 either, and the package mirrors the framework rather than guessing. Set `public $collects = PostResource::class;`,
which works on both versions. The `FooCollection` to `FooResource` convention also works while the run publishes
`FooResource` ([`InspectsResourceCalls`](../src/Analyzers/Concerns/InspectsResourceCalls.php)), and
[Laravel version guards](./laravel-version-guards.md) records the version floor.

### A non-promoted property a constructor always assigns still renders optional

A public property that has no default and is not promoted publishes `?:` even when every constructor assigns it, such
as `DeclaredPropsEvent::$label`. `AstEngine::analyzePublicProperties()` and
`LaravelTsPublish::publicPropertyShapeType()` read the declaration, never the constructor body, and a non-promoted
`readonly` property cannot have a default. Promote the property or give it a default. A fix that reads constructor
bodies moves `DeferredAssignmentDto::$assignedLater`, which `NestedOptionalKeyDto` uses for a nested `?:`.

### A merge whose two sides name same-basename classes publishes only one of them

`$this->mergeWhen($cond, ['party' => $this->sender], ['party' => $this->receiver])`, over an app `User` and a CRM
`User`, types `party` with the CRM `User` alone, though either can arrive. `ResourceAstAnalyzer::mergeReturnBranches()`
unions the two sides by type text, as it unions a method's return branches, so the later side's class takes the shared
name. A ternary or `when()` keeps both classes, so spell the key that way.

### A returned variable is read flat: an early return sees later writes, and some writes are not followed

A method that builds an array in a variable and returns it publishes the keys its writes set
(`ResourceAstAnalyzer::resolveVariableReturnAnalysis()`), in one pass over the whole body:

- An early `return $data;` publishes the keys written after it too, required when that write always runs.
- A variable the walk does not read completely is skipped, as any other non-literal return is: one whose base is
  `$this->resource->toArray()`, or a helper or a parent `toArray()` that returns something the walk cannot read, and
  one with a write the walk does not read, such as `$data[] = …`, a dynamic or nested key, `unset($data['key'])`, or a
  `??=` to a key it does not hold. When no branch read completely has a key and every `return` is a literal or a
  variable, it publishes the writes the walk reads instead; with a `return` of another kind, the first `return` is
  read.

Return a literal on each path, or state the keys in `#[TsCasts]`.

### A helper that returns an empty `[]` on one path publishes an object shape, though `[]` encodes as an array

A method typed from its body, such as `if ($flag) { return []; } return ['a' => 1];`, publishes
`{ a?: number } | null` under a `?array` signature and `{ a?: number }` under `array`, but `json_encode([])` writes a
JSON array, not an object. `MethodReturnTypeResolver::shapeReadsEveryReturn()` reads the empty `[]` as a branch that
omits the keys. Under `?array`, return `null` on that path instead, which publishes `{ a: number } | null`.

### `#[TsCasts]` and the top-level spread flatten disagree by scope, in three separate ways

A top-level spread of a resource, a `$model->toArray()` or a `$collection->toArray()` flattens into the host resource
(`ResourceAstAnalyzer::analyzeSpreadArm()`). The model and collection arms honor the spread model's `#[TsCasts]`,
but three scopes do not line up:

- **The spread resource's own `#[TsCasts]`, and its backing model's, are skipped**, because
  `analyzeResourceSpreadArm()` never reaches `ResourceTransformer`, which reads both.
- **The host's `#[TsCasts]`, on the resource or its model, applies by property name**: an analyzed property records
  no origin, so `ResourceTransformer::applyOverrides()` lets a host override for `created_at` also retype a
  `created_at` flattened from another model.

Every case publishes a plausible type, so no gate moves, and each fix changes a working code path. To apply a spread
resource's override, repeat it on the host resource.

### `$request->validated('key')` declines a wildcard key, and ignores a dotted `#[TsCasts]` key

A literal or dotted key types from the bound request's `rules()`, as the request's own interface does, with three
exceptions ([`KnownMethodRuleHandler::validatedKeyRule()`](../src/Ast/Handlers/KnownMethodRuleHandler.php)):

- **A key with a `*` segment publishes `unknown`**, because `data_get()` returns a list of every match, while the rule
  under `*` types one element. A direct `data_get($target, 'a.*.b')` declines for the same reason.
- **A dotted `#[TsCasts]` key is ignored**, as it is on the request, whose
  `FormRequestTransformer::applyTsCastsOverrides()` matches only top-level fields.
- **A dotted key under an overridden parent types from the rules**: under `#[TsCasts(['options' => 'MyOptions'])]`,
  `validated('options.default')` still publishes the `string` the override replaced. The handler cannot index into a
  hand-written TypeScript type, and declining would trade the disagreement for `unknown`.

### A multi-enum ternary in Inertia shared data emits both enum names with no imports

With `enums.use_tolki_package` on, the default,
`$cond ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft)` in `HandleInertiaRequests::share()`
renders `RoleType | StatusType` and imports neither, which is TS2304 twice in a consumer's build.
`InertiaSharedDataAnalyzer::rewriteEnumResourceTypes()` handles only the single-enum shape, and `AnalysisImports`
drops a multi-enum ternary's type imports, as the resource generator's own rewrite needs. No workbench fixture has
this shape, so the token gate never sees it. Override the key with an import-aware `#[TsCasts]`, or use one enum.

### A mixed enum ternary whose arms are both array-shaped ships a duplicated union member in the globals

Each tree's `laravel-ts-global.ts` carries `app.enums.StatusType[] | app.enums.StatusType[]` twice, from
`EnumCollectionResource` and `TeamStatusAuditResource`, whose own files spell `AsEnum<typeof Status>[] | StatusType[]`.
The globals file has no `AsEnum` import, and `TsTypeString::rewriteAsEnumToType()` folds such a pair into one name
only when no `[]` follows either arm. `A[] | A[]` means `A[]`, so `tsc` and the gates pass, and the cost is a line that
looks like a bug. Keep the fold's `(?![A-Za-z0-9_$\[])` lookahead when you fix it, since it stops the differing pairs
`AsEnum<typeof X> | XType[]` and `AsEnum<typeof X>[] | XType` from folding, and add the both-array case as a shape of
its own.

### Two same-named enums collide instead of aliasing: a companion throws, a route file ships invalid TS

Resources alias two enums that share a basename across namespaces, but model metadata and route files do not. A
metadata companion that would import both throws at publish time ("imports [StatusType] from both [...] and [...]"),
because its inferred imports (`ModelMetadataAnalyzer::inferredTypeImports()`) skip the alias resolver its cast imports
use. A route file with two such page props ships two `import type { StatusType }` lines, TS2300 in the consumer's
build, since `RouteTransformer::resolvePageTypeImports()` has no alias resolver or collision guard, and no workbench
route has this shape. The fix, carrying the FQCN beside the rendered name, changes the import channel.

Give one enum a distinct `#[TsEnum]` name, as `Workbench\Shipping\Enums\Status` does. In a companion you can instead
cast one key to a distinct name its module exports, such as `['type' => 'AppStatusType', 'import' =>
'@/types/app-status']` where that module has `export type { StatusType as AppStatusType }`. Reusing `StatusType` still
collides, since `TsCastsImportResolver` aliases only between casts, and an inline `'Status as AppStatusType'` emits
invalid TypeScript. A key typed by a docblock-displaced union has no such handle, because its enums are keyed by FQCN
rather than by property name, so narrow it to one enum.

### An enum named like another enum's type name collides with it

An enum `Role` publishes the const `Role` and the type `RoleType`, so a second enum named `RoleType` in the same
namespace publishes a const with the same identifier. The enums barrel then re-exports `RoleType` from both files and
fails with TS2308, and `laravel-ts-global.ts` declares both in one namespace and fails with TS2300. No alias can settle
it, because both names are the published ones. `Runner::warnOfCollidingEnumNames()` names the pair in a warning after
the run, and covers a backed enum's `…Kind` name the same way. The token gate does not count TS2308. Across two
namespaces nothing collides, because a file that imports both aliases the const
([Import name registry § Consumers](./components/import-name-registry.md#consumers)). Give one enum a distinct name,
such as `#[TsEnum('AccessLevel')]` on `RoleType`, which renames both its const and its type.

### An empty `[]` under an imported type alias still ships as `[]`

Model metadata writes an empty array as `{}` where the property's type is object-like, but `TsTypeShape::isObjectLike()`
knows only `{...}` and `Record<`, so a `#[TsCasts]` type naming an imported alias keeps `[]`, and an alias of a map
fails `tsc` with TS2322. The tests that render `EmptyValuesModelMetadataProvider`'s `opaque` key set `output_to_files`
to false, so the token gate never compiles it. Resolving the alias would put module resolution inside a transformer
that reads strings, and guessing that an unknown name is object-like would spell `{}` for an alias of `string[]`.
Return `(object) []` from the provider instead.

### A body-inferred metadata enum that enum publishing excludes imports a file that is never written

`ModelMetadataAnalyzer::inferredTypeImports()` imports an enum a provider body returns from the path its namespace
implies, without checking `enums.excluded`, `#[TsExclude]` or `enums.additional_directories`. When enum publishing
leaves the enum out, the companion fails `tsc` with TS2305 or TS2307 instead of `ts:publish` failing. Resources have
`PublishedResourceRegistry` for this check, and enums have no equivalent. Publish the enum, or cast the property with
an import-aware `#[TsCasts]`.

### A model published on demand gets no metadata companion and is not watched

A model outside every configured directory is published, with its interfaces only, when a published model relates to it.
The metadata phase reads its own collector, so the model has no `_meta` companion, and `WatcherJsonWriter` lists
collected classes only, so editing the model's file does not republish it. A relation to a model with no table or view,
a `#[TsExclude]`d model, or one in `models.excluded` or outside `models.included` is left out instead, with no warning.
A custom `models.collector_class` that narrows `collect()` must narrow `accepts()` too, or the models its list relates
to are published on demand. Add the model's class or directory to `models.additional_directories` to publish it like
any other model.

### A model class name containing an underscore can collide with a metadata companion

A companion is named `Str::kebab(ModelName).'_meta'`, and `Str::kebab()` never adds an underscore, so no other
model's file can take that name. A class named `User_meta` can. It shares `user_meta.ts` with `User`'s companion, the
last phase to write wins, and `ModelMetadataTransformer::isMetadataFilename()` hands the one barrel export to the
metadata phase. PSR-1 class names carry no underscores, so this is accepted rather than guarded.

### A `transformer_class` that overrides only one of the two filename methods orphans its companions

`ModelMetadataTransformer::filenameFor()` names a companion and `isMetadataFilename()` recognizes one in the barrel,
and barrel ownership holds only while the two agree. `Runner::validateModelMetadataTransformer()` checks `is_a()`
alone, which cannot see that. Redefining `FILENAME_SUFFIX` keeps them in step, but overriding only `filenameFor()`
orphans the file when a run skips the metadata phase, and overriding only `isMetadataFilename()` claims exports the
phase never wrote. Override both, as the test fixture
[`PrefixedModelMetadataTransformer`](../tests/Fixtures/PrefixedModelMetadataTransformer.php) does.

### Overriding a moved helper on a `LaravelTsPublish` subclass does not change what the package emits

A bound `LaravelTsPublish` subclass still changes what the class owns, such as `toTsType()`, the docblock resolvers
and `relationStrategy()`. The helpers that moved to `Support\JsEmitter`, `Support\TsTypeString` and `Support\TsNaming`
keep their `LaravelTsPublish::` signatures, as `tests/Unit/LaravelTsPublishDelegationTest.php` pins, but the package
calls them through their own facades. Overriding one on the subclass changes only the consumer's direct calls, with no
error, and no test subclasses `LaravelTsPublish`. Bind a replacement helper class instead, from an application
provider, whose binding wins because Laravel registers package providers first:

```php
use AbeTwoThree\LaravelTsPublish\Support\TsNaming;

$this->app->singleton(TsNaming::class, MyTsNaming::class);
```

A subclass that overrode `importSortGroup()` or `$resourceTypeNames` extends `TsNaming` instead, where both are
protected. [Support helpers](./components/support-helpers.md) sets which class owns a helper.

### A `#[TsCasts]` value that spells an imported name inside a string, template or comment keeps the import

`TsTypeString::typeNameOccursIn()` decides which imports a type still needs, and it counts a name wherever its token
stands, inside a string, template literal or comment too. So `#[TsCasts(['app' => "'User' | 'Admin'"])]` keeps an
unused `import type { User }`, which only `noUnusedLocals` reports, as TS6196, or as one TS6192 when the line holds two
or more names and none is used. This is deliberate. Lexing the type as TypeScript does would hide names TypeScript
reads as references, such as one after a `//` comment that U+2028 ends, and each import dropped that way is TS2304 for
every consumer. A name right after a numeric literal (`1User`) is missed, in syntax that fails to compile anyway. Spell
the literal without the name, or cast to a type that uses the import.

### `instanceof` narrowing depends on the spelling, in four different ways

Check which spelling you wrote before you assume a value is `unknown`:

- **A negated early-exit `if` on a local variable binds**: after `if (! $x instanceof Post) { return null; }`, also
  through `||` or a `throw`, later reads of `$x` see `Post`, as `NarrowedParentResource` shows. A write to `$x` after
  the guard means no binding at all, even for reads before that write.
- **A positive `if` statement binds nothing**: `if ($x instanceof Post) { return $x->title; }` leaves `$x->title`
  `unknown`, as `ReceiverHandlersTest` pins. The walk is flat, so the binding would outlive the `if`.
- **A ternary binds for the arm its test proves**: `$x instanceof C ? A : B` narrows `$x` in `A`, and the negated test
  narrows it in `B` ([`TernaryHandler`](../src/Ast/Handlers/TernaryHandler.php)).
- **A ternary whose true arm is the tested expression narrows only reads through it**: after
  `$v = $this->imageable instanceof Post ? $this->imageable : null;`, `$v?->getKey()` reads `Post`, but `$v` itself
  publishes the whole `morphTo` union, as `NarrowedImageableResource` shows.

`$this->resource` narrows in either polarity, for the whole method (`ResourceAstAnalyzer::resolveInstanceOfType()`),
so a resource that guards on it needs no `#[TsCasts]` for what the guard proves.

### A shape whose values name a class loses those values, in one of two ways

Neither the method-body fallback nor a docblock array shape can carry an import. The body fallback
(`MethodReturnTypeResolver::bodyType()`) drops its whole shape when one value names a class, except that `only()` and
`except()` answer with that member as `unknown`, as
[Receiver types](./components/receiver-types.md#the-body-fallback-carries-no-fqcn-channel) details. A docblock shape
degrades one leaf: `@return array{owner: User}` publishes `{ owner: unknown }`, while `Record<string, User>` has
nothing to recurse into and degrades whole. Cast the property with an import-aware `#[TsCasts]`, or give the method a
native return type that names the class.

### An index signature its body types can fail to compile beside a key it cannot take in

`IndexSignatureReconciler` gives a signature its body's value back wherever a docblock fill or a same-pattern union
could conflict with a neighboring key, and under any extends clause, including one from `ts_extends.resources` or
`ts_extends.broadcast_events`, since it cannot see inherited keys. The body value can still fail `tsc`: TS2411 when a
key the pattern matches cannot take it, such as `main_tag: PostResource` beside
``[key: `${string}_tag`]: string | undefined``, and TS2413 when the pattern sits inside another signature's that
rejects its value. Where the union is declined, a second signature with the same pattern replaces the first, so the
signature publishes the last entry's value alone. Type the key, or rename it out of the pattern. The rules are under
[Index signatures](./components/resource-ast-analyzer.md#index-signatures-are-reconciled-with-the-keys-beside-them).

### `Model::toArray()` on a receiver declines, deliberately

`ReceiverMethodReturnResolver::resolveOn()` gives no shape for `$model->toArray()` in value position, even when the
model declares one, because the relations it serializes are whatever is loaded at runtime. `ReceiverHandlersTest` pins
the decline, but nothing pins what such a call publishes instead, since the workbench only spreads a model's
`toArray()`, so expect something vague. The spread form is typed: `[...$user->toArray(), 'flag' => true]` publishes
`Omit<User, 'flag'> & { flag: boolean }`. [Receiver types](./components/receiver-types.md#the-order-for-one-class)
gives the order the receiver rules run in.

### A model spread inside a `collect()->map()` closure names the wrong model, or none

`collect($this->comments)->map(fn (Comment $c) => [...$c->toArray(), 'flag' => true])` binds `$c` to an element type
with no model, so `InlineArrayHandler::spreadModelToArrayFqcn()` falls back to the model an enclosing `whenLoaded()` or
relation `map()` set. At the top level there is none, and only `{ flag: boolean }` publishes. Inside
`whenLoaded('author', …)` the element publishes as `Omit<User, 'flag'> & …`, though it is a `Comment`. Use the
relation chain, `$this->comments->map(fn ($c) => [...$c->toArray(), 'flag' => true])`, which binds the model.

### An index-signature model with a shared key loses its attribute types in `{Model}All` and `{Model}AllResource`

A model that extends an index-signature type, such as `#[TsExtends('Record<string, unknown>')]`, gets
`{Model}All extends Omit<{Model}, 'key'>, {Model}Relations {}` from the `model-split` template when a relation shares
`key` with an attribute. `Omit` over an index signature keeps only the signature, so every attribute of `{Model}` reads
as `unknown` in `{Model}All`, and in `{Model}AllResource`, whose `Omit<{Model}Resource, 'key'>` loses the same keys.
Read the attributes from `{Model}` and the relations from `{Model}Relations`, which keep their types, or give the
relation a name no attribute takes.

### An API resource wrapping a relation the engine cannot type publishes it without `| null`

With `nullable_relations` on, `CategoryResource::make($this->parent)` publishes `CategoryResource` where `parent` types
`unknown` (an undeclared relation, a related model the run does not publish, or a `morphTo` with no resolvable target),
though Laravel sends `null` for a relation loaded as `null`.
`whenLoaded('parent', fn ($p) => CategoryResource::make($p))` does carry the `| null`. Declare the relation's return
type, or publish the related model, and the `| null` comes back.

### A `whenHas()` or `whenAppended()` value the engine cannot type publishes `unknown`

[`ConditionalMethodHandler`](../src/Ast/Handlers/ConditionalMethodHandler.php) publishes `unknown` for
`$this->whenHas('title', fn ($t) => json_decode($t))`, not the `title` column's `string`, because Laravel returns what
the value returns and a plain title decodes to `null`, as `WhenHasValueResource` pins. Give the closure a return type,
such as `fn ($t): ?string => json_decode($t)`, and the key types from it. `whenExistsLoaded()`, `whenCounted()` and
`whenAggregated()` still publish their flag or aggregate type for an untypable closure, so such a key can claim a type
the closure does not return.

### On a class that is not an API resource, `$this->when()` publishes `unknown`

Only a `JsonResource` subject runs [`ConditionalMethodHandler`](../src/Ast/Handlers/ConditionalMethodHandler.php), as
[ResourceAstAnalyzer § Where things live](./components/resource-ast-analyzer.md#where-things-live) lists by profile.
On a broadcast event, a model metadata provider or any other class, the package reads `$this->when()`, `unless()` and
every other `when*()` call as that class's own method, so Laravel's `Conditionable::when()` publishes `unknown`,
required, as `ConditionableBroadcastEvent` pins. The resource rule would be wrong there: `Conditionable::when()`
returns the callback's result, or the object itself, and the key is always sent. Until the package types
`Conditionable::when()`, write the key as a ternary, or type it with `#[TsCasts]`.

### An aggregate's type follows the database of the machine that publishes

[`AggregateValueType`](../src/Ast/AggregateValueType.php) types a `whenAggregated()` aggregate by the driver of the
model's connection on the machine that publishes, as column types follow that machine's schema. A `SUM()` of a decimal
column publishes `number | null` from SQLite and `string | null` from MySQL, so publishing against SQLite for an app
that runs MySQL keeps a `number` the response does not hold. Publish against the driver production runs; the
generation cache rebuilds when `database.default` or a connection's driver changes, even through its `url`. Or give
the alias `{relation}_{function}_{column}`, such as `order_items_sum_total_price`, a built-in cast on the parent model,
or declare it with an accessor or `@property`, and that type publishes on every driver. A query-time `withCasts()`
cannot be seen by a publish.

### On SQL Server a numeric aggregate and every count publish `number`, though pdo_sqlsrv returns numbers as strings by default

Laravel's `sqlsrv` driver runs on pdo_sqlsrv, which returns a number as a string, or on pdo_dblib, which returns a
number, and `getDriverName()` is `sqlsrv` for both. So a numeric `whenAggregated()` aggregate publishes
`number | null` there, and `whenCounted()` and a `'count'` aggregate publish `number`; a date or text `MIN()` or
`MAX()` publishes `string | null`. `PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE` makes pdo_sqlsrv return an integer or float
as a number, but never a `decimal`, `numeric` or `money` value, so a decimal `SUM()` is a string either way. Give the
alias `{relation}_{function}_{column}` a built-in cast on the parent model, or declare it with an accessor or
`@property`, and that type publishes on every driver; a count publishes `number` whatever its declaration, so an
`integer` cast on its alias makes the response hold one.

### A stacked collection that names no resource itself publishes its parent's, though Laravel collects raw models

A collection that declares no `toArray()` and is stacked on another collects what its own class names: its own
`#[Collects]`, the `$collects` it inherits, then the naming convention on its own name. When none names a resource, as
with `StackedUnnamedCollection` over `SupplierSummaryCollection`, Laravel collects the raw models, but the package
publishes the parent's `SupplierSummaryResource[]` rather than an empty interface, which would drop the `data` key the
response carries. That holds only for a parent that wraps: over a `$wrap = null` parent that names its API resource by
attribute or by name, the stacked class still publishes an empty interface. Name the resource on the stacked class with
`public $collects = SupplierSummaryResource::class;`, which works on both Laravel versions, and the published type and
the response agree.

### A collection that keeps the `make:resource` stub `toArray()` publishes an empty interface, or its parent's API resource

`make:resource` writes `return parent::toArray($request);` into a new collection. A collection that keeps it and extends
`ResourceCollection` publishes an empty interface, though Laravel sends `{ data: R[] }` of the API resource it collects.
Stacked on another collection it publishes that parent's collected type, not the API resource its own `$collects` names,
because `ResourceAstAnalyzer::analyzeParentToArray()` analyzes the parent as its own subject. Delete the stub
`toArray()`: it only repeats the inherited one, and the collection then resolves from its own class, as a body-less one
does.

### A runtime `withoutWrapping()` is invisible, so a collection publishes `{ data: R[] }` for a bare list

The package reads each collection's declared `$wrap` default, never a value the app sets at runtime. After
`JsonResource::withoutWrapping()`, often called in a service provider, every body-less collection that does not
redeclare `$wrap` sends a bare list, while its published interface still has the `data` key. A runtime `wrap('items')`
is missed the same way. Declare `public static $wrap = null;` on the collection, or on a base collection the others
extend, as `UnwrappedCollection` does, and the package publishes the bare list. For `wrap('items')`, declare that key.

## Deliberate non-goals

These are absent on purpose. Raise one before you "fix" it:

- **Non-Inertia and JSON responses are never typed**: only the page props that `InertiaRenderLocator` finds in
  `Inertia::render()`, `inertia()` and `inertia()->render()` calls, and the shared-data middleware, are analyzed.
- **The AST engine has no extension point**: there is no `ts-publish.analyzer.handlers` key, and the public surface is
  `AstEngine::analyze()` and its `AnalysisResult`. Every other `src/Ast/` class and `AstEngine` method is tagged
  `@internal`, because it changes as inference grows, so PHPStan's bleedingEdge `internalTag` check or an IDE warns a
  consumer who reaches for one. `tests/Architecture/InternalBoundaryTest.php` checks the tags.
- **Form requests stay runtime**: `FormRequestRulesAnalyzer` instantiates the request and calls `rules()`.
- **Collector class maps are not invalidated mid-process**: `CoreCollector::classMap()` scans each directory once per
  process, and `Runner::run()` and `RunnerForSource::run()` flush it first. Host code that calls `collect()` or
  `allows()` before and after writing a `.php` file gets the first answer twice, so call
  `CoreCollector::flushClassMapCache()` between the two. Stat-based invalidation would charge every run for a case no
  package run reaches, since nothing in `src/` writes a `.php` file.

## Green signals that are narrower than they look

The type-inference gates list their own blind spots in
[What the gates do not cover](./testing/type-inference-gates.md#what-the-gates-do-not-cover).

### Handler ordering is pinned pairwise, corpus-bounded

Several handlers in [`ResourceExpressionHandlers`](../src/Ast/ResourceExpressionHandlers.php) claim `MethodCall`, so
registration order decides which one answers `$this->foo()`. `tests/Unit/Ast/MethodCallOrderingMatrixTest.php` runs
every pair in both orders over a corpus of expressions. A pair that disagrees is pinned in `METHOD_CALL_PINNED`, and
every other pair is proven inert against that corpus only. A pair can still disagree on a shape the corpus lacks, so
read the pins as the divergences found so far, not all there are.
[The ordering inventory](./components/ast-engine.md#the-honest-ordering-inventory) lists them.

### The publish-speed gate is one-sided

`publish-bench.sh` fails only when a push's median time is more than `MAX_RATIO` times that of its merge-base with
`origin/main`, and never checks a speedup. Until a speedup reaches `main`, later branches still measure against the
slower base, so they can give it back without failing. Updating `main` is the owner's step, since agents never merge
into it ([AGENTS.md § Git](../AGENTS.md#git)). [Performance gate](./testing/performance-gate.md) describes the job.

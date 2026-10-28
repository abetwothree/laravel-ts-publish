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
drop). `$matches ?? []` past a `preg_match()` that passes flags, or whose pattern names a group, publishes `never[]` the
same way, though Laravel sends the groups nested or keyed by name. A parameter written in a block drops the value it
came in with the same way: `transform($this->opaque(), …)` whose callback may set `$value = $comment->id` publishes
`number`. So does the join of a local's branches, for a value one branch writes. Widening the union to `unknown` would
be more honest but less specific. Where the drop leaves only `null` or `never[]`, the run output warns, naming the line
and the arm left out, and so does a parameter's or a branch join's drop, whose published type may not admit every
value Laravel sends; a class the generation cache serves prints nothing until a `--fresh` run. A spread helper's key or
a model accessor's getter body left with only that `null` publishes `unknown` instead, while a getter that only ever
returns `null` still publishes `null`. A method read as a value is the exception: one return it cannot type leaves the
whole call `unknown`, or the vague type it declares. So is `$this->resource` read whole under its own `instanceof` test,
whose untypable class leaves the whole ternary `unknown`. Type the arm with a return type, a `@return` docblock or
`#[TsCasts]`, and it comes back; a cast on the key types it but leaves the warning.

### A list literal holding an element no rule types publishes `unknown[]`

`[$this->id, json_decode('{}')]` publishes `unknown[]`, not `number[]`. A list element is in every response, so unlike a
union arm it is never left out ([AST engine § Dropped union arms](./components/ast-engine.md#dropped-union-arms)). Type
the element with a return type or a `@return` docblock, or the key with `#[TsCasts]`, and the list takes its type.

### A method whose declaration and body type nothing publishes `unknown`, even when a model or wrapped class declares a typed namesake

`'label' => $this->label()` publishes `unknown` when the resource's own `label(): mixed` returns a value no rule types,
though the backing model declares `label(): string`. PHP runs the resource's method, so the model's type is only right
when the two bodies agree. The same holds for a class other than a model that an `@var` on `public $resource` names:
the model never answers for it, and a method that class lacks publishes `unknown`, since Laravel throws. Give the
method a return type or a `@return` docblock, and the call takes it.

### A resource macro typed only by its body publishes `unknown` when its value depends on its arguments

A macro's body is read without what the call passes, so `$this->format($x)` on an untyped macro closure publishes
`unknown`. A closure declared outside a class method, such as in `bootstrap/app.php`, gets no body read, and a macro
registered after the application boots, or by a deferred provider, is not seen at all. Give the closure a return type. A
`@return` docblock works only on a closure declared inside a class, such as in a service provider's `boot()`, because
its short class names resolve against that class's file.

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
`FooResource` and no class `Foo` sits beside the collection, since Laravel takes `Foo` first
([`ResourceCollections`](../src/Ast/Resources/ResourceCollections.php)), and
[Laravel version guards](./laravel-version-guards.md) records the version floor.

### A collection that collects through an overridden `collects()` or a `$collects` set in its constructor publishes an empty interface

Laravel reads the API resource a collection collects from its `collects()` method at runtime. The package reads the
`#[Collects]` attribute, the `$collects` property's declared default and the naming convention, so a collection whose
own `collects()` returns `PostResource::class`, or whose constructor assigns `$this->collects`, publishes an empty
interface, or what its name or parent implies, though Laravel sends `{ data: PostResource[] }`. Declare
`public $collects = PostResource::class;` instead.

### A non-promoted property a constructor always assigns still renders optional

A public property that has no default and is not promoted publishes `?:` even when every constructor assigns it, such
as `DeclaredPropsEvent::$label`. `AstEngine::analyzePublicProperties()` and
`LaravelTsPublish::publicPropertyShapeType()` read the declaration, never the constructor body, and a non-promoted
`readonly` property cannot have a default. Promote the property or give it a default. A fix that reads constructor
bodies moves `DeferredAssignmentDto::$assignedLater`, which `NestedOptionalKeyDto` uses for a nested `?:`.

### A merge whose two sides name same-basename classes publishes only one of them

`$this->mergeWhen($cond, ['party' => $this->sender], ['party' => $this->receiver])`, over an app `User` and a CRM
`User`, types `party` with the app `User` alone, though either can arrive. `ResourceAstAnalyzer::mergeReturnBranches()`
unions the two sides by type text, as it unions a method's return branches and the branches a returned variable's walk
joins, so the one `User` token takes the first side's class. A ternary or `when()` keeps both classes, so spell the key
that way.

### A returned variable is read flat: an early return sees later writes, and some writes are not followed

A method that builds an array in a variable and returns it publishes the keys its writes set
(`ReadsReturnedVariables::resolveVariableReturnAnalysis()`), in one pass over the whole body:

- An early `return $data;` publishes the keys written after it too, required when that write always runs.
- The branches of an `if` chain and a loop's body join, so a key holds each branch's value, optional where a branch, or
  a loop that never runs, leaves it unset. A branch that ends in a `throw` or another `return` stays out, but a
  `continue` or `break` is not followed: after `foreach (…) { if (…) { $d['a'] = 1; continue; } $d['a'] = 'x'; }`,
  `a?: string` publishes, though Laravel can send `1`. A `switch` case, a `try` body and a `catch` are read in sequence
  instead: a key first set there is optional, and a key re-set there publishes only its last write's type.
- A variable the walk does not read completely is skipped, as any other non-literal return is: one whose base is
  `$this->resource->toArray()`, a helper or a parent `toArray()` that returns something the walk cannot read, or a
  literal with a `$this->`, `self::` or `static::` helper spread the analyzer declines, and one with a write the walk
  does not read, such as `$data[] = …`, a key no literal or literal local names, a nested key, `sort($data)` or any
  other by-reference call, or a `??=` to a key it does not hold. When no branch read completely has a key and every
  `return` is a literal or a variable, none inside a `try`, a `switch` or a bare block, it publishes the writes the walk
  reads instead, beside `[key: string]: unknown` for a helper read as a value whose base or key write the walk could
  not read; with a `return` of another kind, the first `return` is read. A merge closure that returns only a
  variable merges what the walk reads too, but nothing where an `unset()` the walk cannot name may remove a key.

Return a literal on each path, or state the keys in `#[TsCasts]`.

A helper that returns a parameter it writes keys into joins its writes onto the keys of the array it is passed, or of a
declared `array{…}`. It publishes only the keys it writes, beside `[key: string]: unknown` where it is read as a value
and alone where its result is the method's own return, when the argument is not one object literal (a `Record`, a list,
a `{…} | null`, or an array `json_decode()` returns), names a model no generated file exports, holds an `EnumResource`
wrap or two same-named enums a level apart, or, for a helper read as a value, any class an import-less read cannot
import. An assignment inside another expression, a top-level `unset()`, destructuring, `foreach` target or by-reference
call of the parameter leaves only the helper's own writes, and so does a rewrite of its values in place, `array_walk()`,
a by-reference `foreach` or `Arr::set()`, inside a block too; a `??=`, a key-keeping sort such as `ksort()`,
`array_push()` and `array_unshift()` keep the argument's keys. Inside a block, such a write, or an `=` of a value the
walk cannot read, makes each key held before it optional, a local's too, and, but for an `unset()`, publishes the shape
open, unless it keeps every key, as those calls, a local's rewrite in place and an `=` of `array_merge()` or
`array_replace()`, either recursive, or `Arr::add()` taking the array first, or of a `+` it leads, do. A local rewritten
in place keeps its earlier value types, though `array_walk()` may turn a number into a string. Declare
`@return array{…}` on the helper to publish the exact shape.

A local rewritten through a by-reference parameter of a method on a receiver the package cannot name, such as
`$validator->normalize($data)`, or through `extract()`, keeps its earlier type. Such a write also leaves a non-null
proof made before it standing, so after `if ($y === null) { return []; } $cleaner->clear($y);` a read of `$y` drops
the `null` the call can write.

### A helper that returns an empty `[]` on one path publishes an object shape, though `[]` encodes as an array

A method typed from its body, such as `if ($flag) { return []; } return ['a' => 1];`, publishes
`{ a?: number } | null` under a `?array` signature and `{ a?: number }` under `array`, but `json_encode([])` writes a
JSON array, not an object. `MethodReturnTypeResolver::shapeReadsEveryReturn()` reads the empty `[]` as a branch that
omits the keys. Under `?array`, return `null` on that path instead, which publishes `{ a: number } | null`. A declared
shape whose keys can all be absent, such as `array{a?: int}` or `array<'a'|'b', int>`, publishes the same object and
has the same gap: an empty array is sent as `[]`.

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

### A multi-enum or mixed `EnumResource` ternary in an Inertia prop publishes the wrong enum shape

With `enums.use_tolki_package` on, the default,
`$cond ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft)` as a page prop or in
`HandleInertiaRequests::share()` publishes `RoleType | StatusType`, though Inertia sends each arm's resource object.
`RewritesEnumResourceProps::rewriteEnumResourceTypes()` handles only the single-enum shape. In shared data neither name
is imported either, which is TS2304 twice in a consumer's build: `AnalysisImports` drops a multi-enum ternary's type
imports, as the resource generator's own rewrite needs. A ternary of an `EnumResource` and a bare case of its enum,
`$cond ? EnumResource::make(Status::Draft) : Status::Published`, publishes `AsEnum<typeof Status>` for both arms,
though Inertia sends the bare arm as its value. No workbench fixture has these shapes, so the token gate never sees
them. Override the key with an import-aware `#[TsCasts]`, or wrap every arm in one enum's `EnumResource`.

### An `EnumResource` resolved to an array inside another API resource loses its numeric keys

`'priority' => EnumResource::make($this->priority)->resolve()` publishes `AsEnum<typeof Priority>`, but the outer
resource's filter walks into the array `resolve()` returns and re-indexes every value whose keys are all numeric, so a
method value the published enum holds as `{ "1": 1, "2": 2 }` arrives as `[1, 2]`. The filter never walks into a
resource object, so return `EnumResource::make(…)` itself and the keys arrive as the type says.

### A broadcast event's `EnumResource` publishes the enum's type, not `AsEnum<typeof X>`

`'role' => EnumResource::make($this->role)` in an event's `broadcastWith()` publishes `RoleType` for every enum, though
the payload sends the resource object, `{ name, value, backed, … }`. API resources, pages and shared data rewrite such a
key to `AsEnum<typeof Role>`, but `BroadcastEventTransformer` spells every enum by its `…Type`. An enum whose
`#[TsType]` or `custom_ts_mappings` entry types its values still publishes its own `…Type` here. Override the key with
an import-aware `#[TsCasts]`, typed by a name your own module exports, such as
`export type RoleResource = AsEnum<typeof Role>`.

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
route has this shape. A bare case, such as `'status' => CrmStatus::Lead`, reaches it as an enum-cast read does. A
`->name` read does not: it falls back there to its literal case-name union rather than collide. The fix, carrying the
FQCN beside the rendered name, changes the import channel, and retires that fallback (`SharedEnumKinds`).

Give one enum a distinct `#[TsEnum]` name, as `Workbench\Shipping\Enums\Status` does. In a companion you can instead
cast one key to a distinct name its module exports, such as `['type' => 'AppStatusType', 'import' =>
'@/types/app-status']` where that module has `export type { StatusType as AppStatusType }`. Reusing `StatusType` still
collides, since `TsCastsImportResolver` aliases only between casts, and an inline `'Status as AppStatusType'` emits
invalid TypeScript. A key typed by a docblock-displaced union has no such handle, because its enums are keyed by FQCN
rather than by property name, so narrow it to one enum.

### A pure enum's `->value` read through an API resource wrapping it publishes `string | number`

`$this->resource->value` in a resource wrapping a pure enum, such as `UnitEnumResource.value`, publishes
`string | number`. A pure enum has no `value`: PHP reads the undefined property as `null` with a warning, which
Laravel's error handler turns into an exception. Read `->name`, which publishes the enum's `…Type`.

### An enum named like another enum's type name collides with it

An enum `Role` publishes the const `Role` and the type `RoleType`, so a second enum named `RoleType` in the same
namespace publishes a const with the same identifier. The enums barrel then re-exports `RoleType` from both files and
fails with TS2308, and `laravel-ts-global.ts` declares both in one namespace and fails with TS2300. No alias can settle
it, because both names are the published ones. `Runner::warnOfCollidingEnumNames()` names the pair in a warning after
the run, and covers a backed enum's `…Kind` name the same way. The token gate does not count TS2308. Across two
namespaces nothing collides, because a file that imports both aliases the const
([Import name registry § Consumers](./components/import-name-registry.md#consumers)). Give one enum a distinct name,
such as `#[TsEnum('AccessLevel')]` on `RoleType`, which renames both its const and its type.

### An overridden enum's case can publish the enum's own type

A case of an enum with an override keeps the enum's import channel beside the override's import, and two reads still
take the channel's name:

- An override spelled like the enum's own type, such as `#[TsType(['type' => 'HueType', 'import' => '@acme/dto'])]` on
  enum `Hue`, publishes a case read directly in an API resource as the enum's own `HueType`, imported from the enum's
  module under an alias, and drops the override's import. Through an untyped helper's array it publishes the override,
  so one file can spell both. Name the override something else, such as `HueDto`.
- An array, a constant's or a literal, holding an overridden enum's case before a case of a same-named plain enum, such
  as `[Tone::Bright, Other\Tone::A]` or `['a' => Tone::Bright, 'b' => Other\Tone::A]`, publishes the plain element as
  the overridden enum's own `ToneType`, imported from that enum's module. The type compiles but names the wrong enum.
  List the plain enum's cases first, or give it a distinct `#[TsEnum]` name.

### Two reads of a column cast to an overridden enum miss its `AsEnum<typeof X>`

An `EnumResource` keeps an overridden enum's own names, but two reads of a column cast to such an enum, here `Tone` with
the override `ToneDto`, do not:

- `EnumResource::collection($this->tones)` over an `AsEnumCollection::of(Tone::class)` cast publishes `unknown`, where
  a plain enum's publishes `AsEnum<typeof Status>[]`. Type the key with an import-aware `#[TsCasts]` of
  `ToneResource[]`, where your own module exports `type ToneResource = AsEnum<typeof Tone>`.
- A model's `{Model}Resource` interface types the column as the override, `ToneDto` or `ToneDto[]`, where it types a
  plain enum's as `AsEnum<typeof Status>`. Read the column through an API resource's `EnumResource::make($this->tone)`,
  which publishes `AsEnum<typeof Tone>`.

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
`PublishedResourceRegistry` for this check. Enums have `PublishedEnums`, but only an enum's `->value` and `->name` reads
consult it, and a raw enum read such as this one does not. Publish the enum, or cast the property with an import-aware
`#[TsCasts]`.

### A model published on demand gets no metadata companion and is not watched

A model outside every configured directory is published, with its interfaces only, when a published model relates to it.
The metadata phase reads its own collector, so the model has no `_meta` companion, and `WatcherJsonWriter` lists
collected classes only, so editing the model's file does not republish it. A relation to a model with no table or view,
a `#[TsExclude]`d model, or one in `models.excluded` or outside `models.included` is left out instead, with no warning.
A custom `models.collector_class` that narrows `collect()` must narrow `accepts()` too, or the models its list relates
to are published on demand. Add the model's class or directory to `models.additional_directories` to publish it like
any other model.

### A composed class only a value reaches is not watched

`WatcherJsonWriter` lists each scanned composed class with the files its overrides come from. A composed class outside
`class_types.additional_directories` gets its module when a published value reaches it, but neither it nor its
ancestors are listed, as for a model published on demand. Editing one triggers no publish on its own; the next publish
rebuilds the module, which is cached against those files. Add the class or its directory to
`class_types.additional_directories` to watch it.

### `--source` on a watched contributor file can republish nothing

The watcher lists the file of each class, trait or interface a scanned composed class reads a `#[TsType]` from, and
`--source` on that file republishes the class's module. It cannot follow two edits:

- A contributor whose `#[TsType]` was just removed, or that no longer feeds a scanned class, is no contributor any
  more, so `--source` on it fails as not publishable and the module keeps its old `extends` list.
- A contributor that is itself a routed controller publishes its route file, so the module is not republished.

Run a full `ts:publish` after either edit.

### A composed class beside another publisher's files can lose its barrel export

A composed class's module shares its namespace's `index.ts` with any model, enum, API resource, form request or
broadcast event there. Those phases rebuild the barrel from their own files and the class-type phase, which runs after
them, adds its modules back, so a run that skips class types (`--only-enums`, say) leaves the module's export out, and
a file importing `{Basename}Type` from that barrel fails `tsc` until the next full publish. Each full run also writes
such a barrel twice, once without the module's export and once with it, so its mtime moves when nothing changed. A
composed class in a controller's namespace replaces the route barrel's `export { default … }` lines with its own, and
`ts:publish` warns. Keep composed classes in a namespace of their own.

### A composed module that spells its own name, or imports one name twice, fails `tsc`

A contributor whose `#[TsType]` spells the composed name, `{Basename}Type`, with no import makes the interface extend
itself (TS2310); with an import, the name is aliased instead. Two contributors importing one name from two modules
make the module import it twice (TS2300). `ts:publish` warns of both. Give the override an import or another name.

### A composed type fails `tsc` when a contributor is not an object type, or two contributors disagree on a member

The package reads only an override's spelling, so it cannot tell what the type it names holds. An override naming an
alias of a primitive or a union, such as `type Money = number`, fails the composed interface with TS2312; two
contributors declaring one member at different types fail it with TS2320; and an index-signature type beside a named
member of another type fails it with TS2411. A `Record<…>` override is never composed for that reason, but an alias of
one is. Give the class a `#[TsType]` of its own with `inherit: false`, so it publishes that override alone.

### A run that skips class types can import a composed module no run has written

A run whose `--only-*` flag skips class types, or a `--source` run on a file whose value is a composed class, writes
that file's import of `{Basename}Type` but not the module. When the class has just started to compose, as a new class
or one whose ancestor gained a `#[TsType]`, neither the module nor its barrel export exists yet, and the file fails
`tsc` with TS2305 or TS2307 until the next full publish. A route file importing a form request behaves the same way.
Run a full `ts:publish` after adding a composed class.

### A page that reads a composed type and a class named like it imports the name twice

An API resource, model or broadcast event aliases an enum or model that spells a composed class's `{Basename}Type`,
such as an enum `Chart`, whose type is `ChartType`. An Inertia page and shared data alias no import, so a page that
reads both ships two `import type { ChartType }` lines (TS2300), and a model metadata companion throws at publish time,
as with
[two same-named enums](#two-same-named-enums-collide-instead-of-aliasing-a-companion-throws-a-route-file-ships-invalid-ts).
Give the enum a distinct `#[TsEnum]` name.

### Two composed classes with one basename, read in one file, publish one name

Two composed classes named alike, such as `App\Ui\Chart` and `Vendor\Charts\Chart`, both publish `ChartType`. A
composed name rides the custom imports, which no file aliases, so a file reading both imports `ChartType` twice
(TS2300), a metadata companion throws, and the globals file reads both values as the first class's type. `ts:publish`
warns, naming the file and both classes, except under `--source`, which runs no such check and republishes the file
silently. Give one of them a `#[TsType]` of its own with `inherit: false`, so it publishes that override alone, or read
the two in different files.

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
[`PrefixedModelMetadataTransformer`](../tests/Fixtures/TsPublish/Transformers/PrefixedModelMetadataTransformer.php) does.

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

- **An `if` binds `$x` where its test is proven**: inside `if ($x instanceof Post) { … }`, in an `elseif` or `else`
  the earlier tests rule `$x` into, and after a guard such as `if (! $x instanceof Post) { return null; }` up to the
  end of its block, which a guard nested in a loop or `switch` may also leave by `break` or `continue`, as
  `BlockScopedBindingsResource` shows. A write to `$x` between the test and the end of what it narrows means no binding
  at all, even for reads before that write, unless it is an assignment through a member or key of `$x` that
  stores a constant its column holds, such as `$x->title = 'Draft'`, or names no column or relation, such as
  `$x->seen = true`.
- **Other conditions bind nothing**: an `||` of tests on two variables, an `&&` that fails, a `while` or `match`
  condition, and a read inside the `if` condition itself leave `$x` unnarrowed.
- **A ternary binds for the arm its test proves**: `$x instanceof C ? A : B` narrows `$x`, or a chain of property reads
  on `$this` such as `$this->imageable`, in `A`, and the negated test narrows it in `B`
  ([`TernaryHandler`](../src/Ast/Handlers/TernaryHandler.php)). A subject method `A` calls reads `$this->resource` as
  the one model a test on it leaves. An `if` binds no chain other than `$this->resource`: inside
  `if ($this->imageable instanceof Post) { … }`, `$this->imageable` reads the whole `morphTo` union.
- **A ternary whose true arm is the tested expression narrows only reads through it**: after
  `$v = $this->imageable instanceof Post ? $this->imageable : null;`, `$v?->getKey()` reads `Post`, but `$v` itself
  publishes the whole `morphTo` union, as `NarrowedImageableResource` shows.

On an API resource, an `if` narrows `$this->resource` as it does `$x`, for each value inside a block its test proves,
so a resource collection's items read as the paginator a block tests. The class of the first `if` that tests
`$this->resource` alone, in either polarity, also holds for the whole method
(`ResourceAstAnalyzer::resolveInstanceOfType()`): it is what `$this->resource` holds on a resource with no backing
model, and a call `JsonResource` forwards, such as `$this->m()`, tries it ahead of the backing model on a model-backed
resource too. So a resource that guards on it needs no `#[TsCasts]` for what the guard proves. A ternary that tests a
resource with no backing model for two or more models still publishes `null` for an attribute its arm reads, such as
`$this->title` (#127).

### A shape whose values name a class loses those values, in one of two ways

The method-body fallback, a docblock array shape and a value object's own shape carry an override's import, but no
model's or enum's. Each keeps a member naming a class or an enum with an override, such as `chart: Chart`, or a value
object whose shape keeps one, with its imports, and loses a member naming any other class, such as a model. The body
fallback (`MethodReturnTypeResolver::bodyType()`) publishes such a value as `unknown` and keeps the rest of the shape,
as [Receiver types](./components/receiver-types.md#the-body-fallback-carries-no-fqcn-channel) details. A docblock shape
degrades one leaf: `@return array{owner: User}` publishes `{ owner: unknown }`, or a tuple member, `list{User, int}` →
`[unknown, number]`, while `Record<string, User>` has nothing to recurse into and degrades whole. Cast the property with
an import-aware `#[TsCasts]`, or give the method a native return type that names the class. A member whose union spells
a model or enum beside an override, such as `Person|User`, is `unknown` too, even where the two spell one name.

A member whose override names a type with no import, such as `#[TsType('UiLocal')]`, is `unknown` in a docblock shape
and a value object's shape, though it publishes as written elsewhere. In the body fallback it takes the import another
member brings for that name, or is `unknown` when none does. Give the override an `import`.

### An open shape publishes only its named keys

An open shape, `array{a: int, ...}`, publishes only its named keys, and an open list shape publishes `unknown[]`.
An open positional shape, `array{int, ...}`, publishes `unknown[]` too, though it may carry string keys and encode as an
object.

### An index signature its body types can fail to compile beside a key it cannot take in

`IndexSignatureReconciler` gives a signature its body's value back wherever a docblock fill or a same-pattern union
could conflict with a neighboring key, and under any extends clause, including one from `ts_extends.resources` or
`ts_extends.broadcast_events`, since it cannot see inherited keys. The body value can still fail `tsc`: TS2411 when a
key the pattern matches cannot take it, such as `main_tag: PostResource` beside
``[key: `${string}_tag`]: string | undefined``, and TS2413 when the pattern sits inside another signature's that
rejects its value. Where the union is declined, by this reconcile or a later one, and the entries' body values cannot
join, the signature publishes the last entry's value alone. A publisher warns about each matched key that keeps the
union out; type the key, or rename it out of the pattern. The rules are under
[Index signatures](./components/resource-ast-analyzer.md#index-signatures-are-reconciled-with-the-keys-beside-them).

### `Model::toArray()` on a receiver publishes `Record<string, unknown>`, deliberately

`$model->toArray()` in value position publishes `Record<string, unknown>`, as `except($keys)` does, and a relation's
`$this->comments->toArray()` a list of it, because the relations a model serializes are whatever is loaded at runtime.
A model with no attributes encodes `[]`, which TypeScript does not assign to that `Record`. An override of `toArray()`,
or an abstract model's, publishes `unknown` on a receiver, even when it declares a shape. `$this->resource->toArray()`,
and a model-less subject's `$this->prop->toArray()`, publish what the override declares, and an abstract model's
`$this->resource->toArray()` publishes `unknown[]`. The spread form is typed:
`[...$user->toArray(), 'flag' => true]` publishes `Omit<User, 'flag'> & { flag: boolean }`.
[Receiver types](./components/receiver-types.md#receiver-rules) gives the rule.

### A variable the package cannot name, holding a non-collection with its own `map()`, publishes a list

`$bag->map(fn (Post $p) => ['id' => $p->id])` publishes `{ id: number }[]` when nothing names the class `$bag` holds,
such as a container binding or a helper with no declared return: the model hint is read as the element of a collection.
A hand-written bag whose own `map()` keeps string keys sends an object. Name the class, by a return type or an inline
`@var`, and the bag's own `map()` answers. A local whose one write is an array literal publishes `unknown`, since PHP
throws there.

### An index-signature model with a shared key loses its attribute types in `{Model}All` and `{Model}AllResource`

A model that extends an index-signature type, such as `#[TsExtends('Record<string, unknown>')]`, gets
`{Model}All extends Omit<{Model}, 'key'>, {Model}Relations {}` from the `model-split` template when a relation shares
`key` with an attribute. `Omit` over an index signature keeps only the signature, so every attribute of `{Model}` reads
as `unknown` in `{Model}All`, and in `{Model}AllResource`, whose `Omit<{Model}Resource, 'key'>` loses the same keys.
Read the attributes from `{Model}` and the relations from `{Model}Relations`, which keep their types, or give the
relation a name no attribute takes.

### An `EnumResource` map kept as a `Collection` publishes no arm for the `[]` a null enum sends

`$this->members->map(fn ($m) => EnumResource::make($m->role))` publishes `AsEnum<typeof Role>[]`, but a member whose
nullable `role` is null sends `[]`: `JsonResource::filter()` turns a null-payload wrap into `null` only inside arrays,
and a `Collection` (no `->all()`, or `->values()`, or `filter()->map()`) serializes each wrap itself. End the chain with
`->all()` and it publishes `(AsEnum<typeof Role> | null)[]`, which is what Laravel sends.

### An API resource wrapping a relation the engine cannot type publishes it without `| null`

With `nullable_relations` on, `CategoryResource::make($this->parent)` publishes `CategoryResource` where `parent` types
`unknown` (an undeclared relation, a related model the run does not publish, or a `morphTo` with no resolvable target),
though Laravel sends `null` for a relation loaded as `null`.
`whenLoaded('parent', fn ($p) => CategoryResource::make($p))` does carry the `| null`. A `whenLoaded()` value that
collects such a relation, such as `whenLoaded('branches', CategoryResource::collection(...))`, adds none, since only a
to-many relation, which loads as a collection, fits there.
Declare the relation method, naming a `morphTo`'s targets in `@return MorphTo<A|B, $this>`, or publish the related
model, and the `| null` comes back.

### A mapped API resource read through `toArray()` or `jsonSerialize()` publishes `unknown`

`$this->members->map(fn ($m) => new ProfileResource($m->profile))->all()` publishes `(ProfileResource | null)[]`, since
Laravel sends `null` for a profile loaded as `null`. The same map ending in `->toArray()` publishes `unknown`, and a
helper returning that list `unknown[]`, though Laravel sends the same list; `->jsonSerialize()`, which resolves each
resource at once, publishes `unknown` too. End the chain with `->all()`.

### A relation method that declares no relation class and builds it through a helper is left out

A method with neither a relation return type nor a `@return` naming a relation class, whose body builds the relation
through the model's own helper rather than `$this->hasMany(...)` or another of Laravel's builders, is never read as a
relation, as in Laravel's `model:show`. Its model interface leaves it out, and `whenLoaded()` over it publishes
`unknown`. Declare the class, such as `@return Ancestors<static, static>`, and it is read.

### A relation method whose `@return` is vaguer than its `@phpstan-return` is left out

The docblock reader takes `@return` before `@phpstan-return`, so a method declared `@return mixed` with
`@phpstan-return HasMany<Comment, $this>`, whose body builds the relation through a helper, is read as declaring no
relation class and is left out like the entry above. Name the relation class in `@return` too. Reading the tags as one
grammar would fix it.

### A method call on a `MorphTo<Model, $this>` relation publishes `unknown`

`$this->assignable->label()` publishes `unknown` in every spelling when the relation names no target, even if the
resource's own model declares `label()`, since the related model could be any. Name the targets in
`@return MorphTo<A|B, $this>`, or declare the inverse `morphMany`/`morphOne` on each target, which types it too.

### A `whenHas()` or `whenAppended()` value the engine cannot type publishes `unknown`

[`ConditionalMethodHandler`](../src/Ast/Handlers/ConditionalMethodHandler.php) publishes `unknown` for
`$this->whenHas('title', fn ($t) => json_decode($t))`, not the `title` column's `string`, because Laravel returns what
the value returns and a plain title decodes to `null`, as `WhenHasValueResource` pins. Give the closure a return type,
such as `fn ($t): ?string => json_decode($t)`, and the key types from it; a `@return` docblock on the closure does not
type it. Inside a ternary or another union the `unknown` arm is left out instead
([above](#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm)), so
`$c ? $this->whenHas('title', fn ($t) => json_decode($t)) : null` publishes `null`. `whenExistsLoaded()`,
`whenCounted()` and `whenAggregated()` still publish their flag or aggregate type for an untypable closure, so such a
key can claim a type the closure does not return.

### A global constant types from its value where `ts:publish` runs

`PHP_EOL`, `E_ALL` or a user-defined constant, in place or as a parameter default, types by the value's PHP type on the
machine that ran the publish. A user constant built from `env()` or config can change type between machines, and one
defined only at runtime, or only on another OS or extension, leaves the value `unknown`. Built-in constants are stable.

### On a class that is not an API resource, `$this->when()` publishes `unknown`

Only a `JsonResource` subject runs [`ConditionalMethodHandler`](../src/Ast/Handlers/ConditionalMethodHandler.php), as
[ResourceAstAnalyzer § Where things live](./components/resource-ast-analyzer.md#where-things-live) lists by profile.
On a broadcast event, a model metadata provider or any other class, the package reads `$this->when()`, `unless()` and
every other `when*()` call as that class's own method. So Laravel's `Conditionable::when()` publishes `unknown`,
required, as `ConditionableBroadcastEvent` pins. A resource built around the call is the exception:
`UserResource::make($this->when(…))`, or its `unless()` form, still publishes optional on any subject, because
`InspectsResourceCalls::isConditionalMethodCall()` does not check the subject. The resource rule is wrong off a
resource, because `Conditionable::when()` returns the callback's result, or the object itself, and the key is always
sent. Until the package types `Conditionable::when()`, write the key as a ternary, or type it with `#[TsCasts]`, adding
`'optional' => false` for an API resource built around the call.

### An aggregate's type follows the database of the machine that publishes

[`AggregateValueType`](../src/Ast/Values/AggregateValueType.php) types a `whenAggregated()` aggregate by the driver of the
model's connection on the machine that publishes, as column types follow that machine's schema. A `SUM()` of a decimal
column publishes `number | null` from SQLite and `string | null` from MySQL, and a DECIMAL column with no cast follows
the same rule, so publishing against SQLite for an app that runs MySQL keeps a `number` the response does not hold.
Publish against the driver production runs, or cast a plain DECIMAL column `decimal:N`, which publishes `string` on
every driver; the generation cache rebuilds when `database.default` or a connection's driver changes, even through its
`url`. Or give the alias `{relation}_{function}_{column}`, such as `order_items_sum_total_price`, a built-in cast on the
parent model whose published type is the value Laravel returns, such as `integer`, `float` or `decimal:2`, or declare it
with an accessor or `@property`, and that type publishes on every driver. A query-time `withCasts()` cannot be seen by a
publish.

### On SQL Server a numeric aggregate and every count publish `number`, though pdo_sqlsrv returns numbers as strings by default

Laravel's `sqlsrv` driver runs on pdo_sqlsrv, which returns a number as a string, or on pdo_dblib, which returns a
number, and `getDriverName()` is `sqlsrv` for both. So a numeric `whenAggregated()` aggregate publishes
`number | null` there, and `whenCounted()` and a `'count'` aggregate publish `number`; a date or text `MIN()` or
`MAX()` publishes `string | null`. `PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE` makes pdo_sqlsrv return an integer or float
as a number, but never a `decimal`, `numeric` or `money` value, so a decimal `SUM()` is a string either way. Give the
alias `{relation}_{function}_{column}` a built-in cast on the parent model whose published type is the value Laravel
returns, such as `integer`, `float` or `decimal:2`, or declare it with an accessor or `@property`, and that type
publishes on every driver; a count publishes `number` whatever its declaration, so an `integer` cast on its alias makes
the response hold one.

### A wrap set only for HTTP requests, or forced by `$forceWrapping`, is invisible

The package reads the live `$wrap` the app's service providers leave by the time `ts:publish` runs, so a provider's
`JsonResource::withoutWrapping()` or `wrap('items')` reaches every collection and every Inertia prop that wraps by
it. `ts:publish` is a console command, so a wrap set only for HTTP requests, in middleware, a controller or behind
`! $this->app->runningInConsole()`, is not seen, and the published types keep the key the booted app had. Set it in a
provider's `boot()` for the console too.

`JsonResource::$forceWrapping` (Laravel 12.45 and later) is not read. With it on, Laravel wraps a body that already
holds its wrap key again, so a collection whose `toArray()` returns `['data' => …, 'count' => 1]` is sent as a page
prop as `{"data":{"data":[…],"count":1}}`, while the package publishes the body. Type such a prop with `#[TsCasts]`.

### In a resource collection, `map()`, `pluck()` and `values()` on `$this->resource`, and a read on the item `first()` returns, publish `unknown`

Inside a collection, `$this->resource` holds the items in a `Support\Collection` or the paginator the collection was
given. The package types its value, `first()`, `last()`, `count()`, `only()`, `except()` and the paginator's own
methods, not a collection pipeline on it, and not a member of the API resource `first()` returns, such as
`$this->resource->first()?->title`. Type such a key with `#[TsCasts]`.

### A resource collection prop over a paginator the engine does not type publishes no pagination

An Inertia prop or shared value reads its paginator's kind from a query chain rooted at a model, such as
`Post::query()->simplePaginate()`, or a local variable assigned one. Over a query builder variable
(`$query->paginate()`), a controller method (`$this->page()`) or an injected service's method, `new PostCollection(…)`
publishes `PostCollection` and `PostResource::collection(…)` `AnonymousResourceCollection<PostResource>`, though
Laravel sends `links` and `meta` too, and such a paginator passed bare publishes `unknown`. Build the paginator in the
props, or type the prop with `#[TsCasts]`.

### Over a paginator, a resource collection whose `toArray()` returns `$this->resource` sends the paginator's own array

A collection whose `toArray()` returns `$this->resource`, `$this->resource->toArray()` or
`$this->resource->jsonSerialize()` publishes `{ data: R[] }`, as a body-less one does. Given a paginator,
`$this->resource` is the paginator, so Laravel sends its own array, `current_page`, `data`, `first_page_url` and the
rest, beside `meta` and a `links` merged with the paginator's numbered links. A page prop over it still publishes its
paginator's pagination type, such as `& ResourcePagination`, or `JsonResourcePaginator<R>` under `$wrap = null`.
Return `$this->collection`, which Laravel sends as `{ data, links, meta }`, or named keys such as
`['data' => $this->collection]`.

### A paginated resource collection's own `meta` or `links` key is merged into Laravel's pagination block

Over a paginator, Laravel merges the `meta` and `links` a collection's `toArray()` returns beside its wrap key, such as
`data`, and those its `with()` and `additional()` add, into its pagination block with `array_merge_recursive()`. A
`toArray()` key the block does not send, such as `meta.kind` or `links.self`, is typed from the collection's own body; a
key both name is sent as a list, so a `total` you add to `meta` is sent as `[3, 3]` while the package publishes
`number`. Without the wrap key, Laravel nests the whole body under `data` and nothing of it merges. Name the key
differently, or drop the duplicated key from `$default` in `paginationInformation()`, such as
`unset($default['meta']['total'])`. Do not build a block with other keys there: the package does not read
`paginationInformation()`, so the published pagination type would no longer match.

### A resource prop's `additional()` and `with()` keys are not published

Inertia sends a resource prop as its whole response, beside the keys `additional()` and the resource's `with()` add.
`R::make($x)->additional(['meta' => …])` publishes `{ data: R }` and `(new XCollection($items))->additional(…)`
`XCollection`, without `meta`, and `R::collection($items)->additional(…)` publishes `unknown`. Such keys also make
Laravel nest the body under `data` after `JsonResource::withoutWrapping()`, where the package publishes it bare. Type
the prop with `#[TsCasts]`.

### A `->resolve()`d resource prop publishes the API resources its body holds bare

`R::make($x)->resolve()` as an Inertia prop publishes `R`, the keys `resolve()` returns. Inertia then sends each API
resource still inside them as its response, so a key such as `'author' => new UserResource($this->user)` arrives as
`{ data: … }` while `R` types it `UserResource`. Pass the resource itself, or type the prop with `#[TsCasts]`.

### Props a collaborator builds publish their API resources bare

`Inertia::render('Page', $this->props->build())` types the props from what the collaborator's method returns, where an
API resource is the value a nested resource key holds: `'warehouse' => new WarehouseResource($w)` publishes
`WarehouseResource`, though Inertia sends `{ data: … }`. Build the props array in the action, or type the props with
`#[TsCasts]`.

### Adding a resource collection that a `toResourceCollection()` guess picks needs a `--fresh` publish

The generation cache rebuilds a file when a file it read changes, but an argument-less `toResourceCollection()` can pick
a collection class that did not exist when the file was cached. After adding a key-preserving `CategoryCollection`, a
cached run keeps `siblings: CategoryResource[] | null`, where `--fresh` publishes
`Record<string, CategoryResource> | null`; a page prop that calls the guess keeps its old type the same way. Run
`ts:publish --fresh` after adding such a class.

### A JSON:API resource's sparse fieldsets, dynamic `toType()` and runtime type literal are not typed

A JSON:API resource publishes the resource object Laravel sends with no `?fields[…]` in the request, so every
attribute it always sends is required, though a sparse fieldset sends only the ones it names; read such a response
through `JsonApiSparse<R>` from `@tolki/types`. A `toType()` that returns anything but one string literal publishes
`type: string`. The literal is computed when `ts:publish` runs, so a morph map or `Pluralizer::useLanguage()` set only
later is not in it, and the generation cache cannot see either. Keys `additional()` adds beside `data`, and the outer
key a subclass that redeclares `$wrap` adds, are not published.

After `JsonResource::withoutWrapping()`, a JSON:API collection prop publishes the bare list Laravel then sends, though
a request with `?include=`, or after `JsonApiResource::configure()`, still sends it under `data` beside `included`. A
relationship whose resource is a plain API resource publishes `type: string` and adds nothing to `included`, since
Laravel throws building it on an include. A `toRelationships()` resolver written `SomeResource::CONSTANT` or
`self::class` is read as no resolver, so the related model's own resource names the relationship. On Laravel 12 a
`toRelationships()` closure that returns a resource throws on an include, while the package publishes Laravel 13's
shape.

### On Laravel 12.45 and later, a plain API resource's `$attributes` and `parent::toAttributes()` are not read

From 12.45, `JsonResource::toAttributes()` returns an `$attributes` property in `toArray()`'s place, so a plain
resource with one sends that array as it is, a list staying a list, while the package publishes `toArray()`'s keys. A
`...parent::toAttributes()` spread in a plain resource's own `toAttributes()` publishes no key. Rename the property, or
return every key from `toAttributes()` itself.

### An overridden `resolve()` or `jsonSerialize()` changes what Laravel sends, and the package reads neither

An API resource publishes the keys its `toArray()` returns. Laravel sends its `resolve()` as the response's `data` and
as each item of a resource collection, and its `jsonSerialize()` where another API resource nests it as a single value,
so a key either override adds, such as `[...parent::resolve($request), 'resolved' => true]`, is sent there and missing
from the published type. Put the keys in `toArray()`.

### A `__toString()` class with no public property publishes `Record<string, never>`; three others publish `unknown`

`json_encode()` writes such a class as `{}`, though an instance of a subclass can carry public properties, and an
abstract class publishes its own properties the same way. In another `__toString()` class, an untyped public property
with no `@var` publishes `unknown`, a class that allows dynamic properties or extends `ArrayObject` publishes
`Record<string, unknown>`, and a `jsonSerialize(): array` publishes `unknown[] | Record<string, unknown>` until a
`@return array{...}` shapes it. A `jsonSerialize()` that declares `mixed` or nothing and has no `@return`, a
`SimpleXMLElement` and a plain `DateInterval` publish `unknown`, because only a method body or the runtime value says
what they write. Declaring `jsonSerialize(): string`, or `@return string` on it, publishes `string`, and
`@return \DateTime` publishes PHP's date object.

### A `new` of an `Arrayable` or `Responsable` class, a model or a class with no public property publishes `unknown`

What `'summary' => new Summary` sends depends on who serializes it when `Summary` is `Arrayable`: its `toArray()` in
some places and its public properties in others (#117, and
[below](#an-override-publishes-in-every-context-though-some-send-the-classs-public-properties)). The same holds for a
`Responsable`, which an Inertia prop sends as its `toResponse()` data, and for an Inertia `ProvidesInertiaProperty`,
sent as its `toInertiaProperty()` value. `new Collection($items)` is the exception, typed as `collect($items)` is. A
model, whose JSON depends on its runtime attributes, and a class `json_encode()` writes as `{}` with no `__toString()`
publish `unknown` too; `stdClass` publishes `Record<string, unknown>`. Type such a key with `#[TsCasts]`, or give the
class a `#[TsType]`: a `new` of a class with an override, its own or an inherited one, publishes the override whoever
serializes it, as every other read of the class does. A model's override does not apply to a `new` of the model.

### An override publishes in every context, though some send the class's public properties

An override is the type you declare, and every read of the class publishes it. For an `Arrayable` class that is not
`JsonSerializable`, what is sent takes one of two shapes, depending on where the value sits:

- **Its `toArray()`**: an Inertia prop, and each value inside an array prop; a broadcast event's top-level public
  property; a model attribute, cast or accessor, and each item of a collection attribute; and a `Collection` of the
  class, wherever it sits, which is sent as each item's `toArray()`.
- **Its public properties, `{}` when it has none**, as `json_encode()` writes them: a value in an API resource's
  `toArray()` array or in an event's `broadcastWith()` array; a value inside an array or value object an event's
  property holds, such as `UiBoardUpdated`'s `charts` and `board`; and a value inside any value object.

An override written for the `toArray()` shape therefore matches only the first list. Implement `JsonSerializable`
returning `toArray()`, or wrap the value in an API resource, and every context sends one shape.

### A class's override skips an interface it repeats from its parent or beside an interface that extends it

PHP's reflection lists an interface once, so an interface a class repeats contributes its `#[TsType]` only in the place
of the parent that also implements it, or of the interface that extends it. Where that one declares `inherit: false`,
the override is dropped: `class Badge implements Muted, HasIcon`, with `Muted extends HasIcon` declaring
`inherit: false`, publishes `Muted`'s override alone, and so does a class implementing `HasIcon` under an
`inherit: false` parent that implements it too. Elsewhere the interface moves down the order, which reorders a composed
type's `extends` list and, where a contributor cannot be extended, can change which one wins as nearest:
`class Child extends Base implements HasIcon`, where `Base` also implements `HasIcon` and declares
`#[TsType('string')]`, publishes `string` with a warning, though `Child` lists `HasIcon` before its parent. Declare the
interface's type in the class's own `#[TsType]`, or drop the `inherit: false`.

### A Carbon date publishes `string` or `Date`, whatever `serializeUsing()` or `serializeDate()` writes

A Carbon date, and a date a class cast or new-style accessor returns, publishes the `Carbon\Carbon` date-map entry:
`string`, or `Date` under `timestamps_as_date`. `Carbon::serializeUsing()`, a factory's `toJsonFormat` and a model's
`serializeDate()` override can write something else, such as a number, but they are application code the package
does not run. Type such a property with `#[TsCasts]`.

### A `DateTime` entry in `custom_ts_mappings` retypes the `datetime` column, never the class

The map's keys are lowercased, so `DateTime` and the `datetime` column type share one key. The entry applies to the
column type, and the `DateTime` class keeps the date object `json_encode()` writes. A `DateTime` that a class cast or
new-style accessor returns takes the entry, since `Model::toArray()` writes it as a date column. An entry for
`DateTimeImmutable` or a `DateTime` subclass does retype that class.

### A plain date from a new-style accessor or class cast publishes what `Model::toArray()` writes, in every context

A new-style accessor or class cast that declares a plain `DateTime` or `DateTimeImmutable` publishes the date type,
because `Model::toArray()` runs `serializeDate()` on it. An API resource that reads the attribute directly sends PHP's
date object instead, yet publishes the same type. The reverse holds for a new-style getter with no declared type whose
body returns a plain date: it publishes the object, though `toArray()` writes a string. A Carbon date writes a string
in both places, so declare one, or type the key with `#[TsCasts]`.

### A serializing class cast publishes what `serialize()` returns, in every context

A class cast whose caster implements `SerializesCastableAttributes` publishes the type its `serialize()` declares,
because `Model::toArray()` writes that value. An API resource that reads the attribute directly sends the `get()` value
instead, yet publishes the same type. Type such a key with `#[TsCasts]`.

### A closure nothing calls, and some first-class callables Laravel or Inertia call, publish the wrong type

`'k' => fn () => 1` publishes `number`, though Laravel sends the `Closure` as `{}`: only a first-class callable such
as `strlen(...)` is read by where it sits. Props an Inertia controller delegates to another class's method, or a
helper method the action calls, are read as that method's own values, so a first-class callable there publishes
`Record<string, never>`, and `ts:publish` warns, though Inertia calls it. Use a closure such as
`fn () => $this->label()` there instead. A closure held in a local and passed to a helper, read as a value or spread,
publishes what it returns, as in `$f = fn () => 1; $this->hold($f)` where `hold($v)` returns `['v' => $v]`:
`{ v: number }`, though Laravel sends `{ v: {} }`. Type that key with `#[TsCasts]`.

### A callable in a docblock publishes `unknown`

`callable(int): string`, alone or as a shape member, publishes `unknown`, because what it sends depends on who
serializes it: an API resource writes a `Closure` as `{}`, Inertia calls it, and a callable string or array is sent as
itself. Type the key with `#[TsCasts]`.

### A `Collection` beside an array an API resource lists or under numeric keys, and the arrays one holds, publish as lists

An API resource re-indexes only the arrays it reaches, so a `collect()` value keeps its numeric keys, alone or under a
named key, but `['inner' => collect(['6' => $this->title]), 'ids' => [1 => 2]]` publishes
`{ inner: string[]; ids: number[] }`, and `[6 => collect(['6' => $this->title])]` publishes `string[][]`. The arrays a
`Collection` holds list the same way: `->map(fn () => ['1' => …])` publishes `string[][]`, though Laravel sends
`{ "1": string }[]`. Type the key with `#[TsCasts]`.

### An object's own JSON beside an array an API resource lists, under numeric keys or in a spread, publishes as lists

Where `schedule()` returns a `JsonSerializable` keyed only by numbers,
`['week' => $this->schedule(), 'days' => [1 => 'mon']]` publishes `{ week: string[]; days: string[] }`,
`[1 => $this->schedule()]` publishes `string[][]`, `$c ? $this->schedule() : [1 => 'a']` publishes `string[]` and
`['o' => [...collect([$this->schedule()])]]` publishes `{ o: string[][] }`, though Laravel sends the object with its
keys. Alone, under a named key, or beside `null` or a scalar, it keeps them. An `(object) [...]` cast is such an
object: `['week' => (object) [1 => 'mon'], 'days' => [1 => 'mon']]` publishes `{ week: string[]; days: string[] }`.
Type the key with `#[TsCasts]`.

### A model's `toArray()` keeps the numeric keys of an `Arrayable` value whose class the package cannot name

`Model::toArray()` writes an `Arrayable` cast or accessor value as its `toArray()` array, which an API resource lists.
A resource that sends its model's `toArray()`, or spreads a related model's, lists it only where the value's class can
be named: a `get(): mixed` cast documented `@return Rota`, a native union of such classes or
`AsCollection::using(Collection::class, Rota::class)` keeps the numeric keys, as earlier releases did. Declare the
cast's native return type, or type the key with `#[TsCasts]`.

### A resource collection's items publish as a list or a record by `#[PreserveKeys]`, not by the keys they arrive with

A resource collection publishes `R[]`, or `Record<string, R>` when it preserves keys through `#[PreserveKeys]` or
`$preserveKeys`. Laravel sends the items as an array or an object by the keys the caller passed in, which the package
never sees. Given items keyed by `keyBy()`, a collection that does not preserve keys sends an object while publishing
`R[]`. Given a list, one that preserves keys sends an array while publishing `Record<string, R>`. In a custom
`toArray()`, `$this->collection` and `$this->resource` keep their input's keys whatever the class preserves, so items a
`filter()` left sparse are sent as an object too. Give `#[PreserveKeys]` (or `$preserveKeys`) to a collection that
receives keyed items, call `values()` on filtered items before passing them in, or type the key with `#[TsCasts]`.

### A collection that does not preserve keys lists the arrays of an element that does

A named `ResourceCollection` without `$preserveKeys` runs its own filter over the elements it resolves, so it
re-indexes the numeric-keyed arrays of an element resource that preserves keys. The element's interface still shows
them keyed. Give the collection `$preserveKeys` too, or collect through the element's own `collection()`.

### A list a key-keeping sort reorders publishes as a list inside a `Collection` in an API resource

`asort()`, `uasort()` and the other sorts that keep keys leave a list keyed out of order. An API resource re-indexes
such an array back into a list, so it publishes `string[]`. Inside a `Collection`, as in `collect($tags)`,
`collect(['x' => $tags])` or a `->map()` over it, the resource never re-indexes the array, so Laravel sends it keyed, as
an object, while the type stays `string[]`. Use `sort()`, or `array_values()` before the collection wraps it.

### An API resource whose top-level keys are all numeric publishes an interface of quoted keys

`toArray()` returning `[42 => …]` publishes `{ "42": … }`, though Laravel sends the resource as a list.

### An unwrapped API resource keeps its top-level int keys, which Laravel renumbers only when it is the response

`toArray()` returning `['id' => 1, 42 => 'a']` on a resource sent without a wrap key, one with
`public static $wrap = null` or, after a runtime `JsonResource::withoutWrapping()`, every one that does not redeclare
`$wrap`, publishes `{ id: number; "42": string }`. Returned unwrapped as the response (a resource with no `with()` or
`additional` data; otherwise `ResourceResponse::wrap()` nests it under `data`, where keys keep theirs), it is
`array_merge_recursive()`d and sends `{"id":1,"0":"a"}`; nested in another resource it keeps `"42"`. Which of the two
a resource is cannot be known statically. Use a string key.

### A `broadcastWith()` payload whose top-level keys are all int publishes an object

Where the broadcaster pulls `socket` out (Pusher/Reverb, Mercure) the payload is sent as a JSON list, and as an object
on Redis and Ably, yet publishes `{ "0": … }`. An Inertia page is never all int while the app's middleware merges
`parent::share()`, which always adds `errors`. Use a string key.

### A resolved resource that preserves keys, inside one that does not, shows keys the outer filter lists

`'tiers' => KeyedR::make($x)->resolve($request)` publishes `KeyedR`, whose interface keeps its numeric keys, but the
outer resource's `filter()` enters the array `resolve()` returns and re-indexes it.

### An explicit key written before a spread publishes its own type, though the spread's value wins

`['id' => 'x', ...$arm]` publishes `Omit<Arm, 'id'> & { id: string }`, though PHP lets the later spread write the
arm's own `id`. Every explicit key is subtracted from every spread arm, whatever their order. An array union reads as
that spread with its operands swapped, so `$arm + ['id' => 'x']` publishes the same. Write the key after the spread,
or on the left of `+`.

### An operand of `+` not typed as one object adds no keys beside a keyed literal

`$this->published_at->toArray() + ['x' => 1]` publishes `{ x: number }`, though the response also carries every key the
left side sets. Only an operand typed as one object, such as a `Record` cast or a relation's `only()`, joins the union
as an arm of its own type. A list such as `explode()`'s, an enum list, a union of a shape and `[]`, or a `when()` value
adds nothing. A local written once reads as the array it holds, in a method's whole `return` as in a value, a merge
closure's own included. Type the operand with a docblock, or the key with `#[TsCasts]`.

### A `+` default for a key only some paths set is left out

`if ($c) { $d['label'] = 5; } $d += ['label' => 'none'];` publishes `label?: number`, though Laravel always sends
`label`, as `5` or `'none'`: a key the left side of `+` holds drops the right side's copy, even where the left's is
optional. Write the default first, `$d = ['label' => 'none'];`, and the branch's write after it, which publishes
`label: string | number`.

### A named key a `when*()` call can drop beside numeric keys publishes the keyed shape

`['label' => $this->when($c, 'x'), 42 => 'y']` publishes `{ label?: string; "42": string }`. When `$c` is false the API
resource drops `label`, finds only numeric keys left, and sends `["y"]`. Keep numeric keys apart from a conditional
named key.

### A list holding a `when*()` value publishes a list, where a resource that preserves keys or a `collect()` keeps the gap

In an API resource that preserves keys, `[$this->when($c, 'x'), 'y']` publishes `string[]`, though a false `$c` sends
`{"1":"y"}`. `collect([[$this->when($c, 'x')]])` publishes `string[][]` in any resource, though the filter never enters
a `Collection`, so the missing value encodes as `{}`. Keep conditional values out of lists there.

### An untyped property's default ignores writes the scan cannot see

An untyped property publishes its default's type joined with each value its class, an ancestor, a trait or the published
subclass writes it, whole or by a compound operator, typed in the writing method's scope; an append joins the elements
of its list, and a literal string key joins its object as an optional key. It publishes `unknown` for any other write:
an `unset()` of the property or an element, a computed or nested key, an append onto an object or beside a key write, a
by-reference `foreach`, a write a closure or arrow function in the method holds, a write in a trait's method the class
overrides, or a value the engine cannot type, such as an untyped parameter or a `mixed` call. A helper that a write
calls to read the property back publishes it `unknown` where that helper is read first. A by-reference parameter on a
receiver the scan cannot resolve, such as `$other->fill($this->tags)`, and a write from outside the class both change
the value Laravel sends while the published type stays the default's. So does a write by a subclass of a receiver's
declared class, such as the object behind `$this->holder()->views`. Declare the property's type, or give it a `@var`
docblock.

### A receiver's body reads `static` as its declared class

`'start' => $this->counter()->startConst()`, where `counter(): Holder` returns a subclass and `startConst()` returns
`static::START`, publishes `Holder::START`'s type, though Laravel sends the subclass's. A write naming `static` in such
a body publishes `unknown` instead. Declare the method's return type.

### A column a body writes publishes its declared type, not the value written

A read of a column the body wrote through the model, such as `$this->resource->title = null;` then
`'title' => $this->title`, publishes the column's declared type, `string`, though Laravel sends `null`: the package
reads a column from the model's declaration, never from a write to it. On a local holding a new model, a write the
package cannot show the column holds leaves the local unread instead, so `$p = new Post; $p->title = null; $p->title`
publishes `unknown`.

### A guarded read past a write a mutator stores into its column publishes the column's type

After `if (! $x instanceof Post) { return []; } $x->rating = 5;`, `$x->title` publishes `title`'s declared `string`,
though a write-only `Attribute::make(set: …)` or a `__set()` that stores `rating` into `title` makes Laravel send `5`. A
write to a member no column or relation of the proven class types keeps the proof, and the package never reads what a
mutator stores. State the key's type in `#[TsCasts]`.

### A parameter reassigned to a value the package cannot name keeps its old binding

A parameter keeps its binding past a write whose value names no class: after
`function (User $a) { $a = $this->untyped($a); return $a->name; }` the read still takes `User`'s `name`, whatever the
helper returns. Annotate the assignment, `/** @var Comment $a */`, and the read follows it. A parameter passed a value
takes what its own `$t = …;` writes, so `$t = json_decode($t)` publishes `unknown`. A value read of a model parameter
does too where the write is a typed value naming no class, so `$a = $a->name; return $a;` publishes `string`, while
`$a = $this->untyped($a); return $a;` keeps `User`. Past such a write nested in a block, a value read takes the earlier
value or the one written, and only the earlier one where the package cannot type the written one:
`$t = trim($t); if ($c) { $t = json_decode($t); }` publishes `string`. Where it cannot type the earlier one, only the one
written is read.
`$a->comments->find(1)?->content` reads the comment's `content`, but the call alone publishes `unknown`, as `first()`
does on a parameter's relation.

### An abstract model's key type misses a subclass the run does not publish

`getKey()`, `modelKeys()` and `Auth::id()` on an abstract model publish the key type the class declares, joined with
each concrete subclass the run publishes. A subclass the run leaves out, which the collector skips or a package
holds, adds nothing when it keys differently. So does a key a constructor sets through another method. A
`uniqueIds()` override whose list is not a literal publishes `unknown`. A concrete receiver class never looks at its
subclasses.

### A model saved after `new` or `replicate()` still publishes `getKey()` with `| null`

The package does not follow `save()`, so the key stays `number | null` although the runtime holds a number.

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

Several handlers in [`ResourceExpressionHandlers`](../src/Ast/Dispatch/ResourceExpressionHandlers.php) claim `MethodCall`, so
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

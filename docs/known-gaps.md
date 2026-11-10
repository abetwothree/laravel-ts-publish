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
drop). A parameter written in a block drops the value it came in with the same way: `transform($this->opaque(), …)`
whose callback may set `$value = $comment->id` publishes `number`. So does the join of a local's branches, for a value
one branch writes, and a member read past such a join: past `if (…) { $user = $this->opaque(); }`, `$user->name` reads
the old model's member alone, and so does a class only an inline `@var` there names that cannot answer the read, a tag
over `$this` included; a class a `static`, `self` or `parent` tag over `$this` names that declares the member but cannot
type it leaves the read `unknown` instead, unless the write ends a parameter's block. Widening the union to `unknown`
would be more honest but less specific. Where the drop leaves only `null` or `never[]`, the run output warns, naming the
line and the arm left out, and so does a parameter's, a branch join's or a member read's join drop, whose published type
may not admit every value Laravel sends; a class the generation cache serves prints nothing until a `--fresh` run. A
spread helper's key or a model accessor's getter body left with only that `null` publishes `unknown` instead, while a
getter that only ever returns `null` still publishes `null`. A method read as a value is the exception: one return it
cannot type leaves the whole call `unknown`, or the vague type it declares. So is `$this->resource` read whole under its
own `instanceof` test, whose untypable class leaves the whole ternary `unknown`. Type the arm with a return type, a
`@return` docblock or `#[TsCasts]`, and it comes back; a cast on the key types it but leaves the warning. A local's
inline `@var` replaces a whole `null` the drop left, a whole `never[]` where the tag lists, and a literal's member
its own item's drop left where the tag's member admits it; a member a nested literal, a helper or another local left
stays as it is.

A spread helper's or a merge call's join drops a branch whose `@return array{…}` fill names a class whose name another
branch spells outside a literal, such as `k: Person|User` beside `'k' => $this->user`, or
`k: array{a: Person, p: Post}|User` beside `'k' => $this->post`: no join can say which class each of those tokens is, so
the key publishes the other branch's type alone, as it did before the fill, and the fill's arms, the documented
override's among them, are left out with no warning.

### A list literal holding an element no rule types publishes `unknown[]`

`[$this->id, json_decode('{}')]` publishes `unknown[]`, not `number[]`. A list element is in every response, so unlike a
union arm it is never left out ([AST engine § Dropped union arms](./components/ast-engine.md#dropped-union-arms)). Type
the element with a return type or a `@return` docblock, or the key with `#[TsCasts]`, and the list takes its type.

### A method whose declaration and body type nothing publishes `unknown`, even when a model or wrapped class declares a typed namesake

`'label' => $this->label()` publishes `unknown` when the resource's own `label(): mixed` returns a value no rule types,
though the backing model declares `label(): string`. PHP runs the resource's method, so the model's type is only right
when the two bodies agree. The same holds for a class other than a model that `$resource` is declared to hold, by an
`@var` on `public $resource` or a tag such as `@extends JsonResource<PostStats>`: the model never answers for it, and a
method that class lacks publishes `unknown`, since Laravel throws. Give the method a return type or a `@return`
docblock, and the call takes it.

### A resource macro typed only by its body publishes `unknown` when its value depends on its arguments

A macro's body is read without what the call passes, so `$this->format($x)` on an untyped macro closure publishes
`unknown`. A closure declared outside a class method, such as in `bootstrap/app.php`, gets no body read, and a macro
registered after the application boots, or by a deferred provider, is not seen at all. Give the closure a return type. A
`@return` docblock works only on a closure declared inside a class, such as in a service provider's `boot()`, because
its short class names resolve against that class's file.

### `config()` on an absent key with no default types as null

`config('unset.key')` with no default publishes `null`, the value on the machine that ran the publish. A `config('key')`
or `config()->get('key')` read follows that machine's configuration, so a `.env` that differs from production changes
the type. The package runs inside the booted app, where the live value is the only answer
([`KnownFunctionCallHandler`](../src/Ast/Handlers/Calls/KnownFunctionCallHandler.php)). Only an absent key types from
its default. A key set to `null`, such as an `env()` value unset on that machine, types as `null` whatever the default.
A typed accessor such as `config()->string('key')` types from its declared return instead.

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

### A method on a collection the body builds can keep the analyzed class's element where Laravel maps the items

A method called on a collection the body builds, as in `new self($this->resource)->summary()`, publishes its items as
the API resource the built class collects, or as the analyzed class's element where that already is one, since Laravel
passes such items through
([built payloads](./components/resource-ast-analyzer.md#a-method-called-on-a-new-resource-types-its-payload)).
Two builds keep the analyzed class's element, though Laravel maps the items into the built class's own pick:

- raw items, as in `new self(collect([1]))`, where the analyzed class collects a subclass of that pick;
- a built class whose pick is a naming-convention guess the run does not publish.

Type the key with `#[TsCasts]`.

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
  a loop that never runs, leaves it unset. A branch that ends in a `throw` or another `return` stays out, but not one
  that ends in `abort()`, and a `continue` or `break` is not followed: after
  `foreach (…) { if (…) { $d['a'] = 1; continue; } $d['a'] = 'x'; }`, `a?: string` publishes, though Laravel can send
  `1`. A `switch` case, a `try` body and a `catch` are read in sequence instead: a key first set there is optional, and
  a key re-set there publishes only its last write's type.
- A `??=` of a key a helper's array, a parent `toArray()` or a parameter's argument holds optionally keeps the key's
  type, as over a `when()` value, though the key may be unset there and the default sent: after
  `$d = $this->base(); $d['k'] ??= 'x';`, `k?: number` publishes where `base()` sets `k` only in a branch.
- A variable the walk does not read completely is skipped, as any other non-literal return is: one whose base is
  `$this->resource->toArray()`, a helper or a parent `toArray()` that returns something the walk cannot read, or a
  literal with a `$this->`, `self::` or `static::` helper spread the analyzer declines, and one with a write the walk
  does not read, such as `$data[] = …`, a key no literal or literal local names, a nested key, `sort($data)` or any
  other by-reference call, or a `??=` to a key it does not hold. When no branch read completely has a key and every
  `return` is a literal or a variable, none inside a `try`, a `switch` or a bare block, it publishes the writes the walk
  reads instead, beside `[key: string]: unknown` for a helper read as a value whose base or key write the walk could
  not read; with a `return` of another kind, the first `return` is read. A merge closure that returns only a
  variable merges what the walk reads too, but nothing where an `unset()` the walk cannot name may remove a key.

An `Inertia::render()` props local is read by the same walk, from its writes before the render call, unless every
write the render call sees assigns it a literal whole. It publishes no props where the walk declines, where a `+=`, a
`try`, a `switch` or a loop with a `continue` or `break` writes it first, where a value written to it reads a key of it
the engine cannot type, or where a callback holds the render call or the statement holding it writes the local.

Return a literal on each path, or state the keys in `#[TsCasts]`.

A helper that returns a parameter it writes keys into joins its writes onto the keys of the array it is passed, or of a
declared `array{…}`. It publishes only the keys it writes, beside `[key: string]: unknown` where it is read as a value
and alone where its result is the method's own return, when the argument is not one object literal (a `Record`, a list,
a `{…} | null`, or an array `json_decode()` returns), names a model no generated file exports, or holds an
`EnumResource` wrap or two same-named enums a level apart. An assignment inside another expression, a top-level
`unset()`, destructuring, `foreach` target or by-reference call of the parameter leaves only the helper's own writes,
and so does a rewrite of its values in place, `array_walk()`, a by-reference `foreach` or `Arr::set()`, inside a block
too; a `??=`, a key-keeping sort such as `ksort()`, `array_push()` and `array_unshift()` keep the argument's keys.
Inside a block, such a write, or an `=` of a value the walk cannot read, makes each key held before it optional, a
local's too, and, but for an `unset()`, publishes the shape open, unless it keeps every key, as those calls, a local's
rewrite in place and an `=` of `array_merge()` or `array_replace()`, either recursive, or `Arr::add()` taking the array
first, or of a `+` it leads, do. A local rewritten in place keeps its earlier value types, though `array_walk()` may
turn a number into a string. Declare `@return array{…}` on the helper to publish the exact shape.

A local rewritten through a by-reference parameter of a method on a receiver the package cannot name, such as
`$validator->normalize($data)`, or through `extract()`, keeps its earlier type. Such a write also leaves a non-null
proof made before it standing, so after `if ($y === null) { return []; } $cleaner->clear($y);` a read of `$y` drops
the `null` the call can write.

### A spread whose helper the engine cannot read whole can mistype the keys it writes

`['o' => 1, ...$this->opaque()]` publishes `o: number` even when `opaque()` can return `['o' => 'x']`. A `$this->`,
`self::` or `static::` spread whose helper the engine cannot read adds no key, so the keys before it keep their own
types; one read by its first return alone adds that return's keys over them, though a later return may send others.

### A helper that returns an empty `[]` on one path publishes an object shape, though `[]` encodes as an array

A method typed from its body, such as `if ($flag) { return []; } return ['a' => 1];`, publishes
`{ a?: number } | null` under a `?array` signature and `{ a?: number }` under `array`, but `json_encode([])` writes a
JSON array, not an object. `MethodReturnTypeResolver::shapeReadsEveryReturn()` reads the empty `[]` as a branch that
omits the keys. Under `?array`, return `null` on that path instead, which publishes `{ a: number } | null`. A declared
shape whose keys can all be absent, such as `array{a?: int}` or `array<'a'|'b', int>`, publishes the same object and
has the same gap: an empty array is sent as `[]`. So does `array_filter()` of a shape, such as
`array_filter(['a' => $title])`, which publishes `{ a?: string }` though a filter that keeps no member sends `[]`. A
`| never[]` arm would make `x.a` a TypeScript error, so the shape is published alone.

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
exceptions ([`KnownMethodRuleHandler::validatedKeyRule()`](../src/Ast/Handlers/Calls/KnownMethodRuleHandler.php)):

- **A key with a `*` segment publishes `unknown`**, because `data_get()` returns a list of every match, while the rule
  under `*` types one element. A direct `data_get($target, 'a.*.b')` declines for the same reason.
- **A dotted `#[TsCasts]` key is ignored**, as it is on the request, whose
  `FormRequestTransformer::applyTsCastsOverrides()` matches only top-level fields.
- **A dotted key under an overridden parent types from the rules**: under `#[TsCasts(['options' => 'MyOptions'])]`,
  `validated('options.default')` still publishes the `string` the override replaced. The handler cannot index into a
  hand-written TypeScript type, and declining would trade the disagreement for `unknown`.

### With two user models, the signed-in user publishes `unknown` past `auth()`, `Auth` and `request()`

`auth()->user()?->name`, `Auth::user()?->email`, `request()->user()?->name`, a bare `request()->user()`, and `user()` or
`id()` on `Auth::guard()` or `auth()->guard()` take the default guard's model only while every configured guard's
provider names that one Eloquent model. A route's `auth:admin` middleware makes the admin guard the default for that
request, so where another guard names another model either user may be sent, and these stay `unknown` until a
guard-aware reader lands. `$request->user()`, the bare `auth()->user()` and `Auth::user()`, `auth()->id()` and
`Auth::id()` keep the default guard's model, so behind such a route they publish that model's type. A guard whose
provider names no Eloquent model is not counted, though it can sign in another kind of user: Sanctum's token guard (its
tokenable is any `HasApiTokens` model), a `viaRequest()` or `Auth::extend()` guard with no provider, or a `database`
provider over another table. A named guard, `auth('admin')` or `Auth::guard('admin')`, publishes `unknown`. Type the
key with `#[TsCasts]`.

### Some unions of `EnumResource` arms publish the wrong enum shape

With `enums.use_tolki_package` on, the default, each of these misses what Laravel sends, in an API resource as on an
Inertia page or in shared data. `ValueResult::mergeUnion()` records no channel that tells their arms apart, so the
`AsEnum` rewrite cannot spell each arm as it is sent:

- A wrap beside a bare case of another enum, `$c ? EnumResource::make(Role::Admin) : Status::Published`, publishes
  `RoleType | StatusType`, though the wrap arm sends the `Role` object.
- A wrap beside a nested nullable bare read, `$c ? EnumResource::make(Status::Draft) : ($d ? Status::Published : null)`,
  publishes `AsEnum<typeof Status> | null` and misses the `1` the bare arm sends.
- A nested ternary over three enums' wraps publishes `AsEnum<typeof Role> | StatusType | VisibilityType` and imports
  neither bare name, which is `TS2304` in a consumer's build.
- Wraps of two enums that share a name fold into one `StatusType`, though each arm sends its enum's object.
- A wrap beside an array holding a bare case of its enum, such as `['s' => Status::Published]` in the other arm,
  publishes `{ s: AsEnum<typeof Status> }` for the array, which sends `{ s: 1 }`.

No workbench fixture has these shapes, so the token gate cannot see them. Override the key with a `#[TsCasts]` entry
that has an `import`.

### An `EnumResource` resolved to an array inside another API resource loses its numeric keys

`'priority' => EnumResource::make($this->priority)->resolve()` publishes `AsEnum<typeof Priority>`, but the outer
resource's filter walks into the array `resolve()` returns and re-indexes every value whose keys are all numeric, so a
method value the published enum holds as `{ "1": 1, "2": 2 }` arrives as `[1, 2]`. The filter never walks into a
resource object, so return `EnumResource::make(…)` itself and the keys arrive as the type says.

### A kept `collect()` of `EnumResource` wraps, or a map building one in a helper, publishes `| null` for the `[]` sent

`collect([EnumResource::make($this->role)])` kept as a `Collection`, and a kept map whose callback builds the wrap in a
helper, such as `->map(fn (User $u) => $this->roleWrap($u))`, publish `(AsEnum<typeof Role> | null)[]`, but Laravel
serializes a kept collection item by item, so a null enum's wrap arrives as `[]`. So does a wrap an event's static
helper builds, and `R::make($user)->toArray(request())` read for its value. End the chain with `->all()`, which sends
the `null` the type admits, or build the wrap inline in the callback, which publishes `| never[]`.

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

### A pure enum's `->value` read through an API resource wrapping it publishes `string | number`

`$this->resource->value` in a resource wrapping a pure enum, such as `UnitEnumResource.value`, publishes
`string | number`. A pure enum has no `value`: PHP reads the undefined property as `null` with a warning, which
Laravel's error handler turns into an exception. Read `->name`, which publishes the enum's `…Type`.

### Two classes that publish one name in one namespace collide

A namespace's barrel re-exports every name its modules publish, so two classes there that publish one name cannot both
be exported. Where one phase writes both modules to the barrel, as for two enums or for a composed class beside any
other module, it fails with TS2308; where two other phases share the directory, the later one's barrel drops the earlier
one's modules whatever their names ([§ A class beside another phase's classes loses its barrel
export](#a-class-beside-another-phases-classes-loses-its-barrel-export)). `laravel-ts-global.ts` declares both in one
namespace: a type alias beside another declaration of the name fails with TS2300, but two interfaces merge with no error
into one type holding both classes' members, failing with TS2717 only where they type one member differently. No alias
can settle it, because both names are the published ones. Two examples:

- An enum `Role` publishes the const `Role` and the type `RoleType`, so a second enum named `RoleType` beside it
  publishes a const with the same identifier. A backed enum's `…Kind` name collides the same way.
- A composed class `Chart` publishes `ChartType`, so an enum named `ChartType` beside it collides on its const. In the
  globals file both are interfaces, so they merge silently.

An enum's own name is both its const and, since #149, a type in its module. `Runner::warnOfCollidingPublishedNames()`
names each pair in a warning after the run, over every publisher but route controllers. The token gate counts neither
TS2308 nor TS2717, and a silent merge raises nothing. Across two namespaces the barrels and globals namespaces are
separate, and a file that imports both aliases an enum's const ([Import name registry §
Consumers](./components/import-name-registry.md#consumers)). Give one class a distinct name, such as
`#[TsEnum('AccessLevel')]` on `RoleType`, which renames both its const and its type, or move it to a namespace of its
own.

### An enum named like a global type the app spells without an import hides that type

Since #149 an enum's module exports a type under its const's name, so a file importing the const also binds that name as
a type. Const imports step aside for the global types the package writes bare (`Record`, `Pick`, `Omit`, `Partial`,
`NonNullable`, `Date`, `File`), but not for one the app spells without an import in a `custom_ts_mappings` entry, `#[TsCasts]`, a docblock shape,
`#[TsType]` or `#[TsExtends]`, such as `Blob`: an enum of that name types the property as the enum's values with no error, and neither gate sees it.
Give the enum another name with `#[TsEnum]`, or import the type where it is spelled.

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

`ModelMetadataAnalyzer::composeTypes()` imports an enum a provider body returns, under an import-less cast or not, from
the path its namespace implies, without checking `enums.excluded`, `#[TsExclude]` or `enums.additional_directories`.
When enum publishing leaves the enum out, the companion fails `tsc` with TS2305 or TS2307 instead of `ts:publish`
failing. Resources have `PublishedResourceRegistry` for this check. Enums have `PublishedEnums`, but only an enum's
`->value` and `->name` reads consult it, and a raw enum read such as this one does not. Publish the enum, or cast the
property with an import-aware `#[TsCasts]`.

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

### A class beside another phase's classes loses its barrel export

The enum, model, API resource, form request and broadcast event phases each rebuild their namespaces' `index.ts` from
their own files, so where two of them write into one directory, such as an enum `App\Models\Status` beside the models,
the later phase's barrel drops the earlier one's `export *` lines and importing `Status` from that barrel fails `tsc`
with TS2305. A form request or broadcast event shares a directory only when its `output_directory` is the main one.
Model metadata companions and composed classes keep the other phases' exports. Keep each kind of class in a namespace of
its own.

### A composed class beside another publisher's files can lose its barrel export

A composed class's module shares its namespace's `index.ts` with any model, enum, API resource, form request or
broadcast event there. Those phases rebuild the barrel from their own files and the class-type phase, which runs after
them, adds its modules back, so a run that skips class types (`--only-enums`, say) leaves the module's export out, and
a file importing `{Basename}Type` from that barrel fails `tsc` until the next full publish. Each full run also writes
such a barrel twice, once without the module's export and once with it, so its mtime moves when nothing changed. A
composed class in a controller's namespace replaces the route barrel's `export { default … }` lines with its own, and
`ts:publish` warns. It also warns when such a class publishes another export's name, which fails the barrel with
TS2308 ([§ Two classes that publish one name](#two-classes-that-publish-one-name-in-one-namespace-collide)). Keep
composed classes in a namespace of their own.

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
error. Bind a replacement helper class instead, from an application provider, whose binding wins because Laravel
registers package providers first:

```php
use AbeTwoThree\LaravelTsPublish\Support\TsNaming;

$this->app->singleton(TsNaming::class, MyTsNaming::class);
```

A subclass that overrode `importSortGroup()` or `$resourceTypeNames` extends `TsNaming` instead, where both are
protected. [Support helpers](./components/support-helpers.md) sets which class owns a helper.

Overriding `flattenTraits()` or `propertyDeclaringFileClass()` no longer changes what the package emits either, since
it finds the file declaring a member through [`DeclaringFile`](../src/Ast/Docblocks/DeclaringFile.php). An override
of `methodDeclaringFileClass()` still applies, for methods only; the file declaring a property has no replacement point.
An override of `resolveAccessorType()` or `resolveAccessorBodyType()` is not consulted where a helper read as a value
reads an accessor whole, whose getter's plain enum cases keep their literals there.

Overriding `resolvePhpstanTypeAlias()`, `balanceTypeDefinition()` or `braceAngleDepth()` no longer changes what the
package emits either, since every docblock reader looks an alias up through
[`TypeAliases`](../src/Ast/Docblocks/TypeAliases.php), which has no replacement point. `$phpstanTypeAliasCache` and
`$phpstanTypeAliasPaths` read its answers and take no write: a subclass that writes either throws.

Overriding `shapeTypeInfo()` no longer changes a value object's shape, since `shapeMemberTypes()` reads each member
through `docblockTypeInfo()`, which carries the class behind each token. Override `shapeMemberTypes()` instead, and
return `tokenFqcns` for each member naming an enum or a model, or that member publishes `unknown`.

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
  `$x->seen = true`. A chain of property reads on `$this`, such as `$this->imageable`, binds the same way, each
  spelling apart: a test on `$this->resource->imageable` narrows no `$this->imageable` read. Anything between the test
  and a read that may change the chain ends the proof, so the chain reads unnarrowed: a write to it or a chain it reads
  through, a write through a local or to one bound by reference (`$ref = &$this->post`, a by-reference destructuring,
  or any local once `extract()` or a variable variable runs), a write through another `$this` chain that sets a member
  the tested chain reads through, since two chains can hold one object, code no scan sees, and any call outside the
  known-safe list [`ThisPathWrites`](../src/Ast/Analysis/ThisPathWrites.php) keeps, which leaves out a call naming the
  subject's own or an ancestor's class, as PHP runs one on `$this`. One such call, such as `$this->reload()`,
  `PostResource::make($this->resource)` or a call on a local, publishes every read of the chain unnarrowed. Trusted,
  though each may change the chain: a gate or policy callback a `Gate` or ability check runs, even one that writes the
  model; a callable another `$this` property or another class's static property holds, handed to a call that takes a
  callback, such as `array_map($this->mapper, …)`; a call on global state, such as `auth()->user()->…`,
  `Auth::user()->…` or `request()->route('post')->…`; a generator made before the test and resumed after it; a
  `__destruct()` that rebinding or unsetting a local fires; and an implicit magic call, such as `__toString()`,
  `__get()` or `getIterator()`.
- **Other conditions bind nothing**: an `||` of tests on two variables, an `&&` that fails, a `while` or `match`
  condition, and a read inside the `if` condition itself leave `$x` unnarrowed.
- **A ternary binds for the arm its test proves**: `$x instanceof C ? A : B` narrows `$x`, or a chain of property reads
  on `$this` such as `$this->imageable`, in `A`, and the negated test narrows it in `B`
  ([`TernaryHandler`](../src/Ast/Handlers/Operators/TernaryHandler.php)). A subject method `A` calls reads
  `$this->resource` as the one model a test on it leaves.
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

### A shape whose values name a class can lose some of them

A value object's own `toArray()` or `jsonSerialize()` docblock shape carries an override's import, but no model's or
enum's. It keeps a member naming a class or an enum with an override, such as `chart: Chart`, or a value object whose
shape keeps one, with its imports, and degrades one leaf naming any other class, such as a model: a `toArray()`
documented `@return array{owner: User}` publishes `{ owner: unknown }`, or a tuple member, `list{User, int}` →
`[unknown, number]`, while `Record<string, User>` has nothing to recurse into and degrades whole. Cast the property with
an import-aware `#[TsCasts]`, or give the method a native return type that names the class. A member whose union spells
a model or enum beside an override, such as `Person|User`, is `unknown` too.

A value object's public properties keep a published model or an enum with its import, but read as that docblock shape
does where a member names an API resource or spells an override like the model or enum beside it, or where two enums
sharing a name alternate: `public Status $first; public CrmStatus $crm; public ?Status $last` publishes each `unknown`,
since a resolved type lists each enum once. A member spelling two same-named models, one of them unpublished, is
`unknown` too.

A helper read as a value publishes what the same literal publishes in place, but for a member whose class publishes
under a name another member's override imports, such as an enum typed `ChartType` beside a composed `Chart`: the key
holding the helper would spell both alike, so that member is `unknown`
([Receiver types](./components/receiver-types.md#the-body-fallback-carries-channels-as-an-inline-literal-does)).
Written in place, the literal spells both with the enum's alias, which types the composed class's value as the enum's.
Put the two under different keys.

A docblock shape keeps a model or an enum at any depth, with its import. Read by an API resource's helper, a property's
`@var` or a model's accessor, though, a shape that also names any other class, such as an API resource, or spells an
override like the model or enum beside it, such as `either: Person|User`, is read with no class, each leaf naming a
class `unknown`. So is one where a literal or a key spells the class's name:
`array{kind: 'User', r: User}` publishes `{ kind: 'User'; r: unknown }`, and `array{User: int, owner: User}` publishes
`{ User: number; owner: unknown }`. `share()`'s `@return` keeps them all, as does an API resource's `toArray()` or
spread helper where its `@return` fills a key the body left vague, and a metadata provider's fails the publish on any
class but an enum.

A member whose override names a type with no import, such as `#[TsType('UiLocal')]`, is `unknown` in a docblock shape
and a value object's shape, though it publishes as written elsewhere. Give the override an `import`.

### A docblock union of an override and a model spelled alike binds both to the model, outside `share()`

An API resource's helper read as a value, documented `@return Person|User`, where `Person`'s override is `User` from
another module, publishes `WorkbenchUser | WorkbenchUser`, and `@return list<Person|User>` a list of it, importing only
the model. The override arm then reads as the model and `tsc` passes, though Laravel sends a `Person` as its own JSON,
which the model's type does not describe. `share()`'s `@return` keeps each arm with its own import, and so does the
`@return array{…}` of a resource's `toArray()`, a spread helper or an Inertia page's props helper filling a vague key,
`array{a: Person, p: Post}|User` too, joined with any return or render branch whose type spells the names of the fill's
classes, `User` and `Post` there, nowhere but inside a literal, such as `'User'`. Beside a branch that spells one
elsewhere, such as `'k' => $this->user` or `'k' => $this->post`, no join can bind each token: `toArray()`'s and an
Inertia page's branches publish the key `unknown`, and a spread helper's or merge call's drop the fill
([above](#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm)). Spread into a nested
literal, the fill reads with no class ([below](#a-docblock-fill-spread-inside-a-nested-literal-keeps-no-class)). Type
the key with an import-aware `#[TsCasts]`.

### A docblock fill spread inside a nested literal keeps no class

A key a spread helper's or props helper's `@return array{…}` fills whole, as one its body left vague, keeps the class
behind each token only where the helper is spread at the top level of `toArray()` or of an Inertia page's props; a key
its body typed merges with the member onto its channels, and keeps its classes anywhere. Spread inside a nested literal,
a local or a helper read as a value, such as `'meta' => [...$this->fill()]`, the fill reads with no class: each leaf
naming a model, an enum or an API resource publishes `unknown`, at any depth, and an override's or a literal's text
stays. `array{n: Person, m: User}` folds to `{ n: User; m: unknown }`, and `array{k: User}` to `{ k: unknown }`, as
before fills carried classes. An enum-only or API-resource-only fill folds the same way, `array{s: Status}` to `{ s:
unknown }`, as does each arm of a ternary of spreads, `$c ? [...$this->a()] : [...$this->b()]`. A JSON:API resource's
attributes are one value too, so a fill there reads this way, `toAttributes()`'s own `@return` included. A value has no
channel for the class behind each token, so a model beside an override spelled like it, in the same object or a nested
one, would publish as that model; the fix is such a channel. Spread the helper at the top level, or type the key with an
import-aware `#[TsCasts]`.

### An untyped `map()` or `array_map()` parameter takes its element's class only from a property's `@var`

`array_map(fn ($r) => ['name' => $r->name], $this->recipients())`, where `recipients()` declares
`@return list<Recipient>`, publishes `{ name: unknown }[]`, and so does a local an inline `@var list<Recipient>`
declares, or `->map()` on a `@var Collection<int, Recipient>` property. A parameter declaring no type binds a list
element's class only where `array_map()` or `collect()` reads a property whose own `@var`, or promoted constructor
parameter's, lists it as an array (`ReceiverClassResolver::elementClasses()`). Type the parameter, `fn (Recipient $r)`,
or the key's `@return` docblock, which fills the member.

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

### The relations `load()` adds ride only the call's own value

`$post->load('author')` publishes `Post & { author: User }` as its own value only. A model read after a `load()`
statement (`$post->load('author'); return ['post' => $post]`), a step past the call (`->load('author')->first()`) and
`$this->comments->load('user')` in an API resource publish the model or list alone; under the default split model
template `post.author` then does not compile. A relation no type here admits is left out too: one a closure may narrow
(a call other than a filter, an order or a nested load, or a scope), and a `morphTo` under columns or a child. Only a
chain of steps known to leave relations alone is read: filters, orders and fetches on the model, its query or its
collection, a bare `fresh()` and the signed-in user, besides the eager loads, `makeHidden()` and `setVisible()` it
reads; after any other step, such as a callback, `fresh('author:id')`, `setRelation()` or the application's own method,
the model keeps its own type. What happens on another line, through a variable, in a global scope, in the relation's own
definition (`hasMany(Comment::class)->with('user:id')`) or in a route-binding override is not seen: `loadMissing()` then
keeps a relation already loaded with fewer columns, or a hidden relation is never written, so the member is typed while
its keys may be missing, or it may be `null` or absent, at runtime. `load()` changes the model it runs on, so where two
props load one relation of the same model differently, both send the last load, and the other prop's typed member can be
missing keys or `null` the same way. A loaded member meets a key the model's interface already declares: under the full
template `Post & { author: User | null }` reads as the interface's `author: User`, and a relation named like a column
reads as `string & Category`.
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

### An API resource wrapping a relation the engine cannot type publishes it without `| null`

With `nullable_relations` on, `CategoryResource::make($this->parent)` publishes `CategoryResource` where `parent` types
`unknown` (an undeclared relation, a related model the run does not publish, or a `morphTo` with no resolvable target),
though Laravel sends `null` for a relation loaded as `null`.
`whenLoaded('parent', fn ($p) => CategoryResource::make($p))` does carry the `| null`. A `whenLoaded()` value that
collects such a relation, such as `whenLoaded('branches', CategoryResource::collection(...))`, adds none, since only a
to-many relation, which loads as a collection, fits there.
Declare the relation method, naming a `morphTo`'s targets in `@return MorphTo<A|B, $this>`, or publish the related
model, and the `| null` comes back.

### A map of resources ending in `toArray()` or `jsonSerialize()` publishes `unknown` where an item could be either

`$this->members->map(fn ($m) => new ProfileResource($m->profile))->toArray()` publishes `(ProfileResource | null)[]`,
as `->all()` does. With `->jsonSerialize()`, a resource the callback returns bare is resolved at once, so it publishes
what that resource sends, `never[]` for an `EnumResource` around `null`, while one inside an array the callback returns
takes `| null` from `filter()`. The map publishes `unknown` where, under `->jsonSerialize()`, the callback returns a
resource on one path and an array on another, or, under either call, a list or a Record, such as a nested map: each may
be an array or a `Collection`, which these calls send apart. End the chain with `->all()`.

### A relation method that declares no relation class and builds it through a helper is left out

A method with neither a relation return type nor a `@return`, `@phpstan-return` or `@psalm-return` naming a relation
class, whose body builds the relation through the model's own helper rather than `$this->hasMany(...)` or another of
Laravel's builders, is never read as a relation, as in Laravel's `model:show`. Its model interface leaves it out, and
`whenLoaded()` over it publishes `unknown`. Declare the class, such as `@return Ancestors<static, static>`, and it is
read.

### A method call on a `MorphTo<Model, $this>` relation publishes `unknown`

`$this->assignable->label()` publishes `unknown` in every spelling when the relation names no target, even if the
resource's own model declares `label()`, since the related model could be any. Name the targets in
`@return MorphTo<A|B, $this>`, or declare the inverse `morphMany`/`morphOne` on each target, which types it too.

### A `whenLoaded()` callback whose parameter declares a scalar type publishes the relation, not the string it gets

`$this->whenLoaded('comments', fn (string $c) => $c)` publishes `Comment[]`, but Laravel calls the callback in coercive
mode, which converts the loaded collection, or a loaded model, to its JSON string. Leave the parameter untyped, or type
it as the relation's class, and convert inside the callback where a string is what you want sent.

### A `whenHas()` or `whenAppended()` value the engine cannot type publishes `unknown`

[`ConditionalMethodHandler`](../src/Ast/Handlers/Resources/ConditionalMethodHandler.php) publishes `unknown` for
`$this->whenHas('title', fn ($t) => json_decode($t))`, not the `title` column's `string`, because Laravel returns what
the value returns and a plain title decodes to `null`, as `WhenHasValueResource` pins. Give the closure a return type,
such as `fn ($t): ?string => json_decode($t)`, or a `@return` docblock written before the closure, and the key types
from it; a docblock before the whole call or above the array item is not read. Inside a ternary or another union
the `unknown` arm is left out instead
([above](#a-union-arm-the-engine-cannot-type-is-left-out-so-the-union-publishes-the-other-arm)), so
`$c ? $this->whenHas('title', fn ($t) => json_decode($t)) : null` publishes `null`. `whenExistsLoaded()`,
`whenCounted()` and `whenAggregated()` still publish their flag or aggregate type for an untypable closure, so such a
key can claim a type the closure does not return.

### A global constant types from its value where `ts:publish` runs

`PHP_EOL`, `E_ALL` or a user-defined constant, in place or as a parameter default, types by the value's PHP type on the
machine that ran the publish. A user constant built from `env()` or config can change type between machines, and one
defined only at runtime, or only on another OS or extension, leaves the value `unknown`. Built-in constants are stable.

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

### In a resource collection, `map()`, `pluck()` and `values()` on `$this->resource` publish `unknown`

Inside a collection, `$this->resource` holds the items in a `Support\Collection` or the paginator the collection was
given. The package types its value, `first()`, `last()`, `count()`, `only()`, `except()`, the paginator's own methods
and a member of the item `first()` or `last()` returns, such as `$this->resource->first()?->title`, not a collection
pipeline on it. `$this->first()?->title` and `static::first()` on the collection itself still publish `unknown`. Type
such a key with `#[TsCasts]`.

### A resource collection that sends raw models reads an item's member through the resource its name picks

Where a resource collection's `collects()` names no resource Laravel can use, it sends the raw models, but a member read
off an item, such as `$this->resource->first()?->title`, reads through the API resource the collection's name picks,
which forwards to the model. The two differ only where that resource declares a public property or method of the same
name, whose type is published in place of the model's. Set `$collects` to the resource you mean, or rename the member.

### A `string`-keyed collection declaration publishes a record, though numeric-string keys arrive as a list

A method's declared `Collection<string, X>`, and a local's inline `@var` one, publish `Record<string, X>` from `all()`
and the steps that keep the keys, as the declaration says. PHP turns a numeric string key such as `'1'` into an int,
so a collection whose keys are all such strings is sent as a list. Declare `array-key` or `int|string` keys where they
can occur, which publish `X[] | Record<string, X>`.

### A query chain past a step that provably leaves the query publishes no root-model guess

A query chain rooted at a model types from what each step hands on, in an Inertia prop or shared value, an API
resource, a broadcast event or a model accessor alike. A scope, macro or custom builder method whose body the package
cannot read, a local macro a global scope adds, a step whose branches hand on different things, or a collection step
the package does not follow, still reads as the root model's query before a terminal every such chain was once typed
by. A few shapes publish `unknown`:

- a step after `find($id)` of a key no literal spells, such as `Post::find($id)->comments()->get()`, since the call
  returns a model or a list by the value Laravel is given;
- `pluck()`, `value()`, `cursor()` or `findMany()` past a step the package cannot read, or one whose branches hand on
  different things, since the step may hand on another model's query, whose columns differ;
- a terminal past a collection step that replaces the models, such as a `map()` to each post's title before
  `first()`, since the items sent are no longer the models;
- a query a branched scope or macro hands on sent with no terminal, such as `Post::query()->latestOrTally(false)`,
  since its other branch sends a count;
- a property read through a step whose hand-back the package cannot prove, such as
  `Post::query()->withArchived()->first()?->title` for a local macro, as before.

Type the key with `#[TsCasts]`.

### A paginator the engine cannot name publishes no pagination, and a bare paginator publishes its items only from a model's query

A resource collection prop or shared value reads its paginator's kind from the class its payload holds: a query chain,
a builder variable's `paginate()`, or a helper's or service's declared return type or readable body. Over a payload
none of these names, such as an untyped method whose body the engine cannot read, or one declared as the `Paginator`
contract, which either kind implements, `PostResource::collection(…)` publishes
`AnonymousResourceCollection<PostResource>`, though Laravel sends `links` and `meta` too. A page prop or shared value
holding a paginator bare publishes its items only from a query chain rooted at a model or a body that returns one: from
a query builder variable, `$query->paginate()` publishes `unknown`, and past a setter such as `->withQueryString()`
`{ onEachSide: number }`, as `Post::query()->paginate()->setCollection(…)` does, since the collection it is given may
hold anything. Declare the method's return type, build the paginator in the props, or type the prop with `#[TsCasts]`.
An API resource's own `toArray()` holding a paginator from a builder variable publishes the same: `unknown`, or
`{ onEachSide: number }` past a setter. Type the paginator through a helper's return type or with `#[TsCasts]`.

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

### A resource prop's `with()` or `additional()` key that its body holds too is sent as a list

A resource or collection whose own type holds its wrap key, such as a `toArray()` returning `['data' => …, 'count' =>
1]`, is sent as it stands, and Laravel merges a key `with()` or `additional()` adds into it with
`array_merge_recursive()`. The package intersects the two, which matches two objects, but values that are not both
arrays are sent as a list: `->additional(['count' => 2])` sends `count` as `[1, 2]`, while the package publishes
`number`. Name the key differently.

### A `->resolve()`d resource collection with its own `toArray()` publishes its items bare

`SomeCollection::make($items)->resolve()` as an Inertia prop publishes the collection's own type where it declares its
own `toArray()`, such as `['items' => $this->collection]`. Inertia then sends each item as its response, so an item
arrives as `{ data: … }` where that type gives the resource's keys. Pass the collection itself, or type the prop with
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

The object a `Conditionable` `when()` or `unless()` hands back reads the same way. Where it can be sent, because a
callable returns `null` or no default is passed, the key publishes `unknown` unless the package knows the class's
JSON. That includes a class with an untyped public property, such as an event `make:event` generates, whose
`InteractsWithSockets` declares `public $socket`. Pass a default that returns a value, or type the key with
`#[TsCasts]`.

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

### A serializing class cast whose `get()` proves nothing publishes `serialize()`'s type wherever it is read

A class cast whose caster has a `serialize()` method publishes, on the model, the type `Model::toArray()` writes:
what `serialize()` declares, or, where it declares nothing or only `mixed`, what its returns prove. An API resource
that reads the attribute off the model, directly or through `only()` or `except()`, sends the `get()` value instead,
and publishes `get()`'s type. Where `get()` declares nothing or only `mixed`, as Laravel's `make:cast` stub does, it
publishes what `get()`'s returns prove; where they prove nothing, as `return $value;` does, a caster implementing
`SerializesCastableAttributes` still publishes `serialize()`'s type there, as it always has. Declare `get()`'s return
type, or type the key with `#[TsCasts]`.

Laravel sends a null `get()` value without calling `serialize()`, but a declared `serialize()` type gains `| null` only
where `get()` declares one or returns a literal `null` itself. A null that `get()` passes on from another member or
model is left out, since the type published for that member may hold a null the path `get()` takes never meets. So is
a null held in a local, as in `$out = null; … return $out;`, and one returned from a body the engine cannot read or
that reads its cast back through itself.

On a NOT NULL column, a class or `Castable` cast publishes `serialize()`'s type with no `| null` from `get()`, since
the column never holds a NULL. A `get()` that maps a stored value to null, as one turning `''` into `null` does, still
sends that null, which the type leaves out; a `null` that `serialize()` itself declares or returns stays. An API
resource reading the attribute off the model keeps its `| null`.

### An `array`, `collection` or `AsCollection` cast publishes a list, though an object payload is sent as an object

`'settings' => 'array'` publishes `unknown[]`, as do `collection`, `AsCollection` and their encrypted forms, but a
column holding `{"theme":"dark"}` is sent as that object. A `json` cast, which Laravel reads the same way, publishes
`object`. State the shape with a `@property` tag, such as `@property array{theme: string} $settings`, or with
`#[TsCasts]`.

### A closure nothing calls, and a first-class callable Inertia calls through a property, publish the wrong type

`'k' => fn () => 1` publishes `number`, though Laravel sends the `Closure` as `{}`: only a first-class callable such
as `strlen(...)` is read by where it sits. A property's type is the same for every reader, so a first-class callable a
constructor writes, as in `$this->stamp = now(...)`, publishes `Record<string, never>`, and `ts:publish` warns, even
in a page prop `'stamp' => $this->stamp`, though Inertia calls it. A closure held in a local and passed to a helper,
read as a value or spread, publishes what it returns, as in `$f = fn () => 1; $this->hold($f)` where `hold($v)` returns
`['v' => $v]`: `{ v: number }`, though Laravel sends `{ v: {} }`. Inertia calls a page prop's closure once, so a closure
that closure returns is sent as `{}` too: `'a' => fn () => $f` publishes what `$f` returns, and `fn () => $f ?? 5`
that less its `null`, beside `5`'s type. Type that key with `#[TsCasts]`.

### A `transform()` callable no literal names publishes the callable's own type

`transform()` calls its callback, so `$this->transform($v, 'strlen')` publishes `number`, as does a callable whose value
reads as callable strings or pairs through a constant, a method's literal return, a ternary, a local or a `$this`
property. A callable string or pair a parameter, a `config()` value or another call holds names no call the engine can
read, so it publishes the type of that value, as `$this->transform($v, $callback)` publishes `string` for a
`string $callback` though the call's value is sent. Type that key with `#[TsCasts]`.

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

### A page prop that names a shared-data key publishes both types intersected

A route file publishes each page as `Inertia.SharedData & { … }`, so a page key named like a shared-data key keeps both
types, though Inertia merges the page's props over shared data: a page's `'auth' => 'x'` publishes `auth` as shared
data's type and `string` at once. A dotted page key under a shared-data key, `'auth.name'`, is right only while the
shared value is an array Inertia writes into. The other way round, shared data's own dotted key unpacks after the merge
and writes into, or over, the page's value: shared `'auth.name'` beside a page's `'auth' => 'x'` sends
`{ auth: { name: … } }`. A shared-data index signature that covers a page key's head intersects the same way. Name
page keys apart from shared-data keys.

### A dotted key under an object an array holds publishes the object's keys beside its own

`['post' => ['card' => new PostResource($post)], 'post.card.extra' => 1]` publishes `card` as its response with
`extra: number` beside it, though Inertia replaces a value it does not find as an array, an API resource or an Inertia
prop object such as `Inertia::always([…])` included, and sends `{ card: { extra: 1 } }`. A top-level key publishes as
replaced where it holds an API resource, an Inertia prop object, a `JsonSerializable` object, any other object a `new`
builds there, or a callable returning one; another object only its declared class types is written into as published.
Write the dotted key under a key of its own.

### A props local whose branches write its int keys in different orders numbers them in the first branch's order

Laravel renumbers a page's int keys from 0 in the order the array holds them, but a props local's branches are joined
into one key order: after `if ($c) { $p[1] = 'a'; $p[2] = 5; } else { $p[2] = 5; $p[1] = 'a'; }`, `{ "0": string,
"1": number }` publishes, though Laravel sends `5` on `"0"` from the second branch. Write int keys in one order, or
use string keys.

### A resolved resource that preserves keys, inside one that does not, shows keys the outer filter lists

`'tiers' => KeyedR::make($x)->resolve($request)` publishes `KeyedR`, whose interface keeps its numeric keys, but the
outer resource's `filter()` enters the array `resolve()` returns and re-indexes it.

### An explicit key written before a spread publishes its own type, though the spread's value wins

`['id' => 'x', ...$arm]` publishes `Omit<Arm, 'id'> & { id: string }`, though PHP lets the later spread write the
arm's own `id`. Every explicit key is subtracted from every spread arm, whatever their order. An array union reads as
that spread with its operands swapped, so `$arm + ['id' => 'x']` publishes the same. Write the key after the spread,
or on the left of `+`.

### A `parent::` spread that cycles back into itself types a parameter by the first call alone

`[...parent::m(1)]`, where an untyped `m($v)` returns `['v' => $v, ...static::m2()]` and the class's own `m2()` spreads
`parent::m('x')` once, under a guard, publishes `v: number`, though PHP's second call overwrites `v` with `"x"`. The
package reads the cycle once, with the first call's bindings. Type the key with `#[TsCasts]`.

### An operand of `+` not typed as one object adds no keys beside a keyed literal

`$this->published_at->toArray() + ['x' => 1]` publishes `{ x: number }`, though the response also carries every key the
left side sets. Only an operand typed as one object, such as a `Record` cast or a relation's `only()`, joins the union
as an arm of its own type. A list such as `explode()`'s, an enum list, a union of a shape and `[]`, or a `when()` value
adds nothing. A local whose read sees one write reads as the array it holds, in a method's whole `return` as in a
value, a merge closure's own included. Type the operand with a docblock, or the key with `#[TsCasts]`.

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
subclass writes it, whole, by a compound operator or by element, typed in the writing method's scope. Its limits:

- After a whole `unset()` it publishes `unknown`, since PHP then reads it through `__get()`, which a resource forwards
  to its model.
- A value the engine cannot type publishes `unknown`: an untyped method or closure parameter, a variable a closure
  imports by reference, an import a closure holding the write reassigns, or a `mixed` call. A `@param` on the statement
  above `$f = function ($n)` is not read, so such a parameter counts as untyped.
- Any other write publishes `unknown` as well: a by-reference argument, as in `array_push($this->queue, 1)` or
  `sort($this->tags)`, a `&` reference, a destructuring assignment, and `foreach (… as $this->p)`.
- An element write publishes `unknown` where the array may be `null` or a scalar, where it compounds, as
  `$this->tags['n']++` does, where a nested key follows a computed one or names a member the array may lack, and where
  a by-reference `foreach` passes its element on by reference.
- A write that nests the property's own value, as `$this->x = ['v' => $this->x]` does, publishes one level, though each
  call nests one more. Where it nests what a helper returns, as `['v' => $this->measure()]` does, that helper's own key
  publishes the nested value `unknown`.
- A helper read first publishes `unknown` where the write reaches it through another untyped property's write, as
  `$this->p = $this->mq()` beside `$this->q = $this->mp()` does.
- A by-reference parameter on a receiver the scan cannot resolve, such as `$other->fill($this->tags)`, a write from
  outside the class, and a write by a subclass of a receiver's declared class, such as the object behind
  `$this->holder()->views`, change the value Laravel sends while the published type stays the default's.
- `foreach ($this->p as [&$a])`, which destructures by reference, is not seen as a write either, so the default's type
  publishes though PHP changes the elements.
- An array a plain class holds is typed as that class sends it, so where the holder's `$tags = [5 => 's']` may gain a
  `k` key, a resource reading `$this->holder()->tags` publishes `{ "5": string; k?: number }` though it re-indexes the
  array and may send `["s"]`.

Declare the property's type, or give it a `@var` docblock.

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
read the pins as the divergences found so far, not all there are. Its Inertia half runs only the pairs with a handler
the resource profile lacks, so every other pair is proven on the resource corpus alone, never on a page's scope, and
with the plain `FirstClassCallableHandler`, where the Inertia profile runs the `invoked: true` one.
[The ordering inventory](./components/ast-engine.md#the-honest-ordering-inventory) lists them.

### The publish-speed gate is one-sided

`publish-bench.sh` fails only when a push's median time is more than `MAX_RATIO` times that of its merge-base with
`origin/main`, and never checks a speedup. Until a speedup reaches `main`, later branches still measure against the
slower base, so they can give it back without failing. Updating `main` is the owner's step, since agents never merge
into it ([AGENTS.md § Git](../AGENTS.md#git)). [Performance gate](./testing/performance-gate.md) describes the job.

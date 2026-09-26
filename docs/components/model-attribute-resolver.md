# ModelAttributeResolver

[`ModelAttributeResolver`](../../src/ModelAttributeResolver.php) types a model's attributes and relations for the model
transformer, the resource analyzer and the AST engine. `resolveAttribute()` tries the accessor, then the cast, then
the database column type, and `resolveRelation()` and `resolveMorphToTargets()` type relations. The
PHP-type and docblock resolution underneath lives on [`LaravelTsPublish`](../../src/LaravelTsPublish.php), chiefly
`toTsType()` and `methodOrDocblockReturnTypes()`, and its rules are on this page too. Usage is on the tolki
[Models](https://tolki.abe.dev/ts/models.html) page.

## Where things live

The attribute waterfall spans these classes:

- [`ModelAttributeResolver`](../../src/ModelAttributeResolver.php): the attribute waterfall, relation and `morphTo`
  types, the column-name lists, and `@property` refinement. It is a singleton, so each model's context is cached for
  the run.
- [`Concerns\ResolvesAccessorType`](../../src/Concerns/ResolvesAccessorType.php): the accessor step, shared with
  `ModelTransformer`.
- [`AccessorBodyAnalyzer`](../../src/Analyzers/Model/AccessorBodyAnalyzer.php): types an accessor from its getter
  body. See [AccessorBodyAnalyzer](accessor-body-analyzer.md).
- [`LaravelTsPublish`](../../src/LaravelTsPublish.php): `toTsType()`, return-type resolution, and the docblock helpers
  for generics, shapes, type aliases and trait templates.
- [`TsTypeString`](../../src/Support/TsTypeString.php): `isVagueTsType()`, the one definition of "vague".
- [`ModelInspector`](../../src/ModelInspector.php): extends Laravel's inspector for the attribute and relation lists.

## A vague signature defers to the docblock

`LaravelTsPublish::methodOrDocblockReturnTypes()` takes a method's native return type when it is neither `unknown` nor
vague, and then never parses the docblock. A signature such as `: mixed`, `: array`, `: iterable` or `: object` defers
to a `@return` that is not vague, so `@return array{value: int, label: string}` over `: array` publishes
`{ value: number; label: string }` instead of `unknown[]`. When neither is specific, the first non-`unknown` of the
two wins, signature first. An accessor adds its getter body before that last fallback; see
[AccessorBodyAnalyzer § When the body is read](accessor-body-analyzer.md#when-the-body-is-read).

`TsTypeString::isVagueTsType()` calls a type vague when it is exactly `object`, or when it contains `unknown` and no
`{`. A type that spells an object literal is specific, even `{ filters?: Record<string, unknown> }`, because a `mixed`
key inside a concrete shape is not the same as no shape. The return-type order, the accessor step and `@property`
refinement all use this one predicate.

`resolveAttribute()` takes a `carriesImports` flag, which reaches only the getter-body step and the `@property`
refinement; see [A reader that carries no import](accessor-body-analyzer.md#a-reader-that-carries-no-import). The
accessor step is memoized per model, attribute and import mode, so one mode never reuses the other's answer.
`resolveAttributeClass()` and `resolveAccessorModelFqcns()` take no flag, because the first never reads a getter body
and the models a getter returns do not depend on how its filters are spelled.

## Set-only mutators record no getter

A mutator with no getter is documented `Attribute<never, string>` by convention. The `never` says no getter exists.
It is not the read type, since a read returns the raw, cast column, so `ResolvesAccessorType` treats a `never` Get
like a missing docblock and returns `omittedTypeScriptInfo()`. It strips a `null` arm first with
`ValueResult::stripNullArm()`, because `?never` and `never|null` both arrive as `never | null`. `resolveAttribute()`
then falls through to a real column's type, and `ModelTransformer::transformMutators()` drops a name with no column.
`OutgoingNote` pins the `never` and `?never` spellings and the dropped name.

To assert that a name is left out, use `isOmittedMutator()`, which reads the accessor step's `omit` flag.
`resolveAttribute()` returns `unknown` both for an omitted mutator and for an attribute it cannot type.

## Class types in `toTsType()`

`LaravelTsPublish::toTsType()` numbers its resolution steps in its source comments, and this section uses those
numbers.

### Arrayable and JsonSerializable take their shape differently

`arrayableShapeType()` first reads an `array{...}` shape from the `@return` of `toArray()` or `jsonSerialize()`. Only
an `Arrayable` then falls back to typed public properties, because `(array) $this` ties `toArray()` to a DTO's
properties. `jsonSerialize()` has no such contract, so inferring from its properties could emit a plausible wrong type.
An `Arrayable` with neither is `unknown[]`, and a `JsonSerializable` with no shape falls through to the later steps. A
class that implements both takes the `Arrayable` path.

A property shape reads public, non-static properties only. It marks a property optional when it is neither promoted
nor defaulted, because `json_encode()` omits a typed property that was never assigned. A property naming a class or
enum degrades to `unknown`, since a shape string has no import channel. `$shapeExpansionStack` guards docblock shapes
and property shapes under separate keys, so a self-referencing or mutual DTO degrades its inner reference instead of
exhausting memory.

### Step 5c inlines a plain class's typed properties

A class that reaches step 5c has survived every earlier step. If it is not `JsonSerializable`, and it has at least one
public non-static property with every one of them typed, it publishes its property shape instead of its bare name.
Know these five points before you change it:

- **Publication**: 5c never checks whether the class has a published file. Its motivating classes have none, so a
  bare class token would have no file to import from. A published class, such as a broadcast event, would inline too
  if `toTsType()` met it. `BroadcastEventTransformer` calls `toTsType()` for enums only, so no published class reaches
  5c, and no test would catch one that did.
- **Every property typed**: load-bearing. It keeps `Illuminate\Database\Eloquent\Casts\Attribute`, whose public
  properties are untyped, on step 5. `attributeDocblockReturnTypes()` recognizes a bare `@return Attribute` by
  `classFqcns === [Attribute::class]`, which an inlined shape would break.
- **At least one property**: changes no outcome, since `publicPropertyShapeType()` returns null for a class with none.
  It stays so the predicate means what its name says, and so a class with nothing to inline skips the shape build.
- **Not `JsonSerializable`**: load-bearing, for the reason `arrayableShapeType()` withholds property inference from
  it. `JsonSerializableDivergingPropertiesValueObject` pins it.
- **Not a `Model`**: never decides anything, because `Model` implements `JsonSerializable`. It stays for symmetry with
  steps 5a, 5a-bis and 5b, where the same test does decide. Don't delete it, and don't cite it as the reason models
  never inline.

### Cast strings with arguments

Laravel's `AsEnumCollection::of()`, `AsCollection::of()` and `AsCollection::using()` build cast strings of the form
`CastClass:arguments`. Step 1b handles one only when the text before the first colon is an existing class, so
`decimal:2` and `encrypted:array` fall through to the later steps. `AsEnumCollection` publishes a list of the
enum's type, with the enum's import. `AsCollection` publishes a list of its map class when that class resolves to an
inline shape or an enum, and `unknown[]` otherwise, because a bare class token has no import channel. Any other cast
class resolves as the bare class, arguments ignored.

### Database types come from the schema grammar

A column with no cast resolves the native type string `Schema::getColumns()` reports, such as `tinyint(1)`, or `point`
for a MySQL `geometry(subtype: 'point')` column. The schema grammar writes that string, not the migration method, so
read `Illuminate\Database\Schema\Grammars\*Grammar.php` before you add a `TypeScriptMap` entry.

## Docblock types

### Generic containers

`LaravelTsPublish::resolveGenericContainerType()` handles `list<X>`, `array<K, X>`, `iterable<K, X>`, a
`Collection<K, X>` and `X[]`, by these rules:

- **Shapes in the value slot**: an `array{...}` value resolves through `resolveArrayShapeString()`, the resolver the
  top-level docblock paths use, so a nested shape publishes `{ key: string }[]`, not `unknown[][]`. A shape key that
  names a class or enum degrades to `unknown` on its own.
- **Intersections in the value slot**: the value splits at top-level `&`, and the members join as a TypeScript
  intersection, each keeping its imports. A member that resolves to `unknown` is dropped, since `A & B` is assignable
  to `A`. So
  `Collection<int, User&object{pivot: TaskAssignment}>` publishes `(User & { pivot: unknown })[]`.
- **Key types**: a string-refinement key, such as `non-empty-string` or `class-string<Model>`, gives
  `Record<string, X>`. An `array-key` or `mixed` key gives `X[] | Record<string, X>`, because the keys may not be
  sequential.
- **A leading `?`**: `?array<int, int>` has the `?` stripped, the rest resolved and `| null` appended, as `toTsType()`
  does for a plain type. Without it the container pattern would not match, and partial matching would return a
  plausible wrong scalar.
- **Parentheses**: `wrapAsArray()` wraps a union or an intersection before it appends `[]`.
- **Unrecognized generics**: a part that still contains `<` resolves to `unknown`, not to the `int` that partial
  matching would find inside it.

### Type aliases

`LaravelTsPublish::resolvePhpstanTypeAlias()` looks for a `@phpstan-type` or `@psalm-type` on the class, then follows
`@phpstan-import-type Name from Other`, with or without `as Alias`, recursively. An import chain therefore resolves to
the class that wrote the shape. A definition spanning several docblock lines is joined until its braces and angle
brackets balance. A `$seen` set guards the import chain, so two classes importing each other's alias give `unknown`.

The alias resolves against its defining class's imports and namespace, not the referencing class's.
`resolveDocblockTypePartOrAlias()` expands a hit through the plain pipeline, never alias-aware again, so two local
aliases naming each other cannot recurse outside the `$seen` guard. A nullable `?Alias` resolves the bare name and then
appends `| null`. The shape parser keeps a key's `?`, so an alias expanding to `array{filters?: ...}` publishes
`filters?:`.

### Trait-declared methods read the trait's file

For a method a class takes from a trait, `ReflectionMethod::getDeclaringClass()` reports the using class, though the
file and docblock are the trait's. `LaravelTsPublish::methodDeclaringFileClass()` finds the trait whose file matches,
so a class named in a trait's docblock resolves against the trait's imports, not the model's. The docblock readers all
use it: `docblockReturnTypes()`, `resolveDocblockTypeString()`, `parseDocblockReturnArrayShape()` and
`ModelAttributeResolver::morphToGenericMembers()`.

A trait's `@template` names are bound before resolution. `bindTraitTemplates()` replaces each with the matching
argument of the consumer's `@use Trait<X>` tag, spelled `@use`, `@phpstan-use` or `@psalm-use`. `traitUseArguments()`
reads that tag only from the doc comment of the `use` statement inside the class, never from a prose mention, and
checks the consumer before its parents. It parses the file through `AstParser::parseFile()`, which records the file as
a cache dependency. The trait name in the tag must resolve through a `use` import or the namespace.
`resolveDocblockTypeName()`'s namespace fallback checks `class_exists()` and `enum_exists()`, not `trait_exists()`, so
a same-namespace trait with no `use` import silently fails to bind.

## `@property` refinement

`refineWithPropertyDocblock()` runs only when the waterfall's type is vague, so a concrete result is never
second-guessed. It searches the class and its parents first, the child winning, then every trait in that chain,
recursively, so a class-level tag beats one on a trait. A tag may omit the `$` before the name, as some vendor traits
do, but only when it has no description. That keeps a description's last word from being read as a property name.

`isStrictlyMoreStructured()` accepts a candidate that is not vague. It accepts a vague candidate only when the current
type is entirely vague (`unknown`, `unknown[]`, `object` or `unknown[] | Record<string, unknown>`, optionally with
`| null`) and the candidate is not. A tag's `Record<string, unknown>` therefore replaces an `'array'` cast's
`unknown[]`, but a vague tag never replaces a partly structured type.

`resolveAttributeFallbacks()` uses a tag as the last resort for a name that is neither a column nor a relation, such as
a `selectRaw()` column a resource reads. It first tries the snake_case accessor alias and the `_count` and `_exists`
relation suffixes. It skips a relation name, because an ide-helper tag on a relation ignores relation nullability and
`morphFqcns`, and `resolveRelation()` owns relations. The fallback never adds a property to the model's own interface,
which lists `ModelInspector`'s attributes.

## MorphTo targets

`resolveMorphToTargets()` is the one source of a `morphTo`'s target union. `resolveRelation()` and
`ModelTransformer::transformRelations()` both call it, so a model's interface and a resource that reads the relation
cannot disagree. It reads two sources, in order:

1. **The docblock generic**: the first argument of `@return MorphTo<X|Y, ...>`, resolved through the declaring file's
   imports. A bare `Model` or an abstract class anywhere in it makes the whole generic non-narrowing, because
   `MorphTo<Model, $this>` is what Laravel's own `morphTo()` declares and says nothing about the targets.
2. **The reverse map**: `buildMorphTargetMap()` records every parent whose `morphOne` or `morphMany` points at the
   child, keyed by child and morph name. `relationMorphName()` reads the morph name by building the relation on an
   unpersisted instance, which runs no query.

The reverse map follows four rules:

- **Two keys per relation**: each parent is also written under the bare child key, the fallback when a child's morph
  name can't be read. The morph-name key keeps two differently named `morphTo`s on one child from sharing a union.
- **Subclass parents**: `Venue::reviews()` returning `VenueReview`, where only `Review` declares `reviewable()`, still
  types `Review::reviewable`. `getMorphToTargets()` unions every bucket with the same morph name whose child is a
  subclass of the queried model, so a subclass never picks up a sibling's parents. It never runs the other way either:
  a parent declared against `Review` writes `Review` rows, which a subclass's `morphTo` can never return.
- **Custom morph pivots**: a `morphToMany(...)->using(Labelable::class)` whose pivot declares its own `morphTo` is keyed
  by the pivot and morph name, so `Labelable::labelable()` resolves like any other `morphTo`. `morphPivotKey()` skips a
  `morphToMany` with no custom pivot, an inverse `morphedByMany()`, whose side the pivot's morph column never names,
  and a `->using()` class that is not a `Model`. It checks that last case at runtime, because the
  `class-string<Pivot>` bound is docblock-only.
- **Stale analyses**: `buildMorphTargetMap()` clears `AnalysisMemo`, so no analysis typed under an older map is reused.

An unresolved `morphTo` publishes bare `unknown`, never `unknown | null`, since `unknown` already admits `null`.
`buildMorphUnionInfo()` and `transformRelations()` each apply that guard to their own nullable suffix, and they must
agree.

## `publishedColumnNames()` and the `exclude_hidden` coupling

`databaseColumnNames()` lists every real column, `$hidden` included. `publishedColumnNames()` lists the columns that
reach the model interface, and a caller naming keys against that interface must use it. `Pick<Model, K>` requires
`K extends keyof Model`, so a key `ModelTransformer::transformColumns()` did not emit fails with TS2344.
`ResolvesFilteredRelationTypes::relationFilterModelReference()` gates `only()`'s keys on it and takes `except()`'s
complement from it; see
[ResourceAstAnalyzer § When a Pick reference is emitted](resource-ast-analyzer.md#when-a-pick-reference-is-emitted).

A call site that asks whether a name is a real column uses `databaseColumnNames()` and applies the hidden rule itself.
The `except()` branch of `resolveFilteredRelationType()` does, so an inlined `except()` expands to columns only, as
`HasAttributes::except()` does. `buildModelDelegatedAnalysis()` does too, so `isOmittedMutator()` never drops a real
column.

The two lists differ only when `ts-publish.models.exclude_hidden` is on, and it defaults to `false`.
`excludeHiddenAttributes()` is the only reader of that flag, for every site. It is not cached with the per-model
context, because the context is fixed for the model while the config can change between calls, as it does in tests.

## Laravel 13 model attributes need instance reads

Laravel 13's `#[Table]`, `#[Connection]`, `#[Hidden]`, `#[Visible]` and `#[Appends]` need no code here. Laravel
applies them in `Model::__construct()`, and the package reads the results through instance calls: `getTable()`,
`getConnection()` and `getAppends()` in `ModelTransformer::initInstance()` and `databaseColumnNames()`, and
`attributeIsHidden()` on the instance the inspector makes. A refactor that reads these attributes through
`ReflectionClass::getAttributes()` would break all five, and the failing `ModelTransformerTest` cases would not point
back here. Keep the reads on an instance. The test-only version guards they need, and why the workbench fixtures may
still `use`-import them, are in [Version-guarded Laravel classes](../laravel-version-guards.md).

## A missing table is reported, not guessed

When `ModelInspector` finds no columns, `resolveContext()` asks the schema builder whether the table exists. If it
doesn't, `AnalysisWarnings` records the model, table and connection, and `TsPublishCommand` prints the warning after
the summary. Without it, a missing migration would publish an interface indistinguishable from a model with no typed
columns, and a guessed type would hide the real cause. `hasTable()` runs only on the empty path, and the context is
memoized per model, so the warning appears once per model per run.

## Related

These pages hold the neighboring rules:

- [AccessorBodyAnalyzer](accessor-body-analyzer.md), for the getter-body step
- [ResourceAstAnalyzer](resource-ast-analyzer.md), for how resources use the column lists
- [Receiver types § Attribute classes and morphTo bounds](receiver-types.md#attribute-classes-and-morphto-bounds), for
  `resolveAttributeClass()` and `resolveMorphToBound()`
- [Version-guarded Laravel classes](../laravel-version-guards.md), including the `#[UseResource]` guard in
  `ModelClassResolver`
- [Support helpers](support-helpers.md), for why the docblock helpers stayed on `LaravelTsPublish`
- The known gap where a shape whose values name a class loses those values:
  [known-gaps.md](../known-gaps.md#a-shape-whose-values-name-a-class-loses-those-values-in-one-of-two-ways)
- [Type inference gates](../testing/type-inference-gates.md)

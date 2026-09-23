# ModelAttributeResolver

> User-facing docs: [README § Models](../../README.md#models) (see especially the
> [annotation checklist](https://tolki.abe.dev/ts/models.html#annotation-checklist)). Verified by
> [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\ModelAttributeResolver` resolves a model attribute's TypeScript
type through the accessor → cast → DB type waterfall, and resolves arbitrary method return
types (relation-filter methods, `$this->method()` resource calls) via
`LaravelTsPublish::methodOrDocblockReturnTypes()`.

## Resolution order: signature → docblock-when-vague → fallback

`methodOrDocblockReturnTypes()` no longer treats a present native return type as automatically
final. A native PHP signature is often deliberately loose — `: array`, `: iterable`,
`: object`, `: mixed` — while the method's own docblock frequently carries the real shape via
`@return array{...}`, `@return list<...>`, or a generic container. The resolution order is:

1. **Signature, if specific.** `resolveReflectionType()` reflects the method's declared return
   type. If the resulting TS type is neither `unknown` nor "vague" (see below), it wins
   outright — the docblock is never even parsed. A signature declaring `: string` always beats
   a docblock, no matter what the docblock claims.
2. **Docblock, if it resolves to something non-vague.** Only when the signature is absent,
   unknown, or vague does `docblockReturnTypes()` get a chance. If its result is specific, it
   wins.
3. **Whichever of the two is non-`unknown`**, signature preferred, as a last-resort fallback.

Accessors insert a third source between 2 and 3: **what the getter body returns**. Once both the
getter signature and the `Attribute<>` docblock have proven vague, `Concerns\ResolvesAccessorType`
asks `Analyzers\Model\AccessorBodyAnalyzer` what the body resolves to and returns that if it is
non-vague, so `Attribute::get(fn () => ['major' => $this->major])` publishes `{ major: number }`
instead of `unknown[]`. A specific annotation still wins outright, and the step is identical on the
old-style `get*Attribute()` branch. See
[accessor-body-analyzer.md](accessor-body-analyzer.md).

`resolveAttribute()` takes a `carriesImports` flag for that body step and for the `@property` refinement of its result.
An analysis scope passes its own
`AnalysisScope::$carriesImports`, so a method body the body fallback reads gets each getter analyzed without imports,
and its filters name no token. The model transformer, resources and every other caller keep the default `true`, so
the published accessor type does not change. Without imports, `refineAccessorType()` skips the `@property` refinement
below when the tag names a class and the published type is not vague; a token-free tag refines as usual. See
[accessor-body-analyzer § A reader that carries no import](accessor-body-analyzer.md#a-reader-that-carries-no-import).
`$attributeClassCache` needs no split: `resolveAttributeClass()` reads reflection only, never a getter body. The
accessor waterfall is memoized for the run in `AnalysisMemo`, per model, attribute and import mode
(`accessor-type:model@attribute`, with `@importless` for a read that carries no import), so a mode never reuses the
other's answer. `resolveAccessorModelFqcns()` takes no flag, because the models a getter returns do not depend on how
its filters are spelled.

```php
/** @return array{value: int, label: string} */
public function asAutoCompleteOption(): array
{
    return ['value' => (int) $this->getKey(), 'label' => (string) $this->notes];
}
```

`: array` resolves to the vague `unknown[]` on its own — with no docblock deferral, the
generated property would be typed `unknown[]`, discarding the shape entirely. Deferring to the
docblock resolves this to `{ value: number; label: string }` instead.

### What counts as "vague"

`isVagueTsType(string $type): bool` — a single predicate, defined once on `Support\TsTypeString`
(reused by `Concerns\ResolvesAccessorType`'s accessor-closure-vs-docblock check and by
`ModelAttributeResolver::refineWithPropertyDocblock()`'s `@property` refinement below, both of
which follow the same "keep looking while still vague" shape) — treats a type as vague when it
contains the literal substring `unknown` outside of any `{...}` object literal (covers `unknown`,
`unknown[]`, `unknown[] | Record<string, unknown>`, `Record<string, unknown>`, …) or is exactly
`object`. An object-literal shape is never vague on its own account, even when one of its own
keys resolves to `unknown` — `{ filters?: Record<string, unknown>; sorts?: string[] }` is
specific, because a `mixed`-typed key inside an otherwise-concrete shape is not the same thing as
having no shape at all. Anything else — `string`, `OrderItem[]`, `{ value: number; label: string }`
— is specific enough to win immediately.

## Set-only mutators: a `never` Get records no getter, not a read type

When an `Attribute` has no getter closure at all, `Concerns\ResolvesAccessorType` still reads the
method's own `@return Attribute<Get, Set>` docblock, because a mutator with no getter is
conventionally documented as `Attribute<never, string>` — the `never` states that no getter
exists, it is not a claim about what reading the attribute returns. Reading it returns the raw,
cast column value, so a `never` Get is excluded from the "docblock wins" check alongside `unknown`
and falls through to `LaravelTsPublish::omittedTypeScriptInfo()`, the same as a set-only mutator
with no docblock at all. From there the ordinary waterfall decides the answer: `ModelAttributeResolver::resolveAttribute()`
re-resolves a real DB column's own type once the accessor step comes back empty, and
`ModelTransformer::transformMutators()` omits a name with no backing column entirely rather than
publish it as `unknown`. `Workbench\App\Models\OutgoingNote` pins both outcomes: `subject` and
`type` are real columns behind `Attribute<never, string>` mutators and publish as `string`, while
`normalizedTag` has no backing column and is dropped from the generated interface, not emitted as
`never` or `unknown`.

The rule applies to both nullable spellings of the same Get too, which reach this branch as the
string `'never | null'` by different routes. For `Attribute<?never, ?string>`,
`resolveDocblockTypePartOrAlias()`'s nullable-prefix handling appends `| null` to the resolved
`never`. For `never|null` written without the `?` shorthand, `splitPhpDocUnionType()` splits the
two members, each is resolved separately, and the results are merged back into one union. The check strips the null
arm with `Ast\ValueResult::stripNullArm()` — the same helper `CoalesceHandler` and
`ConditionalMethodHandler` use for the identical "ignore a `| null` arm before deciding" shape —
before comparing against `never`, so both spellings are caught alike rather than only the bare one.
`OutgoingNote::channel` pins this spelling: documented `Attribute<?never, ?string>`, it publishes
its column's `string` type, not the literal `never | null`.

`isOmittedMutator()` is the read for "was this omitted", not `resolveAttribute()`'s own `type`:
once the accessor step's `omit` flag reaches `resolveAttribute()`, the no-backing-column path
discards it and returns a fresh `emptyTypeScriptInfo()` — so a genuinely unresolvable attribute and
one that resolved to `omittedTypeScriptInfo()` both read back `'unknown'` there, indistinguishably.
`isOmittedMutator()` calls `resolveAccessorType()` directly and reads the `omit` key itself — the
same check `ModelTransformer::transformMutators()` applies to `resolveMutatorType()`'s result for
real publish output — so it is the
correct assertion for "this name does not appear in the generated interface" — `resolveAttribute()`'s
`'unknown'` alone does not prove it.

## Arrayable/JsonSerializable shape-source precedence

`arrayableShapeType()` takes a `bool $fallbackToProperties` argument, and the two call sites in
`toTsType()` pass it differently — the shape-source chain is **not** identical for `Arrayable` and
`JsonSerializable`:

- **`Arrayable`/`toArray()`** (`$fallbackToProperties = true`, the default) resolves in this order,
  falling through only when a step yields nothing:
  1. **`@return array{...}` docblock** on `toArray()`, via `parseDocblockReturnArrayShape()`. A
     vague `@return array<string, mixed>` doesn't count — only a real `array{...}` shape wins here.
  2. **Typed public properties**, via `publicPropertyShapeType()` — the fallback for a DTO whose
     `toArray()` is just `(array) $this` with no shape docblock. Promoted constructor properties
     count as public properties for reflection purposes; private, protected, and static properties
     are skipped, since none of them are part of `(array) $this`. Each property resolves through
     `propertyTypes()` (reflection type, nullability appended), and a value carrying an
     unimportable class/enum token degrades to `unknown` via the same
     `shapeValueHasUnimportableToken()` check the docblock path uses.
  3. **`unknown[]`** — the class has neither a shape docblock nor any public instance properties.
- **`JsonSerializable`/`jsonSerialize()`** (`$fallbackToProperties = false`) resolves only from a
  `@return array{...}` docblock; when that's absent it falls through to the rest of `toTsType()`
  (class-basename, `__toString`, etc.) instead of `unknown[]` or a property-derived shape. Typed
  public properties are deliberately **not** consulted here: `(array) $this` is a real contract
  tying `toArray()`'s output to a DTO's own properties, but `jsonSerialize()` carries no such
  contract — it can return anything, unrelated to the object's properties — so inferring a shape
  from them would risk emitting a plausible-looking but wrong type instead of falling through
  conservatively.

`publicPropertyShapeType()` reuses `$shapeExpansionStack`, the same guard `arrayableShapeType()`
uses for docblock shape cycles, under a `"{FQCN}::__properties"` key distinct from the
`"{FQCN}::{method}"` docblock key — a property typed as the class itself, or a mutual A/B pair,
degrades the second-level reference to `unknown[]` instead of recursing until memory is exhausted.

## Plain classes inline their property shape too: `toTsType()` step 5c

**Step 5c is defined by position, not by a list of negatives.** It sits immediately ahead of step 5's
class-basename fallback, so a class reaches it only by surviving *every* earlier return in `toTsType()`.
Read top to bottom, those are: `?T` unwrapping (step 0), the exact type-map match (1), the bare-name
fallback for sized native types like `varchar(255)` (1a), castable-with-arguments strings `Cast:a,b` whose
head is a class (1b), `#[TsType]` (2), PHP enums (3), `CastsAttributes` (4, which returns unconditionally —
either its `get()` type or `unknown`), `Arrayable` non-Model (5a, also unconditional), `JsonSerializable`
non-Model non-Arrayable (5a-bis, the one branch above that can fall *through*, when no `array{...}` docblock
exists), and `__toString()` non-Model (5b).

Listing only "not `Arrayable`, not `JsonSerializable`, no `__toString()`, no `#[TsType]`" would be wrong,
and the corpus falsifies it: `Workbench\App\Enums\Status` and `Workbench\App\Casts\CoordinateCast` both
satisfy all four negatives, yet neither reaches 5c — the enum returns at step 3 as `StatusType`, and the
cast returns at step 4. Probed directly against the real service:

```
Status           Arrayable=no JsonSerializable=no __toString=no TsType=no -> StatusType
CoordinateCast   Arrayable=no JsonSerializable=no __toString=no TsType=no -> { lat: number; lng: number }
Coordinate       Arrayable=no JsonSerializable=no __toString=no TsType=no -> { lat: number; lng: number }
```

(`CoordinateCast`'s inline object comes from step 4 resolving its `get(): Coordinate` return, which then
reaches 5c — not from 5c resolving the cast class itself.)

Once there, `hasFullyTypedPublicProperties()` decides. When it holds, the class resolves through the same
`publicPropertyShapeType()` the `Arrayable` fallback above uses and the shape is inlined; otherwise step 5
emits the bare class name.

### Why inline at all

The motivating reason is that for the classes that actually reach 5c *and get emitted* in this corpus, the
package publishes no file, so step 5's token has nothing to import. `Coordinate` is that case.

**Publication is a real axis, not a constant, and 5c does not test it.** The step-5 path below builds its
import from `classFqcns` through `namespaceToPath()` and `relativeImportPath()` with no published-set check,
so "unimportable" is an assumption about the classes that happen to arrive here, not something either step
verifies. The workbench contains a whole family that contradicts it: **12 of the 15** classes in
`workbench/app/Events/` are plain, non-`Model`, non-`JsonSerializable`, have no `__toString()` and no
`#[TsType]`, and carry typed public properties — so each of those reaches 5c and inlines when
`toTsType()` is called on it directly. (The three broadcast-event entries use `InteractsWithSockets`, whose
untyped `public $socket` fails `hasFullyTypedPublicProperties()`, so they fall through to step 5's bare
class name instead.) Probed against the real service:

```
EnumBroadcastEvent  -> { status: unknown; color: unknown }
OrderShipped        -> { orderId: number; trackingNumber: string; carrier: string; metadata: unknown[] | null }
ServerCreated       -> { serverId: number; serverName: string }
…12 of the 12 that qualify
```

And the package **does** publish a file for each: the two sets are identical, 15 PHP fixtures against 15
`.ts` files in `app/events/` plus an `index.ts` that re-exports them, so `'../events'` — what step 5 would
have derived for `Workbench\App\Events\OrderShipped` — is a directory this package writes.

This costs nothing today. `src/Transformers/BroadcastEventTransformer.php` reaches `toTsType()` for enum
FQCNs only, which resolve at step 3; a class-typed event property is typed by the AST engine and presented
by the transformer (`Model` → `Partial<X>`) — so the shapes above are never consulted when
an event file is written. Confirmed against the output as well: all 12 inlined shapes were searched, as
exact strings, across every file under `workbench/resources/js/types/`, and matched **zero** files. Treat
this as a standing caveat on the reasoning rather than a defect: if some future path *does* send a published
class through 5c, inlining is the wrong answer for it and nothing here would notice.

### The shape approximates `json_encode()`, and one fixture shows the gap

For a plain object PHP serializes its public non-static properties, so for `Coordinate` the two agree
exactly — `json_encode(new Coordinate(1.5, 2.5))` gives `{"lat":1.5,"lng":2.5}` against an emitted
`{ lat: number; lng: number }`.

They are not identical in general. PHP **omits an uninitialized typed property** from `json_encode()` output,
while `ReflectionClass::getProperties(IS_PUBLIC)` — what `publicPropertyShapeType()` reads — includes it:

```php
class H3Uninit { public string $a; public int $b = 1; }

json_encode(new H3Uninit)          // {"b":1}
toTsType(H3Uninit::class)['type']  // { a: string; b: number }
```

So the inlined shape claims a **required** key the wire may never carry; the honest emission would be
`a?: string`.

The corpus reproduces this, in `Workbench\App\Events\UserNotification`, whose
`HasBroadcastTimestamps` trait declares a bare `public string $occurredAt;` alongside three promoted
constructor properties:

```
json_encode(new UserNotification(1, 't', 'm'))
  // {"userId":1,"title":"t","message":"m"}          — no occurredAt
toTsType(UserNotification::class)['type']
  // { userId: number; title: string; message: string; occurredAt: string }
```

No **generated output** is affected, for the reason in the previous section — the published
`app/events/UserNotification.ts` is written by `BroadcastEventTransformer`, which emits
`extends HasTimestamps` from the trait's `#[TsExtends]` and never consults this shape. Among the classes
whose 5c output *does* reach a file, the value objects, none is affected either, and that is worth stating
precisely rather than as a rule about promoted properties. Enumerating all 10 of
`workbench/app/ValueObjects/`: seven carry public non-static properties (13 in total) and every one of those
13 is promoted, hence always initialized; the other three — `OpaqueHandle`, `StringableLabel` and `TreeNode`
— have no public non-static property at all, so they never reach the shape builder. `TreeNode` in
particular has no constructor whatsoever, so "they all use promoted properties" would be the wrong reason
for it; "it has nothing public to inline" is the right one.

This is filed as an out-of-scope entry.

### What the guard does, and what each conjunct is worth

`hasFullyTypedPublicProperties()` requires **at least one** public non-static property and a declared type
on **every** one of them. The two halves are worth very different amounts, and only one of them can change
an outcome today:

- ***Every one typed* is load-bearing.** It keeps `Illuminate\Database\Eloquent\Casts\Attribute` on step 5.
  Its four public properties (`$get`, `$set`, `$withCaching`, `$withObjectCaching`) carry `@var` docblocks
  but no declared types, so inlining would emit
  `{ get: unknown; set: unknown; withCaching: unknown; withObjectCaching: unknown }` — and would silently
  disarm `attributeDocblockReturnTypes()`, which degrades a bare `@return Attribute` by matching
  `classFqcns === [Attribute::class]` on step 5's result. Deleting this half fails three tests.
- ***At least one* changes no outcome.** `publicPropertyShapeType()` already returns `null` for a class with
  no public non-static properties (`return $parts === [] ? null : …`), and step 5c already falls through on
  `null`, so a property-less class reaches step 5 either way. Verified by mutation: dropping the `$found`
  bookkeeping so the predicate returns `true` vacuously leaves the suite at 2494 passed / 5945 assertions
  and all four trees byte-clean. It is kept so the predicate does not lie about its own name, and so the
  shape build (and its `$shapeExpansionStack` push) is skipped for a class that cannot produce one — not
  because it guards anything.

`Workbench\App\ValueObjects\OpaqueHandle` is still the fixture that pins the property-less path end to end:
its promoted property is `protected` on purpose, so `StaticCallResource.money_value` stays `unknown` and
`ReflectedTypeAcceptor::accept()`'s rejection branch keeps its coverage. What the mutation
above shows is that `$found` is not the *mechanism* keeping it there — `publicPropertyShapeType()`'s own
`null` is.

### The two class exclusions

`JsonSerializable` is excluded because it *overrides* the `json_encode()` default the section above rests
on — the same reason `arrayableShapeType()` gets `$fallbackToProperties = false` for it — so the
fall-through described there still happens. This conjunct is load-bearing: removing it inlines
`JsonSerializableDivergingPropertiesValueObject` as `{ internalToken: string }` and fails its test.

`Model` is **subsumed** by the `JsonSerializable` conjunct beside it and can never decide anything.
`Illuminate\Database\Eloquent\Model` implements `JsonSerializable` — one of the nine interfaces
`class_implements()` reports for it on Laravel 13.24.0 — and PHP inherits interfaces, so every class for
which `is_a($x, Model::class, true)` holds also satisfies `is_a($x, JsonSerializable::class, true)`. The
`Model` conjunct can therefore only ever be false where the next conjunct is false too. Confirmed by
mutation: deleting it leaves the suite at 2494 passed / 5945 assertions with all four trees byte-clean.

This is **not** a dormant guard waiting on some upstream change. No future edit to Laravel can revive it
short of `Model` dropping `JsonSerializable`, and in particular it has nothing to do with `Model`'s six
untyped public properties (`$incrementing`, `$preventsLazyLoading`, `$exists`, `$wasRecentlyCreated`,
`$timestamps`, `$usesUniqueIds`) — those would make `hasFullyTypedPublicProperties()` false anyway, a third
independent reason models never inline. It is kept for symmetry with steps 5a, 5a-bis and 5b, where the
identical `! is_a($phpType, Model::class, true)` conjunct genuinely *is* load-bearing, precisely because
`Model` implements `Arrayable`, implements `JsonSerializable`, and defines `__toString()`. Reading all four
guards the same way is worth more than deleting one redundant line; do not "revive" it, and do not cite it
as the reason models are excluded.

## Nested array shapes inside generic containers

A docblock generic's value slot — the `X` in `list<X>`, `array<K, X>`, `Collection<K, X>` —
recurses through `resolveDocblockContainerValue()`. When that slot is itself an `array{...}`
shape (`list<array{key: string, label: string}>`), it now resolves through
`resolveArrayShapeString()` — the same shape resolver `resolvePhpDocTypeToTs()`'s `array{`
branch and `resolveDocblockTypePart()` (the top-level, non-generic docblock path) call — instead
of falling through to a plain `toTsType()` string match, which would degrade a nested shape to a
second `unknown[]` layer (`unknown[][]`). One resolver, three call sites, so a shape formats
identically everywhere it is found in a docblock: `{ key: string; label: string }[]`, not
`unknown[][]`.

A shape value that resolves to an unimportable bare class/enum token (no import channel exists
for a string-only shape map) still degrades that one key to `unknown` — `resolveArrayShapeString()`
applies the same `shapeValueHasUnimportableToken()` check `arrayableShapeType()` uses — so only
the unresolvable leaf is lost, not the whole shape.

### Intersection values (`X&Y`, `X&object{...}`)

A container's value slot may also be a PHPDoc intersection (`Collection<int, User&object{pivot:
TaskAssignment}>`). `resolveDocblockContainerValue()` splits it at the top level with
`splitPhpDocIntersectionType()` — mirroring `splitPhpDocUnionType()`'s brace/angle/paren-aware
scan — before falling through to the union split, resolves each member (an `object{...}` member
through `resolveArrayShapeString()`, same as any other shape; anything else recursively through
`resolveDocblockContainerValue()` itself), and joins the results with
`intersectTypeScriptInfos()`, which keeps every member's import channels via
`mergeTypeScriptInfos()` but joins the type strings with `' & '` instead of `' | '`. A member that
resolves to `unknown` is dropped rather than propagated — `A & B` is assignable to `A`, so losing
an untypable member only widens the result instead of collapsing the whole intersection to
`unknown`. Because an `object{...}` shape value is resolved through the same string-only shape
channel described above, a class-valued key inside it (`pivot: TaskAssignment`) still degrades to
`unknown`, even though the intersection's own member (`User`) carries a real import.
`wrapAsArray()` parenthesizes an intersection the same way it already parenthesizes a union, so
`Collection<int, User&object{pivot: TaskAssignment}>` resolves to `(User & { pivot: unknown })[]`.

### String-refinement key types

`resolveGenericContainerType()` treats a PHPStan string-refinement key (`non-empty-string`,
`class-string`, `class-string<Model>`, `literal-string`, `lowercase-string`, `numeric-string`, …)
the same as a plain `string` key, producing `Record<string, X>` instead of falling through to the
default int-keyed `X[]`; an actual int refinement (`positive-int`, `array-key`, …) is unaffected.

## Nullable-prefixed generics

`resolveGenericContainerType()` strips a leading `?` before attempting to match a container
(`?array<int, int>`, `?Collection<int, X>`), resolves the remainder as usual, and appends
`| null` to the result (skipped if already nullable) — mirroring `toTsType()`'s own step 0 for
the non-generic case. Without this, the `?` prefix stopped the container regex from matching at
all, so the whole generic fell through to `toTsType()`'s partial string matching and produced a
plausible-but-wrong scalar or an unwrapped `unknown`.

```php
/** @return Attribute<?array<int, int>, never> */
protected function stateIds(): Attribute
{
    return Attribute::get(fn () => null);
}
```

Resolves to `number[] | null`, not `unknown[] | null`.

## `@phpstan-type` / `@phpstan-import-type` alias resolution

`LaravelTsPublish::resolvePhpstanTypeAlias(string $name, ReflectionClass $context): array{definition:
string, class: ReflectionClass<object>}|null` looks up a phpstan/psalm type alias visible from
`$context`, in this order:

1. **Local definition** — a `@phpstan-type Name <definition>` (or `@psalm-type`) tag on
   `$context`'s own docblock. A definition whose `{}`/`<>` depth is unbalanced on its own line is
   walked across subsequent docblock lines (`balanceTypeDefinition()`) until it closes, so a
   multi-line `array{...}` shape is captured whole rather than truncated at the first line.
2. **Imported definition** — a `@phpstan-import-type Name from OtherClass` (or `... as Alias`) tag,
   resolved by recursing into `OtherClass`'s own `resolvePhpstanTypeAlias()` call. This is what
   makes resolution **transitive**: an import chain of aliases each re-exporting the next resolves
   all the way to the class that actually wrote the shape.

A `$seen` set of `"{FQCN}::{name}"` keys guards the import recursion — the same "already
expanding" pattern `arrayableShapeType()` uses for `Arrayable`/`JsonSerializable` shape cycles.
Two classes whose aliases `@phpstan-import-type` each other terminate with `null` (degrading the
referencing property to `unknown`) instead of recursing forever. Results are cached per class.

The returned `class` is the alias's **defining** class, not `$context` — its own use-map and
namespace, not the referencing class's, resolve any class names inside the definition. This
matters once the definition is expanded: `resolveDocblockTypePartOrAlias()` (the wiring used by
both `docblockReturnTypes()`/`resolveDocblockTypeString()` and
`refineWithPropertyDocblock()` below) tries `resolvePhpstanTypeAlias()` for each union member
before falling through to the ordinary generic/shape/class pipeline, and on a hit resolves the raw
definition (`resolveDocblockPartToInfo()`) against the alias's own file — deliberately *not*
alias-aware again, so two purely local aliases naming each other can't open an unguarded second
recursion path outside the `$seen`-guarded import chain.

`@property`'s own array-shape parser (`parseArrayShapeToTsTypes()`) keeps a key's `?` as part of
its returned map key (`'filters?'` rather than `'filters'`), so an alias expanding to
`array{filters?: ...}` emits `filters?: ...` in the generated interface instead of silently
dropping optionality.

A nullable member (`?Alias`) strips the leading `?` and recurses into
`resolveDocblockTypePartOrAlias()` with the bare name. A resolved alias returns its expanded type
from that recursive call; an unresolved name falls through to the ordinary pipeline and returns
`unknown`. Either way the outer call appends `| null`, unless the type already contains `null`,
mirroring the `?T` handling `toTsType()` does for a plain (non-alias) type.

## Castable-with-arguments cast strings

`resolveAttribute()` passes a model's raw cast value straight to `LaravelTsPublish::toTsType()`, including
compound `Castable` strings built by Laravel's own `AsEnumCollection::of()`/`AsCollection::of()`/`::using()`
helpers — these encode their arguments after the *first* colon (`"Illuminate\...\AsEnumCollection:App\Enums\Status"`),
since an argument is itself a class name and may contain `\`. `toTsType()`'s cast-string step (`resolveCastWithArguments()`)
splits on that first colon only and dispatches on the head:

| Head class | Args (comma-separated after `:`) | Resolves to |
| --- | --- | --- |
| `AsEnumCollection` (or subclass) | `[enumClass]` | the enum's TS type suffixed `[]`, `enumFqcns` populated for the import — identical wiring to a scalar enum-typed column |
| `AsCollection` (or subclass) | `[collectionClass, mapClass]` (either may be empty) | `mapClass`'s resolved element, suffixed `[]`, when it resolves to an inline `{...}` shape or an enum; `unknown[]` when `mapClass` is absent or resolves to anything else |
| any other existing `Castable`/`CastsAttributes` class | (ignored) | `toTsType($head)` — resolved exactly as the bare class, arguments stripped |
| a head that is not an existing class (`"decimal:2"`, `"encrypted:array"`) | — | untouched; falls through to the DB-type/`encrypted:` steps further down the waterfall |

The `AsCollection` branch only trusts an inline `{...}` shape or an enum as the array element — a bare unpublished
class token has no import channel, so that case degrades to `unknown[]` rather than emit an identifier nothing
imports.

## The DB-native type map is keyed on the grammar's output, not the migration method

When a column has no cast, `resolveAttribute()` falls to `LaravelTsPublish::toTsType($attr['type'])`
with `ModelInspector`'s raw `Schema::getColumns()` value — the native string a schema grammar emits
(`tinyint(1)`, or `point` for a MySQL `geometry(subtype: 'point')` column), never the migration method
name (`boolean()`, `geometry()`) — so `vendor/laravel/framework/.../Schema/Grammars/*Grammar.php`, not
vendor SQL documentation or the `$table->column()` method list, is the authoritative source for a new
`TypeScriptMap` entry.

## `@property` refinement: search order, trait walk, and the acceptance rule

`refineWithPropertyDocblock(ReflectionClass $reflection, string $attributeName, array $tsInfo):
array` only runs at all when `$tsInfo['type']` is already vague (see above) — a concrete waterfall
result is never second-guessed. When it does run, `propertyDocblockClasses()` builds the search
order: the class/parent chain first (child tag wins over parent), then every trait used anywhere in
that chain, recursively via `collectTraitsRecursively()` (`ReflectionClass::getTraits()`, walked
again on each trait for traits-of-traits). A trait is checked only after the whole
inheritance chain has been exhausted, so a real class-level tag always wins over one living on a
trait the class happens to use.

For each candidate class, `refineFromClassDocblock()` looks for an `@property`/`@property-read` tag
naming `$attributeName`. The `$` sigil before the property name is **optional** in the tag regex —
a non-standard but real-world convention (`@property string[] tag_names`, no `$`) some packages
use on a trait's own class docblock. This is safe to widen because the type-capture group still
excludes the literal `$` character entirely: a genuine `@property Type $name Description` line
still can't be walked through by a *different* attribute's search, since the type capture cannot
cross the `$` that marks the real variable, regardless of whether the sigil is required at the
match's own end. Only a docblock tag that never contains `$` anywhere on its line — the exact,
narrow case the optional sigil exists for — is affected by the relaxation.

### Acceptance rule: `isStrictlyMoreStructured()`

A refinement candidate is only used when it clears `isStrictlyMoreStructured(string $candidate,
string $current): bool`:

- A candidate that isn't vague at all (no `unknown`, not bare `object`) is always accepted.
- A candidate that *is* still vague is accepted only when **both** hold:
  1. `$current` — the type being replaced — is *entirely* vague: exactly `unknown`, `unknown[]`,
     `object`, or the `unknown[] | Record<string, unknown>` Collection fallback, each optionally
     suffixed `| null` (`isEntirelyVagueTsType()`).
  2. `$candidate` is not itself in that same entirely-vague set.

This is deliberately narrower than "not vague" — a bare `Record<string, unknown>` (vague per
`isVagueTsType()`, since it names `unknown` outside any `{...}`) still beats a bare `unknown[]`,
because it at least commits to a dictionary shape the original carried no information about at
all. But it does **not** beat another already-somewhat-structured vague type: refining
`Record<string, unknown>` itself, or refining into the Collection fallback
`unknown[] | Record<string, unknown>`, is rejected, since neither side of that comparison is
"entirely" vague to begin with.

```php
/** @property array<string, mixed>|null $settings */
class Team extends Model
{
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
```

`settings` resolves through the `'array'` cast to `unknown[]` first — entirely vague — so the tag's
`Record<string, unknown> | null` is accepted even though it still names `unknown`, and `settings`
generates as `Record<string, unknown> | null` rather than `unknown[] | null`.

### `@property` as the last fallback for a name that is neither a column nor a relation

`resolveAttributeFallbacks()` tries, in order: the snake_case-accessor alias, then the
`_count`/`_exists` relation-suffix guesses, and only once both have declined does it consult
`propertyDocblockClasses()` for an `@property`/`@property-read` tag naming the attribute outright.
This last step exists for a query-selected virtual attribute — a `select`/`selectRaw` column with
no backing DB column, cast, or accessor, typed only by an ide-helper-style class docblock tag —
which reaches `ModelAttributeResolver` when a resource or handler references it, but is never
itself part of `ModelTransformer`'s own generated interface, since that transformer iterates
`ModelInspector`'s attributes, not this resolver's fallback chain.

The lookup is guarded on the name (and its camel alias) not already being a relation: an
ide-helper `@property-read Collection<int, Child> $childRows` tag commonly documents a relation
for IDE purposes only, and `resolveRelation()` — not this fallback — is the correct authority for
a relation name's type. Without the guard, a relation's own tag would satisfy `resolveAttribute()`
with a plausible-looking type that ignores relation nullability and `morphFqcns`.

## MorphTo target resolution: docblock generic → reverse map keyed by morph name

`resolveMorphToTargets(string $modelFqcn, string $relationName): list<class-string>` is the
single entry point both `resolveRelation()` and `ModelTransformer::transformRelations()` call for
a `MorphTo` relation's target union — a duplicate ad hoc implementation in `ModelTransformer` was
exactly how a docblock generic went unread by the actual publish pipeline even after
`resolveRelation()` itself honored it, which is why only one method owns this now. Resolution
order:

1. **`morphToDocblockTargets()`** — a `@return`/`@phpstan-return MorphTo<X|Y, ...>` generic on the
   relation method. Only the *first* generic argument is read (the second, `$this` by Laravel's
   own convention, names the child and carries no target information). Each union member is
   resolved to a FQCN through `methodDeclaringFileClass()`'s use-map (see below — critical for a
   trait-provided relation) and must be an existing, concrete `Model` subclass; a bare `Model` or
   an abstract class anywhere in the union makes the *whole* generic non-narrowing (`[]`), since a
   `MorphTo<Model, $this>` generic is Larastan's own placeholder for "targets not statically
   known" and emitting a literal `Model` token would be a useless, unimportable-in-spirit result.
2. **The reverse-relation map, keyed by morph name.** When the docblock is absent or non-narrowing,
   `relationMorphName()` invokes the relation method on an unpersisted instance (safe — building
   an Eloquent relation only appends to a query builder, it never queries) to read
   `MorphTo::getMorphType()` (`'imageable_type'` minus the `_type` suffix → `'imageable'`), then
   looks up `getMorphToTargets($modelFqcn, $morphName)`.

`buildMorphTargetMap()` writes two keys per parent relation found: `childFqcn.'|'.morphName` (so
two differently-named `morphTo`s on the same child model — e.g. `Activity::causer` and
`Activity::subject` — don't share a union) and the plain `childFqcn` as a legacy aggregate bucket.
`getMorphToTargets()` tries the specific key first and falls back to the plain bucket, so a model
whose own morph name can't be determined (constructor throws, `getMorphType()` unavailable)
degrades to the old, model-wide behavior instead of losing the union to `unknown`. A model with
exactly one `morphTo` relation is unaffected either way, since its keyed and legacy buckets always
hold the same parents.

### A parent targeting a subclass of the child still types the base child's `morphTo`

A parent's `MorphOne`/`MorphMany` may declare its related model as a **subclass** of the model
that actually owns the `morphTo()` — e.g. `Venue::reviews()` returns
`$this->morphMany(VenueReview::class, 'reviewable')`, where `VenueReview extends Review` and only
`Review` declares `reviewable(): MorphTo`. `buildMorphTargetMap()` keys that relation under
`VenueReview::class.'|reviewable'`, not under `Review::class`, so a plain lookup of
`getMorphToTargets(Review::class, 'reviewable')` would miss it entirely — `Review` never appears
as a key on its own account. `getMorphToTargets()` closes that gap with a second pass: for every
`|`-keyed bucket in `$morphTargetMap`, if the bucket's morph name matches and its child
(`VenueReview`) is a real `is_subclass_of()` descendant of the model being queried (`Review`), its
parents (`Venue`) are unioned in. A query against the subclass itself
(`getMorphToTargets(VenueReview::class, 'reviewable')`) does not re-trigger this pass — the loop's
own `$mappedChild !== $childModelFqcn` guard skips a bucket matching the queried class exactly, and
no other `|`-keyed bucket is `VenueReview`'s subclass — so `VenueReview::reviewable` stays scoped
to `Venue`; a subclass never inherits its siblings' parents (`ArtistReview`'s `Artist` parent stays
absent from `VenueReview`'s union, and vice versa).

The union only runs in this one direction. A parent declared against the *base* child
(`SomeModel::morphMany(Review::class, 'reviewable')`) is never folded into a subclass's targets,
because at runtime that relation always returns plain `Review` instances — Eloquent's morph map
resolves each row to the class named in its `reviewable_type` column, and a row written through
that parent's relation carries `Review::class` (or whatever `Review`'s morph alias is) there, never
`VenueReview::class` — so unioning it into `VenueReview::reviewable` would claim a target that row
can never actually be.

### A `morphToMany(...)->using(Pivot::class)` makes its pivot's own `morphTo` resolvable

A `MorphToMany` relation is a different shape entirely — its `related` FQCN is the many-to-many
target (`Label`), not a `morphTo`'s child — so `buildMorphTargetMap()`'s relation loop diverts it
into its own branch before `isMorphParentRelation()` ever sees it: `str_contains($relation['type'],
'MorphToMany')` (confirmed against `Illuminate\Database\Eloquent\ModelInspector::getRelations()`,
which sets `'type'` to `Str::afterLast(get_class($relation), '\\')` — always the bare class name
`'MorphToMany'`, never the FQCN) routes to `morphPivotKey()` instead.

When a custom pivot model is wired in with `->using(Labelable::class)` and that pivot itself
declares a `morphTo()` (`Labelable::labelable()`), the pivot row's own `labelable_type` column
names the *declaring* parent (`Venue`, `Artist`) directly — the same shape as any other `morphTo`,
just reached through a pivot model instead of a plain child model. `morphPivotKey()` reads the
relation's `getPivotClass()` and, when it's neither the base `Pivot` nor `MorphPivot` (i.e. a real
`->using()` was supplied), keys the same way as a `MorphOne`/`MorphMany`: `PivotFqcn.'|'.morphName`,
using `MorphToMany::getMorphType()` (`'labelable_type'` minus the suffix) so the key lines up
exactly with the morph name `Labelable::labelable()` itself resolves through `relationMorphName()`.
Because it's the same `Fqcn|morphName` shape `buildMorphTargetMap()` already writes for `morphMany`
relations, `getMorphToTargets(Labelable::class, 'labelable')` needs no separate code path — it hits
the primary keyed lookup directly, not the subclass-union second pass.

Three relation shapes add nothing to the map, all handled by returning `null` from
`morphPivotKey()`: a `morphToMany()` call with no `->using()` at all (`getPivotClass()`'s only
fallback is the base `Pivot::class` — `$this->using ?? Pivot::class` — never `MorphPivot`, which is
excluded from the same check purely in case a caller passes `->using(MorphPivot::class)` explicitly
without narrowing it further), a `morphedByMany()` (`MorphToMany::getInverse() === true`) — the
*inverse* declaration, found on the many-to-many target (e.g. `Tag::posts()`), not the polymorphic
owner: keying that side would record the wrong parent, since the pivot's morph column stores the
*forward* declarer's own class (`Post`, via `Post::tags()`'s `morphToMany()`), never the inverse
side's — and a `->using()` argument that isn't actually a `Model` at all. `getPivotClass()`'s
`class-string<Pivot>` bound is docblock-only (`using()` itself takes no native parameter type), so
Laravel accepts any class there at runtime; `morphPivotKey()` keeps its own `is_a($pivot,
Model::class, true)` runtime check rather than trusting the docblock bound, even though PHPStan
would otherwise report it as an already-narrowed, always-true condition (silenced with `@phpstan-
ignore function.alreadyNarrowedType`, matching the inline-ignore convention already used elsewhere
in this codebase, e.g. `CoreCollector::collect()`).

## An unresolved MorphTo stays bare `unknown`, never `unknown | null`

When a `MorphTo` has no targets — the docblock generic is absent/non-narrowing and the reverse map
finds no parent — `buildMorphUnionInfo()` types it `'unknown'`. `unknown` already admits `null`, so
appending `' | null'` for a nullable FK would only add a redundant union arm; `buildMorphUnionInfo()`
skips the suffix whenever the resolved base type is exactly `'unknown'`.

`ModelTransformer::transformRelations()` does not call `buildMorphUnionInfo()` — it re-derives the
same union from `resolveMorphToTargets()` and applies its own nullable suffix, because the model's
own interface generation predates `resolveRelation()`'s consolidation (see the section above). The
same `!== 'unknown'` guard is applied there independently, so a model's own MorphTo relation and a
resource's inline reference to it never disagree on this.

## Declaring-file use-maps for trait-provided methods

Every docblock resolution that needs "the use-map/namespace of the file that wrote this
docblock" — `docblockReturnTypes()`, `resolveDocblockTypeString()`,
`parseDocblockReturnArrayShape()`, `ModelAttributeResolver::morphToDocblockTargets()` — resolves
that file via `methodDeclaringFileClass()`, not `ReflectionMethod::getDeclaringClass()` directly.
For a method a class picks up from a `use`d trait, PHP's own `getDeclaringClass()` reports the
*consuming* class, not the trait — a
long-standing reflection quirk, since traits are flattened into the class at compile time — even
though `getFileName()` and `getDocComment()` still read from the trait's own source.
`methodDeclaringFileClass()` compares the method's real file (`getFileName()`) against the
declaring class's file, and when they differ, walks the declaring class's traits (recursively, for
traits-of-traits via `flattenTraits()`) to find the one whose file matches. Left unfixed, a class
name in a trait-declared accessor's docblock — `@return Attribute<Collection<int, OptionValue>,
never>` — would resolve against the *consuming* model's imports instead of the trait's, silently
degrading to `unknown` whenever the model doesn't happen to import the same class.

A trait's own `@template` names are a second case the file swap alone doesn't fix: a generic trait
docblock — `@return Attribute<EloquentCollection<int, TChild>, never>` under a trait-level
`@template TChild of Model` — resolves `TChild` against nothing, because a template name is never a
real class the trait's own use-map could ever hold. `bindTraitTemplates()` runs before
`resolveDocblockTypeStringAgainst()` in both `docblockReturnTypes()` and
`resolveDocblockTypeString()`, substituting each of `traitTemplateNames()`'s ordered `@template`
names with the matching argument from `traitUseArguments()` — the consumer's (or an ancestor's)
`@use Trait<X>` tag, `@phpstan-use`/`@psalm-use` spelled the same way — before the type string ever
reaches resolution. A method that isn't trait-declared, or a trait with no matching `@use` binding
in the class hierarchy, passes the type string through unchanged.

`@use Trait<X>` sits on the `use` statement inside the class body, not on the class docblock, so
`traitUseArguments()` cannot reuse `ReflectionClass::getDocComment()`. It parses the consumer's file
through `AstParser::parseFile()`, which caches the AST per path and records the cache dependency
itself, matching the [dependency recording policy](ast-engine.md#dependency-recording-policy) every
other file-read in the package follows. It finds the consumer's `Class_` node by its resolved name,
then reads the tag from the doc comment of the one `TraitUse` statement inside that class naming the
target trait, in the `@use`, `@phpstan-use`, or `@psalm-use` spelling. A prose mention elsewhere in
the file, for example inside the class docblock, is never a candidate. Only that statement's own doc
comment is read. `traitUseArguments()` checks the consumer before its parents, and skips a class
with no file rather than reading it.

The trait name in the tag must resolve through the file's own `use` imports or its namespace.
`resolveDocblockTypeName()`'s namespace fallback checks `class_exists()` and `enum_exists()`, not
`trait_exists()`, so a trait referenced by its bare name from the same namespace, with no `use`
import, does not resolve, and the binding silently fails.

## `publishedColumnNames()` and the `exclude_hidden` coupling

`databaseColumnNames()` is the raw schema listing (every real column, `$hidden` included).
`publishedColumnNames()` is the subset that actually reaches the emitted model interface — the
list a caller must use when naming keys against that interface, e.g. `ResourceAstAnalyzer`
deciding whether `$this->relation->only(['a', 'b'])` can reference `Pick<Model, 'a' | 'b'>`, or
`$this->relation->except([...])` can reference `Pick<Model, complement>`, instead of expanding
inline (see [ResourceAstAnalyzer § When a Pick reference is
emitted](resource-ast-analyzer.md#when-a-pick-reference-is-emitted)).

Which of the two a call site wants turns on the question it is asking. `publishedColumnNames()`
answers "may I name this key against the generated interface?", so it must track what
`transformColumns()` emitted. `databaseColumnNames()` answers "is this name a real column at all?",
which is what the runtime-fidelity call sites need and why `$hidden` membership is irrelevant to
them: `Ast\Concerns\ResolvesFilteredRelationTypes::resolveFilteredRelationType()`'s except branch intersects the related
model's attribute list with it so an inlined `$this->relation->except([...])` expands to columns
only, matching `HasAttributes::except()`, and `buildModelDelegatedAnalysis()` reads it as
`$dbColumns` to keep `isOmittedMutator()` from ever dropping a real column. Both apply
`excludeHiddenAttributes()` themselves, separately from the listing, so they get the hidden-column
rule without borrowing `publishedColumnNames()`' interface-compatibility rule.

Whether the two lists actually differ is controlled by `ts-publish.models.exclude_hidden`
(`excludeHiddenAttributes()`), which defaults to `false`: `$hidden` attributes are published by
default, so `databaseColumnNames()` and `publishedColumnNames()` agree for every model unless the
setting is turned on. `ModelTransformer::transformColumns()` reads the identical flag through the
same method to decide whether to skip a `$hidden` attribute when building the interface.

Both call sites must agree, because `Pick<Model, K>` constrains `K extends keyof Model` — TypeScript
error TS2344 fires if `publishedColumnNames()` ever names a key `transformColumns()` didn't emit.
`relationFilterModelReference()` binds this for both `only()`'s verbatim keys and `except()`'s
complement: the complement is computed by subtracting from `publishedColumnNames()` itself, so it
can never contain a key the gate above it didn't already clear.
`excludeHiddenAttributes()` is the single source of truth both sites read, and it is deliberately
**not** cached alongside `resolveContext()`'s per-FQCN model context: that cache holds data that's
inherent to the model (its columns, casts, hidden-array membership) and is safe to memoize for the
life of the resolver, but the config flag can change between calls (most concretely in a test that
flips `config()->set('ts-publish.models.exclude_hidden', ...)` between two assertions on the same
resolver instance) and must be re-read every time rather than trapped in that cache.

## `#[UseResource]` model-guessing is Laravel-version-guarded

`Ast\ModelClassResolver::guessModelFromUseResourceAttribute()` — the counterpart lookup that finds
which model a resource belongs to — checks for `Illuminate\Database\Eloquent\Attributes\UseResource`
behind `class_exists()` rather than a `use` import, because the package still supports Laravel 12
releases older than 12.29 that don't ship the attribute. See [Version-guarded Laravel
classes](../laravel-version-guards.md) for the full registry and when this guard can be removed.

## Laravel 13's `#[Table]`/`#[Hidden]`/`#[Visible]`/`#[Appends]`/`#[Connection]` work for free

These five Laravel 13 class attributes change how a model reports its table, its hidden/visible
columns, its appended accessors and its connection — and this package needed **no dedicated code**
to honour any of them. Laravel resolves each attribute itself, inside `Model::__construct()`, via
`initializeModelAttributes()` (`#[Table]`, `#[Connection]`) and the `#[Initialize]`-marked
`initializeHidesAttributes()` (`#[Hidden]`, `#[Visible]`) and `initializeHasAttributes()`
(`#[Appends]`) — by the time this package ever sees a model, the attribute has already been folded
into that instance's ordinary state (`$table`/`$connection`/`$hidden`/`$visible`/`$appends`).

This package only ever reads that state back through plain instance calls, the same four call
sites that already made `protected $table = '...'` etc. work before Laravel 13 existed:

- `ModelTransformer::initInstance()` (`src/Transformers/ModelTransformer.php`) calls
  `$this->modelInstance->getConnection()->getSchemaBuilder()->getColumnListing($this->modelInstance->getTable())`
  — honours `#[Table]` and `#[Connection]` together, since both feed into which schema is queried
  for which table name.
- `ModelTransformer::initInstance()` (`src/Transformers/ModelTransformer.php`) also calls
  `$this->modelInstance->getAppends()` — honours `#[Appends]`.
- `ModelAttributeResolver::databaseColumnNames()` (`src/ModelAttributeResolver.php`) calls
  `$ctx['instance']->getTable()` and `getConnection()` again when resolving a column's type.
- `ModelInspector::getAttributes()` (Laravel's own, in `vendor/laravel/framework/.../ModelInspector.php`,
  which `AbeTwoThree\LaravelTsPublish\ModelInspector` extends) calls `attributeIsHidden($column,
  $model)` against the **made instance** it inspects — `count($model->getHidden())` /
  `count($model->getVisible())` — honouring `#[Hidden]` and `#[Visible]` identically to the
  property-based conventions, because by then there is no distinction left to make.

**This is fragile in one specific way: it depends on working through instances, not static
reflection.** If a future refactor reads `#[Table]`/`#[Hidden]`/etc. directly off the class via
`ReflectionClass::getAttributes()` instead of asking a constructed instance for `getTable()` /
`getHidden()` / `getAppends()`, these five attributes stop working silently — the tests in
`ModelTransformerTest.php` would start failing, but nothing in their names or diffs points back to
this coupling. If you are that refactor: keep going through an instance, or update this section.

No `src/` code references these five attribute classes at all, so unlike `#[UseResource]` and
`#[Collects]` (see the section above), they need no `class_exists()` guard in `src/` and no
`use`-import ban there either. The workbench fixture models `use`-import `Table`/`Hidden`/
`Appends`/`Visible`/`Connection` normally, the same as any Laravel 13 application would — a `use`
statement is a compile-time alias, not an autoload, so it is inert on Laravel 12 for exactly the
same reason `getAttributes()` metadata is inert there: nothing calls `newInstance()` on it. Their
`class_exists()` reference lives only in each attribute's `->skip()` test guard in
`ModelTransformerTest.php`. See [Version-guarded Laravel
classes](../laravel-version-guards.md) for the test-only guard rows this implies.

## A missing table is reported, not guessed

`resolveContext()` inspects a model through `ModelInspector::inspect()`, whose `attributes`
collection comes straight from the schema (`getColumns($table)`). When the model's own table was
never migrated, that call finds no columns at all, and every attribute lookup on the model
silently resolves to `unknown` or the empty `TypeScriptTypeInfo` — indistinguishable, from the
published output alone, from a model that genuinely has no typed columns. A cast-based guess at
that point (say, treating every unresolved attribute as `unknown[] | Record<string, unknown>`
because the shape "looks like a row") would only paper over the real problem: the migration is
missing, not the type.

So `resolveContext()` reports it instead. Once `$attributes` comes back empty, it asks the schema
builder directly — `getConnection()->getSchemaBuilder()->hasTable($instance->getTable())` — and if
the table genuinely does not exist, records one `AnalysisWarnings::add()` entry naming the model
FQCN and the missing table/connection. `hasTable()` only runs on that already-empty path, so a
healthy model with real columns never pays for the extra schema round trip. Because
`resolveContext()` memoizes its result per model FQCN for the life of the resolver, the warning is
recorded at most once per model per `ts:publish` run, no matter how many attributes are looked up
against it.

`TsPublishCommand::renderAnalysisWarnings()` prints every recorded entry after the summary output
(the same mechanism `InertiaPageAnalyzer` uses for a degraded route action), so a missing migration
surfaces as a warning line telling the developer to run their migrations and publish again — not as
a silently empty model interface.

# ImportNameRegistry

[`ImportNameRegistry`](../../src/Support/ImportNameRegistry.php) assigns each class that a generated `.ts` file imports
a local TypeScript name that collides with nothing else in the file. A transformer reserves the names the file declares
itself, registers each FQCN it imports, and reads a `FQCN => local name` map back from `resolve()`.
[`ResolvesImportConflicts::applyResolvedImportNames()`](../../src/Transformers/Concerns/ResolvesImportConflicts.php)
turns that map into `import { X as Y }` aliases and rewrites the property types that spell them.

## Where things live

The registry and its consumers live in these files:

- [`ImportNameRegistry`](../../src/Support/ImportNameRegistry.php): the naming algorithm.
- [`ResolvesImportConflicts`](../../src/Transformers/Concerns/ResolvesImportConflicts.php): the shared trait that
  applies a resolved map and formats aliased imports.
- [`TsTypeString::aliasPropertyType()`](../../src/Support/TsTypeString.php): rewrites each occurrence of a type name in
  one property's type to its own alias.
- The consumers, each with its own `resolveImportConflicts()`:
  [`ModelTransformer`](../../src/Transformers/ModelTransformer.php),
  [`ResourceTransformer`](../../src/Transformers/ResourceTransformer.php),
  [`BroadcastEventTransformer`](../../src/Transformers/BroadcastEventTransformer.php), and
  [`AnalysisComposer`](../../src/Ast/AnalysisComposer.php), which serves `AstEngine::analyze()`.

## How a name is chosen

A type name registered once, and not reserved, keeps its bare name. Every other FQCN climbs this ladder only as far as
it must:

1. **Preferred alias**: a name the caller suggests, such as the relation-derived `OwnerUser`. It survives only if it
   is unique.
2. **Nearest namespace segment**: the immediate parent segment, in StudlyCase, prefixes the type name. A configured
   `ts-publish.namespace_strip_prefix` is stripped first, then segments in `$skipSegments` (default `Models`, `Enums`,
   `App`) are dropped. When every segment is skip-listed, as in `App\Models`, the unfiltered segments are used.
3. **One segment deeper per round**: each round advances every member whose candidate still collides with a group-mate
   or a taken name. A member that is already unique keeps its alias, so `A\B\Foo`, `C\Foo` and `D\C\Foo` resolve to
   `BFoo`, `CFoo` and `DCFoo`. Advancing only the newest collider could leave a member on a shallow alias that looks
   unique only against another member's previous candidate.
4. **Numeric suffix**: a member that runs out of segments takes `2`, `3` and so on, in FQCN order. The first keeps the
   unsuffixed name.

One segment is not enough to be unique. Two unrelated `MailPrice` models, each in a `MailPrice\Models` namespace, both
come out of step 2 as `MailPriceMailPrice`, a TypeScript duplicate identifier (`TS2300`). Step 3 extends both until
they differ.

The result holds two invariants:

- **Global uniqueness**: no two values of `resolve()` are equal, and none equals a reserved name. A reserved name forces
  even a lone import to alias, so `App\Models\Order` becomes `ModelsOrder` in a file that declares its own `Order`.
- **Determinism**: an FQCN's alias depends only on the set of FQCNs and type names registered, never on the order of
  `register()` calls. Groups are processed in type-name order and members in FQCN order.

## Rewriting aliased type references

`applyResolvedImportNames()` aliases a type and a const each on its own account, since an enum reached only through an
inline `EnumResource::make()` has no type import to alias. It calls the consumer's `rewriteTypeReferences()` once if a
type was aliased.

`rewriteTypeReferences()` hands each property's type and FQCN list to `TsTypeString::aliasPropertyType()`, except that
`ResourceTransformer` skips a key whose `#[TsCasts]` entry brings its own import, since that text is the app's own. The
list is a queue per type name, so occurrence N of a name in the type string takes the Nth FQCN registered under that
name. Order and multiplicity are the contract, so never sort or dedupe the list. Deduping `Crm, App, Crm` to
`Crm, App` retypes the third occurrence as the app model. `WarehouseResource::regional_hub_contacts` pins that
interleaved case.

The queue lines up with the type string because each builder writes a union's arms and its FQCN list in one loop:
`LaravelTsPublish::mergeTypeScriptInfos()` for a class union and `ModelAttributeResolver::buildMorphUnionInfo()` for a
morph union. Reorder one without the other and aliases land on the wrong arms.

In `mergeTypeScriptInfos()`, a decorated arm such as `User[]` repeats an earlier one only when its text and the classes
behind its tokens both match, so `User[]|CrmUser[]` merges to `User[] | User[]`. `classFqcns` lists each class once, so
when two classes share a name the merged info also carries `classTokenFqcns`, the class behind each token it kept.
Consumers read the queue through [`ClassTokenQueue::fqcnsOf()`](../../src/Support/ClassTokenQueue.php), which falls back
to `classFqcns`.

Two more builders write one queue entry per token. `ValueResult::unionResults()` reads a union by class, one member per
class, when its arms spell one name for two classes, and keeps the merge by text when an arm queues a name for two
classes more often than it spells it (`ClassTokenQueue::outrunsItsTokens()`). `InlineArrayHandler::memberFqcns()`
queues each member of an inline array through `ClassTokenQueue::perToken()`, as the merge above does for each decorated
arm. It repairs a queue to one entry per token only for a name with one class behind it that a model and a resource do
not both queue, so `User[] | Record<string, User>` queues its class twice.

Callers fill their queues in one of two ways:

- **Exact**: `ModelTransformer` fills one entry per occurrence.
- **Superset in order**: `ResourceTransformer::mergePropertyFqcnMaps()`,
  `BroadcastEventTransformer::collectPropertyFqcns()` and `AnalysisComposer` concatenate several per-property channels.
  A property in two channels carries both channels' entries, so its queue can run past the real occurrences. That is
  safe only while the prefix lines up, which is why `ResourceTransformer::resolveMultiClassAccessorFqcns()` skips a
  property the inline maps already cover.

`aliasPropertyType()` clamps the cursor to the queue's last entry. One FQCN therefore covers every occurrence, as in
`User[] | Record<string, User>` from one `App\Models\User`, and a longer queue's surplus is never read. A queue shorter
than the occurrences also falls back on its last entry, but that is a backstop against a bare token, not the
mechanism. It cannot recover the right model.

**Invariant: no bare colliding token survives `aliasPropertyType()`.** [The unimportable-token
gate](../testing/type-inference-gates.md) depends on it, because a bare `User` in a file that imports only
`User as ModelsUser` and `User as CrmUser` is a `TS2304`.

The trailing `(?![A-Za-z0-9_$])` lookahead, not the longest-first order of the alternation, is what stops `User` from
matching the start of `UserProfile`.

## Consumers

Each transformer's `resolveImportConflicts()` reserves its file's own interface name, registers the FQCNs from its own
maps, and passes the result to `applyResolvedImportNames()`. `AnalysisComposer` runs the same two registries as
`ResourceTransformer`, with the same skip list, and reserves no interface name because its caller names the interface.
Only `ModelTransformer` suggests preferred aliases, derived from relation names. The others have no relation to derive
one from.

Every consumer unions its FQCN maps with `+`, which is safe only because no FQCN lands in two of them. The analyzers
put only `JsonResource` subclasses in a resource map and only Eloquent models in a model map, and an enum is neither.
PHP does not enforce the resource and model split, and a class extending both would break it.

**Types resolve first, and a const steps aside.** `ModelTransformer`, `ResourceTransformer` and `AnalysisComposer`
resolve enum const names through a second, sibling `ImportNameRegistry` with the same skip list, because slicing a const
name out of a type alias would break at the numeric tiebreak. The two run in lockstep, so `StatusType` aliased to
`CrmStatusType` pairs with `Status` aliased to `CrmStatus`. TypeScript gives value and type imports one identifier
namespace, so `ResolvesImportConflicts::applyImportNameRegistries()` resolves the types first and reserves every name
they took in the const registry. An enum `Role` (type `RoleType`) imported beside an enum `RoleType` (const `RoleType`)
keeps the type and aliases the const, and so does a model `Grade` imported beside the `Grade` enum's const. The `Badge`
fixtures pin both shapes.

The file's own interface name is reserved for types only, since a value import merges with a local interface of the same
name. The `Grade` model fixture pins that its const stays unaliased. Two enums in one namespace are a different
collision, which no alias can settle.
[Known gaps](../known-gaps.md#an-enum-named-like-another-enums-type-name-collides-with-it) records it.

### `ModelTransformer`

A model that backs exactly one relation gets the preferred alias `Str::studly($relation).$typeName`, such as
`OwnerUser`. A model reached by two or more relations, or by none, starts from namespace segments. The model being
transformed is never registered.

### `ResourceTransformer`

`ResourceTransformer` adds two rules of its own:

- **Inline-only consts**: an enum reached only through `EnumResource::make()` inside an inline array never enters
  `$enumFqcnMap`, because it needs no bare type import. A second loop registers those leftover consts, so two with one
  name resolve apart instead of shipping as two identical value imports from different files (`TS2300`).
- **The inline wrap's own token**: aliasing the import does not rename the const inside `AsEnum<typeof …>`.
  `rewriteEnumResourceTypes()` substitutes it later, through its own `aliasTypeofConst()` call keyed on the const
  maps. [ResourceAstAnalyzer § The inline wrap's own const token is aliased by the transformer, not
  here](resource-ast-analyzer.md#the-inline-wraps-own-const-token-is-aliased-by-the-transformer-not-here) explains why
  it cannot reuse `rewriteTypeReferences()`.

### `BroadcastEventTransformer`

It has no const registry. Broadcast payloads reference enums as types, never as `AsEnum<typeof Const>` values, so its
`$enumConstMap` is always empty and no const alias is ever resolved.

## Related

These pages hold the user docs and the neighboring rules:

- [Classes Sharing a Name Across
  Namespaces](https://tolki.abe.dev/ts/api-resources.html#classes-sharing-a-name-across-namespaces) in the tolki
  docs, for the aliasing users see.
- [Type inference gates](../testing/type-inference-gates.md), whose `TS2300` and `TS2304` counts check this page's
  invariants over the generated trees.
- [ResourceAstAnalyzer](resource-ast-analyzer.md) and [AstEngine](ast-engine.md), which produce the FQCN channels the
  queues are built from.

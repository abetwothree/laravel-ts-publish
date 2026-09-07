# ImportNameRegistry

> User-facing docs: [README § Models](../../README.md#models). Verified by [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry` assigns collision-free local
TypeScript names to the FQCNs a generated `.ts` file imports. A transformer registers every
FQCN it needs to import — plus any names the file already declares for itself, via
`reserve()` — and `resolve()` returns a deterministic `FQCN => local name` map. It exists
because the previous aliasing approach (a single namespace-segment prefix) could assign the
*same* alias to two *different* classes: two unrelated `MailPrice` models both under an
`Engineering` segment resolved to the identical `EngineeringMailPrice`, a TypeScript
duplicate-identifier error. `ImportNameRegistry` guarantees that cannot happen.

## Algorithm

Every FQCN registered under a type name starts with a candidate local name — a ladder it
climbs only as far as needed:

1. **Preferred alias**, if the caller supplied one (e.g. a relation-derived name such as
   `OwnerUser`) — used only if it is globally unique.
2. **Namespace segments, nearest-first.** Failing (or absent) a preferred alias, the
   candidate is the immediate parent namespace segment (StudlyCase) prefixed to the type
   name — `MailPrice` → `EngineeringMailPrice`. Segments in `$skipSegments` (default
   `Models`, `Enums`, `App`) are filtered out first, and a configured
   `ts-publish.namespace_strip_prefix` is stripped from the namespace before segments are
   read. If every segment is skip-listed (e.g. `App\Models\Order`), the unfiltered segments
   are used instead so the alias can still be built.
3. **Extend one segment deeper per round.** A group is one type name registered more than
   once, or a type name that is reserved. Each round recounts the group's current candidates
   and advances only the members whose own candidate still collides: a colliding preferred
   alias drops to the one-segment prefix; a colliding prefix extends one segment further into
   the namespace (`MailPrice` → `EngineeringMailPrice` → `CustomerEngineeringMailPrice`). A
   member already unique against both its group-mates and the reserved set is skipped and
   keeps the alias it holds — register `A\B\Foo`, `C\Foo` and `D\C\Foo` together and
   `A\B\Foo` stays on the depth-1 `BFoo` while the other two resolve to `CFoo` and `DCFoo`.
   Advancing every *still-colliding* member in the same round, rather than only the newest
   collider, is what stops a member being left on a shallow alias that merely looks unique
   against another member's *previous* candidate.
4. **Numeric suffix**, as the final tiebreak, for any member that exhausts its namespace
   (or two FQCNs that are otherwise identical past the root) — `2`, `3`, … in FQCN-sorted
   order, the first member keeping the unsuffixed name.

## Invariants

- **Global uniqueness.** `resolve()`'s values never collide with each other, and never equal
  a name passed to `reserve()`. A reserved name forces even a lone import of that name to
  alias (e.g. `App\Models\Order` becomes `ModelsOrder` when `Order` is reserved by the file
  itself).
- **Determinism.** The alias a given FQCN receives depends only on the *set* of FQCNs and
  type names registered — never on the order `register()` was called in. Within a colliding
  group this comes from sorting members by FQCN before assigning numeric tiebreaks; across
  groups with different type names, from processing groups in type-name order rather than
  registration order.

## Rewriting aliased type references

`applyResolvedImportNames()` records an FQCN in `$importAliases` only when its resolved local name
differs from its type name, then calls the transformer's `rewriteTypeReferences()` once.

It handles `$constImportAliases` in two passes. The first walks `$resolved` (the type registry's
own output) and cross-references `$constNames` by the same FQCN — the case where a const's FQCN
also carries a bare type import, true for every enum every consumer registers today except one.
The second is a leftover pass over `$constNames` alone, for any FQCN present there but absent
from `$typeNames`: `ResourceTransformer` is the one consumer with such FQCNs — an enum reached
only through an inline `EnumResource::make()` wrap never needs a bare type import, so it never
reaches the first pass. The second pass is a proven no-op for the other two consumers rather than
dead code kept "just in case": `ModelTransformer`'s const registry is populated only by mirroring
`$enumFqcnMap`, so its `$constNames` is always a subset of `$typeNames`'s keys, and
`BroadcastEventTransformer` never passes `$constNames` at all. Generalizing this into the shared
method — rather than a second, transformer-local pass over `$constRegistry->resolve()` — is what
lets `ResourceTransformer::resolveImportConflicts()` stay a single call to
`applyResolvedImportNames()`; see its own section below.

Each transformer walks its own per-item FQCN map — `mergePropertyFqcnMaps()` in `ResourceTransformer`,
`$columnFqcns`/`$mutatorFqcns`/`$appendsFqcns`/`$relationFqcns` in `ModelTransformer`, `$propertyFqcns`
in `BroadcastEventTransformer` — and hands each item's list to `TsTypeString::aliasPropertyType()`.
Callers must neither sort nor dedupe that list: multiplicity and order together *are* the contract.
`ModelTransformer` and `BroadcastEventTransformer` supply **one entry per occurrence, in registration
order**, exactly: `ModelTransformer` by construction (`$columnFqcns[$name][] = $fqcn` and two of its
three siblings are plain appends — `$relationFqcns[$name]` is assigned once, wholesale, from the same
`$morphTargets`/`[$relation['related']]` list that built the relation's type string, so it is
per-occurrence by construction too), `BroadcastEventTransformer` by dropping the
`array_values(array_unique(...))` its `$propertyFqcns` assignment used to end in.

`ResourceTransformer::mergePropertyFqcnMaps()` promises less. It concatenates seven per-property maps
group by group, so a property registered in more than one of them carries every map's entries in
sequence — a **superset-in-order, prefix-aligned** queue, not an exact per-occurrence one. Dropping
its own `array_unique(...)` call is what lets a property naming the same model twice resolve both
occurrences to it; a property a *second* map also registers carries that map's entries too, past the
real occurrence count, and the third clamp below is what keeps that surplus harmless.
`resolveMultiClassAccessorFqcns()` guards the one specific overlap that is cheap to close at the
source — a property `$propertyInlineModelFqcns` already covers is skipped rather than re-queued — but
the general superset case remains the contract callers rely on, not an exception routed around it.

The `array_unique` calls that remain in `ResourceTransformer` and `BroadcastEventTransformer` are on
import-building paths — `enumPropertyFqcns()` and `buildTypeImports()` — where one entry per import
is what you want.

`aliasPropertyType()` builds one queue of aliases per type name, in FQCN source order, then walks
the type string's occurrences left to right with `preg_replace_callback`, so occurrence N takes
FQCN N. When the list is per-occurrence the queue is exact and every occurrence resolves to its own
model — including the interleaved case `Crm, App, Crm`, where the repeat is *not* the trailing FQCN.

Three clamps sit underneath that:

- **One FQCN owns the name.** The queue has length 1, so every occurrence is provably it and every
  one is rewritten — the widened-container case, `User[] | Record<string, User>` from a single
  `App\Models\User`.
- **A queue shorter than the occurrence count.** The last FQCN covers the overflow. This is a
  backstop against a bare token, not the mechanism: any caller that supplies true multiplicity never
  reaches it. It cannot recover the right model — deduping `Crm, App, Crm` to `Crm, App` retypes the
  third occurrence as `App`, which is why the dedupe was removed rather than compensated for.
- **A queue longer than the occurrence count.** `ResourceTransformer::mergePropertyFqcnMaps()`'s
  superset case lands here: a property two of its seven merged maps both register carries every
  map's entries, so the queue outgrows the real occurrence count. The prefix the occurrences do
  consume is unaffected — the surplus past it is simply never read.

Occurrence order *is* FQCN source order by construction, not by luck. `mergeTypeScriptInfos()`
appends to `$types` and `$orderedClassFqcns` inside the same loop iteration and then returns
`implode(' | ', $types)` alongside `classFqcns => $orderedClassFqcns`, so arm N of a plain class
union is FQCN N. `ModelAttributeResolver::buildMorphUnionInfo()` does the same for a morph union:
its type is `implode(' | ', array_map(class_basename(...), $targets))` and its `morphFqcns` is
`$targets`.

A shorter name that prefixes a longer one (`User` against `UserProfile`) cannot claim its match, but
the *mechanism* is the trailing `(?![A-Za-z0-9_$])`, not the alternation order: `User` matches, the
lookahead sees `P` and fails, and PCRE backtracks into the longer alternative. The `usort` that orders
the alternation longest-first is harmless defensive code — deleting it leaves the whole suite green,
including `a longer registered name is not shadowed by a shorter one that prefixes it`.

**Invariant: no bare colliding token survives `aliasPropertyType()`.** Every name that reaches a
queue is rewritten at every occurrence, because the cursor clamps to the queue's last entry rather
than running off the end.
The invariant is what
[the unimportable-token gate](../testing/type-inference-gates.md) depends on — a bare `User` left
in a file that imports only `User as ModelsUser` and `User as CrmUser` is a `TS2304`. The
predecessor, `aliasTypeName()`, rewrote either every occurrence or exactly one per aliased FQCN,
which held only while a basename occurred exactly as often as it had *distinct* FQCNs.
`WarehouseResource::regional_hub_contacts` — `primaryContact`, `manager`, `secondaryContact` named
in one `only()`, resolving to `Crm\Models\User`, `App\Models\User`, `Crm\Models\User` — pins both
halves: the old heuristic left the third occurrence bare, and a deduped list retypes it as the app
user.

## Consumers

Each transformer's `resolveImportConflicts()` builds the registry (or registries) that fit its
own FQCN maps, then hands the resolved names to the shared
`ResolvesImportConflicts::applyResolvedImportNames()` step — the part that actually assigns
aliases and, when anything was aliased, triggers `rewriteTypeReferences()` — rather than each
transformer walking `$registry->resolve()` itself.

### `ModelTransformer`

`ModelTransformer::resolveImportConflicts()` builds one `ImportNameRegistry` per model file:

- **Reserved names.** The model's own interface name (`$this->modelName`) is reserved before
  anything is registered, so an imported class that happens to share the current model's name
  is forced to alias even if it would otherwise be the only member of its group.
- **Registration.** Every enum FQCN in `$enumFqcnMap` is registered with no preferred alias.
  Every model FQCN in `$modelFqcnMap` (excluding the model being transformed) is registered
  too; if it backs exactly one relation, the relation name supplies a preferred alias
  (`Str::studly($relation).$typeName`, e.g. `OwnerUser`) — a model reached by two or more
  relations, or not reached by any (e.g. a plain column cast), gets no preferred alias and
  starts straight from namespace segments.
- **Const-alias mirroring.** Const names are resolved through a second, sibling
  `ImportNameRegistry` (same skip list) rather than derived by string-slicing the type
  alias — slicing breaks once a member reaches the numeric tiebreak, where the local name is
  no longer literally "prefix + type name". Within each registry the two run in lockstep (both
  names are deterministic functions of the same enum FQCN), so the sibling registry naturally
  mirrors the type registry's chosen depth: `StatusType` aliased to `CrmStatusType` pairs with
  `Status` aliased to `CrmStatus`.

  The two registries do **not** see each other, though, and TypeScript gives value and type
  imports one shared identifier namespace. An enum named `Role` (const `Role`, type `RoleType`)
  imported alongside an enum named `RoleType` (const `RoleType`, type `RoleTypeType`) collides
  on `RoleType` across the registries, and neither one notices — the emitted file gets a
  `TS2300`. Known limitation; it needs a name whose `…Type` form is another imported enum's own
  name, so it has not been observed in practice.
- **Applying the result.** `applyResolvedImportNames($registry->resolve(), $this->enumFqcnMap +
  $this->modelFqcnMap, $constRegistry->resolve())` — the `+` union is safe because a given FQCN
  is never legitimately both an enum and a model.

### `ResourceTransformer`

`ResourceTransformer::resolveImportConflicts()` follows the same shape as `ModelTransformer`,
with `new ImportNameRegistry(['Models', 'Enums', 'Http', 'Resources', 'App'])`:

- **Reserved names.** The resource's own interface name (`$this->resourceName`) is reserved.
- **Registration.** Every enum FQCN in `$enumFqcnMap`, resource FQCN in `$resourceFqcnMap`,
  and model FQCN in `$modelFqcnMap` is registered with no preferred alias — resources have no
  relation-derived preference the way `ModelTransformer`'s models do.
- **Const-alias mirroring.** Identical to `ModelTransformer`: a sibling registry (same skip
  list) resolves enum const aliases independently of the type aliases.
- **Inline-only consts.** An enum reached only through `EnumResource::make()` nested inside an
  inline array literal (`analyzeInlineArray()`'s tolki branch) never enters `enumFqcnMap` — no
  bare type import is needed for it, only a value import for its const — so the loop that mirrors
  `enumFqcnMap` into the const registry never sees it. A second loop registers every
  `enumConstMap` FQCN the first loop skipped, guarded by `! isset($this->enumFqcnMap[$fqcn])` so
  it only ever adds the leftovers; the guard does not itself change `$constRegistry`'s output
  (`register()` keeps an already-registered FQCN's original slot, so re-registering it would be a
  harmless no-op), but it keeps the loop legible as "leftovers only." This is what lets two
  same-named consts that are *both* inline-only, or one inline-only and one already registered by
  the first loop, resolve to distinct names instead of colliding as two identical, unaliased value
  imports from different files — a `TS2300`.
- **Applying the result.** `applyResolvedImportNames($registry->resolve(), $this->enumFqcnMap +
  $this->resourceFqcnMap + $this->modelFqcnMap, $constRegistry->resolve())` — the three-way
  union is safe because no FQCN is written into more than one of the three maps. `resourceFqcnMap`
  is populated from `analysis->nestedResources` (`JsonResource` subclasses) and the
  `ResourceCollection` flat-alias branch (`analysis->flatTypeAliasFqcn`); `modelFqcnMap` from
  `analysis->modelFqcns` and the multi-class accessor branch in
  `resolveMultiClassAccessorFqcns()` — all four sites restricted, by how the upstream analyzers
  build their `classFqcns`/`flatTypeAliasFqcn`, to `JsonResource` or Eloquent model classes
  respectively. This is a property of *where these maps are populated from*, not something PHP's
  type system enforces (a class extending both `Model` and `JsonResource` is technically
  possible; nothing in this codebase creates or expects one).

  This single call is also what resolves the inline-only const's alias — no separate
  transformer-local step is needed. See [Rewriting aliased type references](#rewriting-aliased-type-references)
  above for `applyResolvedImportNames()`'s own leftover pass, which is what makes that work.
- **Substituting the inline wrap's own token.** Aliasing the import is not enough on its own — the
  property's *type string* still needs the corrected const name substituted into its
  `AsEnum<typeof {const}>` occurrence. That happens later, in `rewriteEnumResourceTypes()`, not
  here: see [ResourceAstAnalyzer § The inline wrap's own const token is aliased by the transformer,
  not here](resource-ast-analyzer.md#the-inline-wraps-own-const-token-is-aliased-by-the-transformer-not-here)
  for why it has to be a separate `aliasPropertyType()` call keyed on the const maps, rather than
  reusing `rewriteTypeReferences()`.

### `BroadcastEventTransformer`

`BroadcastEventTransformer::resolveImportConflicts()` uses one `ImportNameRegistry` per event
file, `new ImportNameRegistry(['Events', 'Enums', 'Models'])`:

- **Reserved names.** The event's own interface name (`$this->eventName`) is reserved.
- **Registration.** Every enum FQCN in `$enumFqcnMap` and model FQCN in `$modelFqcnMap` is
  registered with no preferred alias.
- **No const-alias handling.** `$enumConstMap` is always empty on this transformer (broadcast
  event payloads reference enums as types, never as tolki `AsEnum<typeof Const>` values), so
  there is no sibling const registry — mirroring one here would be dead code.
- **Applying the result.** `applyResolvedImportNames($registry->resolve(), $this->enumFqcnMap +
  $this->modelFqcnMap)` — the third argument is omitted, defaulting to `[]`, so const aliases
  are never populated.

All three transformer consumers are now on `ImportNameRegistry`; no transformer derives
aliases from a single namespace segment anymore.

# ResourceAstAnalyzer

> User-facing docs: [README § API Resources](../../README.md#api-resources). Verified by
> [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer` walks a `JsonResource`
subclass's `toArray()` method as PHP AST (not reflection) and infers a TypeScript type for
every returned property, following relation chains, closures, casts, and accessor waterfalls
back to their source.

## Relation filters

`$this->relation->only([...])` / `->except([...])` (and their `?->` nullsafe forms) analyze
via `RelationFilterHandler::analyzeRelationFilter()`. When the related model resolves to a single class (not a
multi-model accessor union such as `Attribute<ModelA|ModelB, never>`), the analyzer prefers to
**reference the related model's own generated interface** instead of re-deriving an inline
object shape — the model interface already carries `#[TsCasts]` overrides and `@property`
docblock refinements that a from-scratch recompute loses.

### When a Pick reference is emitted

`RelationFilterHandler::relationFilterModelReference()` builds `Pick<Model, 'a' | 'b'>` — `[]`-suffixed for
many-relations, `| null`-suffixed for nullsafe calls — whenever **every filter key is a column
the model interface actually declares**, per `ModelAttributeResolver::publishedColumnNames()`.
For `only()` the picked keys are the caller's own list, verbatim. For `except()` they are the
**complement**: `publishedColumnNames()` minus the named keys, in schema order — so the emitted
type always names what the property actually contains, not what it excludes.

That gate has to match the *emitted* interface, not just the schema. The raw schema listing
(`databaseColumnNames()`) is a superset only when `ts-publish.models.exclude_hidden` is enabled:
`ModelTransformer::transformColumns()` then skips `$hidden` columns, matching Laravel's own
serialization. `Pick<T, K>` constrains `K extends keyof T`, so naming a hidden column there would
be a hard `TS2344` — `publishedColumnNames()` subtracts them in that case, and such a key falls
back to inline expansion instead (which is also the more faithful output when the column is
excluded: `Model::only()` resolves through `getAttribute()` and *does* return hidden attributes at
runtime). The setting defaults to `false`, so by default hidden columns are published and
`publishedColumnNames()` includes them like any other column — see
[ModelAttributeResolver § `publishedColumnNames()` and the `exclude_hidden`
coupling](model-attribute-resolver.md#publishedcolumnnames-and-the-exclude_hidden-coupling).
The `keyof` constraint binds both branches identically now: `except()`'s complement is computed
by subtracting the named keys from that same already-gated column list, so every picked key is a
`keyof Model` member by construction, the same guarantee `only()`'s verbatim list gets from the
gate above it. A key that is an accessor, mutator, or relation name falls back to the inline
expansion unchanged — `only()` can legitimately request those (Eloquent's `Model::only()`
resolves through `getAttribute()`, which reaches accessors and relations too), but the analyzer
only optimizes the plain-column case and leaves everything else to the existing inline path.

The reference names the model's **own** interface (`Pick<Order, ...>`) — never `{Model}All`,
never a mutators/relations interface — and it is **template-independent by construction**: the
picked key set comes only from `publishedColumnNames()`, so the text is identical whether
`ts-publish.models.template` is `model-split` (the package default, where the bare `{Model}`
already carries columns only) or `model-full` (one interface per model carrying everything —
`workbench/resources/js/types/data/full-template-example/app/models/order.ts`'s `Order` also has
`item_count`, `formatted_total`, `user`, `items`, `items_count`, `user_exists`, … on top of the
same columns). Naming the *excluded* keys instead — `Omit<Order, 'created_at' | 'updated_at'>`,
the shape this analyzer used to emit — would have inherited whatever `keyof Order` is under the
active template, re-widening back to every member `keyof Order` carries under `model-full` — columns,
mutators, relations, counts and exists alike — even though
`Model::except()` never returns any of them. Naming the *surviving* keys explicitly cannot
re-widen, regardless of how many other members the base interface carries. The regenerated
fixtures confirm this directly: the `order_extended`/`post_extended`/`latest_payment_excluded`
properties are byte-identical text across the `default-example`, `full-template-example`, and
`split-template-example` trees.

This is also why the reference is a `Pick<>` of survivors rather than an `Omit<>` of the excluded
keys, independent of the template argument above: `Omit<T, K>` does not constrain `K`, so it
compiles regardless of what `T` actually has, and its member set is not derivable from the type
string alone — reading it off requires already knowing `T`'s full member list elsewhere.
`Pick<T, K>`'s member set *is* `K`, so recomputing `K` as the complement reproduces
`Model::except()`'s real key set directly and leaves it legible without cross-referencing
anything else — a property the ground-truth test described just below depends on.

`Illuminate\Database\Eloquent\Concerns\HasAttributes::except()` iterates only
`$this->getAttributes()` (the raw attribute array) — it never reads `$this->relations` at all, and
`mergeAttributeFromAttributeCasts()` explicitly refuses to merge a get-only `Attribute` cast's
value back into `$attributes` (`if ($attribute->get && ! $attribute->set) { return; }`), so a
get-only accessor can never surface even once it has been accessed. **At runtime,
`Model::except()` only ever returns database columns** — verified empirically in
`tests/Feature/ModelOnlyExceptSemanticsTest.php` against a real, DB-fetched `Post` instance with a
loaded relation and both get-only accessors (`excerpt`, `readingTime`) touched beforehand; the
result was identical to an untouched instance, and neither the relation nor either accessor
appeared. That same file also parses the `Pick<>` reference's key list back out of a live analyzer
run and diffs it, member for member, against `$post->except([...])`'s real output — a check
`Omit<>`'s type string could never support, since its excluded-key list is not the emitted member
set. The *old* inline expansion's `except()` branch — which unioned every attribute name (columns
**and** accessors) with every relation name and subtracted the excluded keys — was the one that
disagreed with this ground truth: it showed relations and accessors `Model::except()` never
actually returns at runtime. That is fixed; see **Relations half fixed too** below.

**Mutator half fixed in a later pass:** `buildModelDelegatedAnalysis()`'s own property set — used
by whole-model delegation and `return $this->except([...])`, not the relation-filter inline path
above — no longer emits a write-only mutator with no getter and no docblock `Get` generic (e.g.
`search_index: unknown`). `ModelAttributeResolver::isOmittedMutator()` is the same signal
`ModelTransformer::transformMutators()` uses to drop it there, so the two now agree for that
model. A real database column is never dropped by this check even when its own resolved type is
`unknown`, matching `transformColumns()`, which always keeps a column.

The skip is gated on `$excludeHidden` — true for the implicit paths, false only for `only()` — for
the same reason `HasAttributes::except()`/`only()` themselves diverge: `except()` iterates
`getAttributes()`, which a write-only mutator was never added to, so it truly cannot appear;
`only()` instead calls `getAttribute($key)` per named key, which *does* return the key
(as `null`, absent a getter to transform a stored value) even for a write-only mutator. So
`return $this->only(['search_index'])` keeps `search_index: unknown` — `unknown` because nothing
here infers a type from a *setter*, and that already admits the `null` `getAttribute()` would
return. `OrderOnlyResource`'s fixture pins this against `search_index` directly.

**Relations half fixed too:** `resolveFilteredRelationType()`'s except branch
(`$this->relation->except([...])`) no longer unions relation names into its key list at all. It
intersects the related model's attribute list with `ModelAttributeResolver::databaseColumnNames()`
and subtracts the excluded keys, so the emitted shape is database columns only — the same set
`HasAttributes::except()` produces by iterating `getAttributes()`. Accessors drop out with the
relations, since `mergeAttributeFromAttributeCasts()` never merges a get-only `Attribute` back into
`$attributes` in the first place. `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` pins the full
result for `Image` against the columns `create_images_table` declares, not against prior output.

One user-visible consequence: naming a relation or an accessor in the exclusion list is now a no-op.
The key list is built by subtracting the named keys from a set that has *already* been intersected
with `databaseColumnNames()`, so a name that is not a column was never in the set to subtract from
and its removal changes nothing. This follows from the mechanism rather than from a fixture — no
workbench resource names a non-column key in an `except()` list, and `WarehouseResource` stopped
doing so when its `only()` counterpart was reworked for same-basename aliasing.

The relation-emitting arm of that loop stays reachable, because the include branch still needs it:
`$this->relation->only(['posts'])` names a relation key directly, and `HasAttributes::only()`
calls `getAttribute($key)` per named key, which resolves accessors and relations alike.
So the two branches now diverge *by design*, for exactly the reason spelled out above — the same
divergence Eloquent itself has — rather than by oversight. `ResourceAstAnalyzerTest.php` pins the
include branch's accessor-and-relation resolution alongside the except branch's column list.

`isOmittedMutator()` still guards that loop, but with the except branch restricted to columns it is
in practice the include branch's gate: a named write-only mutator with no getter and no docblock
`Get` generic has no shape to emit, while a getter-backed accessor that resolves to `unknown`
survives as `key: unknown` — runtime-faithful, since `Model::only()` resolves through
`getAttribute()`, which does return that key.

### `exclude_hidden` on the top-level resource, not just relation filters

The rule established above for `$this->relation->only()/except()` — hidden columns fall out of an
*implicitly* derived property set but survive one the caller *named* — is not scoped to relation
filters. It governs every path that resolves the top-level resource's own property set from its
`@mixin` model, because it is the same distinction Eloquent itself draws: `Model::only()` resolves
through `getAttribute()` and returns a `$hidden` attribute regardless, while `toArray()`/`except()`
go through `getArrayableItems()`, which strips it.

**Implicit — `exclude_hidden` drops the hidden column:**

- **Whole-model delegation** — `return parent::toArray($request)`, `[...parent::toArray($request)]`
  as a spread inside an array literal, or a resource with no `toArray()` at all — all three reach
  `buildModelDelegatedAnalysis()` (the last two via `analyzeParentToArray()`) and build the property
  set from every model attribute.
- **`return $this->except([...])`** — `analyzeExceptFilter()` derives its base set from that same
  method, then subtracts the named keys; a hidden column that was never named to be *kept* falls
  out with the rest.
- **`$this->relation->except([...])`** — `resolveFilteredRelationType()`'s except branch builds its
  key list from every database column on the related model (never an accessor or a relation name),
  so a hidden column the caller never named falls out with the rest.

**Explicit — `exclude_hidden` leaves it alone:**

- **`return $this->only([...])`** — the property set is exactly the caller's key list.
- **`$this->relation->only([...])`** — the include branch above; a named hidden column falls back
  to inline expansion instead of a `Pick<>` reference, but it is never dropped.
- **`$this->whenHas('column')`** — the attribute name is a literal argument to the call.
- **Plain `@mixin` property access** — `'password' => $this->password` — the single most common
  resource idiom. `ThisPropertyHandler::analyzeThisProperty()` resolves it via `resolveModelAttributeTypeInfo()`, the
  same single-attribute resolution `ConditionalMethodHandler::analyzeWhenHas()` reaches through its
  own `ModelAttributeResolver`-backed copy of that helper; it never reaches
  `buildModelDelegatedAnalysis()`, so a hand-written key survives `exclude_hidden` exactly like a
  named `only()` key does.

Two touch points implement the implicit side. `buildModelDelegatedAnalysis()`
(`ResolvesModelTypes.php`) is the property-set builder shared by whole-model delegation *and*
`analyzeOnlyFilter()`/`analyzeExceptFilter()` — the include filter needs the unfiltered set to
select an explicitly-named hidden column from, so the method takes a `bool $excludeHidden = true`
parameter instead of filtering unconditionally: `analyzeOnlyFilter()` is the one caller that
passes `false`. `resolveFilteredRelationType()`'s except branch has no such sharing problem — it
builds its key list fresh per call — so it filters unconditionally there. `WarehouseResource`'s own
union-accessor `except()` calls (`last_user_activity_by_mostly`, `last_checked_by_mostly`) no
longer reach this branch: every excluded key there is a non-hidden published column, so each arm
resolves through `RelationFilterHandler::relationFilterModelReference()` instead — see [Multi-model accessor unions
reference each arm's own model](#multi-model-accessor-unions-reference-each-arms-own-model) below.
`ModelAttributeResolver::publishedColumnNames()` is `relationFilterModelReference()`'s own
`$hidden` gate, and it is still pinned directly — `PostAttachmentFilterResource::$attachment_hidden`
under `exclude_hidden` — but no fixture currently drives a hidden column through this specific
except-branch subtraction end to end: the only two workbench models with a `protected $hidden`
property are `Attachment` (exercised only via `only()`, never reaching this branch) and `App\Models\User`
(whose `except()` calls above now resolve through `Pick<>` instead). This is a test-coverage gap,
not a behavior gap — the subtraction logic itself already agrees with `Model::except()`'s runtime
behavior. Three sites are deliberately left untouched because each already takes the caller's
request verbatim rather than deriving it from the full attribute list: `filterAnalysisByKeys()`,
the include branch of `resolveFilteredRelationType()`, and `ModelAttributeResolver::resolveAttribute()`
(the single-attribute resolution that `only()` and `whenHas()` both end up calling).

## Import dispatch rules

A static-call value (e.g. `UrlService::locateOrder($id)`) whose method has no dedicated handler
falls through to `StaticCallHandler::analyzeStaticCall()`'s general-reflection branch: it reflects
the method's return type into a `TypeScriptTypeInfo` and hands it to `ReflectedTypeAcceptor::accept()`,
which decides whether the result can be emitted at all. The invariant is absolute: **a type token
never outruns its import** — nothing may be accepted unless every referenced name has a
resolvable `import` statement somewhere in the dispatch chain.

| Reflected shape | Accepted? | Dispatch channel |
| --- | --- | --- |
| Primitive / union of primitives (`string`, `int \| null`) | yes | emitted verbatim, no import needed |
| Single enum | yes | `directEnumFqcn` |
| Multiple enums (`Status\|Priority`) | yes | `embeddedEnumFqcns` (per-property inline-enum import plumbing) |
| Single `Model` subclass | yes | `modelFqcn` |
| Multiple / mixed `Model` subclasses | yes | `embeddedModelFqcns` |
| `Model` + enum union (`Order\|Status`) | yes | `embeddedEnumFqcns` *and* `embeddedModelFqcns` together — always the multi-entry channels, never the single-entry ones, whatever each kind's count |
| `#[TsType(import: ...)]`-annotated class | yes | `customImports`, merged into `ResourceAnalysis::$customImports` and consumed by `ResourceTransformer::mergeCustomImports()` |
| Any non-`Model` class (DTO, value object, cast class without `#[TsType]`) | **no** — degrades to `unknown` | none; this package generates no published file for an arbitrary class, so there is nothing to import |
| `void` / `never` / `mixed`'s reflected `unknown \| null` / empty type | **no** — degrades to `unknown` | none; these carry no meaningful TypeScript shape |

"Always the multi-entry channels" in that last row is structural, not a convention.
`ReflectedTypeAcceptor::accept()` sets `directEnumFqcn` only when `count($enumFqcns) === 1 && $classFqcns === []`,
and `modelFqcn` only when `count($classFqcns) === 1 && $enumFqcns === []`. A union carrying both kinds
fails both tests, so the single-entry channels are unreachable for it even when each kind contributes
exactly one FQCN — which is why that row does not contradict the two above it.

The non-`Model`-class rejection is a single guard checked before any channel is populated:
`Order|Status` and `Order|SomeDto` are structurally similar (`classFqcns` and `enumFqcns` both
non-empty) but only the first is importable, so every `classFqcns` entry must pass the
`is_a($fqcn, Model::class, true)` check or the whole result is rejected — accepting the enum
half while dropping an unimportable class token would still leak a compile error.

### `directEnumFqcn` carries two entry kinds

The `directEnumFqcn` channel and `ResourceTransformer::$directEnumProperties` map share one array
carrying two structurally different entry kinds: property-name keys pointing to direct-access enum
FQCNs (from ternary/union branches), and self-keyed FQCN entries (from embedded enums in inline
relation filters). All three consumers live inside `ResourceTransformer::rewriteEnumResourceTypes()`:
the `$isMixed` check is key-sensitive, testing whether a property name is a key in the map, while
the other two — both import-garbage-collection loops — compare values only and work correctly for
both entry kinds. `substituteEnumResourceType()` never reads this map at all.

`InlineArrayHandler::analyzeInlineArray()` runs the identical `$isMixed` check for a *nested* key, against its own
method-local `ResourceAnalysis` rather than `ResourceTransformer`'s instance maps — see
[A mixed ternary inside an inline array](#a-mixed-ternary-inside-an-inline-array) below.

### The tolki `AsEnum` wrap substitutes a token; it never rebuilds the type

An `EnumResource::make()`-wrapped property reaches the `enumResources` channel carrying the
analyzer's own type string, in which the enum appears as its bare TS type name (`RoleType` —
`TypeScriptTypeInfo::$enumTypes[0]`, i.e. the `#[TsEnum]` name or the class basename, suffixed
`Type`). With `enums.use_tolki_package` on, **both** rewrite paths turn that into
`AsEnum<typeof Role>` by *substituting the bare token in place*, for the ordinary (non-mixed) case:
`ResourceTransformer::substituteEnumResourceType()` for a top-level property, and
`InlineArrayHandler::substituteEnumType()` for one nested inside an inline array literal.
Both use the same word-boundary pattern — the lookbehind excludes `.` so a namespace-qualified
`foo.RoleType` is left alone, the lookahead stops `RoleType` matching the prefix of
`RoleTypeExtra`. A nested key whose ternary is *mixed* — wrapped in one arm, read directly in the
other — instead goes through `InlineArrayHandler::expandMixedEnumType()`; see the next section.

Substitution matters because the analyzer's type is often richer than `X`/`X[]`, and the corpus
pins two such shapes:

| Fixture | Analyzer type | After substitution |
| --- | --- | --- |
| `RelationChainResource::$member_role_resources_filtered` — a key-clearing chain, `filter()->map(fn (…) => EnumResource::make(…))` | `RoleType[] \| Record<string, RoleType>` | `AsEnum<typeof Role>[] \| Record<string, AsEnum<typeof Role>>` |
| `EnumCollectionResource::$week_days_when_has_default` — a conditional with an explicit default, `whenHas('week_days', EnumResource::collection(...), 'none')` | `WeekDaysType[] \| null \| string` | `AsEnum<typeof WeekDays>[] \| null \| string` |

The second is the one that shows substitution wrapping *some* arms and leaving others alone: the
`string` arm the explicit default contributes names no enum, so it survives untouched while the
element type is wrapped. `ResourceAstAnalyzerTest.php` pins the analyzer half ("whenHas() with an
explicit default stays enumFqcn-tagged and keeps the full union") and `ResourceTransformerTest.php`
the transformer half ("whenHas() with an explicit default keeps the full union, AsEnum-wrapped").

Rebuilding the type from the FQCN — which is what both paths
used to do — could express only `X`, `X[]`, `X | null`, `X[] | null`, so every richer shape had
to be demoted out of the `enumResources` channel by a guard (`isRebuildableEnumShape()`) and
left un-wrapped. That guard is gone: substitution reproduces any shape losslessly, wrapping
every arm that names the enum and leaving the rest of the union untouched.
`RelationChainResource::$member_role_resources_filtered` (top level) and `$wrapped_filtered`
(inline array) pin the two paths against the identical PHP shape — they must never disagree.

### The inline wrap's own const token is aliased by the transformer, not here

Both substitution paths above embed the enum's **bare** const name (`Role`, not whatever alias it
may need) into `AsEnum<typeof {const}>`, because neither one can do otherwise:
`InlineArrayHandler::substituteEnumType()` runs during analysis (`runAstAnalysis()`, step 6 of
`ResourceTransformer::transform()`), before `resolveImportConflicts()` (step 10) has computed any
alias at all. For a top-level property this is invisible: `rewriteEnumResourceTypes()` reads
`$constImportAliases` itself and builds the *already-aliased* string directly
(`$constName = $this->constImportAliases[$fqcn] ?? $this->enumConstMap[$fqcn]`), so the bare-const
string the analyzer might otherwise have produced is never actually built for that case.

For a wrap nested inside an inline array, the bare-const string *is* what leaves the analyzer —
`InlineArrayHandler::analyzeInlineArray()`'s substituted type string travels out flat, keyed by the *outer* property
name, with no alias information attached. `ResourceTransformer::rewriteEnumResourceTypes()`
corrects it in a dedicated final pass, after `resolveImportConflicts()` has run: for each property
in `$propertyInlineEnumResourceFqcns` it calls `LaravelTsPublish::aliasPropertyType()`, keyed on
`$this->enumConstMap` (the unaliased name) and `$this->constImportAliases` (the alias, when one
exists), walking the FQCN list in the same order `analyzeInlineArray()` built it in. That order
matters: two inline members can wrap *different* FQCNs that happen to share one bare const name —
two distinct `Status` enums, say — and a naive per-FQCN global substitution would let the second
FQCN's replacement clobber the first FQCN's own occurrence. `aliasPropertyType()`'s per-name queue,
consumed left to right, is what keeps each occurrence pinned to its own FQCN.
`DealResource::$status_pair` pins it: both arms wrap an already-top-level-aliased `Status` enum
(`App\Enums\Status` and `Crm\Enums\Status`), and each occurrence must keep its own alias rather than
both collapsing to whichever FQCN's alias is looked up first.

`rewriteTypeReferences()` cannot be reused for this: its `$nameMap` is built from `enumFqcnMap +
resourceFqcnMap + modelFqcnMap` only, so `enumConstMap` — the only map an inline-only EnumResource
FQCN ever populates — is invisible to it. See
[ImportNameRegistry § ResourceTransformer](import-name-registry.md#resourcetransformer) for the
matching registration-side half: an enum reached *only* through an inline wrap never enters
`enumFqcnMap` at all, so it needs its own path onto the const registry too.

### A mixed ternary inside an inline array

`ResourceTransformer::rewriteEnumResourceTypes()`'s top-level `$isMixed` branch synthesizes
`AsEnum<typeof Const> | EnumTypeName` from scratch whenever a property is both EnumResource-wrapped
and directly enum-read — it never trusts the analyzer's own merged type string. `InlineArrayHandler::analyzeInlineArray()`
cannot do the same for a *nested* key: its `ResourceAnalysis` is method-local, and only flat property
lists leave the method, keyed by the array literal's own (outer) property name, so the transformer
has no way to know which inner key was mixed. The fix has to stay in `InlineArrayHandler`, in
`expandMixedEnumType()`, working from the merged type string plus the one out-of-band signal the
analyzer does keep — `$isMixed`.

Two shapes reach this code, and the merged type string carries different information for each:

- **Homogeneous** — both ternary arms produce the identical type string (e.g. `EnumResource::make($this->status)`
  vs `$this->status`, both `StatusType`). `ValueResult::analyzeClosureUnion()` deduplicates
  identical strings before `ValueResult::mergeUnion()` ever joins them, so by the time
  `analyzeInlineArray()` sees it there is exactly one `StatusType` token — no per-member signal
  survives to say "this token stands for two arms, only one of which was wrapped." `$isMixed` is
  the signal that survives instead: `ValueResult::mergeUnion()` sets *both* `enumFqcn` and
  `directEnumFqcn` only when one arm wrapped and
  another read directly, so its presence alone proves a wrapped arm exists that the single token does
  not show. `expandMixedEnumType()` therefore spells that arm out beside the bare one rather than
  substituting the token in place. `ResourceWrappedEnumResource::$ternary_enums_array` pins it:
  `{ status: AsEnum<typeof Status> | StatusType }`. Both halves are load-bearing and were measured
  separately — substituting in place (the pre-fix blanket `substituteEnumType()` path, reached because
  the call site used to gate `expandMixedEnumType()` behind `count($members) > 1`) emitted
  `{ status: AsEnum<typeof Status> }`, dropping the direct-read arm; removing that gate without
  teaching `expandMixedEnumType()` the collapsed shape emitted `{ status: StatusType }`, dropping the
  wrapped arm instead. This now matches the top-level `$isMixed` rewrite, which reconstructs
  `AsEnum<typeof Const> | EnumTypeName` from the FQCN maps regardless of what the analyzer's own
  merged string says — a same-shaped top-level mixed pair (`status_when_not_null_arrow`, below) and
  this nested one both get the full union. (`status_ternary_both`, elsewhere in the same fixture,
  looks similar but is not this case at all — both of its arms are EnumResource-wrapped, so
  `ValueResult::mergeUnion()` never sets `directEnumFqcn` for it; its single `AsEnum<typeof Status>`
  is the ordinary, correct wrapped-only substitution, not a dropped arm.)
- **Heterogeneous** — the two arms produce *different* type strings because one is forced into an
  array shape and the other is not (e.g. `EnumResource::collection($this->status_history)`, already
  array-shaped, vs a direct scalar read of a different accessor sharing the same enum — `StatusType[]`
  vs `StatusType`). Those two strings are not identical, so `ValueResult::analyzeClosureUnion()`
  keeps both and the
  merged type string is a genuine two-member union, each member's own shape still visible.
  `expandMixedEnumType()` substitutes
  only the member matching `{bareTypeName}[]` — the one `EnumResource::collection()` actually
  produced — and leaves any other member untouched; the presence of that array-shaped member is also
  what tells it the arms are already distinguishable, so it does not additionally spell out the
  wrapped arm the way the homogeneous case needs. `EnumCollectionResource::$wrapped_status_fallback`
  pins it: `{ status: AsEnum<typeof Status>[] | StatusType }`. Before this fix, the same blanket
  `substituteEnumType()` call used for the homogeneous case matched *both* members (the word-boundary
  regex does not stop at `[`), wrongly producing `AsEnum<typeof Status>[] | AsEnum<typeof Status>`.

This once deliberately did **not** mirror `rewriteEnumResourceTypes()`'s `isCollection`
reconstruction, which wrapped the *entire* mixed union in `()[]`
(`(AsEnum<typeof Const> | EnumTypeName)[]`) whenever the top-level property's pre-rewrite type
happened to end in `[]` — a shape that is only correct when *both* arms are arrays, and wrong
(`(A | B)[]` where the truth is `A | B[]`) for exactly this heterogeneous case. The top-level rewrite
was later fixed to match: it now array-suffixes only the bare arm
(`AsEnum<typeof Const> | EnumTypeName[]`) instead of wrapping the whole union, the same principle
`expandMixedEnumType()` already applied — an array-shaped arm keeps its own `[]`, a scalar arm
never gains one it didn't earn. That fix still assumed the wrapped arm itself was always scalar,
true only while every wrap was `EnumResource::make()`; a wrap arm using `EnumResource::collection()`
needs its own `[]` too, and the top-level rewrite had no member-position signal of its own to tell
the two apart — its merged type string collapses to one token whenever both arms render the same
way. `TernaryHandler::analyzeTernary()` now closes that gap directly: once the merged result shows
a mixed pair it re-resolves both arms once each (declining when either arm is itself ambiguously
mixed, e.g. a nested ternary), records their own shapes (`wrapIsCollection`, `directIsArray`) on the
result, and threads them through `MethodAnalysis::$enumResourceArmShapes` for
`rewriteEnumResourceTypes()` to read back — a per-arm signal the merged type string alone cannot
carry, from `analyzeReturnArray()` and every other collector that builds a `ResourceAnalysis`
(`mergeReturnBranches()`, `ThisPropertyHandler::extractPropertiesFromArray()`,
`collectVariableArrayAssignments()`). `EnumCollectionResource::$latest_status_or_history` pins the
scalar-wrap case: `AsEnum<typeof Status> | StatusType[]`. `$wrapped_history_or_scalar` and
`$wrapped_history_or_array` pin the collection-wrap case the old fixed convention got backwards:
`AsEnum<typeof Status>[] | StatusType` and `AsEnum<typeof Status>[] | StatusType[]`.

**The nested path now reads the same signal.** `expandMixedEnumType()` had the identical collapse
problem one level down, and there it was worse: when both arms render the same array-shaped string —
an `EnumResource::collection()` wrap and a direct read of an already-list accessor, both `X[]` — the
merged `$members` array collapses to the single member `'X[]'` before `expandMixedEnumType()` ever
ran, and its `$member === $collectionType` branch mapped that one member to `AsEnum<typeof Const>[]`,
dropping the direct arm entirely rather than under-suffixing it. `MethodAnalysis::$enumResourceArmShapes`
is already populated by the time `analyzeInlineArray()` reads `$analysis` — `TernaryHandler` does not
distinguish top-level from nested — so `expandMixedEnumType()` now takes that per-arm shape and
synthesizes `wrapped | direct` from the flags exactly as `rewriteEnumResourceTypes()` does, carrying
over any member the two arms did not account for. `TeamStatusAuditResource::$audit` pins it:
`{ status: AsEnum<typeof Status>[] | StatusType[] }`, with the direct arm's `StatusType` type import
back alongside the wrapped arm's `Status` value import. The member-based reconstruction described in
the two bullets above stays as the fallback for a mixed pair `TernaryHandler` declined to attribute
(either arm itself ambiguously mixed), where no per-arm shape was recorded.

In the globals tree, `LaravelTsPublish::rewriteAsEnumToType()`'s pair pattern folds an *exact*
`AsEnum<typeof Const> | EnumTypeName` adjacency — no `[]` anywhere in that span, neither between
the two names nor trailing the bare one — to a single qualified reference —
`status_when_not_null_arrow`'s top-level homogeneous pair collapses to one `StatusType` reference,
and `ternary_enums_array`'s nested one folds the same way inside its inline object — the bare name is
followed by a space and `}`, which the lookahead allows — so the globals tree kept
`{ status: app.enums.StatusType }` byte-identical across this fix.
`wrapped_status_fallback`'s heterogeneous `AsEnum<typeof Status>[] | StatusType` does
not match (the `[]` sits between the two names), and neither does `latest_status_or_history`'s
`AsEnum<typeof Status> | StatusType[]` (the `[]` trails the bare name instead) — both render as two
independently-qualified references, correct, since the two members mean different things despite
sharing a base name. The trailing-`[]` exclusion came out of the same fix: before it, the pair
pattern's lookahead rejected only a following word character, so it still matched the
`AsEnum<typeof Const> | EnumTypeName` prefix of `AsEnum<typeof Status> | StatusType[]` and folded
it to a single qualified reference with the array arm's `[]` left dangling after — `StatusType[]`
alone, silently dropping the scalar arm.

### Multi-model accessor unions reference each arm's own model

`RelationFilterHandler::analyzeRelationFilter()`'s other branch handles an accessor typed as a union of two or more
Eloquent models, e.g. `Attribute<CrmUser|User, never>`. The `$modelFqcn === null` guard diverts
this receiver into a loop over `resolveAccessorModelFqcns()`'s FQCN list — one arm per model — and,
like the single-model path just above, that loop now tries `RelationFilterHandler::relationFilterModelReference()` for
each arm *first*, falling back to `ResolvesFilteredRelationTypes::resolveFilteredRelationType()`'s inline expansion only when a
filter key is not one of that arm's own published columns (an accessor, mutator, or relation name).
Every filter key on `WarehouseResource::$last_user_activity_by_mostly` (`except(['id', 'name'])`)
and `$last_checked_by_mostly` (`except(['created_at', 'updated_at'])`) is a plain column on both
models in the union, so both arms now resolve to `Pick<>` references:

`last_user_activity_by_mostly`'s emitted type, before:

```ts
{ email: string; company: string | null; status: CrmStatusType; created_at: string | null; updated_at: string | null } | { email: string; email_verified_at: string | null; password: string; options: unknown[] | null; remember_token: string | null; created_at: string | null; updated_at: string | null; role: RoleType | null; membership_level: MembershipLevelType | null; phone: string | null; avatar: string | null; bio: string | null; settings: unknown[] | null; last_login_at: string | null; last_login_ip: string | null } | null
```

After:

```ts
Pick<CrmUser, 'email' | 'company' | 'status' | 'created_at' | 'updated_at'> | Pick<ModelsUser, 'email' | 'email_verified_at' | 'password' | 'options' | 'remember_token' | 'created_at' | 'updated_at' | 'role' | 'membership_level' | 'phone' | 'avatar' | 'bio' | 'settings' | 'last_login_at' | 'last_login_ip'> | null
```

The inline path re-derived each column's type from scratch and lost the `#[TsCasts]` refinements a
model reference keeps by construction: the old `options`/`settings` came out as `unknown[] | null`
where the `User` model interface itself declares `Record<string, unknown> | null` and a full
`{ theme; notifications; locale }` object shape respectively. The `Pick<>` reference carries those
refinements for free, the same benefit the single-model path already had.

Three things had to change together to make each arm reachable, not just one:

- **The single-model reference had to stop being template-dependent first.** It used to emit
  `Omit<Model, …>`, which re-widened under `ts-publish.models.template = model-full` (the bare
  model interface there also carries mutators, relations, counts and exists). It now emits
  `Pick<Model, …>` of the complement instead — template-independent by construction, and immune to
  `$appends` too, since its key universe is `publishedColumnNames()` (schema columns) rather than
  `keyof Model` — see [When a Pick reference is emitted](#when-a-pick-reference-is-emitted) above.
  Reusing the same builder per arm would otherwise have carried that re-widening into every
  multi-model union too.
- **The regression test had to stop passing vacuously first.** `ResourceTransformerTest` used to
  assert `not->toContain('images: Image[]')` against the emitted type *string*, which a `Pick<>`
  arm passes trivially — no member names, so the check never fails. It now splits the union with
  `splitTopLevelType()` and reads each arm's members through `relationFilterArmMembers()`, which
  **throws** on a model-reference arm instead of returning `[]` — so a future `Pick<>` arm fails
  the test loudly the moment it appears, forcing an honest update instead of a silent pass.
- **The union loop's own dedupe was keyed on the wrong thing.** It skipped an arm once its
  *rendered string* repeated, and `relationFilterModelReference()` renders `class_basename($fqcn)`
  — so two FQCNs sharing a basename render identically even when they are different models.
  `WarehouseResource::$last_user_activity_by_partial` (`only(['id', 'name'])`) is the fixture that
  shows the failure mode was not cosmetic: `CrmUser`'s and `ModelsUser`'s picked `{id, name}` shapes
  are structurally identical, so the string-keyed dedupe collapsed two real arms into the single
  merged type `{ id: number; name: string } | null` — the second model's arm, and the FQCN push
  that would have registered its import, were silently dropped. The loop now tracks a `$seenFqcns`
  list and dedupes on the arm's own FQCN instead, so same-basename models never collide, and it
  emits `Pick<CrmUser, 'id' | 'name'> | Pick<ModelsUser, 'id' | 'name'> | null` — two arms, matching
  the two real models. The FQCN push into `embeddedModelFqcns` also moved outside the
  "type accepted" guard it used to share with the string dedupe, and the returned list is no longer
  passed through `array_unique()`: `LaravelTsPublish::aliasPropertyType()` consumes that list
  positionally against left-to-right occurrences of each basename in the rendered type — its own
  docblock in `LaravelTsPublish.php` states the contract directly: never dedupe it, since a caller
  may need more entries than real occurrences, and the method only ever consumes the matching
  prefix. A real repeated occurrence has to survive as a repeat, not collapse to one entry.

A filter key that is not a published column on one of the union's models still falls back to that
arm's inline expansion, same as the single-model path: `WarehouseResource::$crm_contact_partial`
(`$this->primaryContact?->only(['status', 'images'])`) exercises this directly — `images` is a
`MorphMany` relation on `Crm\Models\User`, not a column, so `relationFilterModelReference()`
declines and the property falls back to `{ status: CrmStatusType; images: Image[] } | null`. That
fixture is also what keeps an aliased-enum inline shape in the corpus at all: without it, nothing
in `WarehouseResource` would still spell out `CrmStatusType` inline, `Workbench\Crm\Enums\Status`
would stop colliding with `Workbench\App\Enums\Status`, and `review_priority`'s own enum would
render as the unaliased `StatusType` instead of `EnumsStatusType` — a change to an unrelated
property caused entirely by an import that stopped being needed elsewhere in the same file.

A declining arm's own FQCN must never reach `embeddedModelFqcns`, precisely because it declined:
its inline `{ ... }` expansion spells out member types, never a bare reference to the model's own
name, so there is no occurrence in the rendered text for that FQCN to align against.
`WarehouseResource::$probe_mixed` (`$this->last_user_activity_by?->only(['id', 'phone'])`) pins
this — `phone` is a column on `App\Models\User` but not on `Crm\Models\User`, so the `CrmUser`
arm declines and falls back to `{ id: number }` while the other arm resolves to `Pick<User, 'id' |
'phone'>`. The emitted type has exactly one bare `User` occurrence, so exactly one FQCN belongs in
the queue. Pushing the declining arm's FQCN anyway — as an earlier version of this fix briefly did
— left two entries queued against one occurrence: `aliasPropertyType()` consumed the first (`CrmUser`)
for the arm that was really `App\Models\User`'s, emitting `Pick<CrmUser, 'id' | 'phone'>`, silently
wrong. Only `$filterResult['modelFqcns']` — FQCNs nested *inside* a fallback arm's own inline
expansion, from a relation the arm's shape happens to reference — belong in the list from that
branch, mirroring the single-model path's `'embeddedModelFqcns' => $filterResult['modelFqcns']`
just above (never the arm's own FQCN there either).

## Variable bindings

`AnalysisScope`'s binding maps — `$varModelBindings`, `$varCollectionBindings`, `$localVarBindings`,
`$closureParamExprBindings` — their population sources, the snapshot/restore discipline that scopes
them, and what deliberately stays unbound are documented at
[AstEngine § AnalysisScope](ast-engine.md#analysisscope), which now owns this content.
`AnalysisScope` is a standalone class shared by every AST consumer, not specific to this analyzer.
`ClosureParamShadowResource` and `ShadowedClosureParamResource` (workbench fixtures) pin the
shadowing guarantees described there; both are exercised through this analyzer today.

`collectWrittenVariableNames()` used to count every closure/arrow-function *parameter* as a write to
the enclosing name pool, so a top-level `$member =
$this->slug;` reused as a `map(fn ($member) => $member)` param elsewhere in the same method looked
written twice, and `collectLocalVarBindings()` (which only binds names written exactly once) never
bound `$member` — even at the top-level site the closure never touches. `outer_member` in
`ClosureParamShadowResource` demonstrated the gap; fixed by narrowing `collectWrittenVariableNames()`
to count only assignments, mutations, `foreach` targets, and a `Closure`'s by-ref `use (&$x)` clause.

## Method-spread recursion guard

`analyzeThisMethodSpread()` resolves a `...$this->method()` spread by re-entering the analyzer on
that method's own `toArray()`-style return. `$visitedSpreadMethods` (`array<string, true>`) tracks
which method names are currently on that re-entry stack; a method already on the stack returns
`null` instead of being analyzed again, so a cycle — direct (`method()` spreads itself) or mutual
(`alpha()` spreads `beta()`, `beta()` spreads `alpha()`) — degrades to an empty analysis rather than
recursing. The entry is set right after the `hasMethod()` check and cleared in the same `finally`
that already restores `$localVarBindings`/`$resolvingLocalVars`/`$varModelBindings`, so it clears on
the exception path too. The NodeFinder predicate that locates the target method compares names with
`strcasecmp()`, so call-site casing is normalized to match PHP's own case-insensitive method dispatch.

Before this guard existed, a mutually recursive spread had no way to terminate: each call re-entered
`analyzeThisMethodSpread()` for the other method, which re-entered the AST parser, until PHP's memory
limit was exhausted (a 512 MB limit fails after roughly 22,000 stack frames). This was reachable
through the existing spread path with no special setup — see `MutuallyRecursiveSpreadResource` in
the workbench and the corresponding test in `ResourceAstAnalyzerTest`. The guard is load-bearing, not
defensive: it fixes a crash that was previously trivial to trigger, not a theoretical one.

## A bare `return $this->method()` resolves transitively

`analyze()`'s fallback path used to route every non-array `MethodCall` return through
`analyzeThisAttributeFilter()`, which returns `null` for any method name outside `['only', 'except']`
— so `return $this->data();` produced an empty interface even though the array-literal spread form,
`return [...$this->data()];`, already worked. `analyze()` now falls back to
`analyzeThisMethodSpread($methodName)` when the attribute-filter path declines, resolving the bare
return the same way a `...$this->method()` spread already did.

`analyzeThisMethodSpread()`'s own return dispatch gained a matching `MethodCall` arm, so a method that
returns another method call (`data()` returning `$this->nested()`) recurses instead of degrading to an
empty analysis. `only()`/`except()` are still resolved first at each level via
`analyzeThisAttributeFilter()`, so a `return $this->only([...])` reached transitively keeps working
exactly as a direct one does.

Because `ReflectionMethod::getFileName()` resolves to wherever a method is *declared* — the resource
itself, a trait it uses, or a parent class — this recursion reaches trait- and parent-declared methods
for free; `analyzeThisMethodSpread()` already re-parses that declaring file for the direct spread case,
so no extra resolution step was needed to make the transitive case work.

A cycle (`a()` returning `$this->b()`, `b()` returning `$this->a()`) is not a new risk: the recursion
goes through the same `$visitedSpreadMethods` guard described above, so it degrades to an empty
analysis on re-entry rather than recursing until memory runs out. See `BareMethodReturnResource` in
the workbench and the corresponding test in `ResourceAstAnalyzerTest` for the transitive case
(`toArray()` → `data()` → `nested()`).

### A `new self($x)->method()` receiver resolves the same way

`StaticCallHandler::analyzeSelfReturningResourceMethodCall()` handles `new self($x)->method()`,
`self::make($x)->method()` and chains of both. When `methodPreservesReceiverType()` says the method
hands the same instance back — a native `static`/`self`/the resource class, or a docblock-only
`@return $this` — the receiver's own resolved result is returned, with one adjustment. A nullable
method return appends `| null` to it first
(`if ($this->methodReturnAllowsNull($method) && ! str_contains($receiverResult['type'], 'null'))`), so
`FluentSelfResource::whenAuthorized(): ?static` widens the receiver's type rather than passing it
through; `parent_fluent_nullable` pins that. When it says otherwise, the expression has stopped being the
resource, and the method's *body* is resolved through the analyzer's `spreadAnalysis()` — the
`ExpressionEngine` entry point delegating to `analyzeThisMethodSpread()` — instead of degrading to
`unknown`. That returns a `ResourceAnalysis`, so it is flattened into an inline object literal by
`Ast\Concerns\BuildsInlineObjectTypes::buildInlineObjectType()`, the shared helper behind both this
call and `InlineArrayHandler::analyzeInlineArray()`'s `{ … }` arm.

Three tiers are possible here and only the last is correct. Do not "improve" this to the receiver type:

| Emitted type | Verdict |
| --- | --- |
| `FluentSelfResource` — the receiver type | **Wrong.** The instance is only ever `$this` inside the method and never reaches the payload, so this promises keys the response does not contain. |
| `unknown` | The safe floor. Honest, but it tells the frontend nothing. |
| `{ id: number }` — the method body | **Correct.** `FluentSelfResource::summary(): array` returns `['id' => $this->id]`, so `{"id": 123}` is what the response actually carries. |

An empty analysis returns `null` rather than emitting a bare `{}`, because `{}` asserts the payload has
no keys — a stronger and more often wrong claim than `unknown`.

Recursion needs no new guard. `analyzeThisMethodSpread()`'s existing `$visitedSpreadMethods` entry
covers this entry point exactly as it covers a `...$this->method()` spread, and the same `finally`
already restores `$localVarBindings`/`$resolvingLocalVars`/`$varModelBindings`.

### Scope boundary: a foreign resource class receiver still degrades to `unknown`

`analyzeThisMethodSpread()` is hard-bound to `$this->scope->subjectReflection` — it looks the method up
with `$this->scope->subjectReflection->hasMethod()` and `->getMethod()` — so it can only resolve
methods declared on (or inherited by) the class the analyzer was constructed for. For `new self($x)`
the receiver class *is* that class, which is what makes reusing it sound.

`SomeOtherResource::make($x)->summary()` would need a second analyzer instance built on that other
class, which this does not do. So the hook guards on receiver identity — `$resourceFqcn !==
$this->scope->subjectReflection->getName()` returns `null` — and the property keeps the `unknown` floor.
Without that guard the analyzer would resolve *its own* same-named method and emit a shape belonging
to a different class.

`FluentSelfResource::foreign_summary` pins that boundary in the workbench: it calls
`new CategoryResource($this->parent)->summary()`, and `CategoryResource::summary()` deliberately
returns a different shape (`['slug' => $this->slug]`) from `FluentSelfResource::summary()`'s
`['id' => $this->id]`, so a regression that dropped the guard would emit `{ id: number }` there and
fail the test. Widening this to foreign receivers is therefore a deliberate change, not an accident.

## Inline-array spreads become intersection arms

An inline array literal that spreads a named type alongside its own keys —
`[...UserResource::make($m)->resolve($request), 'profile' => new ProfileResource($m->profile)]` —
emits an intersection rather than flattening the spread's keys inline. `InlineArrayHandler::analyzeInlineArray()`
collects the arms via `collectInlineArraySpreadArms()` and builds each one with
`buildSpreadArmTypes()`. Three arm kinds are recognised:

- **A resource arm** — a spread whose value resolves to a *bare* named resource (not an array or
  collection of one). The guard is that the result carries a `resourceFqcn` *and* its emitted type
  is exactly that resource's basename, so `UserResource[]` never becomes an arm.
- **A model arm** — `$var->toArray()` where `$var` is a closure-bound model. `InlineArrayHandler::spreadModelToArrayFqcn()`
  checks `$varModelBindings` first, then returns `null` for a `$var` bound only in
  `$varCollectionBindings` — a to-many `whenLoaded` param holding the whole collection, not one
  model — else falls back to `$closureRelationModelClass`.
  Every `->map()` closure element actually resolves through that fallback, typed or not:
  `VariableHandler::analyzeVariableMapCall()` sets only `$closureRelationModelClass` for the element and never
  populates `$varModelBindings` (`members_model_spread` pins the fallback path, not the explicit-
  binding one). `$this->toArray()` is excluded by name: it is the resource's own method and
  `InlineArrayHandler::isKnownArraySpreadShape()` already flattens it.
- **A collection arm** — the `null` `spreadModelToArrayFqcn()` returns for a `$varCollectionBindings`
  name is a *decline*, not a drop: `InlineArrayHandler::spreadCollectionToArrayFqcn()` picks the same expression up and
  resolves it to the binding's element model. It emits `Record<number, {Model}>`.
  `members_collection_spread` pins both halves — the decline and the Record.

Resource and model arms are handled identically once collected. A collection arm is not, for the
reason the next section gives.

### Why a collection arm is a `Record<number, {Model}>`

Spreading a *collection* is not spreading a *model*. `[...$members->toArray(), 'flag' => true]`
renumbers the collection's elements, so the members land under `0..n` and the sibling string key
rides alongside them — the payload is `{"0":{…},"1":{…},"flag":true}`, not one member's columns
merged with `flag`. Typing that arm as `Omit<User, 'flag'>` would claim one user's columns at the
top level, which the JSON contradicts; typing it as nothing at all would assert the object has
*only* `flag`, hiding every member. `Record<number, {Model}>` is what the payload actually is.

The element type carries the same honest-floor caveat a model arm does — `Collection::toArray()`
calls each member's `toArray()`, so the value is the model's attribute map, and bare `{Model}` is
the floor for that. See "What a model arm's bare `{Model}` does not say" below.

`Record<…>` is the house idiom for a key-typed object here — 564 occurrences in the committed trees
before this arm existed, against zero index signatures (`grep -E '\[[A-Za-z_]\w*\s*:\s*(string|number|symbol)\s*\]\s*:'`
over `workbench/resources/js/types` returns nothing). `Record<number, …>` itself is new with this arm.

Model detection is deliberately **local to the collector**. `analyzeValueExpression()` still types a
bare `$member->toArray()` as `unknown[]` everywhere else, because giving it a `modelFqcn` generally
would change every non-spread `toArray()` call in the corpus.

### The `Omit<>` subtraction rule

Each arm is `Omit<>`'d against every key a *later* arm or an explicit sibling key will overwrite.
This is not cosmetic: PHP's `[...$a, ...$b, 'k' => $v]` lets the later assignment win, and
TypeScript's `&` does not — it intersects both, collapsing a colliding key to `never` when the two
types disagree. Subtracting the overridden keys from the earlier arm is what makes the emitted type
mean what the PHP means.

A collection arm is the one exception, in both directions: it is never `Omit<>`'d, and it is never
subtracted from an earlier arm. Its members key by index; every sibling key and every other arm's
keys are strings, so the two cannot collide, and `Omit<T, number>` on a string-keyed `T` would
subtract nothing anyway.

The shape that *would* collide is a numeric explicit sibling key — `[...$members->toArray(), 5 => 'x']`
puts `5` in both halves. It is unreachable rather than unhandled: `resolveKeyName()` in
`src/Analyzers/Concerns/InspectsAstNodes.php` returns a name only for a `String_` key, and
`analyzeReturnArray()` skips every item whose key resolves to `null`, so a numeric key never becomes
a property in the first place. `QuirkyResource` pins that independently — it writes `42 => $this->total`
and `42 => 'number_keyed'`, and the generated `QuirkyResource` interface has no `42` member. So the
collision cannot be constructed while numeric keys are dropped wholesale; a change that started
emitting them would have to revisit this arm.

For every other arm the subtraction is **unconditional**: an explicit key is Omitted whether or not
the arm actually declares it. `Omit<T, K>` does not require `K extends keyof T`, so this is well-typed either way,
and it lets `buildSpreadArmTypes()` work from `class_basename()` alone — a later arm's own shape
never has to be resolved, only its name, for `keyof`. `NestedResourceSpreadResource` pins both
sides of that: `members_double_spread` Omits `'note'` from `UserResource`, which has no `note` key,
and `members_model_spread` Omits `'flag'` from `User`, which has no `flag` column.

Arm order is source order, because the subtraction reads every arm *after* the current index. All
three kinds are tracked in one list for that reason, and split only when the imports are dispatched:
resource arms travel `embeddedResourceFqcns`; model *and* collection arms travel
`embeddedModelFqcns`, both naming a model, or the emitted token would be looked up in the wrong
channel and never resolve to an import.

That guarantee holds today only because `collectInlineArraySpreadArms()` appends to one list as it
walks `$array->items`, never splitting the kinds into separate lists that get
concatenated afterward — a plausible-looking refactor that would silently regroup arms by kind and
subtract against the wrong later arm. `members_model_then_resource_spread` and
`members_resource_then_model_spread` on `NestedResourceSpreadResource` pin this directly: each
spreads model and resource arms in alternating order (`M, R, M` and its mirror `R, M, R`), the
minimum shape where a by-kind grouping in *either* direction reorders both fixtures rather than
coincidentally matching one of them.

### What a model arm's bare `{Model}` does not say

A model arm emits the bare `{Model}` interface, which is the honest floor rather than an exact
match for `toArray()`'s runtime output. `Model::toArray()` is
`array_merge($this->attributesToArray(), $this->relationsToArray())`, so line the two halves up:

- **`attributesToArray()` — matches.** It is columns plus `getArrayableAppends()`, and bare
  `{Model}` is generated from exactly those two channels: `model-split.blade.php` renders
  `$data->columns` *and* `$data->appends` into the `export interface {{ $data->modelName }}` body.
  `Address` proves it — `protected $appends = ['full_address']`, and the generated `Address`
  interface ends with `full_address: string | null;`. **There is no `$appends` gap.**
  `{Model}Mutators` holds `$data->mutators`, which is the accessors a model did *not* append.
- **`relationsToArray()` — the real gap.** Bare `{Model}` omits it, so a relation loaded on
  `$member` before the spread is in the JSON payload but not in the type. Unknowable statically;
  the same trade-off `Pick<Model, columns>` already makes for relation filters. Under `model-split`
  relations live in `{Model}Relations`, which the arm does not reference.

One gap runs the other way: `$hidden` columns are stripped at runtime (`attributesToArray()` goes
through `getArrayableItems()`) but stay in bare `{Model}` unless `exclude_hidden` is set — see the
existing `publishedColumnNames()` coupling. So the arm is a superset on the hidden axis and a subset
on the relations axis.

## `whenNotNull()`/`whenNull()` read `($value, $default)`, not a callback

`ConditionalMethodHandler::analyzeWhenPossiblyNull(MethodCall $call, bool $stripNull, AnalysisScope $scope, ExpressionEngine $engine)` handles both `$this->whenNotNull($value,
$default)` and `$this->whenNull($value, $default)`. Both delegate to `ConditionallyLoadsAttributes::when()`
(`vendor/laravel/framework/.../Http/Resources/ConditionallyLoadsAttributes.php`): `whenNotNull($value,
$default)` is `$this->when(! is_null($value), $value, $default)`, and `whenNull($value, $default)` is
`$this->when(is_null($value), $value, $default)`. Argument 0 is always the value returned on the
condition's success arm; argument 1, when passed, is the default returned on the failure arm. Neither
argument is ever a callback bound to the other — `value()` invokes a Closure passed as `$value`/`$default`
with **zero** arguments, never with the sibling argument as a parameter.

An earlier version of this handler's docblocks claimed a callback form ("whenNotNull($this->value,
$callback) — resolve the callback expression type") that Laravel does not have, and the implementation
matched that wrong docblock: it read `$args[1]` unconditionally and returned that as the property's type.
So `whenNotNull($this->description, 0)` (`description: string | null`) emitted `?number` — the default's
type alone — instead of `string | number, required`. The fix was checked against
`ConditionallyLoadsAttributes.php` directly rather than assumed; see that trait for the exact
`func_num_args()` gating this handler mirrors.

### Which arm each analysis path reads

- **`whenNotNull()`** (`stripNull: true`) analyzes argument 0, then strips a top-level `| null` arm from its
  type via `ValueResult::stripNullArm()`: the `! is_null($value)` guard on the success arm proves that arm unreachable,
  so `whenNotNull($this->description)` emits `?string`, not `?string | null`.
- **`whenNull()`** (`stripNull: false`) forces argument 0's contribution to the literal string `'null'`
  instead of analyzing it — the success arm always returns `null` when the guard holds, so the value's own
  type is irrelevant to what the property can be.

### `stripNullArm()` only drops the top-level `null` arm

`stripNullArm()` splits the type on `LaravelTsPublish::splitTopLevelUnion()`, a depth-aware splitter over
braces, parens, angle brackets, and square brackets, and filters out a member equal to exactly `'null'`.
Only a union member sitting at depth zero is ever removed — `(string | null)[]` and `{ a: string; b: number
| null }` both keep their nested `| null` untouched, since neither nested `null` is a top-level member of
the outer type. `ConditionalMethodHandler` (stripping `whenNotNull()`'s success arm) and `CoalesceHandler`
(stripping the left operand of `??`) both call `ValueResult::stripNullArm()`, its single home; the splitter
underneath is `TsTypeShape::splitTopLevel()`, which `splitTopLevelUnion()` delegates to.
One consequence: a left operand of exactly `null`
(`null ?? $x`) strips to `'unknown'` and falls through to the right arm, since `null ?? $x` always
evaluates to `$x`.

### The default argument controls both `optional` and the union

`ConditionalMethodHandler::hasExplicitDefaultArg(MethodCall $call, int $index)` decides whether argument 1 was passed at all — purely
positionally, since Laravel distinguishes an omitted argument from an explicitly-passed `null` via
`func_num_args()`, not via `$value === null`. A `ConstFetch(null)` at the default position
(`whenNotNull($x, null)`) counts as an explicit default: `func_num_args() === 2` there too, so the key
survives at runtime as `null`, not as a missing key. Named or spread arguments make position meaningless,
so both bail the helper out to `false` rather than guessing — it is reused as-is by later conditional-family
handlers for the same reason.

When no explicit default is present, the property is `optional: true` and its type is just the (possibly
null-stripped) value arm — matching every pre-existing single-argument fixture (`ProductResource`,
`ImageResource`, `AddressResource`, …). When an explicit default *is* present, both the `optional` flag and
the union are decided by the shared `ConditionalMethodHandler::applyConditionalDefault()` helper described
[below](#every-handler-unions-the-default-arm-in-through-applyconditionaldefault), which `whenNotNull()` and
`whenNull()` reach with `$index: 1`. The default's own type is analyzed independently via
`analyzeValueExpression()`, since PHP evaluates it eagerly as an argument regardless of which arm ultimately
wins at runtime — unless the default is a closure requiring a parameter, none of which this pair ever
supplies, in which case it is never analyzed at all; see below.

### A default closure requiring more parameters than Laravel supplies is unreachable and never analyzed

Laravel invokes *almost* every conditional default via `value($default)` with **zero** arguments (verified
against `ConditionallyLoadsAttributes.php` in the installed Laravel 13 vendor tree): `when()` calls it
directly, `whenNull()`/`whenNotNull()` delegate to `when()`, and `whenLoaded()`, `whenHas()`,
`whenCounted()`, `whenAggregated()`, `whenExistsLoaded()` each call `value($default)` on their own miss
path. `transform()` is the one exception: it delegates to the global `transform()` helper
(`Support/helpers.php`), whose miss path calls an unfilled default as `$default($value)` — **one**
argument, not zero. A closure or arrow function passed as the default that declares more required
parameters than its caller actually supplies therefore throws `ArgumentCountError` at runtime instead of
producing a value — it can never contribute to the property's type.

`ConditionalMethodHandler::applyConditionalDefault($value, $call, $index, $scope, $engine, $defaultArgCount = 0)`
checks this before analyzing the default expression at all:
`InspectsAstNodes::closureRequiresArguments(Expr $expr, int $providedArgs = 0)`
returns `true` when `$expr` is a `Closure` or `ArrowFunction` whose count of parameters lacking both a
default value and a variadic marker exceeds `$providedArgs`. Every handler leaves `$defaultArgCount` at its
default of `0` except `analyzeTransform()`, which passes `defaultArgCount: 1` to match the global helper's
one-argument call. When the check trips, `applyConditionalDefault()` returns the value arm's result alone
with `optional: false` — the same "unresolved default leaves the value arm standing" policy used elsewhere
in this helper — without ever calling `$engine->resolve()` on the default's body.

The check sits at `applyConditionalDefault()`, the one choke point every handler's default argument passes
through, with the per-method argument count as an explicit parameter rather than a special case. It does
not need to special-case the per-method `value($value, $resolved)` asymmetry described
[below](#unlessmergeunless-delegate-whenappended-whenexistsloaded-and-transform-are-new-handlers): that
asymmetry only affects *value*-arm closures (which receive the resolved value as an argument), and
value-arm closures never reach `applyConditionalDefault()` — only the default argument does. A parameter
with its own default (`fn ($notes = '') => strlen($notes)`) or a variadic parameter (`fn (...$args) => ...`)
still invokes cleanly with zero (or `$defaultArgCount`) arguments, so closures shaped like those are not
excluded and still union their return type in as usual.

### An empty-array default collapses instead of widening the property

`InlineArrayHandler::analyzeInlineArray()` discriminates on `$array->items === []` before it consults the extracted
properties, because the two empty results mean different things. A literal `[]` is `never[]` —
`json_encode([])` emits `[]`, never `{}`, so the old blanket `Record<string, unknown>` described a shape
the runtime could not produce. An array whose *keys* failed to resolve keeps the `Record` fallback, which
is still the honest answer there.

`applyConditionalDefault()` then drops a `never[]` member whenever another member is an array type. That
is sound precisely because the arm is provably empty: `[]` is assignable to every array type, so it adds
nothing beside a real one. `whenLoaded('children', $this->children, [])` is therefore `Category[]`, not
`Category[] | Record<string, unknown>` — a union whose second arm no caller could consume.

The two halves are coupled. The collapse keys on the literal `never[]`, and it is only sound because
`never[]` means *provably empty*. Retyping empty literals as `unknown[]` would break it: the rule could no
longer tell a provably-empty arm from a genuinely-unknown-element one, and `X[] | unknown[]` → `X[]` is
unsound. `never[]` is also the friendlier of the two for consumers — it is assignable to every array type,
where `unknown[]` is assignable to none.

### Dropping an `unknown` arm is a deliberate policy, not an incidental side effect

The `unknown`-filtering is recorded here explicitly because it is easy to mistake for defensive scaffolding
and simplify away in a later refactor — it is load-bearing. It fires whenever `analyzeValueExpression()`
fails to resolve **either** arm, and it has two justifications, not one:

- **Precedent.** `CoalesceHandler` already treats an `unknown` operand as "no information" rather than a
  real union member, and `ValueResult::analyzeClosureUnion()` does the same for an `unknown` return
  branch. Unioning `unknown` in literally here would be the odd one out.
- **`T | unknown` collapses to `unknown` in TypeScript.** The honest-looking alternative — emitting the raw,
  un-filtered union when one arm can't be resolved — wouldn't degrade gracefully to a *partial* type; it
  would degrade the *whole property* to `unknown`, which is exactly the outcome this package's own
  no-type-regressions-to-`unknown` discipline exists to prevent. Filtering the unresolvable arm out is what
  keeps a resolvable sibling arm's type surfacing at all.

What the filtering never does is change `optional`. Before the arity rule above existed,
`ConditionalParamFullClosureResource::status_resource` —
`whenNotNull($this->status, function ($status) { return EnumResource::make($status); })` — demonstrated the
unresolved-*default* direction: the closure's `$status` param was unbound, so `EnumResource::make($status)`
resolved to `unknown`, and the property emitted `OrderStatusType`, required, because the explicit second
argument still meant Laravel always emitted the key. That closure requires `$status`, though, so it is now
caught earlier by [the arity rule](#a-default-closure-requiring-more-parameters-than-laravel-supplies-is-unreachable-and-never-analyzed)
and never reaches `analyzeValueExpression()` at all — `OrderStatusType`, required, is correct by the same
evidence but for a different reason. `ConditionalDefaultsResource::pivot_loaded_with_default` still
exercises the unresolved-*value*-arm direction, via `whenPivotLoaded()`'s hard-coded `unknown` value arm:
`unknown`, also required.

### A model-level `#[TsCasts]` override can mask this fix in the final output

`AddressExtendsResource`/`AddressMixinResource` both call `whenNotNull($this->latitude)` — the value-arm fix
alone makes that `latitude?: number` at the analyzer level. It doesn't reach the generated `.ts`: `Address`
carries a model-level `#[TsCasts(['latitude' => ...])]` override, and
`ResourceTransformer::applyOverrides()` applies model-level `#[TsCasts]` *after* AST analysis, so it wins
regardless of what this handler determines. That is expected, unrelated behavior, not a regression — verify
the value-arm/default-arm split against a fixture whose properties carry no `#[TsCasts]` override (e.g.
`ConditionalDefaultsResource`, or `AddressResource`'s `line_2`) rather than against `latitude`/`longitude`.

## An explicit default makes the rest of the conditional family required too

Every method in the conditional family — `when()`, `whenHas()`, `whenLoaded()`, `whenCounted()`,
`whenAggregated()`, `whenPivotLoaded()`, `whenPivotLoadedAs()` — takes a trailing
`$default = new MissingValue` parameter (`whenNotNull()`/`whenNull()` already covered above). When a
caller passes one explicitly, the key can never be a `MissingValue`, so the property must not be
emitted `optional`. The default sits at a **different argument index per method** — verified against
`ConditionallyLoadsAttributes.php` — and that is the whole difficulty:

| method | signature | default index |
| --- | --- | --- |
| `when`, `unless`, `mergeWhen`, `mergeUnless` | `($condition, $value, $default)` | 2 |
| `whenHas`, `whenAppended`, `whenLoaded`, `whenCounted`, `whenExistsLoaded` | `($attribute\|$relationship, $value, $default)` | 2 |
| `whenNull`, `whenNotNull` | `($value, $default)` | 1 |
| `whenPivotLoaded` | `($table, $value, $default)` | 2 |
| `whenPivotLoadedAs` | `($accessor, $table, $value, $default)` | 3 |
| `whenAggregated` | `($relationship, $column, $aggregate, $value, $default)` | 4 |
| `transform` | `($value, $callback, $default)` | 2 |

Every handler passes its own `N` to `applyConditionalDefault()`, which asks
`hasExplicitDefaultArg($call, N)` first. That check is purely positional: Laravel distinguishes an
omitted argument from an explicitly-passed `null` via `func_num_args()`, not `=== null`, so a
`ConstFetch(null)` at the default position still counts as a real default —
`whenLoaded('user', fn ($user) => $user, null)` is required and typed `User | null`, not optional and typed
`User` (see `loaded_with_default` in `ConditionalDefaultsResource`). A named or spread argument at the
default position makes position meaningless, so the helper bails out to `false` — the property behaves as
if no default were passed at all.

### Every handler unions the default arm in, through `applyConditionalDefault()`

`ConditionalMethodHandler::applyConditionalDefault($value, $call, $index, $scope, $engine)` is the single vehicle for the whole family: every
handler builds its value arm, then hands it over with its own default index. It union-merges the two
arms' `' | '` members, deduplicates them, and folds their import channels via `ValueResult::mergeUnion()`,
re-asserting `optional` to `false` afterwards (`ValueResult::mergeUnion()` resets it). The merge is not
cosmetic — a default like `Status::Draft` or `UserResource::make(...)` carries an import channel that
only `ValueResult::mergeUnion()` preserves; concatenating type strings by hand would emit a type name with no
import and trip the unimportable-token gate.

The settled rule is one sentence:

> An explicit default always makes the property required. Union the default's type in when it resolves;
> emit the value-arm type alone when it does not.

`optional` is decided by presence, not by type. Passing a default means the key is always emitted at
runtime, so `required` is factually true regardless of how well either arm resolved — forcing a consumer
through a presence check for a key that always exists would be its own kind of lie. That is why the single
early return sets `optional` to `false` rather than backing out to `true`.

It covers both directions of an `'unknown'` arm, which reach the same result for different reasons:

- **The default resolves to `unknown`** (an unanalyzable expression or closure body). There is no type to
  union, so the value arm's own type stands alone — the `'unknown'` arm is dropped rather than unioned in
  literally, the same treatment `CoalesceHandler` gives an `'unknown'` operand. Unioning it in would
  collapse the whole property to `unknown`, which the no-type-regressions-to-`unknown` discipline exists to
  prevent.
- **The value arm is `unknown`.** `unknown` already admits every value the default can produce, so the
  union collapses back to `unknown`. Narrowing to the default's type alone would drop whatever the value
  arm can still return. This is the `whenPivotLoaded()` / `whenPivotLoadedAs()` case, whose value arm is a
  hard-coded `unknown` the handler never inspects.

Routing the whole family through one helper replaced three near-identical inline merge blocks and closed a
soundness hole: `ConditionalMethodHandler::analyzeWhenHas()`, `analyzeWhenAppended()`, `analyzeWhenLoaded()` and
`analyzeWhenExistsLoaded()`, plus the `whenCounted()`/`whenAggregated()` inline arms, used to flip **only**
`optional` and emit the value arm's type alone. `$this->whenLoaded('user', fn ($user) => $user, null)` was
emitted as a required `User`, so a consumer could dereference the very `null` Laravel returns when the
relation is not loaded. Note that `whenLoaded()`, `whenHas()` and `whenAppended()` do already analyze
`$args[0]`/`$args[1]`, so reading one more argument was never the obstacle it was once described as.

### `whenPivotLoaded()`/`whenPivotLoadedAs()` need separate `if` branches, not a combined one

Before this task, both were handled by one `isThisMethodCall(...) || isThisMethodCall(...)` branch,
because they shared identical output (`unknown`, always optional). Once `optional` depends on the
default's argument index, that combined branch stops working: `whenPivotLoadedAs()` takes a leading
`$accessor` argument that `whenPivotLoaded()` doesn't, so their default sits at index 3 vs. index 2.
Each method needs its own branch reading its own index.

## `unless()`/`mergeUnless()` delegate; `whenAppended()`, `whenExistsLoaded()`, and `transform()` are new handlers

Five methods in the conditional family had no handler at all before this task and fell through to the
generic `$this->method()` branch, which reflects a declared return type and emits **required** `unknown` —
strictly worse than the optional `unknown` an unrecognized conditional should produce, since a required
key tells the consumer the value is always present.

All five (`unless`, `whenAppended`, `whenExistsLoaded`, `transform`, `mergeUnless`) also belong to
`InspectsAstNodes::$conditionalMethods`, the separate list consulted when one of them wraps a *nested
resource constructor* (`Resource::make(...)`/`new Resource(...)`), so that case is optional too.

### `unless()` and `mergeUnless()` reuse `when()`/`mergeWhen()` unchanged

`ConditionallyLoadsAttributes::unless($condition, $value, $default)` is
`$this->when(! $condition, $value, $default)`, and `mergeUnless()` is
`$this->mergeWhen(! $condition, $value, $default)` — Laravel negates the condition and forwards straight
through. Negating which branch of an `if` runs never changes what either branch's *type* is, so
`ConditionalMethodHandler::resolve()` dispatches `unless` straight to the existing `analyzeWhen()`, and
`analyzeMergeExpression()` treats `mergeUnless` exactly like `mergeWhen()` (array/closure argument at index
1, always optional). Neither needed a new method.

### `whenAppended()` types from the named attribute, like `whenHas()`

`whenAppended('attribute', $value, $default)` mirrors `ConditionalMethodHandler::analyzeWhenHas()`:
`analyzeWhenAppended()` resolves the accessor's type via its own `resolveModelAttributeTypeInfo()` helper
from the attribute name alone, never from analyzing `$value`. That helper is a `ConditionalMethodHandler`-
private duplicate of the shared trait method of the same name, calling `ModelAttributeResolver` directly
with `$scope->modelClass` rather than the trait's cached-property gate (see its own docblock). This matters
because Laravel's `whenAppended()` does **not** forward the resolved value
into a `$value` closure the way `whenHas()`/`whenLoaded()`/`whenCounted()`/`whenAggregated()`/
`whenExistsLoaded()` do — it calls `value($value)` with zero arguments, not `value($value, $resolved)` — so
a `$value` closure parameter has nothing bound to it in Laravel's own implementation. Typing from the
attribute name sidesteps that distinction entirely. The *default* at index 2 is still analyzed and unioned
in by `applyConditionalDefault()` — it is a plain eagerly-evaluated argument, not a closure needing a
binding.

### `whenExistsLoaded()` resolves to the generated `{relation}_exists` flag — and must agree with `ModelTransformer`

`whenExistsLoaded('relation')` reads `Model::withExists()`'s `{relation}_exists` attribute — the same flag
`ModelAttributeResolver::resolveAttributeFallbacks()` types as `boolean` for a model's own `*_exists`
properties (the `_exists` suffix → `boolean` fallback, mirroring `_count` → `number`).
`ConditionalMethodHandler::analyzeWhenExistsLoaded()` emits that same `boolean`, deliberately: a resource and the model it wraps
disagreeing about the type of the same underlying flag is exactly the kind of divergence this package
exists to prevent. An explicit default unions its own type alongside that `boolean` — but only when a real
`$value` is passed too, per the next section.

### A null `$value` makes the arm `null`, not the attribute or the flag

`whenHas()`, `whenAppended()` and `whenExistsLoaded()` have no identity-closure swap: none of them
substitutes the attribute for an unhelpful `$value` the way `whenLoaded()` and `whenCounted()` do. So a
`$value` that is skipped by a later named argument, or written as a literal `null`, leaves Laravel
evaluating `value(null, $attribute)` — or, for `whenAppended()`, `value(null)` with no extra argument, per
[above](#whenappended-types-from-the-named-attribute-like-whenhas). Both are `null`.
`ConditionalMethodHandler::valueSkipped()` recognises both spellings and the three handlers emit a `null`
arm for them, leaving only the default to carry a type:
`whenExistsLoaded('user', null, 'absent')` is `string | null`, never `boolean | string`. A genuinely
absent `$value` is different again — Laravel's one-argument branch returns the attribute itself, so
`whenExistsLoaded('user')` stays an optional `boolean`.

### `transform()` types from the callback's return, not `$value`'s

`transform($value, $callback, $default)` (`vendor/laravel/framework/.../Support/helpers.php`) calls
`$callback($value)` when `$value` is filled and returns that result — the callback's return type, not
`$value`'s own type, is what the property carries. `ConditionalMethodHandler::analyzeTransform()` mirrors `analyzeWhen()`'s
value-argument handling but analyzes `$args[1]` (the callback) instead of `$args[0]`, binding the
callback's first parameter to `$args[0]`'s `$this->prop` expression via `bindClosureParamsFromCondition()`
the same way `analyzeWhen()` binds a value closure to its condition, then hands the result to
`applyConditionalDefault()` with index 2, exactly like `analyzeWhen()` does — except for
`defaultArgCount: 1`: the same `transform()` helper invokes an unfilled default as `$default($value)`,
one argument, not the `value($default)`/zero-argument call every other handler's default receives (see
[above](#a-default-closure-requiring-more-parameters-than-laravel-supplies-is-unreachable-and-never-analyzed)).
A one-parameter closure default is therefore reachable here and unions in, where the same shape would be
excluded as unreachable anywhere else in the family.

## `#[Collects]` resolution is Laravel-version-guarded

`InspectsAstNodes::resolveCollectedResourceClass()` — called directly by every consumer
(`Ast\Concerns\ResolvesSingularResourceClass`, `ToResourceHandler`, `StaticCallHandler`,
`NewResourceHandler`) — checks for `Illuminate\Http\Resources\Attributes\Collects` behind
`class_exists()` rather than a `use` import,
because the package still supports Laravel 12 releases that don't ship the attribute. See
[Version-guarded Laravel classes](../laravel-version-guards.md) for the full registry and when this
guard can be removed.

## `toResource()` convention guesses are gated on the published set

`Model::toResource()` and `Collection::toResourceCollection()` reach a resource class three ways: an
explicit `SomeResource::class` argument, a `#[UseResource]`/`#[UseResourceCollection]` attribute, or
Laravel's naming convention (`ToResourceHandler::guessResourceNames()`). Only the last one *invents* a class name, and
`isResourceClass()` accepts whatever `class_exists()` finds — including a third-party or `#[TsExclude]`d
resource this package never writes a file for. `ResourceTransformer` would then emit the
`class_basename()` token plus an import built by `LaravelTsPublish::namespaceToPath()`, which is pure
string transformation and never touches the filesystem, so the import names a module that does not exist.

`PublishedResourceRegistry` holds the resource classes the current run will actually emit.
`isPublishedResourceClass()` is `isResourceClass()` plus that membership test, and it gates every site
that invents a candidate class name, four in total:

| Site | Candidates it can now reject |
| --- | --- |
| `ToResourceHandler::resolveResourceForModel()`'s candidate loop | `{Model}Resource`, then bare `{Model}` |
| `ToResourceHandler::resolveResourceCollectionForModel()`'s `{Guessed}Collection` loop | `{Model}ResourceCollection`, then `{Model}Collection` — the inline `class_exists()`/`is_a()` pair gained a third `PublishedResourceRegistry::isPublished()` conjunct |
| `ToResourceHandler::resolveResourceCollectionForModel()`'s bare-candidate loop | the `{Model}Resource` fallback |
| `InspectsAstNodes::resolveCollectedResourceClass()`'s naming-convention branch | `{X}Resource`, then bare `{X}` — shared by every direct caller (`ResourceAstAnalyzer`, `ToResourceHandler`, `StaticCallHandler`, `NewResourceHandler`) |

**`isResourceClass()` itself is unchanged.** Every branch that reads a class the developer wrote down
stays ungated on purpose — an explicitly named resource is a declaration, not a guess:

- the explicit-argument arms of `ToResourceHandler::analyzeToResourceCall()` and `analyzeToResourceCollectionCall()`
- `ToResourceHandler::resolveUseResourceAttribute()` and `resolveUseResourceCollectionAttribute()`
- `resolveCollectedResourceClass()`'s first two branches: the `#[Collects]` attribute and the
  `$collects` property default
- `NewResourceHandler`'s `new $resourceFqcn(...)` resolution — the class name comes from an explicit
  `new` expression in the analyzed source, not an invented candidate, so it stays ungated on the
  same basis

### One resolver, not many — every `#[Collects]` caller shares it

`Ast\Concerns\ResolvesSingularResourceClass`, `ToResourceHandler`, `StaticCallHandler` and
`NewResourceHandler` used to carry near-verbatim copies of the same `#[Collects]` / `$collects` /
naming-convention resolution order,
including the same third, naming-convention branch — the one gap this section used to carry as a
recorded follow-up rather than a fix. Every one of them now calls
`InspectsAstNodes::resolveCollectedResourceClass()` directly, the only place that logic exists; its
naming-convention branch is gated on `PublishedResourceRegistry` exactly like the three sites above.
No call site can drift apart on this resolution order, because there is only one implementation left
to diverge from.

### The registry fails open, and `RunnerForSource` depends on it

An empty registry means "no information", so `isPublished()` returns `true` for every class. That is a
contract, not a convenience: `RunnerForSource` handles a single FQCN and never resolves a collector, so it
never reaches `PublishedResourceRegistry::register()`. A `ts:publish --source=…` regeneration must still
analyze against an empty registry even in the same process as an earlier full run — otherwise a leftover
set from that run narrows this run's own convention guess and a real type silently collapses to `unknown`.
Failing closed there would also silently strip the regenerated file's *convention-guessed* nested resource
references. Only those. The registry is consulted at four candidate-inventing sites:
`ToResourceHandler::resolveResourceForModel()`'s naming-convention loop,
`resolveResourceCollectionForModel()`'s two naming-convention loops (the `{Guessed}Collection`
candidates, then the bare guessed resources), plus the candidate list inside the naming-convention
branch of `InspectsAstNodes::resolveCollectedResourceClass()`. An explicitly named reference never
reaches it and would survive — `SomeResource::make()` and the `::collection()` arm of `StaticCallHandler`
test `isResourceClass()` rather than `isPublishedResourceClass()`, and so do the explicit-argument arms of
`ToResourceHandler::analyzeToResourceCall()` and `analyzeToResourceCollectionCall()`.

### Both runners reset the registry at the top of `run()`, once per run

`PublishedResourceRegistry` means *this run*, not "every run since the process started". Both concrete
runners enforce that as the first statement of `run()`: `Runner::run()` calls
`PublishedResourceRegistry::reset()` before doing anything else, and `RunnerForSource::run()` does the
same — its own reset, not a side effect of skipping `register()`.

Placement matters. `Runner::generateResources()` returns early, before it would otherwise call
`register()`, whenever `shouldPublishResources` is `false` (e.g. `--only-routes`) (see "Populated
once, before the generate loop" below). A reset placed next to that `register()` call would never run
on a resources-disabled run, so the registry would still hold the *previous* run's set — the wrong
kind of narrowing, not the intended "allow everything" of an empty registry. Resetting at the top of
`run()` instead means a resources-disabled run reaches every convention-guessed resource unfiltered,
exactly like the single-FQCN `RunnerForSource` path above, rather than being gated by stale state.

This also closes the other direction: two full `Runner::run()` calls in one process, the second with a
narrower `ts-publish.resources.excluded`, no longer leave the first run's classes registered — a
resource the second run does not emit is no longer imported by that run's output, which would otherwise
name a symbol `BarrelWriter::writeModular()`'s rebuilt `index.ts` does not export.

### Populated once, before the generate loop

`Runner::generateResources()` registers the whole collected list before generating anything, not as each
generator completes. Analysis happens *inside* the `foreach`, and a resource analyzed on the first
iteration may legitimately reference one collected on the last, so incremental registration would make
resolution depend on collection order. One `$collected` value feeds both the registration and the loop:
`CoreCollector::collect()` is not memoised and re-runs `ClassMapGenerator::createMap()` per call, so a
second call would double the class-map scan.

The registry is process-static, in the shape of `DependencyRecorder` and `OutputRecorder`, because the
collector is `resolve()`d per call rather than bound as a singleton (the service provider registers only
`ModelAttributeResolver` and `CacheRepository`) and constructor plumbing would have to cross four hops
from `Runner` down to the analyzer. `Tests\TestCase::setUp()` resets all three, since process-static
state otherwise leaks across a parallel Pest run — that reset stays even though both runners now reset
`PublishedResourceRegistry` themselves, because it also protects tests that never construct a runner at
all. A consuming application gets no such per-test hook, which is why both runners reset on entry too.

CI catches some regressions here and misses others, and the axis is **not** namespace. A resource file's
references to other published classes are written as relative *directory* specifiers, cross-namespace ones
included (`'../../../crm/http/resources'`), so relativeness alone decides nothing. What decides the
diagnostic is whether the package writes a barrel into the target directory **at all**.

Scope that claim to resource files, because the tree as a whole is mixed. Measured across the committed
trees (`import ... from` lines only, excluding barrel `export ... from` re-exports): **1816** import
specifiers, of which **620 are bare** — `@tolki/ts` (364, emitted under this package's own shipped
`'use_tolki_package' => true` default, so not an app-side escape hatch), `@tolki/types` (24) and 232
`@/types/*`, `@js/types/*` and `@workbench/types` aliases. Of the **1196** relative ones, 1068 resolve to
a directory and 128 to a single file — `broadcast-events.ts` and `echo-broadcast-events.d.ts` (56 each,
`'./app/events/OrderShipped'` and friends) plus 16 controller-to-request imports such as
`'../requests/store-post-request'`. None resolves to nothing any more: the four `'../value-objects'`
imports that did, one per tree, went away when `toTsType()` step 5c started inlining `Coordinate`'s
property shape instead of emitting a class token for it. Inside
generated resource files specifically, excluding their barrels, **all 680 relative imports resolve to a
directory**; that is the enumerated set the paragraph below is about.

If it never writes that directory, the specifier resolves to nothing, tsc reports TS2307, and
`unimportable-token-gate.sh`'s relative-specifier sub-gate counts it — CI runs that armed
(`.github/workflows/run-tests.yml` invokes `unimportable-token-gate.sh 0 0 0`). The corpus's one live
instance was exactly this shape until step 5c removed it: `warehouse.ts` imported `'../value-objects'`,
and `default-example/app/value-objects/` does not exist. The sub-gate's baseline is `0` now, so nothing in
the corpus exercises its failure path — see
[Type inference gates](../testing/type-inference-gates.md#proving-each-gate-fires).

If the directory *is* written, its `index.ts` resolves and only the symbol is missing, so tsc reports
TS2305 "has no exported member" — or **TS2724** when the barrel happens to export a near-miss name. Both
codes are now in the main gate's grep; they were counted by neither gate until the app-side stubs took the
bare-specifier baseline to zero and made "resolves but does not export it" a reachable shape worth gating.
That is the shape of the `AttachmentResource`/`AttachmentCollection`
fixtures below, and it does not depend on their sharing a namespace with `MerchantResource`: importing an
unpublished symbol *cross*-namespace out of `crm/http/resources` fails the same way. Reproduced against
this repo's tsc, importing both `#[TsExclude]`d fixtures from the 124-export `app/http/resources` barrel
gives TS2724 for `AttachmentResource` ("Did you mean 'CommentResource'?") and TS2305 for
`AttachmentCollection`.

Both flavors are now gated, which was not true before the stubs. In the **modular** files a leaked resource
is an *import*, so it takes the TS2305/TS2724 shape above — counted since those codes joined the main grep.
In `laravel-ts-global.ts` the same resource is referenced by **bare name** inside a
nested namespace, with no import at all — the file's only imports are 20 bare app-side aliases — so an
unresolvable one is a missing *name*, which `unimportable-token-gate.sh` **does** count. The corpus carried
one live instance of that split until `AddressResource`'s `#[TsResource(name: 'Address')]` rename started
being honored by analyzer-derived references; it produced `TS2552` in the global flavor and `TS2724` in the
modular one, and both went away together. Nothing in the generated tree exercises the split now — tsc
reports no TS2305 and no TS2724 over it — so the split has to be synthesized to be seen. Injecting the leak
into a scratch copy of the global file confirms both codes it can take, and both are in the gate's grep:
`unpublished_guess?: AttachmentResource` gives `TS2552: Cannot find name 'AttachmentResource'. Did you mean
'CommentResource'?`, and a name with no near neighbor gives plain `TS2304`. The same defect is now gated in
both flavors — TS2304/TS2552 in the global one, TS2305/TS2724 in the modular one. See
[Type inference gates](../testing/type-inference-gates.md). The coverage is instead the published-set
tests in `ResourceAstAnalyzerTest.php`, against the `#[TsExclude]`d `AttachmentResource` and
`AttachmentCollection` workbench fixtures, plus a matching pair for `resolveCollectedResourceClass()`'s
own naming-convention branch: `SupplierSummaryCollection` and `Admin\StoreCollection` prove the two
positive candidates (Resource-suffixed and bare) still resolve when published, and `LedgerCollection`
with `#[TsExclude]`d `LedgerResource`/`Ledger` proves both are rejected when neither is. There is one
implementation of the resolver, so that coverage is the coverage for every call site.

## `#[PreserveKeys]`/`$preserveKeys` flip a collection's element type to `Record<string, R>`

A `ResourceCollection` normally serializes as a JSON array, so a collected element type gets a `[]`
suffix. Laravel honours two ways of opting a collection out of that: the `#[PreserveKeys]` class
attribute (Laravel 13+) and the older `public $preserveKeys = true;` property (every supported
version). Either one makes Laravel keep the collection's original keys, so the payload is a JSON
object instead — `collectionPreservesKeys()` checks both, and `wrapCollectionElementType()` is the
single point that turns that boolean into `Record<string, R>` instead of `R[]`.

Every collection-typing call site routes through `wrapCollectionElementType()`. There are **seven**,
across three paths, split between `ResourceAstAnalyzer` and the resource-construction handlers, which
now own static-call/`new`/`toResource` construction;
`grep -rn 'wrapCollectionElementType(' src/Analyzers/ResourceAstAnalyzer.php src/Ast/Handlers/` is the check:

- **`SomeResource::collection(...)` / `SomeCollection::make()`/`::collection()` / `new
  SomeCollection(...)`, referenced inside another resource's `toArray()`** — three calls, in
  `StaticCallHandler::analyzeStaticCall()` (two: the named-collection branch and the plain-resource
  branch) and `NewResourceHandler::analyzeNewResource()` (one).
- **`$collection->toResourceCollection()`** — two calls, both in
  `ToResourceHandler::analyzeToResourceCollectionCall()`: the explicit-argument arm
  (`toResourceCollection(SomeResource::class)`) and the arm that resolves a collection through
  `resolveResourceCollectionForModel()`. They reflect on different classes; see the note below.
- **A `ResourceCollection` with no `toArray()` override, delegating to `$this->collection`** — two
  calls: `ResourceAstAnalyzer::buildCollectionDelegatedAnalysis()` (one call whose element type feeds
  both the `flatTypeAlias` branch and the wrapped-`data`-key branch) and
  `ThisPropertyHandler::analyzeCollectionProperty()` (the `$this->collection` property read).

That count is exactly what a future change to any of these methods is liable to get wrong — a site
that's missed silently keeps emitting `R[]`, and nothing catches it until a fixture exercises that
exact call shape with `#[PreserveKeys]` or `$preserveKeys` set. The list has already been wrong once
in that exact way: it read "six" and omitted `analyzeToResourceCollectionCall()` altogether.

The reflection target passed to `wrapCollectionElementType()` differs by site to match what Laravel
itself reflects on at runtime. Two sites reflect on the *resource*. One is `SomeResource::collection(...)`,
where Laravel's `JsonResource::collection()` checks `static::class` — the singular resource being called
on, not a separate collection class. The other is `toResourceCollection(SomeResource::class)`, because
`TransformsToResourceCollection::toResourceCollection()` returns `$resourceClass::collection($this)` and
so lands in that same method. The remaining sites reflect on whatever class Laravel instantiates — the
`ResourceCollection` subclass for `make()`, `new`, and the collection-delegated path. The
argument-less `toResourceCollection()` arm — the no-explicit-argument branch of
`analyzeToResourceCollectionCall()` — is the one that varies: it reflects on
`resolveResourceCollectionForModel()`'s `collectionFqcn`, and that is only sometimes a collection
class.
Of the method's four value-returning arms, the two that resolve *through a collection class*
(`#[UseResourceCollection]`, and the `{Guessed}Collection` naming branch) set it to that class; the two
that find **no collection class at all** — the `#[UseResource]` arm and the naming-convention fallback
over `guessResourceNames()` — set `collectionFqcn` to the plain resource, because the resource is what
stands in for the collection. Vendor does the same in both: `guessResourceCollection()` returns
`$resourceClass::collection($this)` for each. Probing the resolver on `Workbench\App\Models\TrackingEvent`
(`#[UseResource(EventLogResource::class)]`) returns `collectionFqcn === resourceFqcn === EventLogResource`,
a plain `JsonResource`. So read the rule as "reflect on whatever Laravel instantiates", not "always the
collection class".

### Inertia props

`collectionPreservesKeys()` and `wrapCollectionElementType()` live in the
`AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys` trait, which this class shares
with the handlers `InertiaPageAnalyzer` runs page props through — `InertiaResourcePropHandler`,
`NewResourceHandler`, `StaticCallHandler`, `ToResourceHandler` and `ThisPropertyHandler`. Page props
get preserve-keys handling from those handlers, not from a rewrite of their own, so the two shapes
agree by construction:

- A **paginated flat collection** (`$wrap === null`, e.g. `new SomeFlatCollection($paginator)`) and a
  **paginated `SomeResource::collection($paginator)`** would otherwise emit
  `JsonResourcePaginator<Singular>`, whose `data` member is `Singular[]` — wrong for a key-preserving
  collection, since Laravel serializes its `data` as an object, not an array. `InertiaResourcePropHandler`
  emits `Omit<JsonResourcePaginator<Singular>, 'data'> & { data: Record<string, Singular> }` when
  `collectionPreservesKeys()` is true, gated on the reflected collection or, for the static-collection
  form, the reflected resource — `Resource::collection()` inherits the singular resource's own
  preserve-keys state, mirroring `wrapCollectionElementType()`'s site-dependent reflection target above.
- A **named collection**, paginated or not, references the collection's own generated interface — e.g.
  `PostCollection` — rather than re-deriving a shape, and `ResourceAstAnalyzer` already emits that
  interface with a keyed `data: Record<string, T>` member via `wrapCollectionElementType()`. Fixtures
  pin both shapes end to end in `InertiaPageAnalyzerTest.php`.

## `mergeReturnBranches()` carries every `MethodAnalysis::merge()` channel, plus two flat scalars

A resource with multiple direct `return [...]` branches (`if`/`elseif`/`else`, loop bodies, guard
clauses) is analyzed per-branch, then unioned by `mergeReturnBranches()`. It carries the same ten
channels `MethodAnalysis::merge()` does — `properties`, `enumResources`, `nestedResources`,
`directEnumFqcns`, `modelFqcns`, `customImports`, `multiEnumResourceFqcns`, and the three inline
maps (`inlineEnumFqcns`, `inlineModelFqcns`, `inlineEnumResourceFqcns`) — unioning each inline map
per property key, exactly like `MethodAnalysis::merge()`. Missing this union silently drops a property's
only enum/model reference when that reference sits inside an inline array literal in one branch,
emitting a type token with no import.

**`inlineModelFqcns` unions per occurrence; the two enum inline maps still dedupe.** Both merge
paths used to `array_unique` all three inline maps, which lost real multiplicity whenever a merged
or branched property named the same model twice — `aliasPropertyType()`'s per-occurrence queue then
fell back to its shorter-than-occurrence-count clamp and mistyped the missing occurrence.
`BranchedInlineFqcnResource` pins the branch-merge case; `ChildInlineFqcnResource` pins the
`MethodAnalysis::merge()` case.

`inlineEnumFqcns` and `inlineEnumResourceFqcns` stay deduped **even though they feed the same
per-occurrence queue**, so this is an asymmetry the code has, not a difference in what the maps are
for. Both reach `aliasPropertyType()`: `inlineEnumResourceFqcns` becomes
`ResourceTransformer::$propertyInlineEnumResourceFqcns` and is walked directly by
`rewriteEnumResourceTypes()`, and `inlineEnumFqcns` becomes `$propertyInlineEnumFqcns`, which
`mergePropertyFqcnMaps()` folds into the list `rewriteTypeReferences()` passes to the same helper —
and `mergePropertyFqcnMaps()`'s own docblock ends "never dedupe it". `array_unique` was dropped
from `inlineModelFqcns` only; the two enum maps kept theirs.

What makes the dedupe safe today is the corpus, not the design. The exact rule, established by invoking
`aliasPropertyType()` directly rather than by reading it: **dropping duplicates is lossless if and only if
the queue is its distinct FQCNs in first-appearance order followed only by repeats of the last one.**
Everything else mistypes an occurrence. The trailing run is free because the
`min($cursor + 1, count($queues[$name]) - 1)` clamp keeps re-serving the final entry. Run against the real
function, the rule agrees with observed behavior on every shape tried: `[A, B]`, `[A, A, A]`, `[A, B, B]`
and `[A, B, B, B]` are lossless; `[A, A, B]`, `[A, B, A]`, `[B, A, B]`, `[A, B, A, B]` and `[A, A, B, B]`
are not.

**Two** corpus properties meet the precondition for that to bite — one property naming two distinct
members of a same-basename FQCN group — and both are lossless. The corpus has exactly one such group
(`Workbench\App\Enums\Status`, `Workbench\Crm\Enums\Status`, `Workbench\Shipping\Enums\Status`).
Enumerating every generated property line whose *type* side names two distinct members of it, and
collapsing each enum's const and type names together, returns exactly two property names, each in all four
example trees: `DealResource::$status_pair` and `DealEnumInlineResource::$summary`. Both are the on-point
shape rather than a curiosity — both are inline `EnumResource::make()` wraps, so both populate
`inlineEnumResourceFqcns`, the map this paragraph is about. Both name each FQCN exactly once, so each
queue is `[d1, d2]` and there is no multiplicity to lose. (`EnumCollectionResource::$wrapped_status_fallback`
is a near-miss, not a third: its `Status` and `StatusType` tokens are the const and type names of the
*same* FQCN, both imported from `'../../enums'`.)

Treat the two `array_unique` calls as a latent bug with no current trigger rather than a decision to
preserve — the plan's Out of Scope section records the fixture that would be needed to fix it.

**A single inline array member's own multi-FQCN accessor now contributes its own arms too.** The three
fixes above only cover *merging* an already-populated queue across branches or inheritance. A member whose
own value is a multi-FQCN accessor (`Attribute<CrmUser|User, never>`) never populated that queue at all:
`resolveModelAttributeTypeInfo()` discarded `classFqcns`, so `ThisPropertyHandler::analyzeThisProperty()` had nothing to attach
as `embeddedModelFqcns`, and separately `InlineArrayHandler::analyzeInlineArray()` built the array literal's own
`embeddedModelFqcns` from the self-keyed, deduplicated `$analysis->modelFqcns` map rather than
`$analysis->inlineModelFqcns`, which would have collapsed any remaining multiplicity anyway. Both are
fixed: `resolveModelAttributeTypeInfo()` now carries `classFqcns` through, `analyzeThisProperty()` attaches
it as `embeddedModelFqcns` whenever there is more than one, and `analyzeInlineArray()` walks
`$analysis->properties` in declaration order, preferring each member's `inlineModelFqcns` entry over
`modelFqcns`. `WarehouseResource::$probe_nested` (`['first' => $this->last_user_activity_by, 'second' =>
$this->manager]`) pins the fix: `first` keeps its `CrmUser`/`ModelsUser` arms aliased apart instead of
rendering `User | User` and losing the CRM arm entirely in `laravel-ts-global.ts`, where it used to
collapse to `app.models.User | app.models.User`.

`analyzeReturnArray()`'s child-overrides-parent `unset()` now clears all three inline maps for the
overridden key, not just the five non-inline maps it always cleared. Without that, a
`...parent::toArray()` spread's stale inline-model entries for a key the child then overrides
survive into the child's own push, so the child's occurrences consume the parent's leftover queue
instead of their own — `ChildInlineFqcnResource`'s `regional_hub_contacts` pins this; its
`regional_hub_leads`, spread through with no override, pins the dedupe removal on its own.

It additionally resolves `flatTypeAlias`/`flatTypeAliasFqcn`, two scalars `MethodAnalysis::merge()`
never touches: the first non-null branch wins on conflict. No fixture exercises that conflict rule, and the
argument has to cover **both** callers. `analyzeAllReturnBranches()` builds its branches with
`analyzeReturnArray()`; `resolveArrayOrClosureToProperties()` — the `merge()`/`mergeWhen()` path, which
merges a multi-return closure's branches — builds its own with `ThisPropertyHandler::extractPropertiesFromArray()`. Neither
builder ever sets either field, so every branch reaching this method already has both null. Only
`buildCollectionDelegatedAnalysis()` sets them, and it returns directly without going through branch
merging.

**The branch type union hoists a single top-level `| null`.** Each key's per-branch type strings are
deduped whole (`array_unique` over the strings) and then joined. Two branches that differ in shape but
are both nullable — `$this->regional_hub?->only(['primaryContact', 'manager'])` against
`->only(['manager', 'secondaryContact', 'primaryContact'])` — survive that dedupe as two distinct
strings, so a plain `implode(' | ', …)` repeated the nullable marker once per arm: `A | null | B | null`.
`unionBranchTypes()` splits every arm on its top-level `|` via `LaravelTsPublish::splitTopLevelUnion()`
(depth-aware over `{`, `(`, `<` and `[`, and it skips single-quoted literals whole), drops the top-level
`null` members, and appends one trailing `| null` if any arm carried one. A nested null — `| null` on a
member inside `{ … }` — sits inside a group, so the splitter never yields it and it is left alone. Arm
order is otherwise preserved, which is load-bearing: `aliasPropertyType()` consumes `inlineModelFqcns`
positionally against the type-name tokens in the rendered string, and moving only `null` members leaves
every type-name token where it was.

The duplicate it removed was cosmetic — TypeScript normalizes duplicate union members, so
`A | null | B | null` and `A | B | null` denote the same type, and the token gate's `tsc` baselines
(10 duplicate/unfindable identifiers, 0 relative-specifier `TS2307`s) were unmoved by the fix. A brace-aware
scan of all four generated trees (counting `| null` only outside `{}`, `()` and `[]`) found exactly
eight lines carrying a top-level duplicate before the fix, all of them
`BranchedInlineFqcnResource::$regional_hub_contacts` — the four per-tree resource files plus the four
`laravel-ts-global.ts` files — and zero after. The 176 lines that carry two or more `| null` in total are
unchanged: those are nested member nulls, which is what a nullable member inside an object shape should
look like.

`ValueResult::analyzeClosureUnion()`, the ternary/Elvis union, has the same repetition and is **not**
covered by this helper: it collapses only a standalone `'null'` member against arms that already carry
one, so two arms each ending in `| null` still double. No corpus property reaches it; the plan's Out of
Scope section records it.

## Resource inheritance: a subclass with no `toArray()` of its own

`analyze()` looks up `toArray` in the **subclass's own file only**, via `MethodLocator::locateOwn()`:
it reflects the class, parses its own file through `AstParser` (recording that file as a cache
dependency), and matches the method by exact name in that file's AST. Reflection only confirms the
method exists somewhere in the hierarchy — the own-file, exact-name AST match is what makes an
*inherited* `toArray()` a miss, so declaring no `toArray()` of its own still falls straight to model
or collection delegation, which produced an empty interface whenever no model resolved either.

### The ancestor walk and its `properties !== []` termination

When that search finds no `ClassMethod` — or one with a `null` body — `analyze()` now calls
`analyzeParentToArray()` first, and returns its result **only if `properties !== []`**:

- `analyzeParentToArray()` returns `null` when there is no parent, or the parent is not a
  `JsonResource`.
- When the parent *is* `JsonResource` itself it returns `buildModelDelegatedAnalysis()` — the
  bottom of the chain, not an inherited shape.
- Otherwise it builds `new self($parentClass, $this->scope->modelClass, $this->methodName)` and calls
  `analyze()` on it. The multi-level walk and its termination therefore come for free through that
  recursion: each level repeats the own-file lookup and only stops at the nearest ancestor that really
  declares a body.

The `properties !== []` guard is what keeps the pre-existing behaviour intact. An ancestor chain
where **nobody** declares a `toArray()` yields an empty analysis at every level, so `analyze()` falls
through to `isResourceCollection()` → `buildCollectionDelegatedAnalysis()`, or to
`buildModelDelegatedAnalysis() ?? new ResourceAnalysis`, exactly as before. `ChildSharedResource`
pins this: `BaseSharedResource` declares no `toArray()` either and the name resolves no model, so it
still generates `export interface ChildSharedResource extends SharedInterface {}`.

The same guard is why the walk running *before* the `isResourceCollection()` check is safe.
`Illuminate\Http\Resources\Json\ResourceCollection` does declare a `toArray()`, but both of its
returns are `$this->collection->map->…->all()` — neither an array literal nor a `$this` receiver the
analyzer can read — so the recursion yields no properties and body-less collections such as
`PostCollection` and `PreserveKeysCollection` still reach `buildCollectionDelegatedAnalysis()`.

### A body-less `ResourceCollection` stacked on another inherits its parent's `$collects` — not its own

Two body-less `ResourceCollection` subclasses stacked on each other (`LeafCollection extends
MidCollection extends ResourceCollection`, neither declaring `toArray()`) do not each resolve
`$collects` independently. `LeafCollection::analyze()` finds no own-file `toArray()` and calls
`analyzeParentToArray()`, which builds `new self($midClass, $this->scope->modelClass, $this->methodName)`
and analyzes *that*.
`MidCollection` is itself body-less, so its own `analyzeParentToArray()` walks up again to the real
`ResourceCollection::toArray()`, which — per the guard above — yields no usable properties, so
`MidCollection::analyze()` falls through to its **own** `buildCollectionDelegatedAnalysis()`, resolving
`$collects` from `MidCollection`'s own reflection. If `MidCollection` doesn't declare `$wrap` (or
declares a non-null one), that call returns a non-empty `properties` array — the wrapped `{ data: … }`
shape. Back in `LeafCollection::analyze()`, the `properties !== []` guard passes on that inherited
result and returns it unchanged: `LeafCollection`'s own `#[Collects]`/`$collects` is never consulted,
even when it names a different resource than `MidCollection`'s.

No corpus fixture has this shape, so this was verified with a throwaway two-class probe outside the
workbench tree rather than asserted from a trace: `MidCollection` (`#[Collects(PostResource::class)]`,
default `$wrap`) and `LeafCollection extends MidCollection` (`#[Collects(UserResource::class)]`, no
`$wrap` of its own) both analyze to `{ data: PostResource[] }` — `LeafCollection` emits its parent's
collected type, not its own.

A parent with `$wrap === null` still breaks the `properties !== []` guard the same way:
`buildCollectionDelegatedAnalysis()` returns `new ResourceAnalysis(flatTypeAlias: $elementType,
flatTypeAliasFqcn: $singular)` when `$wrap` resolves `null`/empty — `properties` stays at its `[]`
default, so `LeafCollection::analyze()` falls through instead of returning the parent's result,
reaches `isResourceCollection()`, and calls its **own** `buildCollectionDelegatedAnalysis()` — this
time resolving `$collects` from `LeafCollection`'s own reflection, so its own override *is* honoured.

> `$wrap` is read through reflection from wherever it is declared — the collection itself, a parent
> collection, or `JsonResource`'s own `'data'` default — so an abstract base that sets `public static
> $wrap = null` unwraps every body-less subclass.

That recomputation inherits the parent's `$wrap = null` for the same reason:
`buildCollectionDelegatedAnalysis()` reads `ReflectionProperty::getDefaultValue()` with no
declaring-class check, so `LeafCollection` resolving `$wrap` on its own reflection still finds the
value `MidCollection` declared. The same probe's `$wrap = null` variant (`MidCollection` declares
`public static $wrap = null;`, `LeafCollection` does not) confirms it: `LeafCollection` now analyzes
to the flat `UserResource[]` — its own `$collects` combined with the parent's inherited `$wrap = null`
— not the `{ data: UserResource[] }` shape a declaring-class check would have produced.

### The explicit `parent::toArray($request)` forms are unchanged

Writing the call out by hand remains fully supported and is still the idiomatic form. Both spellings
route through the same `analyzeParentToArray()`:

- `...parent::toArray($request)` spread inside an array literal — `analyzeReturnArray()` matches the
  unpacked item with `isParentToArrayCall()`, then merges the parent analysis in through
  `MethodAnalysis::merge()`. `ApiPostResource` pins this one.
- a bare `return parent::toArray($request);` — the non-array fallback in `analyze()` matches the
  `Return_` expression with `isParentToArrayCall()`. `PreserveKeysTeamResource` pins this one.

Because both classes declare a `toArray()`, the own-file lookup succeeds and the new walk is never
reached for them.

## An inherited shape needs an inherited model

`Ast\ModelClassResolver::resolve()` — which `ResourceTransformer::resolveModelClass()` and
`AstEngine::analyzeMethod()` both delegate to — used to read the docblock of the resource itself
only. A body-less child with no docblock of its own resolved no model, and the inherited analysis
degraded every column to `unknown` — so the walk is only half a feature without a matching docblock
walk. `modelFromDocblock()` now reads the `@mixin`/`@extends` tags off any one `ReflectionClass`, and
`modelFromAncestorDocblock()` climbs the parent chain calling it until one resolves. Precedence:

1. `#[TsResource(model:)]`
2. the resource's own `@mixin` / `@extends`
3. **the nearest ancestor's `@mixin` / `@extends`**
4. a typed `$resource` property
5. the `App\Http\Resources\{Name}Resource` → `App\Models\{Name}` naming convention
6. `#[UseResource]` on a collected model

Step 3 is the only new one; everything else keeps its previous relative order. `BodylessOrderResource`
pins it — it declares neither a body nor a `@mixin`, and `Workbench\App\Models\BodylessOrder` does not
exist, so its `number`/`AsEnum<…>` columns can only come from `OrderResource`'s own `@mixin Order`.
`BodylessTeamResource` carries its own `@mixin Team` and so pins the analyzer walk alone.

**The docblock walk is not scoped to the body-less case.** The body-less resource is what motivated
it, but `ModelClassResolver::resolve()` runs `modelFromAncestorDocblock()` unconditionally once step 2 fails,
so *any* resource lacking its own `@mixin`/`@extends` — body or no body — now resolves an ancestor's
before falling through to the `$resource` property and the naming convention. That is arguably the
right scope, since an ancestor's `@mixin` describes the same model either way, and it moved nothing
in the generated trees; but read the precedence list above as the whole rule, not as a body-less
special case.

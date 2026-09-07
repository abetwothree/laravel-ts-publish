# FormRequestRulesAnalyzer

> User-facing docs: [README § Form Requests](../../README.md#form-requests). Verified by
> [the type-inference gates](../testing/type-inference-gates.md).

`AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer` resolves a
FormRequest's `rules()` array into `FormRequestRuleNode`s ready for interface generation, and
composes `parent.*.child`/`parent.child` rule keys into their nearest undotted ancestor instead
of emitting them as separate flat, quoted keys.

## Rule priority: position-independent passes before declaration order

`resolveTsType()` resolves a single node's rule list in fixed pass order, not declaration order:
`File` (also catching `ImageFile`, which extends it), `AnyOf`, `Enum`, then `In` — both the object
form (`Rule::in(...)`) and the string form (`'in:a,b,c'`) — then a pass over the other rule-object
types (`StringRule`, `Email`, `Numeric`, …). Only after all of those fail to match does a final
declaration-ordered loop run, matching each remaining string rule (`'string'`, `'integer'`, a bare
parameterless `'in'`, …) in the order it was written. Because `In` is resolved in the
position-independent pass, `['string', 'in:a,b']` and `['in:a,b', 'string']` both type as
`'a' | 'b'` — an earlier `string`/`integer` rule can never shadow the literal union.

## One constant drives both the `number` mapping and the `in:` coercion trigger

`ValidationRuleParser::parse()` always parses string-form `in:a,b,c` params as strings, so
`resolveInFromParams()` needs an explicit signal before it emits an unquoted numeric literal instead
of a quoted string one. That signal is `hasNumericTypeSibling()`, called once *inside*
`resolveInFromParams()` — so both of its call sites, the position-independent `'in'` short-circuit
and the `'in'` arm of `resolveTsType()`'s declaration-ordered match, get it — and it now shares a single
`NUMERIC_TYPE_RULES` constant with the match's own `'number'` arm: `integer`, `int`, `numeric`,
`decimal`, `digits`, `digits_between`. The two used to drift — `hasNumericTypeSibling()` recognized
only the first three, so `['digits:1', 'in:1,2,3']` typed `number` from its own rule but still
quoted its `in:` literals as `'1' | '2' | '3'`. One list now backs both checks, so a rule cannot be
numeric to one and not the other.

Coercion itself stays conservative even on a numeric field: `resolveInFromParams()` only rewrites a
param when `$v === (string) ($v + 0)` round-trips losslessly back to identical text, because
`validateIn()` compares `(string) $value` against the literal param string, not a normalized number.
`'2.50'` fails that round-trip (`(string) (2.50 + 0)` is `'2.5'`), so `['decimal:2', 'in:1.50,2.50']`
still emits `'1.50' | '2.50'` — quoted — even though `decimal` is now in `NUMERIC_TYPE_RULES`.
Emitting the unquoted `2.5` would describe a value Laravel's own validator rejects for that field, so
this guard is not a simplification opportunity.

## Trie collapse: dot-paths compose into their ancestor

`normalizeRules()` hands the raw rules to `buildRuleTrie()`, which splits every rule key on `.`
and inserts it into a trie — a `*` segment marks "array of this node", any other segment nests an
object key. A node's own rule data (its resolved TS type, required/nullable/prohibited flags,
JSDoc metadata) lives at the exact path it was declared on; intermediate ancestors created only
to reach a deeper path (e.g. `order` when only `order.id` was declared) have none.

Laravel lets an attribute contain a *literal* dot by escaping it (`'v1\.0'`), so the split is not
a bare `explode('.')`: `buildRuleTrie()` first replaces every `\.` with the `DOT_PLACEHOLDER`
sentinel (`"\x00ltsp-dot\x00"`, a fixed byte sequence no realistic rule key contains — unlike
Laravel's own placeholder, which is randomized per validator instance, this one is a constant),
explodes on the real separators, then restores the placeholder to `.` inside each segment. `'v1\.0'` therefore
becomes one trie node named `v1.0`, not two named `v1` and `0`.

The trie node itself is a small internal class ([`FormRequestRuleTrieNode`](../../src/Analyzers/FormRequest/FormRequestRuleTrieNode.php)),
not a plain nested array — PHPStan (this project runs level 10) rejects a directly self-referencing
`@phpstan-type` array shape as a "circular definition," so a `children` property typed
`array<string, FormRequestRuleTrieNode>` is the type-checkable way to express its unbounded depth.

`composeTrieNode()` then collapses the trie bottom-up. The branches are checked in this order,
and the order is load-bearing:

1. **A node carrying `syntheticArrayKeys`** (from `required_array_keys`/`in_array_keys`/`array:`/`array_keys:`)
   merges pseudo-children for those keys into whatever real children it already has, then re-enters the
   list below — see [synthesized array keys](#synthesized-array-keys-required_array_keys-in_array_keys-array-array_keys).
2. **A node with no children** returns its own leaf as-is — this is the existing flat behavior,
   completely unchanged for fields with no dotted continuation (`title`, `email`, `published`, …).
3. **A node whose keys are *all* explicit numeric indices** (`allKeysAreNumeric()`) composes as a
   list, not an object — `composeIndexedNode()`. See
   [numeric indices](#numeric-indices-compose-as-a-list) below.
4. **A node with a `*` child** composes as an array (`composeArrayNode()`) when `*` is its *only*
   child, or as an intersection (`composeMixedNode()`) when named siblings sit alongside it.
5. **Anything else** — named children only — composes to an inline object type
   (`composeObjectNode()`).

Because the numeric check precedes the wildcard check but demands that *every* key be numeric, a
node mixing `0` with `*` or with a named key is not "all numeric" and falls through to branch 4 or
5, where the numeric key is treated as an ordinary object key.

### Array nodes (`*` alone)

The `*` child's composed type, suffixed `[]` via `arrayWrapType()`. Required, nullable, prohibited,
and JSDoc come from the array node's **own** rule, not the element's — `roles` is required because
`'roles' => ['required', 'array', ...]` says so, not because of anything on `'roles.*'`. The
element's *own* nullability is the exception: it is not discarded, it folds into the element type
before wrapping, so `'limited_choices' => ['nullable', 'array'], 'limited_choices.*' => ['nullable', 'string']`
composes to `limited_choices?: (string | null)[] | null` — the inner `| null` is the element's, the
outer one the array's, appended later by the Blade template from the node's own `isNullable`.

A **prohibited** element short-circuits the wrap entirely: `'empties.*' => ['prohibited']` yields
`never[]`, i.e. "an array that may not contain anything", rather than an array of the element's
nominal type.

`arrayWrapType()` parenthesizes before suffixing whenever `hasTopLevelSeparator()` says so —
`('a' | 'b')[]`, not the ambiguous `'a' | 'b'[]`, which TypeScript parses as `'a' | ('b'[])`. The
same holds for an intersection: a mixed node (see below) nested beneath a wildcard —
`'buckets.*' => ['array'], 'buckets.*.*' => ['string'], 'buckets.*.name' => ['string']` — composes
to `buckets?: ({ name?: string } & Record<string, string>)[]`, not the ambiguous
`{ name?: string } & Record<string, string>[]`, which TypeScript parses as `A & (B[])` rather than
`(A & B)[]`.

`hasTopLevelSeparator()` is depth- and quote-aware: it tracks `{}`, `<>`, `()` and `[]` nesting and
reports a `|` or `&` at depth zero, and it skips over single-quoted string literals whole, so a
bracket, pipe, or ampersand character *inside* a literal (`in:>a,b` → `'>a' | 'b'`) is read as data
rather than as structure. A `|` or `&` nested inside a `{ ... }` shape therefore never triggers the
parens — `[]` on an object shape is unambiguous even when a property inside it is a union or
intersection. Its depth counter floors at zero on an unmatched closing bracket rather than going
negative, so malformed input fails toward a redundant-but-harmless paren rather than a missed one.

### Object nodes (named children)

One part per child, `{$key}{$optional}: {$type}`, joined `'; '` and wrapped `'{ ... }'` — the same
`JsEmitter::validJsObjectKey()` + optional-`?` convention
`InlineArrayHandler::analyzeInlineArray()` already uses for its own inline object shapes (the
`'{ '.implode('; ', $parts).' }'` wrapping itself is shared even more widely, e.g.
`arrayableShapeType()`, though that method doesn't mark individual keys optional).

A **prohibited** child is dropped from the object entirely; it can never legally appear in the
payload. When *every* named child is prohibited, no parts survive and the node composes to
`Record<string, never>` — "an object with no permitted keys" — instead of an empty `{}` or a
permissive `Record<string, unknown>`. (A prohibited *top-level* field is dropped one layer later,
by `form-request.blade.php`, which skips any field whose `isProhibited` is set.)

### Mixed nodes (`*` beside named children)

`'options' => ['array'], 'options.*' => ['string'], 'options.default' => ['string']` is Laravel's
way of describing a map whose values all share a rule and some of whose keys are pinned.
`composeMixedNode()` composes the named children as an object, composes the `*` child as the
element type (folding its nullability in the same way an array node does), and emits the
intersection:

```typescript
options?: { default?: string } & Record<string, string>;
```

An intersection rather than a `"*"` pseudo-key, and rather than a single index signature: TypeScript
rejects an index signature whose named siblings have a different type, and the intersection stays
valid even when the two halves disagree. Everything except `tsType` — required, nullable,
prohibited, JSDoc — is inherited from the object composition, i.e. from the node's own rule.

### Numeric indices compose as a list

`'items.0.name' => ['required', 'string']` describes element 0 of a list. Composing it as an object
would give `{ "0": { name: string } }`, a type no real JSON array is assignable to. When
`allKeysAreNumeric()` holds, `composeIndexedNode()` composes each index's shape, drops any
prohibited one, de-duplicates the results, joins the survivors with `' | '` and array-wraps that:

```php
'items'   => ['array'],
'items.0.name' => ['required', 'string'],

'variants' => ['array'],
'variants.0.name'  => ['required', 'string'],
'variants.1.email' => ['required', 'email'],
```

```typescript
items?: { name: string }[];
/** @format email variants.1.email */
variants?: ({ name: string } | { email: string })[];
```

The de-duplication is what keeps `items.0.name`/`items.1.name` from producing
`({ name: string } | { name: string })[]`, and `hasTopLevelSeparator()` is what puts the parens
around the two-shape `variants` union. If every index is prohibited the node composes to `never[]`.

### Own rule plus children

A node with both an own rule and children (`'products' => ['required', 'array']` *and*
`'products.*.name' => [...]`) uses the children — the composed type is strictly more specific
than the `unknown[]`/`unknown` placeholder the own rule alone would give. The own rule still
supplies required/nullable/prohibited and its own JSDoc.

```php
'products'              => ['required', 'array'],
'products.*.name'       => ['required', 'string'],
'products.*.price'      => ['required', 'decimal:2'],
'order'                 => ['required', 'array'],
'order.id'              => ['required', 'uuid'],
'order.items'           => ['required', 'array'],
'order.items.*.product_id' => ['required', 'integer'],
'order.items.*.quantity'   => ['required', 'integer', 'min:1'],
```

resolves to:

```typescript
products: { name: string; price: number }[];
/** @format uuid order.id */
order: { id: string; items: { product_id: number; quantity: number }[] };
```

Recursion means the depth is unbounded — `order.items.*.quantity` is two `.*`/`.` hops deep, and
composes exactly the same way a single `tags.*` hop always has.

## Every dotted key composes — including the one-level case

Composition is a single code path with no special-casing for depth. A key with no dot never
enters a trie branch deeper than the root, so it round-trips unchanged. A key ending in a bare
`.*` (`roles.*`, `tags.*`) is a one-segment-deep case of the same array-node rule described
above — `roles: string[]` collapses identically to how it did before this composition existed,
the only difference being that the flat `"roles.*"` key is no longer also emitted alongside it
(see below).

## `analyzeField()`: one rule by dotted path

`analyze()` composes the whole request — every top-level trie node, each with its descendants folded
in — and is what `FormRequestTransformer` calls to build the request's `.ts` interface. `analyzeField()`
answers a narrower question a single call site needs: given one dotted path, what does *that* node
compose to? It builds the same trie from the same raw rules, walks it segment by segment, and returns a
`FormRequestRuleNode` whose `fieldPath` echoes the path back.

Because it is the same trie and the same `composeTrieNode()`, the answer for `options.default` is the
type the request's own interface nests under `options` **as the rules describe it**: a sibling `options.*`
wildcard is never consulted, since the walk descends into `children['default']` and the wildcard is
`default`'s sibling, not its child. The node's own descendants still compose in, so
`analyzeField('order')` on a request declaring `order.id` returns the composed object with
`@format uuid order.id` hoisted onto it — `collectChildJsDoc()` runs exactly as `normalizeRules()` runs
it, so the two entry points cannot drift.

The qualifier is load-bearing. Nothing above the trie is shared: a `#[TsCasts(['options' => 'MyOptions'])]`
on the request replaces that whole subtree in the emitted interface, which `FormRequestTransformer` applies
*after* calling `analyze()`, while `analyzeField('options.default')` still composes the rule the override
replaced. That divergence is recorded in [known gaps](../known-gaps.md); this section describes the trie,
not the emitted file.

### When it returns `null`

- **An undeclared segment.** Nothing in the rules reaches that path.
- **A prohibited node anywhere *on* the path.** `composeObjectNode()` drops a prohibited child outright,
  so nothing beneath one appears in the composed type either. `ArrayRulesRequest` declares `order.secret`
  prohibited and `order.secret.token` required; `order` composes to `{ id: string; items: … }` with no
  `secret` key, so `analyzeField('order.secret.token')` must decline rather than describe a key no payload
  can carry. Only ancestors are filtered here — a prohibited *target* is returned with `isProhibited`
  set, leaving the caller to decide, which is what `validatedKeyRule()` already did.
- **An escaped-dot key.** The walk splits on every `.`, unlike `buildRuleTrie()`, which first protects
  `\.` behind `DOT_PLACEHOLDER`, so `'v1\.0'` is unreachable by path. This is the correct answer rather
  than a shortfall: `data_get()` splits the key the same way and cannot reach the attribute either.

A `*` segment is *not* filtered here — the element node it resolves to is a truthful trie answer. It is
the caller's expansion semantics that differ, so `KnownMethodRuleHandler` declines those keys itself (see
[known gaps](../known-gaps.md)).

### Behaviour change: `validated()` on an escaped-dot key

`KnownMethodRuleHandler::validatedKeyRule()` is the only caller today. Before it looked up by path, it
scanned `analyze()`'s top-level nodes for a matching `fieldPath` — and `'v1\.0'` *is* a top-level node,
named `v1.0`. So `$request->validated('v1.0')` used to type as a non-optional `string` while the runtime
call returns `null`, because `data_get('v1.0')` splits on the dot and never finds the attribute. It now
declines and the property types as `unknown`. That is a property moving from a real type to `unknown`,
but the real type was wrong; `unknown` is the honest answer for a call that returns `null`.

## JSDoc hoisting: a nested annotation still reaches the reader

Composing `order.id` into `order` puts its type inside an opaque type string, and an inline object
type has nowhere to hang a `/** @format uuid */` comment. Rather than lose the annotation,
`normalizeRules()` calls `collectChildJsDoc()` on each top-level node's children and appends the
result to that node's own `jsDocMetadata`.

`collectChildJsDoc()` walks the subtree depth-first and suffixes every collected entry with the
**full declared rule key** it came from — the accumulated path from the top-level field down,
wildcard segments included verbatim:

```php
'order.id'                   => ['required', 'uuid'],
'products.*.contact_email'   => ['required', 'email'],
```

```typescript
/** @format uuid order.id */
order: { ... };

/** @format email products.*.contact_email */
products: { ... }[];
```

The path is the disambiguator: several descendants can contribute `@format`, and without the
suffix the reader could not tell which nested key each one describes.

One subtree is skipped: a child whose own rule is prohibited contributes neither its own metadata
nor **any** of its descendants' — `collectChildJsDoc()` `continue`s before recursing. So
`'order.secret' => ['prohibited'], 'order.secret.token' => ['required', 'uuid']` hoists nothing.
That matches what the type says: `composeObjectNode()` already dropped `secret` from `order`'s
shape, so an `@format uuid order.secret.token` comment would document a key the interface does not
have.

## Optionality mapping: reused, not reinvented

A composed child's `?` comes from the same `isRequired($parsedRules) && ! $isSometimes($parsedRules)`
expression the flat path has always used (`buildLeafData()`) — a child rule list containing
`required` (and not `sometimes`) renders without `?`; anything else renders `key?:`. Nullable
children get `| null` appended inline before the part is assembled, mirroring what the Blade
template already does for a top-level field's `isNullable` flag. The old "dotted paths are
never required" override is gone: it existed only because dotted keys used to survive as
pseudo-top-level fields you could never actually supply. Now that they compose into a real
nested property, their own `required` rule is exactly what should decide their optionality.

## Synthesized array keys: `required_array_keys`, `in_array_keys`, `array:`, `array_keys:`

Four rules describe a key set for an otherwise-untyped array, and `resolveSyntheticArrayKeys()`
composes all of them the same way: each declared key becomes a synthesized `unknown`-typed
pseudo-child, so `composeTrieNode()` takes the same object-node branch as a real nested rule set.
The rules differ only in the optionality they imply, per Laravel's own validator
(`Illuminate\Validation\Concerns\ValidatesAttributes`):

| Rule | Validator semantics | Emitted key |
| --- | --- | --- |
| `required_array_keys:a,b` | *all* listed keys must be present (`array_all`) | required (`a: T`) |
| `in_array_keys:a,b` | *at least one* listed key must be present (`array_any`) | optional (`a?: T`) |
| `array:a,b` | restricts which keys are *allowed*; presence unenforced | optional (`a?: T`) |
| `array_keys:a,b` | restricts which keys are allowed; requires ≥1 listed key; presence of any given key unenforced | optional (`a?: T`) |

```php
'permissions' => ['required', 'array', 'required_array_keys:read,write'],
'config' => ['required', 'array', 'in_array_keys:timezone'],
'preferences' => ['nullable', 'array:theme,locale'],
'attributes_map' => ['required', 'array_keys:color,size'],
```

resolves to `permissions: { read: unknown; write: unknown }`, `config: { timezone?: unknown }`,
`preferences?: { theme?: unknown; locale?: unknown } | null`, and
`attributes_map: { color?: unknown; size?: unknown }` — the keys are known even though their
individual types aren't, which is still strictly more useful than `unknown[]`.

**Merge, not replace.** `syntheticArrayKeyChildren()`'s output is merged into the node's real
children with `+=`, so a field with both a synthesized key set and a real declared child (a `*` or
named continuation) keeps both — the real child wins the name collision, since PHP's `+` keeps the
left (real) operand's entry on a duplicate key:

```php
'shipping' => ['required', 'array', 'required_array_keys:method,address'],
'shipping.method' => ['nullable', 'in:standard,express'],
```

resolves to `shipping: { method?: 'standard' | 'express' | null; address: unknown }` — `method` keeps
its own declared type and optionality even though `required_array_keys` also named it; `address` has
no real child, so it stays the synthesized `unknown`, required because `required_array_keys` said so.
If a key is named by more than one synthesizing rule (e.g. both `required_array_keys` and `array:`
list the same key), the required interpretation wins — `resolveSyntheticArrayKeys()` merges its
required and optional key sets with `+`, so a key required by one rule is never demoted by another.

## Flat quoted keys are dropped once they compose

A key that folds into a parent (`products.*.name`, `order.id`, `tags.*`, …) no longer also
appears as its own flat, quoted `FormRequestRuleNode`. Keeping both was redundant and, for
anything beyond one level, actively misleading (`"order.id"?: string;` implied a caller could
supply a literal `order.id` key on the request payload, which Laravel's dot-notation validation
never means).

What the fold carries into the parent is the child's **type, optionality, nullability** and — via
[JSDoc hoisting](#jsdoc-hoisting-a-nested-annotation-still-reaches-the-reader) — its **metadata
annotations, path-qualified**. What it deliberately discards is the child's identity as an
addressable field: after composition there is exactly one node per top-level key, and everything
below it is a substring of that node's type string. That is what makes the next section a real
constraint rather than a bug list.

Note that "top-level key" is not the same as "dot-free key". A key whose dot was escaped
(`'v1\.0'`) is a single trie node and therefore a single top-level field, emitted quoted because
`validJsObjectKey()` quotes anything that isn't a bare identifier:

```typescript
"v1.0": string;
```

## What composition cannot express

Three limits, all of them deliberate. Only the first is a true loss; the second is a placement
constraint, and the third is not really about composition at all:

- **`#[TsCasts]` keyed on a dot-notation path.** `FormRequestTransformer::applyTsCastsOverrides()`
  matches a node's `fieldPath`, and after composition the only field paths that exist are top-level
  keys. `#[TsCasts(['order.id' => 'Uuid'])]` or `['tags.*' => ...]` therefore matches nothing and is
  silently ignored — the override map is applied to the already-analyzed field list, so an unmatched
  key adds no field either. The composed parent is a single opaque type string, so there is no
  per-key override point inside it. Override the parent (`'order'`) to replace the whole shape, or
  type the rule precisely enough that no override is needed. The one dotted key that *does* match is
  an escaped one: `'v1\.0'` produces the field path `v1.0`, so `#[TsCasts(['v1.0' => ...])]` hits it.
- **Per-element JSDoc placement.** The annotation itself survives — it is hoisted onto the composed
  parent and path-qualified (`@format uuid order.id`) — but it cannot be *placed* on the nested
  property it describes, because an inline object type has nowhere to hang a comment. A parent with
  several annotated descendants therefore accumulates one comment block listing all of them. The
  exception is a prohibited child: its subtree's annotations are dropped outright, since the keys
  they describe are not in the emitted type at all.
- **Rules the analyzer cannot obtain.** Unchanged from the flat behavior, and not really a property
  of composition: `resolveRules()` actually *calls* `rules()` against a fake request and a stub user,
  so keys computed at runtime are captured like any other. What fails is a `rules()` that **throws**
  in that context — typically one reading real request or session state — which sets `isDynamic` and
  degrades the whole class to `Record<string, unknown>`. Nothing composes, because there is nothing
  to compose.

# FormRequestRulesAnalyzer

[`FormRequestRulesAnalyzer`](../../src/Analyzers/FormRequest/FormRequestRulesAnalyzer.php) turns a form request's
`rules()` array into the fields [`FormRequestTransformer`](../../src/Transformers/FormRequestTransformer.php) renders.
It builds a trie from the rule keys and folds every dotted or wildcard key into its nearest undotted ancestor, so the
interface has exactly one property per top-level key. Open `analyze()` for the whole request and `analyzeField()` for
one dotted path. Usage is on the tolki [Form Requests](https://tolki.abe.dev/ts/form-requests.html) page.

## Where things live

These classes and files produce and consume the analysis:

- [`FormRequestRulesAnalyzer`](../../src/Analyzers/FormRequest/FormRequestRulesAnalyzer.php): runs `rules()`, maps
  each rule list to a type, and composes the trie.
- [`FormRequestRuleTrieNode`](../../src/Analyzers/FormRequest/FormRequestRuleTrieNode.php): one trie node.
- [`FormRequestRuleNode`](../../src/Analyzers/FormRequest/FormRequestRuleNode.php): one composed top-level field.
- [`FormRequestTransformer`](../../src/Transformers/FormRequestTransformer.php): applies `#[TsCasts]` to the analyzed
  top-level fields.
- [`form-request.blade.php`](../../resources/views/form-request.blade.php): skips prohibited fields, appends a field's
  `| null`, and renders a dynamic request as `Record<string, unknown>`.
- [`KnownMethodRuleHandler`](../../src/Ast/Handlers/KnownMethodRuleHandler.php): types `$request->validated('key')`
  through `analyzeField()`.

## `rules()` runs for real

`resolveRules()` calls `rules()` on the form request built from a fake `POST` request. When no user is authenticated, it
sets a stub user whose undefined methods return `false`, and it restores the previous auth state afterwards. Keys that
`rules()` computes at runtime are captured like any other. A `rules()` that throws, typically one reading request
or session state, sets `isDynamic`, and the whole request publishes as `Record<string, unknown>`.

## Rule priority is by pass, not by declaration order

`resolveTsType()` runs fixed passes: `File` (which also catches `ImageFile`), `AnyOf`, `Enum`, then `In` in object and
`in:a,b` string form, then the other rule objects. Only then does a loop match the remaining string rules in the order
they were written. Because `In` wins by pass, an earlier `string` or `integer` rule never shadows the literal union:
`['string', 'in:a,b']` and `['in:a,b', 'string']` both publish `'a' | 'b'`.

## One constant decides `number` and unquoted `in:` literals

`ValidationRuleParser::parse()` returns string-form `in:` params as strings, so `resolveInFromParams()` emits an
unquoted number only when a sibling rule is in `NUMERIC_TYPE_RULES`. The same constant backs the `number` arm of the
declaration-ordered match, so a rule cannot be numeric to one check and not to the other.

Even then, a param loses its quotes only when `$v === (string) ($v + 0)`. Laravel's `validateIn()` compares
`(string) $value` strictly against the literal param, so an unquoted `2.5` or `7` would describe a value the validator
rejects. `'2.50'` and `'007'` therefore stay quoted. Keep this guard.

## Dotted keys compose through a trie

`buildRuleTrie()` splits each key on `.`. A `*` segment means "array of this node", and any other segment nests an
object key. A node's own rule sits at the exact path it was declared on, and an ancestor created only to reach a
deeper path has none. An escaped dot (`'v1\.0'`) is part of the attribute name, so `buildRuleTrie()` hides `\.`
behind `DOT_PLACEHOLDER` while it splits. `'v1\.0'` stays one top-level field, named `v1.0` and emitted quoted.

`composeTrieNode()` merges any synthesized key-list children first, then picks the node's shape in this order: no
children, all-numeric keys, a `*` child, named keys. The numeric check needs every key to be numeric. A node mixing `0`
with `*` or with a named key therefore composes as a wildcard or object node, with `0` as an ordinary key.

Each shape composes by its own rule:

- **Own rule and children**: the children's composed type wins over the own rule's `unknown[]` or `unknown`. The own
  rule still decides required, nullable, prohibited and JSDoc.
- **Array** (`*` alone): required, nullable, prohibited and JSDoc come from the array's own rule, not the element's.
  The element's nullability folds into the element type, so `(string | null)[] | null` keeps both. A prohibited
  element makes the node `never[]`.
- **Object** (named keys): a prohibited child is dropped, since it can never appear in the payload. When every child
  is prohibited, the node is `Record<string, never>`, not `{}`. A prohibited top-level field is dropped later, by the
  Blade template.
- **Mixed** (`*` beside named keys): `{ ... } & Record<string, T>`. TypeScript rejects an index signature whose named
  siblings have a different type, and the intersection stays valid when the two halves differ.
- **List** (every key numeric): each index composes, prohibited ones drop, duplicates collapse, and the rest union
  inside `[]`, or `never[]` when none is left. An object keyed `"0"` is a type no JSON array is assignable to.

`arrayWrapType()` parenthesizes a union or an intersection before appending `[]`, because `'a' | 'b'[]` means
`'a' | ('b'[])`. `hasTopLevelSeparator()` tracks bracket depth and skips quoted literals, so a `|` inside a shape or
inside `'>a'` adds no parentheses. Its depth floors at zero, so malformed input gets a redundant paren, never a missing
one.

## A composed key keeps its own presence rules

A nested key is required when its own rules say `required` and not `sometimes`, the same `buildLeafData()` test a
top-level field uses, and a nullable one gets `| null` inside the shape.

Four rules list an array's keys without typing them: `required_array_keys`, `in_array_keys`, `array:` and
`array_keys:`. `resolveSyntheticArrayKeys()` makes each listed key an `unknown` pseudo-child, required only under
`required_array_keys`, the one rule whose validator requires every listed key. A key that `required_array_keys` lists
stays required when another rule lists it too. A real declared child keeps its own type and optionality, because the
synthesized children merge in with `+=`.

## Nested JSDoc is hoisted to the top-level field

An inline object type has nowhere to hang a comment, so `normalizeRules()` appends `collectChildJsDoc()`'s entries to
the top-level node. Each entry ends with the full declared key, wildcards included (`@format uuid order.id`), because
several descendants can add the same tag. A prohibited child adds nothing for itself or its descendants, since its key
is not in the type.

## `analyzeField()` reads the same trie

`analyzeField()` builds the same trie, walks it one segment at a time, and hoists JSDoc the same way, so
`options.default` composes exactly as it nests in the interface, and a sibling `options.*` is never consulted. It
returns null for an undeclared segment, for a path through a prohibited ancestor, and for an escaped-dot key. That
last case is correct, because `validated()` delegates to `data_get()`, which splits on that dot too, so
`$request->validated('v1.0')` returns null at runtime and the prop publishes `unknown`. A prohibited target comes back
with `isProhibited` set, and a `*` segment is not filtered at all. `KnownMethodRuleHandler::validatedKeyRule()`
declines both.

The two entry points share the trie, not the transformer. `FormRequestTransformer::applyTsCastsOverrides()` runs after
`analyze()` and matches top-level field paths only. A `#[TsCasts]` key on an ancestor therefore replaces the subtree in
the interface, while `analyzeField()` still composes the rule. A dotted `#[TsCasts]` key matches nothing, except an
escaped one such as `v1.0`.

## Related

These pages hold the neighboring rules:

- The known gap where `validated()` declines a wildcard key and ignores a dotted `#[TsCasts]` key:
  [known-gaps.md](../known-gaps.md#request-validatedkey-declines-a-wildcard-key-and-ignores-a-dotted-tscasts-key)
- [Type inference gates](../testing/type-inference-gates.md), which check the generated interfaces
- The tolki [Form Requests](https://tolki.abe.dev/ts/form-requests.html) page, for the rule-to-type table

# Known gaps

**What this file is for.** It carries the accepted limits that someone working from a fresh clone cannot
discover any other way — you have the code and the test suite, but not the working notes of whoever
deferred the work. Two kinds of entry earn a place here:

- **It changes what you get out of the package.** A shape the generator types as `unknown`, `null`, or an
  empty interface, and a user would otherwise file as a bug.
- **It means a green signal is narrower than it looks.** A gate that passes without checking what you
  would assume it checks.

Nothing here is a regression. These are limits that were understood and accepted.

**What does not belong here.** Internal refactor debt, test-suite quality notes, release chores, and
anything whose real audience is "whoever picks that work back up" — those live with the plan that deferred
them, under its Follow-Ups Ledger, which is where that work is actually re-read from. Filing them here
buries them. If you decline something a reviewer raised, ask which of the two bullets above it satisfies;
if neither, it does not go in this file.

## Types the generator will not give you

### `config()` on an absent key with no default types as null

`config('key', $default)` types from the default expression only when the key is absent; a key set to
`null` types as `null`, and named arguments are honoured. The one residual: `config('unset.key')` with
no default types as `null`, which is the live value on the generating machine. All reads follow that
machine's config, so a `.env` that differs from production changes the emitted type.

### On Laravel 12, `#[Collects]` cannot be resolved — use the `$collects` property

`#[Collects]` is a Laravel 13 attribute. On Laravel 12 the class does not exist, so a `ResourceCollection`
that names its collected resource *only* that way resolves to nothing, and the generated type degrades from
`export type PostFlatCollection = PostResource[];` to an `export interface PostFlatCollection` with no
members. That is worse than it looks: an empty interface accepts `42` and `"str"` under `tsc --strict` —
it rejects only `null` and `undefined` — so the type reads as specific while checking almost nothing.

Laravel's own `collects()` cannot resolve the attribute on 12 either, so the package mirrors the framework
rather than guessing. **The workaround is fully supported:** `public $collects = PostResource::class;` works
on both versions, as does the `FooCollection` → `FooResource` naming convention. The guard is in
`src/Analyzers/Concerns/InspectsAstNodes.php`; see
[docs/laravel-version-guards.md](./laravel-version-guards.md) for how the version floor was established and
which tests are skipped below it.

### `EnumResource::collection()` inside a mixed ternary, nested one level down

Task 28 fixed `ResourceTransformer::rewriteEnumResourceTypes()`'s top-level `$isMixed` branch, which
assumed the wrapped arm of a mixed EnumResource/direct-access ternary was always scalar.
`InlineArrayHandler::expandMixedEnumType()` (`src/Ast/Handlers/InlineArrayHandler.php`) has the same
defect for the identical ternary shape nested inside an inline array literal, and there it is worse:
when both arms independently render the same array-shaped type string — an
`EnumResource::collection()` wrap and a direct read of an already-list accessor, both `X[]` — the
merge that builds the property's type collapses them to one member before `expandMixedEnumType()`
ever runs, so it substitutes that single member and the direct arm's own presence in the union is
lost outright, not just under-suffixed. Verified against a throwaway fixture during Task 28's fix
round; not reproduced as a committed test or golden-tree property, so nothing here pins it yet.

### A broadcast event's own uninitialized typed property still types as required

Task 29 made an uninitialized typed public property optional wherever a class's shape is inlined —
`LaravelTsPublish::publicPropertyShapeType()` (`src/LaravelTsPublish.php`), reached from `toTsType()`'s
step 5c and from `arrayableShapeType()`'s no-docblock fallback. A broadcast event's own top-level
property list never reaches that method: `AstEngine::analyzePublicProperties()` reflects the event
class directly and hardcodes `'optional' => false` for every property, regardless of whether reflection
says the property was ever assigned. An event with `public Carbon $occurredAt;` and no default therefore
still emits `occurredAt: string;` in its generated `.ts` file — required — even though `json_encode()`
would omit the key exactly as Task 29's fix accounts for everywhere else. Verified against a throwaway
event fixture during Task 29's fix round, then removed once it stopped pinning anything the golden tree
would show; not reproduced as a committed test or golden-tree property, so nothing here pins it yet.
Fixing it means threading the same `hasDefaultValue()`/`isPromoted()` check into
`analyzePublicProperties()`, which is a change to every existing broadcast event's blast radius, not a
one-fixture addition — worth doing as its own task. `analyzePublicProperties()`'s own docblock already
states it never marks a property optional — nullability is `| null`, optionality is a `#[TsCasts]`
concern — so a future fix has to reconcile that deliberate boundary rather than be surprised by it. A
related case is inherent rather than fixable: a public non-promoted `readonly` property that a
hand-written constructor always assigns still renders `?:`, because a `readonly` property cannot carry
a declaration default for static reflection to read — absent from the corpus today.

### `#[TsCasts]` and the top-level spread flatten disagree by scope, in three separate ways

Task 32 flattens a top-level `...SomeResource::make(...)->resolve()`, `...$model->toArray()`, or
`...$collection->toArray()` spread into the host resource's own properties
(`ResourceAstAnalyzer::analyzeSpreadArm()` and its three arm builders, `src/Analyzers/ResourceAstAnalyzer.php`).
Only one of the three places `#[TsCasts]` can apply is wired up for it, and a fourth interaction —
unrelated to spreading — makes the gap sharper than "missing", not just narrower than it looks.

- **A spread resource's own `#[TsCasts]` is skipped.** `analyzeResourceSpreadArm()` calls
  `AstEngine::analyzeMethod($resourceFqcn, 'toArray')`, which runs `ResourceAstAnalyzer::analyze()`
  directly — never `ResourceTransformer::parseResourceTsCastsOverrides()`/`applyOverrides()`, which is
  where a resource's own `#[TsCasts]` attribute is read and applied. A `#[TsCasts]` override declared on
  `PostResource` itself would apply when `PostResource.ts` is generated standalone, and silently not
  apply to the identical property once `PostResource` is spread into another resource.
- **The spread resource's own backing model's `#[TsCasts]` is skipped for the same reason** — it is
  `ResourceTransformer::parseModelTsCastsOverrides()` that reads it, and the resource arm never reaches
  that transformer either.
- **The model and collection arms *do* apply the spread target's own `#[TsCasts]`** (`analyzeModelSpreadArm()`,
  same file) — that half is fixed, and it is why `User::options`, cast to a plain array at the DB/Eloquent
  level, still flattens as `Record<string, unknown> | null` and not `unknown[] | null`: `Address::$appends`'s
  `full_address` gets the same treatment.
- **The host resource's own `#[TsCasts]` is applied by property name, blind to where the property actually
  came from.** `ResourceTransformer::applyOverrides()` walks `$this->modelTsCastsOverrides` (from the *host*
  resource's own backing model) and rewrites `$this->properties[$property]` by name alone — it has no
  notion that a flattened property named `created_at` or `settings` came from a *different* model than
  the host's own. A host resource's `#[TsCasts]` entry for `created_at` — a common override, since raw
  `datetime` casts rarely need one but developers add them anyway for consistency — silently overwrites
  a same-named column flattened from an entirely unrelated spread arm, in whichever direction the
  override happens to point.

None of this is a regression to `unknown`: every case above still emits a real, plausible-looking type —
just possibly the wrong one, or missing a refinement its own standalone file carries. Fixing the first two
cleanly means giving the resource arm a path to the spread resource's and its model's own overrides without
re-running the whole `ResourceTransformer` pipeline recursively; fixing the fourth means `applyOverrides()`
knowing which flattened properties are actually the host's own versus foreign, which the current
`ResourceAnalysis::properties` list (name/type/optional/description only) does not carry. Both are scope
changes to existing, working code paths, not one-fixture additions — worth doing as their own task.

### `$request->validated('key')` only types a literal, top-level key

A literal, top-level key on a bound `FormRequest` types from `rules()` — `$request->validated('title')`
reads `StorePostRequest::rules()` through `FormRequestRulesAnalyzer`, the same analyzer the form request's
own generated interface uses (`requestMethodRule()` in `src/Ast/Handlers/KnownMethodRuleHandler.php`). Two
shapes short of that decline or diverge silently rather than fixing:

- **A dotted key declines even when the rule defines it.** `FormRequestRulesAnalyzer::analyze()` returns
  only the top-level trie nodes: a nested rule like `'options.default' => ['string']` composes into
  `options`'s own object type, but never surfaces as its own `fieldPath` entry in the returned list.
  `$request->validated('options.default')` on `Workbench\App\Http\Requests\NestedEdgeCasesRequest` — whose
  `rules()` declares exactly that key — resolves to `null` and the property types as `unknown`, though the
  rule is both defined and resolvable. Verified directly: the handler returns `null` for that call.
  Nested validation rules (`'address.street' => 'required|string'`) are routine Laravel, so a user is
  likely to hit this immediately after learning `validated()` is typed at all.
- **A `#[TsCasts]` override on the form request is not honoured.** `StorePostRequest::rating` carries a
  `#[TsCasts]` override to `number | bigint`; `$request->validated('rating')` types as the raw-rule
  `number | null` instead — verified directly: the handler returns `['type' => 'number | null', 'optional'
  => true]`, not the override. The override is applied by `FormRequestTransformer::applyTsCastsOverrides()`
  when the form request's own `.ts` interface is generated, a call site `KnownMethodRuleHandler` never
  reaches. Same shape as the entry above: two generated descriptions of the same field, disagreeing.

Fixing the first means flattening `analyze()`'s trie output (or walking it by dotted path) instead of
scanning only its top-level nodes; fixing the second means either routing through
`FormRequestTransformer`'s override application or duplicating its `#[TsCasts]` parsing at this call site.
Both are scope changes beyond the single literal top-level key this call site was built for.

## Deliberate non-goals

Absent on purpose. Do not "fix" these without raising it first.

- **Non-Inertia and JSON responses are never typed.** Only `Inertia::render()` page props and the
  shared-data middleware are analyzed.
- **No `ts-publish.analyzer.handlers` config key, and no supported extension of the AST engine.** The
  only user-facing surface of the engine is `AstEngine`. Every handler, concern, resolver and value
  object under `src/Ast/` is internal and changes without notice as inference grows; nothing there is a
  compatibility promise, and code that extends it is on its own.
- **Form requests stay runtime.** They are resolved by instantiating and calling `rules()`, on purpose.

## Green signals that are narrower than they look

### Handler ordering is pinned pairwise, corpus-bounded

Nine of the twenty-four handlers in the resource profile claim `MethodCall`
(`src/Ast/ResourceExpressionHandlers.php`), so for a `$this->foo()` expression the dispatcher's registration
order is what decides which one answers. Every one of the 36 unordered pairs among those nine is now run
in both orders by `tests/Unit/Ast/MethodCallOrderingMatrixTest.php`: seven pairs disagree and are held in
the direction `handlers()` lists them (its `METHOD_CALL_PINNED` map, and the `MethodCall` row of the ordering table in
[docs/components/ast-engine.md](./components/ast-engine.md#the-honest-ordering-inventory)); the other 29
are proven inert against that same corpus.

The residual limit is the corpus, not the method: an expression shape the matrix never constructs cannot
be proven to disagree there, however plausible it looks by inspection — a new shape that turns an inert
pair into a disagreeing one fails the matrix, which is the signal to pin it. One of the seven pins has a
narrower practical consequence than "seven pins" alone suggests: `RelationCollectionChainHandler` wins
over `KnownMethodRuleHandler` for `$this->can(...)`/`cannot(...)`/`canAny(...)`. The two orders diverge
whenever the resource's model — resolved or not — does not declare `can()`: `RelationCollectionChainHandler`'s
generic `$this->method()` fallback gates its model check on `method_exists($scope->modelClass,
$methodName)`, which fails identically whether `scope->modelClass` is `null` or a real, resolved class
that simply has no `can()` (this matrix's own `CommentResource`/`Comment` corpus row is exactly that
case: the model resolves fine, but `Comment` declares no `can()`, so `RelationCollectionChainHandler`
floors at `unknown` while `KnownMethodRuleHandler`'s unconditional rule still answers `boolean`). The two
orders already agree in the mainstream case, though: whenever the model resolves to something
Authorizable (e.g. `UserResource`/`User`), `RelationCollectionChainHandler`'s fallback reaches
`Authorizable::can(): bool` through that same `method_exists()` check and lands on `boolean` too. A
resource over a model with no `can()` at all is not shipping code regardless of which order wins:
`$this->can(...)` there would throw `BadMethodCallException` at runtime (neither `Comment` nor
`JsonResource` declares `can()`, and `JsonResource::__call()` forwards to a receiver that doesn't have
it either) — that's why the practical impact is small, not why the divergence condition is narrow. The
matrix pins the order `handlers()` actually uses; it does not change it, since reordering `handlers()`
is outside this pin's scope. Read the pin count the same way as before: "the divergences someone has
actually gone and found", not "the only divergences that exist" — now bounded by the matrix's corpus
rather than by nothing at all.

### The publish-speed gate is one-sided

`.github/scripts/publish-bench.sh` fails only when head is slower than the merge-base by more than
`MAX_RATIO=1.25`. A large speedup becomes headroom the next branch can spend, so after merging one,
fast-forward `main` so the fast side becomes the base. Both arms install from the same `composer.lock`,
and the run prints each arm's framework version.

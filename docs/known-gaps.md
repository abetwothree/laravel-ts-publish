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

### `$request->validated('key')` is never typed

The Inertia page path types `$request->url()`, `->integer()` and friends by reflecting the method off
`Illuminate\Http\Request` — `requestMethodRule()` in `src/Ast/Handlers/KnownMethodRuleHandler.php`
(`->user()` is the one name answered ahead of reflection, from the configured auth model). `validated()`
is declared on `Illuminate\Foundation\Http\FormRequest`, and the scope records only that a variable holds
*a* `Request`, never which subclass, so reflecting against the base class finds no such method and the
rule declines. `Inertia::render('X', ['title' => $request->validated('title')])` — a headline user shape
— therefore ships `title: unknown`. It is in the golden tree today:
`InertiaFormRequestController::store()` in
`workbench/app/Http/Controllers/InertiaFormRequestController.php` emits
`export type StorePageProps = Inertia.SharedData & { title: unknown };` at
`workbench/resources/js/types/data/default-example/app/http/controllers/inertia-form-request-controller.ts:15`.

Deferred, not overlooked — but only one of the two reasons is a real obstacle. The scope tracks *that* a
variable is a `Request`, not *which* `FormRequest` subclass it is; that is a data-shape widening with a
known blast radius, not a wall. The objection that actually holds is the second: resolving the rules means
instantiating the form request and calling `rules()` during type resolution, which runs application code
inside the analyzer. Worth doing as its own change with that trade-off argued explicitly.

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
property list never reaches that method: `AstEngine::analyzePublicProperties()`
(`src/Ast/AstEngine.php:86-125`) reflects the event class directly and hardcodes `'optional' => false`
for every property (line 105), regardless of whether reflection says the property was ever assigned.
An event with `public Carbon $occurredAt;` and no default therefore still emits `occurredAt: string;`
in its generated `.ts` file — required — even though `json_encode()` would omit the key exactly as
Task 29's fix accounts for everywhere else. Verified against a throwaway event fixture during Task 29's
fix round, then removed once it stopped pinning anything the golden tree would show; not reproduced as
a committed test or golden-tree property, so nothing here pins it yet. Fixing it means threading the
same `hasDefaultValue()`/`isPromoted()` check into `analyzePublicProperties()`, which is a change to
every existing broadcast event's blast radius, not a one-fixture addition — worth doing as its own task.

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

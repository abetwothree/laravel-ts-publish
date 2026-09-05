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
`Illuminate\Http\Request` — `requestMethodRule()` in `src/Ast/Handlers/KnownMethodRuleHandler.php:79`
(`->user()` is the one name answered ahead of reflection, from the configured auth model). `validated()`
is declared on `Illuminate\Foundation\Http\FormRequest`, and the scope records only that a variable holds
*a* `Request`, never which subclass, so reflecting against the base class finds no such method and the
rule declines. `Inertia::render('X', ['title' => $request->validated('title')])` — a headline user shape
— therefore ships `title: unknown`. It is in the golden tree today:
`workbench/app/Http/Controllers/InertiaFormRequestController.php:35` emits
`export type StorePageProps = Inertia.SharedData & { title: unknown };` at
`workbench/resources/js/types/data/default-example/app/http/controllers/inertia-form-request-controller.ts:15`.

Deferred, not overlooked — but only one of the two reasons is a real obstacle. The scope tracks *that* a
variable is a `Request`, not *which* `FormRequest` subclass it is; that is a data-shape widening with a
known blast radius, not a wall. The objection that actually holds is the second: resolving the rules means
instantiating the form request and calling `rules()` during type resolution, which runs application code
inside the analyzer. Worth doing as its own change with that trade-off argued explicitly.

### `config()` calls have three residual cases

`config('key', $default)` types from the default expression when the key is unset. Still imperfect:
a single-argument `config('unset.key')` types as `null`; only positional arguments are understood, so
`config(default: 'x', key: 'k')` reads the wrong one; and a key explicitly set to `null` with a default
types as the default, where Laravel would hand you `null`.

All three are read from the config as it stands when `ts:publish` runs. If the machine generating types has
a different `.env` from production, the emitted type follows the generating machine.

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

### `laravel-ts-global.ts` drops the `extends` clause it imports for

On the global flavour, a type that should compose via `#[TsExtends]` gets the *import* but not the
`extends` clause — `BroadcastableEvent`, `FormRequestBase` and `HasValidationMeta` are imported while
`ServerCreated`, `StringRulesRequest` and `NumberRulesRequest` are emitted with no base. The per-file
flavour emits both halves correctly.

So on the global flavour those interfaces are silently missing their inherited members, and the only trace
is an unused import. If you are on the global flavour and a base member is missing, this is why; the
per-file flavour is correct today. The emitter is `resources/views/globals.blade.php`.

### `EnumResource::collection()` inside a mixed ternary arm

`ResourceTransformer` assumes the wrapping arm of a mixed enum ternary is never
`EnumResource::collection()`, which is not true in general — the array suffix can come out wrong. The
assumption is written at the branch it governs, `src/Transformers/ResourceTransformer.php:471`.

### Inertia shared data does not rewrite `EnumResource` types for Tolki

An `EnumResource::make(...)` returned from `HandleInertiaRequests::share()` is analyzed as its bare enum
type, but with Tolki enabled the shared-data analyzer neither rewrites it to `AsEnum<typeof Enum>` nor
emits the enum's value import. This predates the `typeImports` consolidation: the removed
`importStatements` channel was generated only from `#[TsCasts]` and contained only `import type` lines.

Use an import-aware `#[TsCasts]` override for that shared property. Supporting the serialized enum shape
requires the same type-rewrite and separate value-import pipeline used by resource generation; moving the
value import into `typeImports` would be incorrect.

## Deliberate non-goals

Absent on purpose. Do not "fix" these without raising it first.

- **Non-Inertia and JSON responses are never typed.** Only `Inertia::render()` page props and the
  shared-data middleware are analyzed.
- **No `ts-publish.analyzer.handlers` config key.** You cannot append your own `ExpressionHandler`. Every
  extension point is a compatibility promise; worth adding only if someone asks.
- **Form requests stay runtime.** They are resolved by instantiating and calling `rules()`, on purpose.

## Green signals that are narrower than they look

### Handler ordering is pinned by example, not by the suite

Nine of the twenty-four handlers in the resource profile claim `MethodCall`
(`src/Ast/ResourceExpressionHandlers.php`), so for a `$this->foo()` expression the dispatcher's registration
order is what decides which one answers. Three of those ordered pairs have a dedicated ordering pin in
`tests/Unit/Ast/ResourceExpressionHandlersTest.php`. Every other pair among the nine is held only by
whichever end-to-end fixture happens to traverse it.

Each of the three pins exists because a mutation found a reordering the rest of the suite did not catch —
two crash-level, one a silent type divergence. Nobody has traced the remaining pairs the same way, so a
green `composer test` is not evidence that reordering `handlers()` is safe. The full per-node-class
inventory — which pairs are pinned, which are proven inert, and which are neither — is the ordering table
in [docs/components/ast-engine.md](./components/ast-engine.md#the-honest-ordering-inventory). Read the pin
count as "the divergences someone has gone and found", not "the only divergences that exist".

### The token gate type-checks one of the four generated trees

`tsconfig.json`'s `include` covers `data/default-example/**` and `tests/types/**` only, so
`.github/scripts/unimportable-token-gate.sh` never compiles `data/testing`, `data/full-template-example` or
`data/split-template-example`. A bad token — or a parse error, the fail-open shape the gate was hardened
against — appearing in only one of those three is invisible to it. Low likelihood, because all four trees
come from one pipeline over one fixture set, but do not read a green gate as covering all four.

### `skipLibCheck` hides every generated `.d.ts` from that same gate

`tsconfig.json:43` sets `skipLibCheck: true`, so `tsc` never checks the body of a declaration file. The
generated tree contains `.d.ts` files and lists them in `include` on purpose, so their imports are read and
then checked against nothing — straight through the zero-tolerance relative-specifier sub-gate the script's
header calls out as having no legitimate non-zero cause.

Confirmed by mutation, not inferred: pointing a relative import in a generated `.d.ts` at a nonexistent
module produces no diagnostic at all, and the gate still passes. Turning the flag off is not a one-line
fix — it also starts checking `node_modules`, which surfaces a failure this package does not own.

### The publish-speed gate is one-sided and its two arms are not pinned

`.github/scripts/publish-bench.sh` runs and passes in CI. Two things it does not do.

**It is a one-sided guard.** It fails only when head is **slower** than base by more than `MAX_RATIO=1.25`.
Nothing ratchets: whenever a branch lands a large speedup, that whole win becomes headroom the next branch
can spend without tripping the gate. Read a PASS as "no blowup", never as "the speed held".

**Its two arms are not pinned.** `composer.lock` is gitignored, so the base and head worktrees each run an
independent `composer install` and re-resolve from scratch. They have agreed on the same framework version
in every run so far, but nothing enforces it — a release landing between the two installs would put
different vendor code under the two arms, and the ratio would measure that instead of your change.

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

### Two same-named enums in one metadata companion collide instead of aliasing

Model metadata imports the enums body inference resolves, so a value the AST reads as an enum contributes
an `import type` line of its own. Those inferred imports are pruned by property name, except when the
engine has no property name to prune by: a value two direct enums can produce merges through
`embeddedEnumFqcns`, and `DispatchesFqcnResults` keys those by FQCN
(`src/Ast/Concerns/DispatchesFqcnResults.php:64`). `ModelMetadataAnalyzer::inferredTypeImports()` spares
FQCN-keyed entries deliberately — pruning them by key would drop the union's own imports — which leaves
the still-spelled filter as their only owner, and that filter matches the *rendered* TypeScript name, not
the FQCN. Two enums with the same basename in different namespaces both render `StatusType`, so it cannot
tell a stale channel from a live one.

A provider whose `@return array{...}` retypes a key holding `App\Status | Crm\Status`, alongside a live key
typed `Crm\Status`, therefore emits `StatusType` from both paths and trips the collision guard in
`ModelMetadataTransformer::resolveImports()`:

```
Model metadata for model [App\Models\User] imports [StatusType] from both [../../crm/enums]
and [../enums]; declare one of them with an import-aware #[TsCasts] alias.
```

This is a property of the inferred-import channel, not of the union case alone: two *live* keys typed
`App\Status` and `Crm\Status` fail the same way. The guard's own advice does not resolve it — declaring one
of the two keys as `['type' => 'StatusType', 'import' => '../enums']` still collides, because
`TsCastsImportResolver` aliases only when two *cast* entries share a name and cannot see the inferred
import at all. What works is giving one side a different local name that its path really exports:
`['type' => 'AppStatusType', 'import' => '@/types/app-status']`, with that module re-exporting
`export type { StatusType as AppStatusType }`. Writing the alias inline as `'Status as AppStatusType'` is
not a substitute — it lands verbatim in the property type and emits invalid TypeScript.

On the docblock-displaced union there is no handle at all: the channel is keyed by FQCN, so moving the key
to `#[TsCasts]` does not prune it either. Drop that key to a single enum, which restores a property name
for the prune to match.

Fixing it means carrying the FQCN alongside the rendered name through the prune so the filter can compare
identities rather than basenames, and then routing inferred imports through the same alias resolver the
cast imports use. That is a channel change, not a patch at the filter.

### An empty `[]` under an imported type alias still ships as `[]`

Model metadata coerces an empty PHP array to `{}` wherever the property's resolved TypeScript type is
object-like, because PHP cannot tell an empty map from an empty list and `[]` does not satisfy `Record<>`
or an object literal. The decision is made by `TsTypeShape::isObjectLike()` reading the type *string*
(`ModelMetadataTransformer::coerceEmptyArray()`, `src/Transformers/ModelMetadataTransformer.php:321`), and
a bare imported identifier is opaque to it — `armIsObject()` recognises only `{...}` and `Record<`
(`src/Support/TsTypeShape.php:203`). A `#[TsCasts]` type that names an imported alias therefore keeps `[]`,
however object-like the alias resolves to on the TypeScript side.

`tests/Fixtures/EmptyValuesModelMetadataProvider.php:26` pins exactly that shape — `'opaque' =>
['type' => 'OpaqueShape', 'import' => '@/types/opaque-shape']` holding `[]`. Point the alias at the
map it reads as (`export type OpaqueShape = Record<string, unknown>;`) and the emitted companion fails:

```
error TS2322: Type 'readonly []' is not assignable to type 'OpaqueShape'.
  Index signature for type 'string' is missing in type 'readonly []'.
```

Every other property in that same companion type-checks clean, so this is the residue of a bug that used to
hit every object-like property, not a new one. **The workaround is to return `(object) []`**, which the
provider may now do explicitly and which survives coercion untouched.

No gate catches it. The writer tests that render this companion all run with `ts-publish.output_to_files`
false (`tests/Unit/Writers/ModelMetadataWriterTest.php:93`), so the file never reaches the generated tree
the token gate compiles — read a green gate as saying nothing about this case either way.

Fixing it means resolving the alias to a type the shape inspector can read, which puts a module-resolution
step inside a transformer that today does pure string inspection. Widening `isObjectLike()` to guess that
any unknown identifier is object-like is not the fix: it would spell `{}` for an alias of `string[]`,
turning a narrow wrong answer into a broad one.

## Deliberate non-goals

Absent on purpose. Do not "fix" these without raising it first.

- **Non-Inertia and JSON responses are never typed.** Only `Inertia::render()` page props and the
  shared-data middleware are analyzed.
- **No `ts-publish.analyzer.handlers` config key.** You cannot append your own `ExpressionHandler`. Every
  extension point is a compatibility promise; worth adding only if someone asks.
- **Form requests stay runtime.** They are resolved by instantiating and calling `rules()`, on purpose.
- **Collector class maps are not invalidated mid-process.** `CoreCollector::classMap()` scans each directory
  once per process, and `Runner::run()` / `RunnerForSource::run()` clear it first, so a `ts:publish` run
  always reads the disk. Host code that calls `collect()` or `allows()` directly on either side of writing a
  `.php` file — a custom collector, a `tinker` or test-helper loop that generates a model and re-collects —
  gets the pre-write answer from both. Call `CoreCollector::flushClassMapCache()` between the write and the
  second call. Stat-based invalidation would charge every run for a case no package run path reaches:
  nothing in `src/` writes a `.php` file.

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

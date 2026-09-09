# Changelog

All notable changes to `laravel-ts-publish` will be documented in this file.

## v2.5.2 - 2026-09-09

Add more granular docs for models metadata in the main README.md and the individual metadata skill resource file

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.5.1...v2.5.2

## v2.5.1 - 2026-09-08

Improvements to Boost skills to make sure agents understand how to write PHP code that can be inferred into TypeScript and how to use those types.

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.5.0...v2.5.1

## v2.5.0 - 2026-09-07

### What's Changed

Model metadata is the headline. It publishes a runtime companion beside each model interface, so values like a model's morph class come from your models instead of being hand-maintained in TypeScript.

Everything else is the type engine, which was replaced wholesale. A full publish runs about six times faster, and a lot of things that used to type as `unknown` now resolve properly.

**Read [Upgrading](#upgrading) before you pull this in.** Generated files change substantially on the first run even if you change nothing of your own, and types that were `unknown` are now concrete, which can surface real errors in the code that consumes them.

### Model metadata

Until now this package emitted only types, and types are erased at compile time. A model metadata companion is a real module you can import at runtime.

Turn it on:

```php
// config/ts-publish.php
'model_metadata' => ['enabled' => true],



```
Every published model gains a companion beside its interface. `user.ts` gets `user_meta.ts`:

```typescript
export const UserModelMetadata = {
    morphClass: 'user',
} as const satisfies {
    morphClass: string;
};



```
`as const` keeps every value a literal type. `satisfies` checks it against the declared shape without widening it. The companion joins the existing barrel, so one import path serves both:

```typescript
import { User, UserModelMetadata } from '@js/types/data/app/models';

form.commentable_type = UserModelMetadata.morphClass;



```
That last line is the point. Polymorphic payloads previously meant hardcoding `'App\\Models\\User'` in the frontend and keeping it in step with PHP by hand. The default provider publishes the model's morph class and honours your morph map, so a mapped model emits `'user'` and an unmapped one emits the fully-qualified class name.

**Publish whatever you like.** The payload comes from a provider class you control:

```php
final class AppModelMetadataProvider implements ModelMetadataProvider
{
    /** @return array{morphClass: string, routeKey: string} */
    public function provide(Model $model): array
    {
        return [
            'morphClass' => (string) $model->getMorphClass(),
            'routeKey' => $model->getRouteKeyName(),
        ];
    }
}

// config/ts-publish.php
'model_metadata' => [
    'enabled' => true,
    'provider_class' => AppModelMetadataProvider::class,
],



```
Providers resolve through the container, so constructor injection works. Values may be scalars, arrays, enums, or objects implementing `Arrayable` or `JsonSerializable`, nested and normalized recursively.

**Types work the way model columns do.** `#[TsCasts]` on the provider method comes first, then a precise `@return array{...}` shape, then inference over the returned array literal. An enum value imports its TypeScript type rather than widening to `string`:

```php
#[TsCasts(['role' => ['type' => 'RoleType', 'import' => '../enums']])]
public function provide(Model $model): array



```
```typescript
import type { RoleType } from '../enums';

export const UserModelMetadata = {
    morphClass: 'Workbench\\App\\Models\\User',
    role: 'Admin',
} as const satisfies {
    morphClass: string;
    role: RoleType;
};



```
**It is its own phase.** `--only-model-metadata` publishes it alone. `models.enabled` and `--only-models` control interfaces only, and neither generates nor suppresses companions. Its `included`, `excluded` and `additional_directories` fall back to the `models` values unless you set them. Companions are runtime output rather than erased types, so `--only-functional` includes them and the Vite plugin writes them on `vite build`.

**Failures are contained.** A provider that throws for one model keeps that model's last published companion and its barrel export, publishes every other file, and exits non-zero with the model name and the error. A value that cannot become valid TypeScript fails with the model name and the property path, rather than writing a file that fails `tsc` later. That covers integers beyond ±(2^53−1), non-finite floats, circular references, and nesting past 64 levels.

Full details: [Model metadata](https://tolki.abe.dev/ts/model-metadata.html).

### Faster publishing

Inference no longer runs on two upstream analysis packages. It is one pass over your source, and it never autoloads or executes an application class to learn its shape.

A full uncached publish of 324 files went from 5.49s to 0.94s on the same machine. Each directory is now read once per run instead of once per collector. `laravel/surveyor` and `laravel/ranger` are no longer installed.

An action the analyzer cannot read is now a warning rather than a failed run. That action loses its page-prop type, everything else publishes, and the exit code stays 0.

### Inertia

Page props type from the expression you wrote:

```diff
- export type StorePageProps = Inertia.SharedData & { post: string };
+ export type StorePageProps = Inertia.SharedData & { post: Post };



```
Eloquent finders resolve to their model. `$request->user()` resolves through your auth config, guard to provider to model, and writes the import for you. The Inertia v2 wrappers (`defer()`, `optional()`, `lazy()`, `always()`, `merge()`, `deepMerge()`, `scroll()` and `once()`) type as the value they wrap. Shared data reads the whole middleware inheritance chain. `config('some.key')` types from the live value.

An `EnumResource` shared from `HandleInertiaRequests::share()` now publishes a working type. The generated file previously named a type it never imported, which failed every consumer build.

Full details: [Inertia](https://tolki.abe.dev/ts/inertia.html) and [Routing](https://tolki.abe.dev/ts/routing.html).

### API resources

Three changes worth knowing about:

- Top-level `...` spreads flatten into the resource's own properties. They previously contributed nothing at all.
- Named arguments to the conditional family (`when()`, `whenLoaded()`, `whenCounted()` and the rest) are read by name. Any named argument used to make the engine give up on the call.
- `$request->validated('key')` types from the bound form request's rules instead of `unknown`, including dotted keys such as `validated('options.default')`.

Also fixed: a `toArray()` returning `array_merge(...)` is analyzed instead of emitting an empty interface, a `ResourceCollection` inheriting `$wrap = null` is unwrapped, both arms of a `when()` with two object literals survive, two enums sharing a class basename stop misaligning, and unions normalize so `null` hoists to a single trailing arm.

Full details: [API resources](https://tolki.abe.dev/ts/api-resources.html) and [Form requests](https://tolki.abe.dev/ts/form-requests.html).

### Enums, models and events

Page-prop enums import by their `#[TsEnum(name:)]` name, and `#[TsResource(name:)]` is honoured for controller page-prop imports. Both previously imported a name that did not exist.

Broadcast events read class-body public properties alongside promoted ones, prefer a `@var` docblock over the native declaration, and skip properties a used trait declares. `laravel-ts-global.ts` now emits the `extends` clause it was already writing the import for, so inherited members stop going missing.

Full details: [Enums](https://tolki.abe.dev/ts/enums.html), [Models](https://tolki.abe.dev/ts/models.html) and [Broadcast events](https://tolki.abe.dev/ts/broadcast-events.html).

### Analyzer API

The engine is callable directly, outside the publish pipeline:

```php
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;

$result = resolve(AstEngine::class)->analyze(App\Http\Resources\PostResource::class);

$result->properties;   // the typed property list ts:publish would generate
$result->typeImports;  // the `import type` lines those types need
$result->valueImports; // the value imports an AsEnum<typeof X> wrapper reads



```
The three fields agree with each other, so rendering all three gives you a module that compiles. `analyze()` and `AnalysisResult` are the whole supported API.

Full details: [Analyzer API](https://tolki.abe.dev/ts/analyzer-api.html).

### Upgrading

**Expect a large diff on your first publish**, even if you change nothing of your own. Run `php artisan ts:publish` once and review the diff before committing.

These can fail a consumer build until you update the code that reads them:

- Types that were `unknown` are now concrete, most often `User | null` for `$request->user()`. Code that relied on `unknown` accepting anything can start failing `tsc`.
- A broadcast event's public typed property with no declaration default and no promotion becomes optional, so `label: string` becomes `label?: string`. This matches what `json_encode()` does with an unassigned typed property. Give it a declaration default or promote it to keep the key required.
- A resource with a top-level `...` spread can go from a handful of properties to dozens.
- A named `default:` argument flips a property from optional to required and adds the default's type to the union.
- A `ResourceCollection` inheriting `$wrap = null` flips from `{ data: T[] }` to a bare array type. The old shape was wrong, and the API already returned the array.
- Union member order changes, so `null | string` becomes `string | null`. Same type, different text.

Configuration and tooling:

- A config file you published earlier has no `model_metadata` block. Nothing breaks, because every key falls back in code and the feature is off. To use it, copy the whole block from the package config. Laravel merges one level deep, so a partial block replaces the packaged one outright.
- If you published the views and share an `EnumResource`, re-publish or merge the new import block into `inertia-config.blade.php`. A stale copy silently drops the enum value imports.
- `--source="App\Models\Foo"` now respects `models.included` and `models.excluded`. A model your filters exclude is reported and the command exits non-zero, where it previously published anyway.
- `laravel/surveyor` and `laravel/ranger` are removed. Add them to your own `composer.json` if you use them directly.
- The generation cache invalidates automatically, so the first run after upgrading is a full rebuild.
- Model barrels are rebuilt by phase ownership rather than merged, so an export for a deleted model is finally pruned. Do not hand-edit a generated `index.ts`.
- Float literals are emitted at full round-trip precision rather than rounded to PHP's `precision` ini value. This reaches enum case values, per-case enum method returns, and form-request rule values.
- `ts:publish` can exit non-zero on a run that wrote every file, but only when `model_metadata.enabled` is true and a provider throws for one model.

If you extend the package's classes:

- `BroadcastEventTransformer`'s constructor is now `__construct(string $findable)`. A subclass calling `parent::__construct($findable, $analyzer)` must drop the second argument.
- `ResourceTransformer`'s protected `modelFromDocblock()`, `modelFromAncestorDocblock()`, `guessModelFromConvention()` and `guessModelFromUseResourceAttribute()` were removed.
- A custom `barrel_writer_class` overriding `writeModular()` must also override `writeModularPreserving()`, which partial runs call.
- Twenty-five helpers moved out of `LaravelTsPublish` into three internal classes. Every `LaravelTsPublish::` call you make yourself still works with an identical signature. But if you bound a subclass to override one of those helpers, the override is now silently ignored. Bind the specific `Support\JsEmitter`, `Support\TsTypeString` or `Support\TsNaming` instead.

* Feat / Unified AST Engine by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/65
* Add configurable model metadata files - metadata.morph_class by @ostapchenko in https://github.com/abetwothree/laravel-ts-publish/pull/63
* Fix model metadata review findings from #63 by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/67
* Chore / backlog closeout by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/66
* Feat / Engine Contract and Inference by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/74
* Refactor / Laravel TS Publish Facades by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/75

### New contributors

* @ostapchenko made their first contribution in https://github.com/abetwothree/laravel-ts-publish/pull/63

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.4.0...v2.5.0

## v2.4.0 - 2026-08-23

### What's Changed

Two rounds of analyzer work, both aimed at the same thing: resource types that match the JSON Laravel actually sends. The `except()` and `only()` renderings changed shape, several properties that resolved to `unknown` now resolve properly, and four type tokens TypeScript could not import are gone.

One output change worth reading before you upgrade. Relation `except()` now emits `Pick<Model, kept>` instead of `Omit<Model, excluded>`. The new type is narrower and matches the response, but any frontend code leaning on the old shape needs updating.

### API resources

- Relation `except()` emits the complement as a `Pick`, so `Omit<Post, 'created_at' | 'updated_at'>` becomes `Pick<Post, 'id' | 'title' | ...>`. `Omit` left mutators, relations, counts and `exists` flags in the type. The response only ever contains columns.
- A multi-model accessor filtered with `only()` references each arm's own model. `{ id: number; name: string } | null` becomes `Pick<crm.models.User, 'id' | 'name'> | Pick<app.models.User, 'id' | 'name'> | null`.
- Spreading a model inside an inline array intersects its `toArray()` with the sibling keys. `{ flag: boolean }[]` becomes `(Omit<app.models.User, 'flag'> & { flag: boolean })[]`.
- A to-many `whenLoaded()` parameter's spread types as `Record<number, Model>` instead of collapsing to the single element model.
- A resource subclass that declares no `toArray()` inherits the parent's body instead of generating an empty interface.
- A method called on a resource receiver that returns something other than `static`, `self` or `$this` resolves its real return shape. `parent_summary?: unknown` becomes `parent_summary?: { id: number }`.
- Enum resources inside inline array literals are substituted rather than rebuilt, so both arms of a mixed wrap/direct ternary survive: `{ status: app.enums.StatusType[] | app.enums.StatusType }`.
- Resources guessed by naming convention (`FooCollection` to `FooResource`) now have to be in the published set. A third-party or `#[TsExclude]`d class can no longer be named in a type that has no file to import. The Inertia page analyzer shares the same gate.

### Imports and naming

- Every occurrence of a same-basename type in one property is aliased, not just the first: `status_pair: { app: app.enums.StatusType; crm: crm.enums.StatusType }`.
- Morph relations in `ModelTransformer` go through the shared aliasing path instead of their own copy of it.
- `#[TsResource(name: 'Address')]` is honoured when the analyzer derives the reference, not only when the resource is transformed directly.
- Form request custom type imports now reach the globals file.
- A plain value object whose public properties are all typed inlines its shape. `location: Coordinate`, which nothing could import, becomes `location: { lat: number; lng: number }`.

### Inertia and form requests

- A paginator called inline in the `render()` props array is detected with no intermediate variable, so `Inertia::render('Teams/Index', ['teams' => new TeamCollection(Team::query()->paginate(10))])` still types as a paginator.
- `digits` and `decimal` count as numeric when coercing `in:` values. `['digits:1', 'in:1,2,3']` gives `1 | 2 | 3` instead of `'1' | '2' | '3'`.

* Feat/analyzer followups by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/62
* Feat/analyzer backlog by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/64

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.3.0...v2.4.0

## v2.3.0 - 2026-08-16

### What's Changed

This release is another large set of updates to make output more accurate and lower the amount of `unknown` output types.

Three areas got most of the work:

- resolving types that previously degraded to `unknown`
- teaching the resource analyzer more of what `toArray()` can express
- and completing the database column type map across all four drivers Laravel supports.

### Fewer `unknown` types

- `@phpstan-type` / `@phpstan-import-type` aliases now resolve, including the `Name = Definition` form.
- Castable-with-arguments cast strings (`AsEnumCollection:Status`) resolve to their inner type.
- An `Arrayable` DTO's own typed public properties become an object shape.
- `MorphTo` docblock generics resolve to a union, with a morph-name-keyed target map.
- Variables carry their model through — `whenLoaded()` closure params, `foreach`, chain terminals.

### Resources understand more of `toArray()`

- Five more conditional methods: `unless()`, `whenAppended()`, `whenExistsLoaded()`, `transform()`, `mergeUnless()`.
- `whenNull()` / `whenNotNull()` read their value argument instead of discarding it.
- An explicit default makes the property required, and unions the default's type in where resolvable.
- A bare `return $this->someMethod();` resolves transitively, same as its spread form.
- `#[PreserveKeys]` emits `Record<string, Resource>` instead of `Resource[]`.
- Relation `only()`/`except()` reference the model via `Pick<>`/`Omit<>` instead of re-deriving.
- `models.exclude_hidden` now applies to resource interfaces too.

### Models & the column type map

- ~30 more native column types — spatial, vector, binary, network, and legacy.
- Sized types (`varchar(255)`, `tinyint(1)`) now match the map exactly instead of a substring scan.
- Laravel 13's `#[Table]`, `#[Hidden]`, `#[Visible]`, `#[Appends]`, `#[Connection]` are honoured.

### Form Requests / Routes / Imports

- `required_array_keys`, `in_array_keys`, `array:a,b` resolve to a keyed object, not `unknown[]`.
- GET routes carry `head`, so `.head()` and `.form.head()` exist.
- New import-name registry gives collision-proof aliasing across namespaces.

* Feat/unknown inference second pass by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/59
* Feat/resource inference and laravel 13 attributes by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/60

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.2.0...v2.3.0

## v2.2.0 - 2026-08-08

### What's Changed

- A lot of improvements to type model & resource properties into TypeScript equivalent versions.
- Testing upgrades
- General clean up

* Feat/type inference unknowns by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/57

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.1.0...v2.2.0

## v2.1.0 - 2026-07-11

Fix docs & Laravel Boost skill to help AI understand how to create types and how to use them from this package.

You can install the skill on your Laravel application by re-running `boost:install`

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.0.3...v2.1.0

## v2.0.3 - 2026-07-10

### What's Changed

* Fix/writer mkdir race by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/56

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.0.2...v2.0.3

## v2.0.2 - 2026-07-10

### What's Changed

* fix: Handle cache dir creation race condition by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/55

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.0.1...v2.0.2

## v1.5.1 - 2026-07-09

### What's Changed

* Report ts:publish failures on stderr under --quiet
* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/4
* Bump dependabot/fetch-metadata from 2.5.0 to 3.1.0 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/26
* Update awobaz/compoships requirement from ^2.5 to ^2.5 || ^3.0 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/29
* Bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/51

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/abetwothree/laravel-ts-publish/pull/4

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.5.0...v1.5.1

## v2.0.1 - 2026-07-09

Report ts:publish failures on stderr under --quiet

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v2.0.0...v2.0.1

## v2.0.0 - 2026-07-04

### What's Changed

* Initial Routing  by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/20
* V2 breaking changes updates by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/24
* V2 Inertia features by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/27
* 2x inertia page return props by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/30
* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/4
* Bump dependabot/fetch-metadata from 2.5.0 to 3.1.0 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/26
* Update awobaz/compoships requirement from ^2.5 to ^2.5 || ^3.0 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/29
* Form Requests  by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/47
* Broadcast channels by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/48
* Broadcast Events by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/49
* 2.x routing spec checks by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/50
* 2x Caching by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/52
* 2x inertia table UI by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/53
* Bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/abetwothree/laravel-ts-publish/pull/51
* 2.x by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/23

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/abetwothree/laravel-ts-publish/pull/4

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.5.0...v2.0.0

## v1.5.0 - 2026-05-23

### What's Changed

* Issue #43 resource->enums by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/45
* Preserve multiline comment formatting by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/46

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.7...v1.5.0

## v1.4.7 - 2026-05-22

### What's Changed

* PHP functions return types by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/41
* Conditional closure params by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/42

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.6...v1.4.7

## v1.4.6 - 2026-05-21

### What's Changed

* Fix Support "self" keyword in resources by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/39
* Ternary operator in resources by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/40

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.5...v1.4.6

## v1.4.5 - 2026-05-06

### What's Changed

* AST static methods support  by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/34

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.4...v1.4.5

## v1.4.4 - 2026-05-06

### What's Changed

* Further AST chain analysis for model methods by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/33

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.3...v1.4.4

## v1.4.3 - 2026-05-05

### What's Changed

* Resource AST nullsafe chains by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/32

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.2...v1.4.3

## v1.4.2 - 2026-04-25

### What's Changed

* Resource typing improvements by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/28

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.1...v1.4.2

## v1.4.1 - 2026-04-18

### What's Changed

* Many relationships fix for only & except on resources by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/25

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.4.0...v1.4.1

## v1.4.0 - 2026-04-16

### What's Changed

* Ast handle closure data return by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/21

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.3.1...v1.4.0

## v1.3.1 - 2026-04-07

Remove `no-op` reflection method

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.3.0...v1.3.1

## v1.3.0 - 2026-04-01

### What's Changed

* Appends attributes to model & resource @extends doc tag by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/17
* Model-less resource support by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/18

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.2.1...v1.3.0

## v1.2.1 - 2026-03-28

### What's Changed

* Globals namespace fixes by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/15

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.2.0...v1.2.1

## v1.2.0 - 2026-03-27

### What's Changed

* Mutators attributes doc block types by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/13
* Ability to extend interfaces by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/12
* Greater support for model `only` & `exclude` methods with relations by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/14

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.1.2...v1.2.0

## v1.1.2 - 2026-03-24

### What's Changed

* Edge cases with resources by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/11

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.1.1...v1.1.2

## v1.1.1 - 2026-03-23

### What's Changed

* Support Laravel 13 by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/10
* Implement nullable enums and other casted values by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/9

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.1.0...v1.1.1

## Support for transforming Eloquent API Resources to TypeScript declaration files - 2026-03-23

### What's Changed

* Eloquent API Resources to TypeScript by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/8

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v1.0.1...v1.1.0

## v1 release 🎉  - 2026-03-17

### What's Changed

* Naming conflicts for published TS files fixes  by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/1
* Several large features before final release  by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/2
* Nullable relationships setup and testing by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/3
* Handle nullable relations with composite foreign keys by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/5
* Add ability to exclude content by @abetwothree in https://github.com/abetwothree/laravel-ts-publish/pull/6

### New Contributors

* @abetwothree made their first contribution in https://github.com/abetwothree/laravel-ts-publish/pull/1

**Full Changelog**: https://github.com/abetwothree/laravel-ts-publish/compare/v0.0.0...v1.0.0

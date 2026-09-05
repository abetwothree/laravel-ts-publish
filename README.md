# Generate TypeScript types from your Laravel models, enums, resources, routes & events

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/abetwothree/laravel-ts-publish)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Coverage](assets/coverage.svg)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)

<p align="center"><img src="./assets/laravel-typescript-publish-logo-short.svg" width="50%" alt="Laravel TypeScript Publisher Logo"></p>

Transform Laravel models, enums, API resources, routes, broadcast events, and custom cast classes into TypeScript declaration types.

Enums and routes become functional objects. Enums support PHP-like enum functions and can include your own methods.

Every Laravel app is different, so what the package infers is yours to override, and the backend and frontend tooling keeps your frontend types in sync with your PHP as it changes.

For examples of the generated TypeScript output, see [these output examples](workbench/resources/js/types/data/default-example).

## Also by me

- [Laravel Iconify API & Icon Rendering](https://github.com/abetwothree/laravel-iconify-api)
- [Tolki JS NPM packages](https://github.com/abetwothree/tolki)

## Table of contents

- 📦 [Installation](#installation)
- 🚀 [Usage](#usage)
- 🏷️ [Enums](#enums)
- 🗃️ [Models](#models)
- 📡 [API resources](#api-resources)
- 🚗 [Routes](#routes)
- 📝 [Form requests](#form-requests)
- 📡 [Broadcast channels](#broadcast-channels)
- 🎤 [Broadcast events](#broadcast-events)
- 🌉 [Inertia](#inertia)
- 🔑 [Vite env](#vite-env)
- 🧬 [Extending interfaces](#extending-interfaces-with-tsextends--configs)
- ❌ [Excluding content](#excluding-with-tsexclude)
- 🔤 [Casing configurations](#casing-configurations)
- 🌐 [Enum API resource](#json-enum-http-api-resource)
- 📂 [Modular publishing](#modular-publishing)
- 🔧 [Customizing the pipeline](#extending--customizing-the-pipeline)
- 🔍 [Analyzer API](#analyzer-api)
- ⚡ [Pre-command hook](#pre-command-hook)
- 💾 [Cache generation](#cache-generation)
- 📤 [Output options](#output-options)
- ⚙️ [Configuration reference](#configuration-reference)

## Installation

**Requires PHP 8.4+ and supports Laravel 13, 12**

Upgrading from version 1.x? Please refer to the [Upgrade Guide](./docs/v2-upgrade-guide.md) for instructions on migrating from version 1.x to the current version.

You can install the package via composer:

```bash
composer require abetwothree/laravel-ts-publish
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="ts-publish-config"
```

Optionally, you can publish the views using:

```bash
php artisan vendor:publish --tag="laravel-ts-publish-views"
```

## Usage

### Publishing types

You can publish your TypeScript declaration types using the `ts:publish` Artisan command:

```bash
php artisan ts:publish
```

The first run caches its work, so reruns only regenerate what changed. Use `--fresh` to rebuild everything. See [Cache generation](#cache-generation).

```bash
php artisan ts:publish --fresh
```

By default, generated types are written to `resources/js/types/data/`.

The package scans the standard Laravel directories (`app/Models`, `app/Enums`, `app/Http/Resources`). Change any of that in the published config file.

For a full installation and setup guide, see the [Installation & Setup](https://tolki.abe.dev/ts/) documentation.

#### Preview mode

You can preview the generated TypeScript output in the console without writing any files by using `--preview=true`:

```bash
php artisan ts:publish --preview=true
```

> [!WARNING]
> The `=true` is required. `--preview` is declared with a default value (`{--preview=false}`), so a bare `--preview` flag parses as unset rather than `true`, and the command writes real files instead of previewing them.

Useful for debugging, or for reviewing what will be generated before it hits disk.

#### Single-file republishing

You can republish a single enum, model, or resource instead of the entire set by using the `--source` option with a fully-qualified class name or file path:

```bash
php artisan ts:publish --source="App\Enums\Status"
php artisan ts:publish --source="app/Enums/Status.php"
php artisan ts:publish --source="App\Http\Resources\UserResource"
```

On a large project this is much faster than a full publish. The [Vite plugin](https://tolki.abe.dev/ts/vite-plugin.html) uses it automatically during development to republish only the file that changed.

Single-file model publishing respects the model and model-metadata `included` and `excluded` settings independently. `additional_directories` affects discovery only and is not required for an explicitly supplied source.

#### Automatic publishing after migrations

By default, this package will automatically re-publish your TypeScript declaration types after running migrations. This ensures your TypeScript types stay in sync with your database schema changes.

You can disable this behavior in the config file or via environment variable:

```php
// config/ts-publish.php

'run_after_migrate' => false,
```

```env
TS_PUBLISH_RUN_AFTER_MIGRATE=false
```

#### Filtering models, enums & resources

Choose what to include or exclude, and add directories to search. By default everything in `app/Models`, `app/Enums`, and `app/Http/Resources` is included.

```php
// config/ts-publish.php

'models' => [
    // Only publish these specific models (leave empty to include all)
    'included' => [
        App\Models\User::class,
        App\Models\Post::class,
    ],

    // Exclude specific models from publishing
    'excluded' => [
        App\Models\Pivot::class,
    ],

    // Search additional directories for models
    'additional_directories' => [
        'modules/Blog/Models',
    ],
],
```

Similar options are available for other content types like enums, events, resources, etc., allowing you to specify `included`, `excluded`, and `additional_directories` for each type.

> [!TIP]
> Include and exclude settings accept both fully-qualified class names and directory paths. When a directory is provided, all matching classes within it will be discovered automatically.

#### Conditional publishing

You can choose which output phases to publish through configuration or command flags.

##### Via configuration

Disable any publishing phase independently in the config file:

```php
// config/ts-publish.php

'enums' => ['enabled' => true],
'models' => ['enabled' => true],
'model_metadata' => ['enabled' => false],
'resources' => ['enabled' => true],
```

Setting any to `false` will skip that type on every run, including automatic post-migration publishing.

##### Via command flags

Use one of the `--only-*` flags to limit a single run to a specific type: `--only-enums`, `--only-models`, `--only-model-metadata`, `--only-resources`, `--only-routes`, `--only-form-requests`, `--only-broadcast-channels`, or `--only-broadcast-events`.

```bash
php artisan ts:publish --only-enums
php artisan ts:publish --only-models
php artisan ts:publish --only-model-metadata
php artisan ts:publish --only-resources
```

The flags cannot be combined. Passing two returns an error.

There's also `--only-functional`, which publishes only runtime TypeScript output (enums, model metadata, routes, form requests, broadcast channels/events) while skipping model and resource interfaces. The [Vite plugin](https://tolki.abe.dev/ts/vite-plugin.html) appends it on `vite build`, since interfaces are erased at compile time anyway. Combined with another `--only-*` flag, it wins.

Partial model or metadata publishes merge their generated exports into existing model barrels. They create barrels when missing and preserve exports owned by the skipped companion phase. Because partial runs cannot identify every stale export safely, run both model phases together to rebuild the complete barrel after removing models.

Custom `barrel_writer_class` implementations can opt into partial merging by implementing `MergesModularBarrels`. Otherwise, partial model runs leave existing barrels untouched.

##### Config & flag conflicts

When a command flag requests a type that is disabled in config (e.g. `--only-enums` while `enums.enabled` is `false`), the command will prompt you to confirm whether to override the config setting. In non-interactive environments (CI, queued jobs, post-migration hooks), the config value is respected and the command exits gracefully.

If all types end up disabled (all config values are `false` and no override flag is given), the command prints a warning and exits with a success status.

#### Verbosity levels

The `ts:publish` command supports three verbosity levels using the standard Artisan verbosity flags:

| Flag | Output |
|------|--------|
| `--quiet` / `-q` | Nothing but the exit code. Suits automated tooling like the [Vite plugin](https://tolki.abe.dev/ts/vite-plugin.html). |
| *(default)* | A compact summary showing the output directory, file counts, and any extra files generated (barrels, globals, JSON). |
| `--verbose` / `-v` | Detailed tables listing every generated file with per-file metadata (cases, methods, columns, mutators, relations). |

```bash
# Compact summary (default)
php artisan ts:publish

# Detailed tables
php artisan ts:publish -v

# Silent — for scripts, CI, or the Vite plugin
php artisan ts:publish --quiet
```

Quiet mode still writes every file; it suppresses console output only. The [Vite plugin](https://tolki.abe.dev/ts/vite-plugin.html) passes it by default because it only needs the exit code.

## Enums

PHP enums become functional TypeScript objects rather than a bare union of values, with PHP-like behavior (`.from()`, `.tryFrom()`, `.cases()`) powered by [`@tolki/ts`](https://tolki.abe.dev/ts/). Your own enum methods and static methods can come along too.

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    #[TsEnumMethod]
    public function label(): string
    {
        return match($this) {
            self::Active => 'Active User',
            self::Inactive => 'Inactive User',
        };
    }
}
```

```typescript
import { Status } from '@js/types/data/enums';

Status.Active;                // 'active'
Status.label.Active;          // 'Active User'
Status.from('active').label;  // 'Active User' — a PHP-like enum "instance"
```

Key capabilities:

- **`#[TsEnumMethod]` / `#[TsEnumStaticMethod]`** — opt individual instance/static methods into the TypeScript output (or enable `enums.auto_include_methods` / `enums.auto_include_static_methods` to include all public methods automatically).
- **`#[TsEnum]` / `#[TsCase]`** — rename the enum or a case, or add a JSDoc description, when the PHP name doesn't match what you want on the frontend.
- **`{Name}Type` / `{Name}Kind`** — generated type aliases for validating a raw case value or case name.
- **`defineEnum()` from `@tolki/ts`** — wraps the enum so you can call `.from()`, `.tryFrom()`, and `.cases()` on it just like PHP's `BackedEnum`.
- **PHPDoc-aware** — class, case, and method doc blocks are carried over as JSDoc comments automatically.
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by models and resources.
- **`#[TsExclude]`** — exclude an entire enum or specific methods from the output. See [Excluding with `#[TsExclude]`](#excluding-with-tsexclude).
- **`EnumResource`** — an HTTP JSON resource for returning flattened, instance-specific enum data from your API routes. See [JSON enum HTTP API resource](#json-enum-http-api-resource).

For every attribute option, the metadata/`@tolki/ts` integration, the Vite plugin, and the full behavior of auto-including methods, see the full [Enums documentation](https://tolki.abe.dev/ts/enums.html).

## Models

Eloquent models become TypeScript interfaces for their properties, mutators, and relations. They are split into separate interfaces by default, so a page imports only the parts it uses.

```php
class User extends Model
{
    public function casts(): array
    {
        return ['status' => Status::class];
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => /* ... */);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

```typescript
import type { User, UserMutators, UserRelations } from '@js/types/data/models';

// User          → id: number; status: StatusType; ...
// UserMutators  → initials: string
// UserRelations → posts: Post[]; posts_count: number; posts_exists: boolean
```

Model runtime metadata is published beside each model by default:

```typescript
// models/user_meta.ts
export const UserModelMetadata = {
    morphClass: 'user',
} as const satisfies {
    morphClass: string;
};
```

The configured provider's `provide()` method receives each model instance and returns its complete metadata payload. Prefer a precise `@return array{...}` shape so PHPStan or Psalm can validate that contract. When a provider only declares `array<string, mixed>`, import-free types fall back to inference from statically analyzable array returns. The PHPDoc shape overrides body inference and is required for optional or dynamically constructed keys; `#[TsCasts]` has final precedence and owns explicit overrides and imports. Providers are resolved through Laravel's container, so constructor dependencies are supported.

Optional PHPDoc keys may be absent from a model's payload. When present, they are emitted as required properties because each generated metadata object represents that concrete payload. Required PHPDoc keys must always be present, and every returned key must have either an inferred or `#[TsCasts]` type.

Scalar and nested inline-array types can be inferred directly from the method body. PHPDoc additionally supports scalar, container, and nested array-shape types. PHP class, enum, and other named TypeScript types need an explicit `#[TsCasts]` override because metadata does not infer their TypeScript import path.

Metadata values may be `null`, scalars, arrays, enums, or objects implementing Laravel's `Arrayable` or PHP's `JsonSerializable` contract. These values can be nested and are normalized recursively. Unsupported objects, resources, non-finite floats, circular objects, and values nested more than 64 levels fail with the model and metadata property path instead of producing invalid TypeScript.

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class AppModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * @return array{
     *     morphClass: string,
     *     identifiers: array{
     *         primaryKey: string,
     *         routeKey: string,
     *     },
     * }
     */
    #[TsCasts([
        'identifiers' => [
            'type' => 'ModelIdentifiers',
            'import' => '@/types/model-identifiers',
        ],
    ])]
    public function provide(Model $model): array
    {
        return [
            'morphClass' => (string) $model->getMorphClass(),
            'identifiers' => [
                'primaryKey' => $model->getKeyName(),
                'routeKey' => $model->getRouteKeyName(),
            ],
        ];
    }
}

// config/ts-publish.php
'model_metadata' => [
    'enabled' => true,
    'provider_class' => AppModelMetadataProvider::class,
    'template' => 'laravel-ts-publish::model-meta',
],
```

Model metadata is disabled by default. When enabled, the default provider publishes `morphClass` as a string and preserves configured morph-map aliases. Model metadata is a separate publishing phase. `models.enabled` and `--only-models` control only model interfaces; they do not generate or disable metadata. Use `model_metadata.enabled` and `--only-model-metadata` for metadata. Metadata discovery inherits `models.included`, `models.excluded`, and `models.additional_directories` when the corresponding key is omitted from `model_metadata`. An explicitly configured metadata value takes precedence, including an empty array.

If your app calls `Relation::enforceMorphMap()`, every discovered model must appear in the map. Use `model_metadata.excluded` for models you do not map.

Key capabilities:

- **Split or full templates** — `models.template` controls whether properties/mutators/relations are generated as separate interfaces (default) or combined into one `model-full` interface.
- **Smart nullable relations** — singular relations (`HasOne`, `BelongsTo`, `MorphOne`, ...) are automatically typed with `| null` based on the relation type and foreign key nullability, with a config to override the strategy per relation type.
- **Annotate instead of configuring** — `@property` / `@property-read` tags, `@phpstan-type` aliases, `Attribute<>` generics, `@return MorphTo<A|B, $this>`, `AsEnumCollection::of()` / `AsCollection::of()`, and an `Arrayable` DTO's own typed properties all sharpen a column's type with no `#[TsCasts]` needed, and PHPStan/Larastan read the same annotations. See [Typing attributes without `#[TsCasts]`](https://tolki.abe.dev/ts/models.html#typing-attributes-without-tscasts).
- **PHPDoc-aware** — class, column, mutator, and relation doc blocks are carried over as JSDoc comments automatically.
- **`#[TsCasts]` / `#[TsType]`** — for more advanced TypeScript types for generated properties or an entire custom cast class, including custom types imported from your own files.
- **`$hidden` and write-only accessors** — hidden attributes publish by default. `models.exclude_hidden` opts out for model *and* resource interfaces alike, so a resource's `except()` or whole-model delegation loses the column too, though `only(['password'])` still keeps one you name explicitly. A write-only `Attribute::make(set:)` resolves from its `@return Attribute<Get, Set>` generic, then from a same-named column, and failing both is omitted rather than emitted as `unknown`.
- **Runtime metadata** — each `{model}_meta.ts` companion exports a `{Model}ModelMetadata` object whose values come from the configured provider. Prefer a precise return shape for static analysis; body inference is the fallback for generic array declarations, PHPDoc refines it, and `#[TsCasts]` owns explicit overrides and imports.
- **`#[TsExclude]`** — exclude an entire model, or a specific accessor/relation, from the output.
- **Laravel 13 model attributes** — `#[Table]`, `#[Hidden]`, `#[Visible]`, `#[Appends]`, and `#[Connection]` are honoured automatically, no configuration needed. See [Laravel 13 Model Attributes](https://tolki.abe.dev/ts/models.html#laravel-13-model-attributes) for the full attribute-by-attribute table.
- **Enum-typed columns** also generate a matching `{Model}Resource` interface using `AsEnum<>`, for when you've resolved a raw enum column to a full enum instance (e.g. via `Status.from(user.status)`).
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums and resources.

> [!TIP]
> Still seeing `unknown` in the output? The [annotation checklist](https://tolki.abe.dev/ts/models.html#annotation-checklist) indexes each case by symptom and names the docblock tag that fixes it. PHPStan and Larastan read all of them too.
>
> If it still comes out `unknown`, open an issue with the PHP and the generated TypeScript so we can look.

For the template comparison, nullable relation strategies, every attribute option, and the complete type-mapping reference, see the full [Models documentation](https://tolki.abe.dev/ts/models.html).

## API resources

This package reads a `JsonResource`'s `toArray()` method statically and generates the interface from it, so you don't hand-maintain a second type for what your API already returns. See Laravel's [API Resources](https://laravel.com/docs/eloquent-resources).

```php
/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => EnumResource::make($this->role),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
        ];
    }
}
```

```typescript
import { type AsEnum } from '@tolki/ts';
import { Role } from '../enums';
import type { PostResource } from '.';

export interface UserResource {
    id: number;
    name: string;
    role: AsEnum<typeof Role> | null;
    posts?: PostResource[];
}
```

Key capabilities:

- **Model-aware type resolution** — property types come from the backing Eloquent model's database schema and casts, with the model resolved via `#[TsResource(model:)]`, `@mixin`, naming convention, or `#[UseResource]`.
- **Conditional methods** — `when()`, `unless()`, `whenLoaded()`, `whenHas()`, `whenAppended()`, `whenNotNull()`, `whenCounted()`, `whenAggregated()`, `whenExistsLoaded()`, `whenPivotLoaded()`, and `transform()` all become optional (`?`) properties, and passing an explicit default makes the property required.
- **Nested & collection resources** — `SomeResource::make()` / `::collection()` (or `new SomeResource(...)`) resolve to imported resource types, including self-references; a collection carrying `#[PreserveKeys]` or `$preserveKeys = true` emits `Record<string, R>` instead of `R[]`.
- **`merge()` / `mergeWhen()` / `mergeUnless()`, parent `toArray()` spreads, trait method spreads, and a bare `return $this->method()` (resolved transitively, the same as its `...$this->method()` spread form)** — all contribute properties, with types resolved from PHPDoc `@return array{...}` shapes or `#[TsCasts]`.
- **`EnumResource::make()`** — exposes an enum-cast property as `AsEnum<typeof Enum>` with automatic imports.
- **`#[TsResource]` / `#[TsCasts]` / `#[TsExclude]`** — override the interface name/model/description, override or add property types, or exclude a resource entirely. See [Excluding with `#[TsExclude]`](#excluding-with-tsexclude).
- **Smart nullable relations** — the same nullability-detection strategy used by [models](#models), with config to override the strategy per relation type.
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums and models.
- **Relation `only()` / `except()`** — `$this->relation->only([...])` / `->except([...])` references the related model's generated interface as `Pick<Model, 'a' | 'b'>` (`except()` picks the complement, every other column), keeping its `#[TsCasts]` and `@property` refinements, whenever the relation resolves to a single model **and** every filtered key is a real database column. Anything else expands inline, where `except()` yields **database columns only** — see [Relation Filters](https://tolki.abe.dev/ts/api-resources.html#relation-filters).
- **Resource inheritance** — a resource that extends another resource and declares no `toArray()` of its own inherits the parent's shape and its backing model, walking up to the nearest ancestor that declares each. The explicit `...parent::toArray($request)` spread and bare `return parent::toArray($request);` forms are unchanged and still idiomatic — see [Inheriting a Parent `toArray()`](https://tolki.abe.dev/ts/api-resources.html#inheriting-a-parent-toarray).
- **Model `toArray()` spreads** — `[...$user->toArray(), 'flag' => true]` types as `Omit<User, 'flag'> & { flag: boolean }` instead of collapsing to `unknown[]`, the `Omit<>` keeping PHP's later-key-wins from collapsing the collision to `never`. The arm references `{Model}` rather than re-deriving its shape, so a relation loaded before the spread is missing from the type and `$hidden` columns stay in it unless `models.exclude_hidden` is on — see [Model `toArray()` Spread](https://tolki.abe.dev/ts/api-resources.html#model-toarray-spread).
- **`toResource()` / `toResourceCollection()`** — both resolve through an explicit `SomeResource::class` argument, a `#[UseResource]` / `#[UseResourceCollection]` attribute, or Laravel's naming convention. Only the naming-convention guess is gated on this package actually emitting that resource, so an unpublished guess falls back to `unknown` instead of importing a file that never gets written — see [`toResource()` and `toResourceCollection()`](https://tolki.abe.dev/ts/api-resources.html#toresource-and-toresourcecollection).
- **Same-basename class aliasing** — when two classes in different namespaces share a class name (`App\Models\User` and `Crm\Models\User`), every occurrence of that name inside a single property's type now resolves to its own aliased import, in source order — see [Classes Sharing a Name Across Namespaces](https://tolki.abe.dev/ts/api-resources.html#classes-sharing-a-name-across-namespaces).

For every supported `toArray()` pattern, the full attribute reference, and nullable-relation strategies, see the full [API Resources documentation](https://tolki.abe.dev/ts/api-resources.html).

## Routes

Every controller action gets a functional route helper. The URL-building, parameter-binding, query-string, and form-spoofing logic lives in one `defineRoute()` factory from [`@tolki/ts`](https://tolki.abe.dev/ts/) rather than being generated inline for every route. The helpers are built to be spec-compliant with [Laravel Wayfinder](https://github.com/laravel/wayfinder) and work with Inertia the same way.

```typescript
// resources/js/types/data/app/http/controllers/post-controller.ts (generated)
import { defineRoute, annotateRequestPayload } from '@tolki/ts';
import type { UpdatePostRequest } from '../requests/update-post-request';

export const update = annotateRequestPayload<UpdatePostRequest>()(defineRoute({
    name: 'posts.update',
    url: '/posts/{post}',
    methods: ['put'] as const,
    args: [{ name: 'post', required: true, _routeKey: 'id' }] as const,
}));
```

```typescript
// Anywhere in your frontend
import { PostController } from '@js/types/data/app/http/controllers';

PostController.update({ post: 42 });           // { url: '/posts/42', method: 'put' }
PostController.update.form.put({ post: 42 });  // { action: '/posts/42', method: 'post' } — with `_method=PUT` spoofed
PostController.update(post);                   // pass the Post model instance directly
```

Key capabilities:

- **Structural typing** — model and enum route bindings are typed without importing the PHP model or enum class into the route file.
- **Multiple calling conventions** — named object, positional arguments, an array of positional arguments, or a bare model/scalar for single-parameter routes.
- **Query strings** — extra keys become query parameters automatically, with a `_query` escape hatch and a `mergeQuery` option for updating the current page's query string.
- **`.form()` helper** — builds `{ action, method }` for HTML forms, including Laravel's `_method` spoofing for `PUT`/`PATCH`/`DELETE`, and mapping `HEAD` to a plain GET form action (HTML forms can't submit `HEAD`).
- **Inertia integration** — page-prop types and the component name are inferred and attached automatically when `inertia.enabled` is on.
- **Inertia UI Table typing** — routes rendering an [Inertia UI Table](https://inertiaui.com/) get an automatically typed `TableResource<Model>` page prop, resolved by reflection and AST only so the table is never instantiated or serialized. Sibling actions on the same controller are typed normally.
- **Form Request payloads** — a controller method's `FormRequest` type-hint automatically attaches its generated interface to the route.
- **Filtering** — `#[TsExclude]`, wildcard/negation route-name patterns (`routes.only` / `routes.except`), middleware exclusion, and named-routes-only mode.

For every calling convention, model/enum binding rule, query-string behavior, route defaults, form-spoofing detail, and the Inertia/FormRequest typing helpers, see the full [Routing documentation](https://tolki.abe.dev/ts/routing.html).

## Form requests

A Form Request's `rules()` method is analyzed statically and becomes a TypeScript interface for the request payload, so you don't hand-maintain a second type for what your validation rules already define.

```php
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'rating' => ['nullable', 'numeric'],
            'tags' => ['array'],
            'tags.*' => ['string'],
            'order.items.*.sku' => ['required', 'string'],
        ];
    }
}
```

```typescript
import type { StorePostRequest } from '@js/types/data/form-requests';

// { title: string; rating?: number | null; tags?: string[]; order?: { items?: { sku: string }[] }; }
```

Key capabilities:

- **Rule-aware type inference** — scalar, array, `in:`/`Rule::in()`, `Rule::enum()`, `Rule::anyOf()`, file, and dozens of other rules resolve to the matching TypeScript type. `required_array_keys:a,b`, `in_array_keys:a,b`, `array:a,b`, and `array_keys:a,b` name an array's keys without a full nested shape, resolving to a keyed object (`config: { timezone?: unknown }`) instead of `unknown[]`.
- **Numeric `in:` literals** — a string-form `in:1,2,3` emits an unquoted `1 | 2 | 3` when a sibling rule declares the field numeric. The same list of rules now drives both that decision and the `number` mapping, so `decimal`, `digits`, and `digits_between` count alongside `integer`/`int`/`numeric` (`['digits:1', 'in:1,2,3']` → `1 | 2 | 3`). Coercion still only happens when the literal round-trips losslessly: `['decimal:2', 'in:1.50,2.50']` stays `'1.50' | '2.50'`, because Laravel's own `validateIn()` compares the raw string and would reject `2.5`.
- **Nested/wildcard composition** — `parent.*.child` and `parent.child` dot-notation rules compose recursively into their nearest undotted ancestor (`tags.*` → `tags: string[]`, `order.items.*.sku` → `order?: { items?: { sku: string }[] }`) instead of surviving as separate flat, quoted keys. Declaring the parent's own rules (e.g. `'order' => ['required', 'array']`) makes the composed key required instead of optional.
- **Presence & nullability** — `required`/`sometimes` control whether a field is optional (`?`), `nullable` adds `| null`, and `missing`/`prohibited` fields are excluded from the interface entirely.
- **`#[TsCasts]`** — override or add field types on the request class itself, the same attribute used by models and resources.
- **`#[TsExtends]`** — extend shared interfaces, the same mechanism used by models and resources. See [Extending interfaces](#extending-interfaces-with-tsextends--configs).
- **Dynamic fallback** — requests whose `rules()` can't be resolved without real HTTP context (e.g. reading `$this->user()->id` directly) fall back to `Record<string, unknown>` instead of failing the publish.
- **Route integration** — a controller action type-hinted to a `FormRequest` automatically gets its route export wrapped with `annotateRequestPayload<T>()`. See [Form Request Payload Types](https://tolki.abe.dev/ts/routing.html#form-request-payload-types).
- **`#[TsExclude]`** — exclude an entire request class from the output. See [Excluding with `#[TsExclude]`](#excluding-with-tsexclude).
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums, models, and resources.

For the full rule-to-type mapping, every JSDoc metadata annotation, and all attribute options, see the full [Form Requests documentation](https://tolki.abe.dev/ts/form-requests.html).

## Broadcast channels

Every channel name in `routes/channels.php` compiles into one `broadcast-channels.ts` file: a `BroadcastChannel` template-literal union, plus a `BroadcastChannels` const with a nested accessor for every dynamic segment. You never hand-type a `{placeholder}` channel string on the frontend.

```php
// routes/channels.php
Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return true;
});

Broadcast::channel('public-announcements', PublicAnnouncementsChannel::class);
```

```typescript
import { BroadcastChannels } from '@js/types/data/broadcast-channels';

BroadcastChannels.orders(42);               // 'orders.42'
BroadcastChannels["public-announcements"];  // 'public-announcements'
```

Key capabilities:

- **Dot-notation tree** — multi-segment channel names (`user.{userId}.notifications`) become nested accessor objects, matching Laravel's own dot-notation channel naming.
- **Both registration styles** — closure-based and class-based (`Broadcast::channel('name', ChannelClass::class)`) channels are collected identically, since only the channel name string drives the output.
- **`BroadcastChannel` type** — a template-literal union of every registered channel name, handy for typing a generic "subscribe to any channel" helper.
- **Single combined file** — unlike enums/models/resources/form requests, there's no per-item filtering or attributes; every registered channel is compiled into one `broadcast-channels.ts` output.

For the dot-notation tree algorithm, parameter typing, and quoted-key handling, see the full [Broadcast Channels documentation](https://tolki.abe.dev/ts/broadcast-channels.html).

## Broadcast events

Every `ShouldBroadcast` and `ShouldBroadcastNow` event gets its own interface, built from its `broadcastWith()` return shape or, when there is none, its public properties. A combined `broadcast-events.ts` index adds a `BroadcastEvent` union and a flat `BroadcastEvents` const of every Echo event name.

```php
class OrderShipped implements ShouldBroadcast
{
    public function __construct(
        public int $orderId,
        public string $trackingNumber,
        public string $carrier,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("orders.{$this->orderId}");
    }
}
```

```typescript
/** @see App\Events\OrderShipped */
export interface OrderShipped {
    orderId: number;
    trackingNumber: string;
    carrier: string;
}
```

Key capabilities:

- **`broadcastWith()` or public properties** — when present, `broadcastWith()`'s return shape drives the interface (handy for hiding private fields); otherwise both constructor-promoted and class-body public properties are used, with a `@var` docblock preferred over the native declaration. Every trait-declared property is skipped, `#[TsExtends]` traits included — their fields already arrive through the `extends` clause.
- **Model & enum-aware** — a property typed as an Eloquent model resolves to `Partial<Model>`, and a PHP enum property resolves to the enum's `{Name}Type` alias (honouring `#[TsEnum(name:)]`), both with automatic imports.
- **`broadcastAs()` support** — a custom Echo event name is used when `broadcastAs()` returns one whole string literal; a computed name (`'order.'.$this->kind`) falls back to Laravel's `.Fully.Qualified.ClassName` convention rather than shipping a half-built key.
- **`#[TsCasts]` / `#[TsExtends]`** — override property types or extend shared interfaces, the same attributes used by models, resources, and form requests.
- **`#[TsExclude]`** — exclude an entire event class from the output. See [Excluding with `#[TsExclude]`](#excluding-with-tsexclude).
- **Echo module augmentation** — optionally generates an `echo-broadcast-events.d.ts` file that augments `@laravel/echo`'s (or `@laravel/echo-vue`/`-react`/`-svelte`'s, auto-detected) `Events` interface for fully-typed `Echo.private(...).listen()` calls.
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums, models, and form requests.

For the full property-resolution rules, import-conflict aliasing, and Echo augmentation setup, see the full [Broadcast Events documentation](https://tolki.abe.dev/ts/broadcast-events.html).

## Inertia

With `inertia.enabled` on, the package reads your `HandleInertiaRequests` middleware's `share()` method and writes `inertia-config.d.ts`: a module augmentation for `@inertiajs/core` plus a global `Inertia.SharedData` type. Every Inertia page gets typed shared props with no manual typing.

```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => ['user' => $request->user()],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state'),
        ];
    }
}
```

```typescript
import type { User } from './app/models';

declare global {
    namespace Inertia {
        type SharedData = { name: string, auth: { user: User | null }, sidebarOpen: boolean };
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: { name: string, auth: { user: User | null }, sidebarOpen: boolean };
    }
}
```

Key capabilities:

- **Static `share()` analysis** — every key returned from `share()` is statically resolved to a TypeScript type, no running the app required. Both composition forms are read: a `...parent::share($request)` spread and `array_merge(parent::share($request), [...])`, up the whole middleware inheritance chain, with a later key overriding an earlier one exactly as PHP does.
- **`$request->user()` typed through your auth config** — resolved via `auth.defaults.guard` → its provider → that provider's model, so the prop is typed `User | null` with the model's import written for you. `auth()->user()`, `auth()->id()`, `Auth::user()` and `Auth::id()` resolve the same way, and `$request->url()`, `->path()`, `->integer()`, `->boolean()`, `->string()`, `->cookie()` and `->hasCookie()` are typed from Laravel's own signatures.
- **`config('some.key')`** — a literal key is typed from the live configuration value, since the package runs inside your booted application. A computed key stays `unknown`.
- **Inertia v2 prop wrappers** — `Inertia::defer()`, `optional()`, `lazy()`, `always()`, `merge()` and `deepMerge()` are typed as the value they wrap; the three that a partial reload can omit produce an optional key.
- **`errors` is left to Inertia** — `@inertiajs/core` already types `page.props.errors`, so the package never infers a weaker `errors` entry of its own. A `#[TsCasts]` or `@return` docblock entry for it still wins if you want one.
- **`#[TsCasts]` / `@return` docblock overrides** — override or add types for keys the analyzer can't infer on its own, the same `#[TsCasts]` attribute used everywhere else in the package.
- **`errorValueType`** — automatically added to the augmentation when the middleware's `$withAllErrors` property is `true`, matching Inertia's validation error bag shape.
- **Route-linked page props** — a related but separate piece: a controller action's `Inertia::render()` call gets its own page-prop type that intersects with `Inertia.SharedData`, threaded into that route's generated file automatically. See [Inertia Integration](https://tolki.abe.dev/ts/routing.html#inertia-integration) in the Routing docs.
- **Page props read the expression you wrote** — Eloquent finders (`Post::findOrFail($id)` → `Post`, `Post::find($id)` → `Post | null`, `User::all()` → `User[]`, `->paginate()` → `LengthAwarePaginator<Post>`), route-bound model parameters, `$request->user()`, the v2 prop wrappers, `compact('post', 'comments')`, `array_merge($base, [...])`, and a props array assigned from a ternary all type without an annotation. Two renders of the same component merge into one type, and a key only one branch sets becomes optional.
- **Preserve-keys resource collections** — a paginated `Inertia::render()` prop backed by a `#[PreserveKeys]`/`$preserveKeys` resource collection types its `data` member as `Record<string, T>`, matching Laravel's key-preserving JSON shape instead of the default array.
- **Inline paginators** — a paginator called directly inside the render array (`'teams' => new TeamCollection(Team::query()->paginate(10))`) is typed as a paginator, with no intermediate variable needed. `paginate()`, `simplePaginate()`, and `cursorPaginate()` are all recognised, in both the `new SomeCollection(...)` and `SomeResource::collection(...)` forms — see [Paginating Inline in the Render Call](https://tolki.abe.dev/ts/inertia.html#paginating-inline-in-the-render-call).
- **Degrades instead of aborting** — an action whose props can't be analyzed is reported as a warning after the run and typed as `Inertia.SharedData` alone, rather than failing the whole `ts:publish` run.

For the full middleware discovery rules, the type-override priority order, and the generated file anatomy, see the full [Inertia documentation](https://tolki.abe.dev/ts/inertia.html).

## Vite env

When `vite_env.enabled` is on, this package reads the `VITE_`-prefixed variables from your `.env` (or `.env.example`) file and generates a `vite-env.d.ts` that augments Vite's `ImportMetaEnv` interface, so `import.meta.env.VITE_APP_NAME` is typed without a hand-maintained declaration file.

```env
VITE_APP_NAME=MyApp
VITE_APP_URL=https://example.test
```

```typescript
/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string;
  readonly VITE_APP_URL: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
```

Key capabilities:

- **Automatic `VITE_` filtering** — only variables prefixed with `VITE_` are included, matching Vite's own convention for client-exposed environment variables.
- **`.env` with `.env.example` fallback** — reads `.env` first, falling back to `.env.example` when `.env` doesn't exist (useful in CI or fresh clones), or point it at a specific file with `vite_env.source_file`.
- **Always `string`** — every variable is typed as `string`, matching what Vite actually provides at runtime regardless of the value's apparent type.
- **Skips cleanly when empty** — no `VITE_`-prefixed variables found (or the source file doesn't exist) means no file is generated at all.

For the exact variable-parsing rules and source-file resolution order, see the full [Vite Env documentation](https://tolki.abe.dev/ts/vite-env.html).

## Extending interfaces with `#[TsExtends]` & configs

Sometimes a generated interface needs to extend a hand-written one, either for properties this package can't infer or to share common fields across many classes without duplication. The `#[TsExtends]` attribute (repeatable, and inherited from parent classes and traits) and the matching `ts_extends.*` config arrays both add to the generated interface's `extends` clause.

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('HasTimestamps', import: '@/types/common')]
#[TsExtends('Pick<Auditable, "created_by" | "updated_by">', import: '@/types/audit', types: ['Auditable'])]
class Warehouse extends Model
{
    // ...
}
```

```typescript
import type { Auditable } from '@/types/audit';
import type { HasTimestamps } from '@/types/common';

export interface Warehouse extends HasTimestamps, Pick<Auditable, "created_by" | "updated_by">
{
    // ... model properties
}
```

Key capabilities:

- **Works on models, resources, form requests, and broadcast events** — via `#[TsExtends]` and the matching `ts_extends.models` / `ts_extends.resources` / `ts_extends.form_requests` / `ts_extends.broadcast_events` config arrays.
- **Inherited from parent classes and traits** — an attribute on a base class or a trait used by several classes is picked up automatically and combined with the class's own attributes.
- **Repeatable** — stack multiple `#[TsExtends]` attributes on the same class, trait, or parent to extend several interfaces at once.
- **TypeScript helper support** — wrap the interface name in `Partial<>`, `Pick<>`, `Omit<>`, or any other generic, with `types` naming which identifiers need importing.
- **Automatic deduplication & conflict resolution** — the same extends clause reachable through multiple paths (e.g. a shared trait) is combined into one, and the same type name imported from two different paths is aliased automatically to avoid a collision.

For the full attribute reference, the trait/parent-class inheritance rules, and how naming conflicts are resolved, see the full [Extending Interfaces documentation](https://tolki.abe.dev/ts/extending-interfaces.html).

## Excluding with `#[TsExclude]`

`#[TsExclude]` keeps a whole class out of the TypeScript output, or just one of its methods, accessors, relations, or actions. It works on enums, models, resources, form requests, broadcast events, and controllers. It's especially useful alongside `enums.auto_include_methods` / `enums.auto_include_static_methods`, letting you opt a single method back out of an otherwise-automatic inclusion.

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

class User extends Model
{
    #[TsExclude]
    protected function secretToken(): Attribute
    {
        return Attribute::make(get: fn (): string => 'hidden');
    }
}
```

The `secretToken` accessor above never reaches the generated `User` interface. Everything else on the model still publishes.

Key capabilities:

- **Works everywhere** — enum classes/methods, model classes/accessors/relations, resource classes, form request classes, broadcast event classes, and controller classes/actions.
- **Always wins** — even when `#[TsEnumMethod]`, `#[TsEnumStaticMethod]`, or an auto-include config would otherwise include something, `#[TsExclude]` takes priority.
- **Class-level exclusion removes the class from collection entirely** — it won't appear in any generated output, index, or barrel file.
- **Member-level exclusion** — removes that one method, accessor, relation, or action. Everything else on the class still publishes.

For the full target reference and a worked example for every supported type, see the full [Excluding Content documentation](https://tolki.abe.dev/ts/excluding-content.html).

## Casing configurations

Three independent config options control the casing of generated names: `models.relationship_case` for model relations, `enums.method_case` for enum methods, and `routes.method_casing` for route actions. All three accept `'snake'`, `'camel'`, or `'pascal'`.

```php
// config/ts-publish.php

'models' => [
    'relationship_case' => 'snake', // default
],
'enums' => [
    'method_case' => 'camel', // default
],
'routes' => [
    'method_casing' => 'camel', // default
],
```

Key capabilities:

- **`models.relationship_case`** — controls relation names and their generated `_count` / `_exists` properties in model interfaces (default `'snake'`).
- **`enums.method_case`** — controls instance/static method key names in enum output (default `'camel'`); an individual method can still override its own name via the `name` parameter on `#[TsEnumMethod]` / `#[TsEnumStaticMethod]`.
- **`routes.method_casing`** — controls the casing of each generated route action's exported identifier (default `'camel'`); it only affects the generated variable name, never the underlying Laravel route name.
- **Independent settings** — each config option only affects its own feature; there's no single global casing setting.

For the full casing tables and worked examples for all three settings, see the full [Casing Configurations documentation](https://tolki.abe.dev/ts/casing-configuration.html).

## JSON enum HTTP API resource

`EnumResource` is a Laravel [JSON resource](https://laravel.com/docs/eloquent-resources.html) that turns any PHP enum case into a flat, API-friendly array. It runs through the same transformer pipeline as `ts:publish`, so every `#[TsEnumMethod]` and `#[TsEnumStaticMethod]` you configured appears in the response.

```php
use AbeTwoThree\LaravelTsPublish\EnumResource;
use App\Enums\Status;

return new EnumResource(Status::Published);
```

```json
{
    "name": "Published",
    "value": 1,
    "backed": true,
    "icon": "check",
    "color": "green"
}
```

Key capabilities:

- **Same pipeline as `ts:publish`** — only `#[TsEnumMethod]` / `#[TsEnumStaticMethod]` methods (or all public methods when auto-include is on) are included, using the same `enums.method_case` casing.
- **Works standalone or embedded** — instantiate directly (`new EnumResource($enum)`) for a top-level API response, or use `EnumResource::make()` inside another resource's `toArray()` to embed a rich enum object. See [Enum Properties with EnumResource](https://tolki.abe.dev/ts/api-resources.html#enum-properties-with-enumresource).
- **`AsEnum<T, V?>` from `@tolki/ts`** — the TypeScript type companion that matches this exact response shape, so you can type an API response that used `EnumResource`.
- **Auto-generated `{Model}Resource` interfaces** — any model with enum-cast columns automatically gets a companion set of interfaces using `AsEnum<>`, so you don't have to hand-compose `Omit` + `AsEnum` yourself.
- **Unit enum support** — enums without a backed type still work; `value` mirrors the case `name` and `backed` is `false`.

For the full response shape, unit enum behavior, and the auto-generated model resource interfaces, see the full [Enum API Resource documentation](https://tolki.abe.dev/ts/enum-api-resource.html).

## Modular publishing

Generated files always mirror your PHP namespace structure as a directory tree. There is no flat-output mode and no toggle to opt out. Modular and domain-driven apps (for example [InterNACHI/modular](https://github.com/InterNACHI/modular)) stay tidy, and a single-namespace app produces one `app/` tree.

```text
resources/js/types/data/
├── app/
│   ├── enums/
│   │   ├── role.ts
│   │   └── index.ts
│   ├── models/
│   │   ├── user.ts
│   │   └── index.ts
│   └── http/
│       └── resources/
│           ├── user-resource.ts
│           └── index.ts
├── accounting/
│   ├── enums/
│   │   ├── invoice-status.ts
│   │   └── index.ts
│   └── models/
│       ├── invoice.ts
│       └── index.ts
└── global.d.ts
```

Key capabilities:

- **Namespace-derived paths** — every class's PHP namespace (minus the class name itself) is kebab-cased segment-by-segment and joined into a directory path, e.g. `Accounting\Models\Invoice` → `accounting/models/invoice.ts`.
- **Automatic relative imports** — cross-namespace imports (e.g. a model importing a related model from another namespace) are computed as relative paths automatically; no path aliases required.
- **Per-namespace barrel files** — every namespace directory gets its own `index.ts` re-exporting everything inside it, so you can import from a namespace root instead of a specific file.
- **`namespace_strip_prefix`** — strip a common namespace prefix (e.g. `Modules\`) from the output path when your app already nests everything under one root namespace.
- **Applies to every feature** — models, enums, resources, form requests, broadcast events, and routes are all placed using the same namespace-derived path.

For the full kebab-casing algorithm, the relative-import-path rules, and the barrel file format, see the full [Modular Publishing documentation](https://tolki.abe.dev/ts/modular-publishing.html).

## Extending & customizing the pipeline

Every feature in this package runs through a **Collector → Generator → Transformer → Writer → Template** pipeline, though not every feature uses all five stages. Each stage is swappable per feature through the config file. Extend the built-in class, override the matching config key, and the rest of the pipeline keeps working as-is.

```php
// config/ts-publish.php

'models' => [
    'transformer_class' => App\TypeScript\CustomModelTransformer::class,
],
```

Key capabilities:

- **Every feature is customizable** — models, enums, resources, routes, form requests, broadcast channels, and broadcast events each expose their own `*.collector_class` / `*.generator_class` / `*.transformer_class` / `*.writer_class` config keys.
- **Abstract base classes** — `CoreCollector`, `CoreGenerator`, `CoreTransformer`, and `CoreWriter` define the exact method contract a custom class must implement.
- **Cache-compatible generators** — a custom `*.generator_class` can opt into the [generation cache](https://tolki.abe.dev/ts/generating-cache.html) with the `RehydratesFromCache` trait, the same way every built-in generator does.
- **Swap just the templates** — publish and edit the Blade templates directly with `php artisan vendor:publish --tag="laravel-ts-publish-views"` if you only need to change output formatting, without writing any PHP classes.

For the full per-feature pipeline-stage reference, every abstract base class's method contract, and the cache rehydration mechanics, see the full [Customizing the Pipeline documentation](https://tolki.abe.dev/ts/customizing-the-pipeline.html).

## Analyzer API

The same static analysis engine that powers every feature above is also available directly, outside the `ts:publish` pipeline. `AstEngine` takes a class and a method name and returns a `MethodAnalysis` DTO of typed properties, plus the enum/model/resource references needed to build imports for them. It's the same output a resource's `toArray()` produces, but callable directly from your own code — a custom Artisan command, a package that wants this package's own typing — without running a full publish.

```php
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;

$analysis = resolve(AstEngine::class)->analyzeMethod(App\Http\Resources\PostResource::class);

// $analysis->properties is the same typed property list `ts:publish` would generate for PostResource.
```

Key capabilities:

- **`analyzeMethod()`** — analyzes any method's return shape, not only `toArray()`; a `JsonResource` subclass still gets full resource semantics (conditional methods, `EnumResource`, nested resources, relation filters) with no extra setup.
- **`analyzePublicProperties()`** — reads a class's properties directly instead of a method body (promoted constructor parameters and class-body declarations), skipping anything a used trait declares. Nullability is always `| null`, never `?`.
- **`AnalysisImports::build()`** — turns a `MethodAnalysis`'s FQCN references into resolved import paths for one generated file, merging colliding paths; resolving a name collision between two imports is left to the caller.
- **Every feature runs on it** — broadcast events are `analyzeMethod($event, 'broadcastWith')` (or `analyzePublicProperties()` when the event has no such method) and Inertia shared data is `analyzeMethod($middleware, 'share')`, so calling either yourself returns exactly what `ts:publish` publishes.
- **Page props take the expression path** — Inertia page props run on this same engine, but from an `Inertia::render()` call's props argument rather than a method's return shape, so `analyzeMethod()` on a controller action gives you that method's return type instead. There is no public entry point for the expression path.

For the full walkthrough, including `MethodAnalysis`'s fields and the engine's limits, see the full [Analyzer API documentation](https://tolki.abe.dev/ts/analyzer-api.html).

## Pre-command hook

Register a closure with `LaravelTsPublish::callCommandUsing()` to run logic right before `ts:publish` executes, whether that is building directory lists, swapping pipeline classes, or reacting to feature flags. The closure only runs when the command actually runs, not at service provider boot time, so it never adds overhead to a normal request.

```php
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;

public function boot(): void
{
    LaravelTsPublish::callCommandUsing(function () {
        config()->set('ts-publish.models.additional_directories', [
            'modules/Blog/Models',
            'modules/Shop/Models',
        ]);
    });
}
```

Key capabilities:

- **Runs on every invocation** — a full `ts:publish`, a `--source=...` rerun, and a `--preview=true` run all trigger the hook identically, unconditionally, before any command flags are parsed.
- **Only one closure at a time** — calling `callCommandUsing()` again replaces the previous closure entirely; it doesn't stack.
- **Set any config, not just directories** — since it runs with the full config already loaded, the closure can set any `ts-publish.*` key, including swapping a `*_class` override (see [Customizing the Pipeline](https://tolki.abe.dev/ts/customizing-the-pipeline.html)).
- **Dynamic directory discovery** — a common pattern is scanning the filesystem (e.g. with Symfony Finder) or a package's own module registry to build `additional_directories` lists that stay in sync automatically as modules are added or removed.

For worked examples (modular package integration, conditional pipeline swaps, feature-flag-driven publishing), the exact invocation timing, and how to safely reset the hook between tests, see the full [Pre-Command Hook documentation](https://tolki.abe.dev/ts/pre-command-hook.html).

## Cache generation

After the first full publish, `ts:publish` can skip re-generating classes whose source files (and everything they depend on) haven't changed. The cache is busted automatically whenever the package version or your output-affecting config changes, and a class is only served from cache if every file it previously wrote still exists on disk.

```php
// config/ts-publish.php

'cache' => [
    'enabled' => env('TS_PUBLISH_CACHE_ENABLED', true),
    'store' => env('TS_PUBLISH_CACHE_STORE'),
    'directory' => storage_path('framework/cache/ts-publish'),
    'key' => env('TS_PUBLISH_CACHE_KEY'),
],
```

Key capabilities:

- **Content-based fingerprinting** — each class is fingerprinted over its own source file plus everything it depends on (parent classes, traits, interfaces, related models, and more); for routes, the route definitions themselves (URI, methods, name, middleware) are folded in too, since those live outside any class file.
- **`--fresh`** — forces a full rebuild, ignoring and regenerating the cache from scratch. A no-op under `--source` and `--preview=true`.
- **Always bypassed by `--source` and `--preview=true`** — single-class republishing and preview runs never read or write the cache.
- **File or Laravel cache store backend** — defaults to a signed file-based cache; point `cache.store` at any Laravel cache store (`redis`, `database`, …) to keep the manifest there instead, without ever touching keys outside this package's own.
- **HMAC-signed & tamper-resistant** — cache payloads are signed with your app key (or a dedicated `cache.key`) and deserialized with object instantiation disabled, so a corrupted or tampered cache file can never inject a PHP object.

For the full fingerprinting algorithm, the dependency-recording rules, the `ProvidesCacheSignature` extension point for custom generators, and both storage backends' internals, see the full [Cache Generation documentation](https://tolki.abe.dev/ts/generating-cache.html).

## Output options

This package provides several output formats that can be enabled independently:

| Config Key                    | Default | Description                                                                 |
|-------------------------------|---------|-----------------------------------------------------------------------------|
| `output_to_files`             | `true`  | Write individual `.ts` files with barrel `index.ts` exports                 |
| `globals.enabled`             | `false` | Generate a `global.d.ts` file with a global TypeScript namespace            |
| `json.enabled`                | `false` | Output all generated definitions as a JSON file                             |
| `watcher.enabled`             | `true`  | Output a JSON list of collected PHP file paths (useful for file watchers)   |

When `globals.enabled` is enabled, a global declaration file is created that makes all your types available without explicit imports:

```php
// config/ts-publish.php

'globals' => [
    'enabled' => true,
    'filename' => 'laravel-ts-global.d.ts',
],
'models' => [
    'namespace' => 'models',
],
'enums' => [
    'namespace' => 'enums',
],
```

When `json.enabled` is enabled, a `laravel-ts-definitions.json` file is written alongside the generated `.ts` files, containing every collected model, enum, resource, form request, and broadcast event as structured data (columns, cases, properties, and so on) rather than TypeScript source:

```php
// config/ts-publish.php

'json' => [
    'enabled' => true,
    'filename' => 'laravel-ts-definitions.json',
],
```

The file has one top-level object per feature (`models`, `enums`, `resources`, `formRequests`, `broadcastEvents`), and **every one of them is keyed by fully-qualified class name** (`"Workbench\\App\\Models\\User"`), not by short class name. Each entry carries a `name` field holding the short name that used to be the key. Keying by FQCN is deliberate: two classes sharing a basename across namespaces (`App\Models\User` and `Crm\Models\User`) are common in larger apps, and a short-name key silently overwrites one with the other. Key your lookups by FQCN and read `name` for display. **This is a breaking change** for anything written against the older bare-name-keyed file.

The JSON output from `watcher.enabled` is designed to work with build tools and file watchers (like the [@tolki/ts Vite plugin](https://tolki.abe.dev/ts/vite-plugin.html)) that need to know which PHP source files were collected so they can trigger a re-publish when those files change. When model metadata uses a custom provider, its PHP file is included because changing it can affect every metadata companion.

## Configuration reference

Every configuration option lives in `config/ts-publish.php`, organized by feature (`models.*`, `enums.*`, `routes.*`, `cache.*`, and so on). Publish the config file to customize any of it:

```bash
php artisan vendor:publish --tag="ts-publish-config"
```

For the full list of every configuration key, its type, default, and description, see the complete [Configuration Reference](https://tolki.abe.dev/ts/configuration-reference.html).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Abraham Arango](https://github.com/abetwothree)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Laravel TypeScript Enums, Models, & Resources Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/abetwothree/laravel-ts-publish)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Coverage](assets/coverage.svg)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)

<p align="center"><img src="./assets/laravel-typescript-publish-logo-short.svg" width="50%" alt="Laravel TypeScript Publisher Logo"></p>

This is an extremely flexible package that allows you to transform Laravel PHP models, enums, API resources, and other cast classes into TypeScript declaration types.

Enums are treated as functional objects with support for PHP-like enum functions and the inclusion of your custom methods in your enums.

Every Laravel application is different, and this package aims to provide the tools to tailor TypeScript types to your specific needs while providing powerful backend & frontend tooling to keep your frontend types in sync with your backend PHP code.

For examples of the generated TypeScript output, see [these output examples](workbench/resources/js/types/).

## Table of Contents

- 📦 [Installation](#installation)
- 🚀 [Usage](#usage)
- 🏷️ [Enums](#enums)
- 🗃️ [Models](#models)
- 📡 [API Resources](#api-resources)
- 🚗 [Routes](#routes)
- 📝 [Form Requests](#form-requests)
- 📡 [Broadcast Channels](#broadcast-channels)
- 🎤 [Broadcast Events](#broadcast-events)
- 🌉 [Inertia](#inertia)
- 🔑 [Vite Env](#vite-env)
- 🧬 [Extending Interfaces](#extending-interfaces-with-tsextends--configs)
- ❌ [Excluding Content](#excluding-with-tsexclude)
- 🔤 [Casing Configurations](#casing-configurations)
- 🌐 [Enum API Resource](#json-enum-http-api-resource)
- 📂 [Modular Publishing](#modular-publishing)
- 🔧 [Customizing the Pipeline](#extending--customizing-the-pipeline)
- ⚡ [Pre-Command Hook](#pre-command-hook)
- 💾 [Cache Generation](#cache-generation)
- 📤 [Output Options](#output-options)
- ⚙️ [Configuration Reference](#configuration-reference)

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

### Publishing Types

You can publish your TypeScript declaration types using the `ts:publish` Artisan command:

```bash
php artisan ts:publish
```

By default, the generated TypeScript declaration types will be saved to the `resources/js/types/data/` directory and follow default configuration settings.

Additionally, by default, the package will look for models in the `app/Models` directory, enums in the `app/Enums` directory, and API resources in the `app/Http/Resources` directory. You can customize these settings in the published configuration file.

For a full installation and setup guide, see the [Installation & Setup](https://tolki.abe.dev/ts/) documentation.

#### Preview Mode

You can preview the generated TypeScript output in the console without writing any files by using the `--preview` flag:

```bash
php artisan ts:publish --preview
```

This is useful for debugging or reviewing what will be generated before committing to file output.

#### Single-File Republishing

You can republish a single enum, model, or resource instead of the entire set by using the `--source` option with a fully-qualified class name or file path:

```bash
php artisan ts:publish --source="App\Enums\Status"
php artisan ts:publish --source="app/Enums/Status.php"
php artisan ts:publish --source="App\Http\Resources\UserResource"
```

This is significantly faster than a full publish on large projects and is used automatically by the [Vite plugin](https://tolki.abe.dev/ts/vite-plugin) to republish only the file that changed during development.

#### Automatic Publishing After Migrations

By default, this package will automatically re-publish your TypeScript declaration types after running migrations. This ensures your TypeScript types stay in sync with your database schema changes.

You can disable this behavior in the config file or via environment variable:

```php
// config/ts-publish.php

'run_after_migrate' => false,
```

```env
TS_PUBLISH_RUN_AFTER_MIGRATE=false
```

#### Filtering Models, Enums & Resources

You can fully customize which models, enums, and resources are included or excluded, and add additional directories to search in. By default, all models in `app/Models`, all enums in `app/Enums`, and all resources in `app/Http/Resources` are included.

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

The same options are available for enums with `enums.included`, `enums.excluded`, and `enums.additional_directories`, and for resources with `resources.included`, `resources.excluded`, and `resources.additional_directories`.

> [!TIP]
> Include and exclude settings accept both fully-qualified class names and directory paths. When a directory is provided, all matching classes within it will be discovered automatically.

#### Conditional Publishing

You can choose to publish only enums, only models, or only resources, either through configuration or command flags.

##### Via Configuration

Disable enum, model, or resource publishing entirely in the config file:

```php
// config/ts-publish.php

'enums' => ['enabled' => true],
'models' => ['enabled' => true],
'resources' => ['enabled' => true],
```

Setting any to `false` will skip that type on every run, including automatic post-migration publishing.

##### Via Command Flags

Use the `--only-enums`, `--only-models`, or `--only-resources` flags to limit a single run:

```bash
php artisan ts:publish --only-enums
php artisan ts:publish --only-models
php artisan ts:publish --only-resources
```

These flags cannot be combined — passing any two together will return an error.

##### Config & Flag Conflicts

When a command flag requests a type that is disabled in config (e.g. `--only-enums` while `enums.enabled` is `false`), the command will prompt you to confirm whether to override the config setting. In non-interactive environments (CI, queued jobs, post-migration hooks), the config value is respected and the command exits gracefully.

If all types end up disabled (all config values are `false` and no override flag is given), the command prints a warning and exits with a success status.

#### Verbosity Levels

The `ts:publish` command supports three verbosity levels using the standard Artisan verbosity flags:

| Flag | Output |
|------|--------|
| `--quiet` / `-q` | No output at all — only the exit code indicates success or failure. Ideal for automated tooling like the [Vite plugin](https://tolki.abe.dev/ts/vite-plugin). |
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

In quiet mode, files are still generated normally — only console output is suppressed. The [Vite plugin](https://tolki.abe.dev/ts/vite-plugin) passes `--quiet` by default since it only needs the exit code.

## Enums

PHP enums are transformed into functional TypeScript objects — not just a union of values, but PHP-like behavior (`.from()`, `.tryFrom()`, `.cases()`) powered by [`@tolki/ts`](https://tolki.abe.dev/ts/), plus any of your own enum methods and static methods can be included in the output.

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
- **`#[TsExclude]`** — exclude an entire enum or specific methods from the output. See [Excluding with TsExclude](#excluding-with-tsexclude).
- **`EnumResource`** — an HTTP JSON resource for returning flattened, instance-specific enum data from your API routes. See [JSON Enum HTTP API Resource](#json-enum-http-api-resource).

For every attribute option, the metadata/`@tolki/ts` integration, the Vite plugin, and the full behavior of auto-including methods, see the full [Enums documentation](https://tolki.abe.dev/ts/enums).

## Models

Laravel Eloquent models are converted into TypeScript interfaces for their properties, mutators, and relations — split into focused interfaces by default so a page only needs to import the parts of a model it actually uses.

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

Key capabilities:

- **Split or full templates** — `models.template` controls whether properties/mutators/relations are generated as separate interfaces (default) or combined into one `model-full` interface.
- **Smart nullable relations** — singular relations (`HasOne`, `BelongsTo`, `MorphOne`, ...) are automatically typed with `| null` based on the relation type and foreign key nullability, with a config to override the strategy per relation type.
- **`#[TsCasts]` / `#[TsType]`** — override or add TypeScript types for columns, mutators, relations, or an entire custom cast class, including custom types imported from your own files.
- **`#[TsExclude]`** — exclude an entire model, or a specific accessor/relation, from the output.
- **PHPDoc-aware** — class, column, mutator, and relation doc blocks are carried over as JSDoc comments automatically.
- **Enum-typed columns** also generate a matching `{Model}Resource` interface using `AsEnum<>`, for when you've resolved a raw enum column to a full enum instance (e.g. via `Status.from(user.status)`).
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums and resources.

For the full template comparison, nullable relation strategies, every attribute option, and the complete type-mapping reference, see the full [Models documentation](https://tolki.abe.dev/ts/models).

## API Resources

This package generates TypeScript interfaces from your Laravel [API Resources](https://laravel.com/docs/eloquent-resources) (`JsonResource` classes) by statically analyzing the `toArray()` method — no need to hand-maintain a separate type for what your API already returns.

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
- **Conditional methods** — `when()`, `whenLoaded()`, `whenHas()`, `whenNotNull()`, `whenCounted()`, `whenAggregated()`, and `whenPivotLoaded()` all become optional (`?`) properties.
- **Nested & collection resources** — `SomeResource::make()` / `::collection()` (or `new SomeResource(...)`) resolve to imported resource types, including self-references.
- **`merge()` / `mergeWhen()`, parent `toArray()` spreads, and trait method spreads** — all contribute properties, with types resolved from PHPDoc `@return array{...}` shapes or `#[TsCasts]`.
- **`EnumResource::make()`** — exposes an enum-cast property as `AsEnum<typeof Enum>` with automatic imports.
- **`#[TsResource]` / `#[TsCasts]` / `#[TsExclude]`** — override the interface name/model/description, override or add property types, or exclude a resource entirely. See [Excluding with TsExclude](#excluding-with-tsexclude).
- **Smart nullable relations** — the same nullability-detection strategy used by [models](#models), with config to override the strategy per relation type.
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums and models.

For every supported `toArray()` pattern, the full attribute reference, and nullable-relation strategies, see the full [API Resources documentation](https://tolki.abe.dev/ts/api-resources).

## Routes

This package publishes a lightweight, functional route helper for every controller action in your app — matching the feature set of [Laravel Wayfinder](https://github.com/laravel/wayfinder), but with all the URL-building, parameter-binding, query-string, and form-spoofing logic tucked away inside a single `defineRoute()` factory from [`@tolki/ts`](https://tolki.abe.dev/ts/) instead of being generated inline for every route.

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

- **Structural typing** — model and enum route bindings are fully typed without ever importing the PHP model or enum class into the route file.
- **Multiple calling conventions** — named object, positional arguments, an array of positional arguments, or a bare model/scalar for single-parameter routes.
- **Query strings** — extra keys become query parameters automatically, with a `_query` escape hatch and a `mergeQuery` option for updating the current page's query string.
- **`.form()` helper** — builds `{ action, method }` for HTML forms, including Laravel's `_method` spoofing for `PUT`/`PATCH`/`DELETE`.
- **Inertia integration** — page-prop types and the component name are inferred and attached automatically when `inertia.enabled` is on.
- **Inertia UI Table typing** — routes rendering an [Inertia UI Table](https://inertiaui.com/) get an automatically typed `TableResource<Model>` page prop without evaluating the table, with table-tainted controllers safely falling back instead of erroring.
- **Form Request payloads** — a controller method's `FormRequest` type-hint automatically attaches its generated interface to the route.
- **Filtering** — `#[TsExclude]`, wildcard/negation route-name patterns (`routes.only` / `routes.except`), middleware exclusion, and named-routes-only mode.

For every calling convention, model/enum binding rule, query-string behavior, route defaults, form-spoofing detail, and the Inertia/FormRequest typing helpers, see the full [Routing documentation](https://tolki.abe.dev/ts/routing).

## Form Requests

Laravel Form Request `rules()` methods are statically analyzed and converted into a TypeScript interface describing the request payload — no need to hand-maintain a separate type for what your validation rules already define.

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
        ];
    }
}
```

```typescript
import type { StorePostRequest } from '@js/types/data/form-requests';

// { title: string; rating?: number | null; tags?: string[]; "tags.*"?: string; }
```

Key capabilities:

- **Rule-aware type inference** — scalar, array, `in:`/`Rule::in()`, `Rule::enum()`, `Rule::anyOf()`, file, and dozens of other rules resolve to the matching TypeScript type, including nested/wildcard (`tags.*`) array element rules.
- **Presence & nullability** — `required`/`sometimes` control whether a field is optional (`?`), `nullable` adds `| null`, and `missing`/`prohibited` fields are excluded from the interface entirely.
- **`#[TsCasts]`** — override or add field types on the request class itself, the same attribute used by models and resources.
- **`#[TsExtends]`** — extend shared interfaces, the same mechanism used by models and resources. See [Extending Interfaces](#extending-interfaces-with-tsextends--configs).
- **Dynamic fallback** — requests whose `rules()` can't be resolved without real HTTP context (e.g. reading `$this->user()->id` directly) fall back to `Record<string, unknown>` instead of failing the publish.
- **Route integration** — a controller action type-hinted to a `FormRequest` automatically gets its route export wrapped with `annotateRequestPayload<T>()`. See [Form Request Payload Types](https://tolki.abe.dev/ts/routing#form-request-payload-types).
- **`#[TsExclude]`** — exclude an entire request class from the output. See [Excluding with TsExclude](#excluding-with-tsexclude).
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums, models, and resources.

For the full rule-to-type mapping, every JSDoc metadata annotation, and all attribute options, see the full [Form Requests documentation](https://tolki.abe.dev/ts/form-requests).

## Broadcast Channels

Every channel name registered in `routes/channels.php` is compiled into a single `broadcast-channels.ts` file — a `BroadcastChannel` template-literal type union, plus a `BroadcastChannels` const with a nested accessor function for every dynamic segment, so you never hand-type a `{placeholder}` channel string on the frontend.

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

For the dot-notation tree algorithm, parameter typing, and quoted-key handling, see the full [Broadcast Channels documentation](https://tolki.abe.dev/ts/broadcast-channels).

## Broadcast Events

Every `ShouldBroadcast` / `ShouldBroadcastNow` event class gets its own TypeScript interface — generated from its `broadcastWith()` return shape, or its public constructor properties when there's no `broadcastWith()` — plus a combined `broadcast-events.ts` index with a `BroadcastEvent` union type and a flat `BroadcastEvents` const of every Echo event name.

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

- **`broadcastWith()` or public properties** — when present, `broadcastWith()`'s return shape drives the interface (handy for hiding private fields); otherwise every public constructor-promoted property is used.
- **Model & enum-aware** — a property typed as an Eloquent model resolves to `Partial<Model>`, and a PHP enum property resolves to the enum's `{Name}Type` alias, both with automatic imports.
- **`broadcastAs()` support** — a custom Echo event name from `broadcastAs()` is used as-is; otherwise the Echo name defaults to Laravel's `.Fully.Qualified.ClassName` convention.
- **`#[TsCasts]` / `#[TsExtends]`** — override property types or extend shared interfaces, the same attributes used by models, resources, and form requests.
- **`#[TsExclude]`** — exclude an entire event class from the output. See [Excluding with TsExclude](#excluding-with-tsexclude).
- **Echo module augmentation** — optionally generates an `echo-broadcast-events.d.ts` file that augments `@laravel/echo`'s (or `@laravel/echo-vue`/`-react`/`-svelte`'s, auto-detected) `Events` interface for fully-typed `Echo.private(...).listen()` calls.
- **Filtering** — the same `included` / `excluded` / `additional_directories` config pattern used by enums, models, and form requests.

For the full property-resolution rules, import-conflict aliasing, and Echo augmentation setup, see the full [Broadcast Events documentation](https://tolki.abe.dev/ts/broadcast-events).

## Inertia

When `inertia.enabled` is on, this package analyzes your `HandleInertiaRequests` middleware's `share()` method and generates `inertia-config.d.ts` — a module augmentation for `@inertiajs/core` plus a global `Inertia.SharedData` type, so every Inertia page automatically has fully-typed shared props with no manual typing.

```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user()],
        ];
    }
}
```

```typescript
declare global {
    namespace Inertia {
        type SharedData = { auth: { user: { id: number; name: string; email: string } | null }; /* ... */ };
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: Inertia.SharedData;
    }
}
```

Key capabilities:

- **Static `share()` analysis** — every key returned from `share()` (including a spread `...parent::share($request)`) is statically resolved to a TypeScript type, no running the app required.
- **`#[TsCasts]` / `@return` docblock overrides** — override or add types for keys Surveyor can't infer on its own, the same `#[TsCasts]` attribute used everywhere else in the package.
- **`errorValueType`** — automatically added to the augmentation when the middleware's `$withAllErrors` property is `true`, matching Inertia's validation error bag shape.
- **Route-linked page props** — a related but separate piece: a controller action's `Inertia::render()` call gets its own page-prop type that intersects with `Inertia.SharedData`, threaded into that route's generated file automatically. See [Inertia Integration](https://tolki.abe.dev/ts/routing#inertia-integration) in the Routing docs.

For the full middleware discovery rules, the type-override priority order, and the generated file anatomy, see the full [Inertia documentation](https://tolki.abe.dev/ts/inertia).

## Vite Env

When `vite_env.enabled` is on, this package reads the `VITE_`-prefixed variables from your `.env` (or `.env.example`) file and generates a `vite-env.d.ts` declaration file that augments Vite's `ImportMetaEnv` interface — so `import.meta.env.VITE_APP_NAME` is fully typed without hand-maintaining a separate declaration file.

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

For the exact variable-parsing rules and source-file resolution order, see the full [Vite Env documentation](https://tolki.abe.dev/ts/vite-env).

## Extending Interfaces with `#[TsExtends]` & Configs

Sometimes a generated interface needs to extend a hand-written TypeScript interface — for properties this package can't infer, or to share common fields across many models, resources, form requests, or broadcast events without duplication. The `#[TsExtends]` attribute (repeatable, and inherited from parent classes and traits) and the matching `ts_extends.*` config arrays both add to the generated interface's `extends` clause.

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

For the full attribute reference, the trait/parent-class inheritance rules, and how naming conflicts are resolved, see the full [Extending Interfaces documentation](https://tolki.abe.dev/ts/extending-interfaces).

## Excluding with `#[TsExclude]`

The `#[TsExclude]` attribute excludes a specific enum, model, resource, form request, broadcast event, or controller — or one of their individual methods/accessors/relations/actions — from the TypeScript output. It's especially useful alongside `enums.auto_include_methods` / `enums.auto_include_static_methods`, letting you opt a single method back out of an otherwise-automatic inclusion.

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

The `secretToken` accessor above is entirely omitted from the generated `User` interface — everything else on the model still publishes normally.

Key capabilities:

- **Works everywhere** — enum classes/methods, model classes/accessors/relations, resource classes, form request classes, broadcast event classes, and controller classes/actions.
- **Always wins** — even when `#[TsEnumMethod]`, `#[TsEnumStaticMethod]`, or an auto-include config would otherwise include something, `#[TsExclude]` takes priority.
- **Class-level exclusion removes the class from collection entirely** — it won't appear in any generated output, index, or barrel file.
- **Method/accessor/relation/action-level exclusion** only removes that one member — everything else on the class still publishes normally.

For the full target reference and a worked example for every supported type, see the full [Excluding Content documentation](https://tolki.abe.dev/ts/excluding-content).

## Casing Configurations

This package provides three independent config options to control the casing of generated property and method names — `models.relationship_case` for model relationship names, `enums.method_case` for enum method names, and `routes.method_casing` for route action names. All three accept `'snake'`, `'camel'`, or `'pascal'`.

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

For the full casing tables and worked examples for all three settings, see the full [Casing Configurations documentation](https://tolki.abe.dev/ts/casing-configuration).

## JSON Enum HTTP API Resource

This package ships with `EnumResource` — a Laravel [JSON resource](https://laravel.com/docs/eloquent-resources) that transforms any PHP enum case into a flat, API-friendly array, running it through the same transformer pipeline used for TypeScript publishing so every `#[TsEnumMethod]` / `#[TsEnumStaticMethod]` you've configured is automatically included in the response.

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
- **Works standalone or embedded** — instantiate directly (`new EnumResource($enum)`) for a top-level API response, or use `EnumResource::make()` inside another resource's `toArray()` to embed a rich enum object. See [Enum Properties with EnumResource](https://tolki.abe.dev/ts/api-resources#enum-properties-with-enumresource).
- **`AsEnum<T, V?>` from `@tolki/ts`** — the TypeScript type companion that matches this exact response shape, letting you fully type an API response that used `EnumResource`.
- **Auto-generated `{Model}Resource` interfaces** — any model with enum-cast columns automatically gets a companion set of interfaces using `AsEnum<>`, so you don't have to hand-compose `Omit` + `AsEnum` yourself.
- **Unit enum support** — enums without a backed type still work; `value` mirrors the case `name` and `backed` is `false`.

For the full response shape, unit enum behavior, and the auto-generated model resource interfaces, see the full [Enum API Resource documentation](https://tolki.abe.dev/ts/enum-api-resource).

## Modular Publishing

Generated TypeScript files are always organized into namespace-derived directory trees that mirror your PHP namespace structure — there is no flat-output mode or config toggle to opt out of. This keeps output tidy for modular/domain-driven applications (e.g. [InterNACHI/modular](https://github.com/InterNACHI/modular)), while a single-namespace app (just `App\Models`, `App\Enums`, etc.) simply produces one `app/` directory tree.

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

For the full kebab-casing algorithm, the relative-import-path rules, and the barrel file format, see the full [Modular Publishing documentation](https://tolki.abe.dev/ts/modular-publishing).

## Extending & Customizing the Pipeline

Every feature in this package runs through a **Collector → Generator → Transformer → Writer → Template** pipeline (or a subset of those stages — not every feature uses all five), and each stage is swappable per-feature via the config file. Extend the built-in class, override the matching config key, and the rest of the pipeline keeps working as-is.

```php
// config/ts-publish.php

'models' => [
    'transformer_class' => App\TypeScript\CustomModelTransformer::class,
],
```

Key capabilities:

- **Every feature is customizable** — models, enums, resources, routes, form requests, broadcast channels, and broadcast events each expose their own `*.collector_class` / `*.generator_class` / `*.transformer_class` / `*.writer_class` config keys.
- **Abstract base classes** — `CoreCollector`, `CoreGenerator`, `CoreTransformer`, and `CoreWriter` define the exact method contract a custom class must implement.
- **Cache-compatible generators** — a custom `*.generator_class` can opt into the [generation cache](https://tolki.abe.dev/ts/generating-cache) with the `RehydratesFromCache` trait, the same way every built-in generator does.
- **Swap just the templates** — publish and edit the Blade templates directly with `php artisan vendor:publish --tag="laravel-ts-publish-views"` if you only need to change output formatting, without writing any PHP classes.

For the full per-feature pipeline-stage reference, every abstract base class's method contract, and the cache rehydration mechanics, see the full [Customizing the Pipeline documentation](https://tolki.abe.dev/ts/customizing-the-pipeline).

## Pre-Command Hook

Register a closure with `LaravelTsPublish::callCommandUsing()` to run custom logic right before `ts:publish` executes — dynamically configuring directories, swapping pipeline classes, or reacting to feature flags. The closure only runs when the command actually runs, not at service provider boot time, so it never adds overhead to a normal request.

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

- **Runs on every invocation** — a full `ts:publish`, a `--source=...` rerun, and a `--preview` run all trigger the hook identically, unconditionally, before any command flags are parsed.
- **Only one closure at a time** — calling `callCommandUsing()` again replaces the previous closure entirely; it doesn't stack.
- **Set any config, not just directories** — since it runs with the full config already loaded, the closure can set any `ts-publish.*` key, including swapping a `*_class` override (see [Customizing the Pipeline](https://tolki.abe.dev/ts/customizing-the-pipeline)).
- **Dynamic directory discovery** — a common pattern is scanning the filesystem (e.g. with Symfony Finder) or a package's own module registry to build `additional_directories` lists that stay in sync automatically as modules are added or removed.

For worked examples (modular package integration, conditional pipeline swaps, feature-flag-driven publishing), the exact invocation timing, and how to safely reset the hook between tests, see the full [Pre-Command Hook documentation](https://tolki.abe.dev/ts/pre-command-hook).

## Cache Generation

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
- **`--fresh`** — forces a full rebuild, ignoring and regenerating the cache from scratch. A no-op under `--source` and `--preview`.
- **Always bypassed by `--source` and `--preview`** — single-class republishing and preview runs never read or write the cache.
- **File or Laravel cache store backend** — defaults to a signed file-based cache; point `cache.store` at any Laravel cache store (`redis`, `database`, …) to keep the manifest there instead, without ever touching keys outside this package's own.
- **HMAC-signed & tamper-resistant** — cache payloads are signed with your app key (or a dedicated `cache.key`) and deserialized with object instantiation disabled, so a corrupted or tampered cache file can never inject a PHP object.

For the full fingerprinting algorithm, the dependency-recording rules, the `ProvidesCacheSignature` extension point for custom generators, and both storage backends' internals, see the full [Cache Generation documentation](https://tolki.abe.dev/ts/generating-cache).

## Output Options

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

The JSON output from `watcher.enabled` is designed to work with build tools and file watchers (like the [@tolki/ts Vite plugin](https://tolki.abe.dev/ts/vite-plugin)) that need to know which PHP source files were collected so they can trigger a re-publish when those files change.

## Configuration Reference

Every configuration option lives in `config/ts-publish.php`, organized by feature (`models.*`, `enums.*`, `routes.*`, `cache.*`, and so on). Publish the config file to customize any of it:

```bash
php artisan vendor:publish --tag="ts-publish-config"
```

For the full list of every configuration key, its type, default, and description, see the complete [Configuration Reference](https://tolki.abe.dev/ts/configuration-reference).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Abraham Arango](https://github.com/abetwothree)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

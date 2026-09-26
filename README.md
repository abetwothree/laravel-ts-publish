# Generate TypeScript types from your Laravel models, enums, resources, routes & events

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/abetwothree/laravel-ts-publish)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![PHP Compatibility](https://badge.laravel.cloud/php-badge/abetwothree/laravel-ts-publish)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![Laravel Boost](https://badge.laravel.cloud/boost-badge.svg)](https://github.com/laravel/boost)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Coverage](assets/coverage.svg)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)

<p align="center"><img src="./assets/laravel-typescript-publish-logo-short.svg" width="50%" alt="Laravel TypeScript Publisher Logo"></p>

This package generates TypeScript from your Laravel app: model and API resource interfaces, enums, route helpers, form request payloads, broadcast channels and events, Inertia props, and Vite env variables.

Enums and routes become functional objects. Enums get PHP-like `.from()`, `.tryFrom()`, and `.cases()`, and can include your own methods.

You can override anything the package infers. By default, types republish after `migrate`, and the `@tolki/ts` Vite plugin republishes them when your PHP changes, so your frontend stays in sync.

To see what the package writes, browse the [generated output examples](workbench/resources/js/types/data/default-example).

## Also by me

- [Laravel Iconify API & Icon Rendering](https://github.com/abetwothree/laravel-iconify-api)
- [Tolki JS NPM packages](https://github.com/abetwothree/tolki)

## Table of contents

- [Installation](#installation)
- [Usage](#usage)
- [Enums](#enums)
- [Models](#models)
- [Model metadata](#model-metadata)
- [API resources](#api-resources)
- [Routes](#routes)
- [Form requests](#form-requests)
- [Broadcast channels](#broadcast-channels)
- [Broadcast events](#broadcast-events)
- [Inertia](#inertia)
- [Vite env](#vite-env)
- [Extending interfaces](#extending-interfaces-with-tsextends--configs)
- [Excluding content](#excluding-with-tsexclude)
- [Casing configurations](#casing-configurations)
- [Enum API resource](#json-enum-http-api-resource)
- [Modular publishing](#modular-publishing)
- [Customizing the pipeline](#extending--customizing-the-pipeline)
- [Analyzer API](#analyzer-api)
- [Pre-command hook](#pre-command-hook)
- [Cache generation](#cache-generation)
- [Output options](#output-options)
- [Configuration reference](#configuration-reference)

## Installation

The package requires PHP 8.4+ and Laravel 12 or 13. Install it with Composer:

```bash
composer require abetwothree/laravel-ts-publish
```

Publish the config file:

```bash
php artisan vendor:publish --tag="ts-publish-config"
```

To edit the Blade templates that render each file, also publish the views with `php artisan vendor:publish --tag="laravel-ts-publish-views"`.

[Installation & Usage](https://tolki.abe.dev/ts/) covers installing `@tolki/ts`, setting up import aliases, and adding the Vite plugin. If you're upgrading from an earlier version, follow the [upgrade guide](https://tolki.abe.dev/ts/upgrade-guide.html).

## Usage

Run the `ts:publish` Artisan command to generate your types:

```bash
php artisan ts:publish
```

It finds classes in Laravel's standard directories, such as `app/Models` and `app/Enums`, and writes to `resources/js/types/data/`.

These options cover the common cases:

| Option | Effect |
| --- | --- |
| `--fresh` | Rebuilds every file. Without it, a run rebuilds only the classes whose source changed. |
| `--preview=true` | Prints the output without writing files. A bare `--preview` doesn't preview, and writes real files. |
| `--source=` | Republishes one class, given as a class name or a file path. |
| `--only-enums`, `--only-models`, and the other `--only-*` flags | Publishes one feature for this run. These flags can't be combined with each other. |
| `--only-functional` | Publishes every enabled feature except model and resource interfaces. It overrides any other `--only-*` flag. |
| `-v`, `--quiet` | `-v` adds detailed tables of the published classes and extra files. `--quiet` prints only errors. |

By default, types republish after each `migrate` that runs migrations. To turn this off, set `run_after_migrate` to `false`, or `TS_PUBLISH_RUN_AFTER_MIGRATE=false` in `.env`.

Models, enums, resources, form requests, and broadcast events each take `included`, `excluded`, and `additional_directories` settings to choose which classes publish. For those settings, partial runs, output files, and console output, see the [Publishing Types documentation](https://tolki.abe.dev/ts/publishing.html).

## Enums

PHP enums become TypeScript objects that behave like the PHP enum, with `.from()`, `.tryFrom()`, and `.cases()` from [`@tolki/ts`](https://tolki.abe.dev/ts/). This enum publishes its cases and its `label()` method:

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

On the frontend, you use the published enum much like the PHP one:

```typescript
import { Status } from '@data/app/enums';

Status.Active;                // 'active'
Status.label.Active;          // 'Active User'
Status.from('active').label;  // 'Active User', a PHP-like enum "instance"
```

Key capabilities include:

- **Your own methods**: publish them with `#[TsEnumMethod]` and `#[TsEnumStaticMethod]`, or auto-include public ones.
- **Renames and descriptions**: `#[TsEnum]` and `#[TsCase]` rename an enum or a case, or add a JSDoc description.
- **Type aliases**: `{Name}Type` types a raw case value, and a backed enum's `{Name}Kind` types a case name.
- **PHPDoc carried over**: class, case, and method doc blocks become JSDoc comments.

For every attribute option, the auto-include settings, and the runtime helpers, see the [Enums documentation](https://tolki.abe.dev/ts/enums.html).

## Models

Eloquent models become TypeScript interfaces for their columns, accessors, and relations, typed from your schema, casts, and docblocks. Each model splits into separate interfaces by default, so a page imports only the parts it uses, and `models.template` can combine them into one. This model has a cast, an accessor, and a relation:

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

Its columns, accessors, and relations publish as three separate interfaces:

```typescript
import type { User, UserMutators, UserRelations } from '@data/app/models';

// User          → id: number; status: StatusType; ...
// UserMutators  → initials: string
// UserRelations → posts: Post[]; posts_count: number; posts_exists: boolean
```

Key capabilities include:

- **Docblock-aware**: `@property`, `@phpstan-type`, and `Attribute<>` docblocks sharpen types, and PHPStan reads them too.
- **Accessor getter bodies**: an accessor with a vague signature is typed from the value its getter returns.
- **Nullable relations**: singular relations get `| null` from their type and foreign key, configurable per type.
- **Overrides**: `#[TsCasts]` retypes a property, and `#[TsType]` types every column that uses a custom cast class.
- **Enum columns**: a parallel `{Model}Resource` interface types each enum column as a resolved `AsEnum<>` instance.
- **Hidden columns**: `$hidden` attributes publish unless you turn on `models.exclude_hidden`.
- **Laravel 13 attributes**: `#[Table]`, `#[Hidden]`, `#[Visible]`, `#[Appends]`, and `#[Connection]` apply with no setup.

For templates, relation strategies, and type mappings, see the [Models documentation](https://tolki.abe.dev/ts/models.html). If a property still publishes `unknown`, the [annotation checklist](https://tolki.abe.dev/ts/models.html#annotation-checklist) names the fix.

## Model metadata

This opt-in feature writes a runtime companion, `{model}_meta.ts`, beside each model interface. Unlike the interface, the companion is a real module, so the frontend can read values the backend owns instead of hard-coding them. Turn it on in the config:

```php
// config/ts-publish.php

'model_metadata' => [
    'enabled' => true,
],
```

With the default provider, each companion holds the model's morph class:

```typescript
// resources/js/types/data/app/models/user_meta.ts
export const UserModelMetadata = {
    morphClass: 'App\\Models\\User',
} as const satisfies {
    morphClass: string;
};
```

`morphClass` holds what `getMorphClass()` returns: the class name, or its alias once you register a morph map. Read it for a polymorphic field such as `commentable_type` instead of typing the PHP class name.

Key capabilities include:

- **Own switches**: `model_metadata.enabled` and `--only-model-metadata` control it, and `--only-functional` includes it.
- **Custom providers**: implement `ModelMetadataProvider` and set `model_metadata.provider_class` to add values.
- **Typed values**: each key is typed from `#[TsCasts]`, a `@return array{...}` shape, or the method body.
- **Checked values**: a value TypeScript can't hold fails its companion, and the error names the model and path.
- **Contained failures**: a provider that throws keeps the model's last good companion, and the run exits non-zero.
- **Inherited filters**: `included`, `excluded`, and `additional_directories` fall back to the `models.*` values.
- **Cache-aware**: a morph-map change or a new value republishes only the affected companions, without `--fresh`.

For the provider contract, value rules, and failure handling, see the [Model Metadata documentation](https://tolki.abe.dev/ts/model-metadata.html).

## API resources

The package reads each `JsonResource`'s `toArray()` without calling it, and generates an interface for what it returns. You don't maintain a second type for your API's JSON. This resource reads columns, an enum, and a relation from its `User` model:

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

The package publishes this interface:

```typescript
import { type AsEnum } from '@tolki/ts';

import { Role } from '../../enums';
import type { PostResource } from '.';

export interface UserResource {
    id: number;
    name: string;
    role: AsEnum<typeof Role> | null;
    posts?: PostResource[];
}
```

Key capabilities include:

- **Model-aware types**: properties are typed from the backing model's columns, casts, accessors, and relations.
- **Conditional methods**: `when()`, `whenLoaded()`, `whenCounted()`, and the rest publish optional properties.
- **Nested resources**: `::make()`, `::collection()`, `new`, and `toResource()` publish the imported resource type.
- **Computed values**: method calls, local variables, `instanceof` narrowing, and collection chains keep their types.
- **Spreads and inheritance**: `merge()`, parent `toArray()` spreads, and trait method spreads add their keys.
- **Attribute filters**: `only([...])` and `except([...])`, on the resource or on a relation, are typed from the model.
- **Overrides**: `#[TsResource]` sets the name or model, and `#[TsCasts]` overrides or adds property types.

For every supported `toArray()` pattern and the attribute reference, see the [API Resources documentation](https://tolki.abe.dev/ts/api-resources.html).

## Routes

Every routed controller action gets a helper that builds its URL, binds its parameters, adds query strings, and spoofs form methods. The helpers follow [Laravel Wayfinder](https://github.com/laravel/wayfinder)'s conventions, and their logic lives once in `defineRoute()` from [`@tolki/ts`](https://tolki.abe.dev/ts/). A controller's generated file holds one call per action:

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

Your frontend calls the helper:

```typescript
import { PostController } from '@data/app/http/controllers';

PostController.update({ post: 42 });       // { url: '/posts/42', method: 'put' }
PostController.update(post);               // pass a Post object directly
PostController.update.form({ post: 42 });  // { action: '/posts/42?_method=PUT', method: 'post' }
```

Key capabilities include:

- **Structural typing**: model and enum route bindings are typed without importing the model or enum.
- **Calling conventions**: pass a named object, positional arguments, an array, or a model object.
- **Query strings**: extra keys become query parameters, and `mergeQuery` updates the current page's query string.
- **Form helpers**: `.form()` builds `{ action, method }` for HTML forms, with Laravel's `_method` spoofing.
- **Inertia page props**: an action's `Inertia::render()` component and props are typed, Inertia UI Table included.
- **Form request payloads**: an action that type-hints a `FormRequest` gets that request's interface.
- **Filtering**: `#[TsExclude]`, route-name patterns, middleware exclusion, and a named-routes-only mode.

For every calling convention, binding rule, and the Inertia and form request helpers, see the [Routing documentation](https://tolki.abe.dev/ts/routing.html).

## Form requests

A form request's `rules()` becomes a TypeScript interface for its payload, so your `useForm()` calls and request bodies match your validation rules. This request mixes plain, wildcard, and nested rules:

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

The generated interface has this shape:

```typescript
import type { StorePostRequest } from '@data/app/http/requests';

// { title: string; rating?: number | null; tags?: string[]; order?: { items?: { sku: string }[] }; }
```

Key capabilities include:

- **Rule-aware types**: scalar, array, file, `in:`, and `Rule::enum()` rules, among others, map to TypeScript types.
- **Nested rules**: dot-notation and wildcard keys, such as `tags.*`, compose into nested shapes.
- **Presence rules**: `required` and `sometimes` decide the `?`, `nullable` adds `| null`, and `prohibited` drops a field.
- **JSDoc hints**: rules such as `email`, `uuid`, and `exists` add JSDoc tags such as `@format email`.
- **Overrides and extends**: `#[TsCasts]` overrides a field's type, and `#[TsExtends]` extends shared interfaces.
- **Dynamic fallback**: a `rules()` that can't run during a publish gives `Record<string, unknown>`, not an error.

For the full rule-to-type mapping and the JSDoc annotations, see the [Form Requests documentation](https://tolki.abe.dev/ts/form-requests.html).

## Broadcast channels

Every channel in `routes/channels.php` compiles into one `broadcast-channels.ts` file, with a `BroadcastChannel` union type and a `BroadcastChannels` object of accessors. You never hand-type a `{placeholder}` channel string. These channels are registered:

```php
// routes/channels.php
Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return true;
});

Broadcast::channel('public-announcements', PublicAnnouncementsChannel::class);
```

The frontend builds channel names from the accessors:

```typescript
import { BroadcastChannels } from '@data/broadcast-channels';

BroadcastChannels.orders(42);               // 'orders.42'
BroadcastChannels["public-announcements"];  // 'public-announcements'
```

Key capabilities include:

- **Nested accessors**: dot-notation names such as `user.{userId}.notifications` become nested accessor functions.
- **Both registration styles**: closures and channel classes publish alike, because only the name matters.
- **Channel name type**: `BroadcastChannel` types a helper that accepts any registered channel name.
- **One combined file**: every registered channel lands in one file, with no per-channel filters or attributes.

For how names become accessors and how quoted keys work, see the [Broadcast Channels documentation](https://tolki.abe.dev/ts/broadcast-channels.html).

## Broadcast events

Every `ShouldBroadcast` and `ShouldBroadcastNow` event gets an interface built from its `broadcastWith()` return shape or, when there is none, its public properties. A combined `broadcast-events.ts` adds a `BroadcastEvent` union and a `BroadcastEvents` const of every Echo event name. This event has three promoted properties:

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

It publishes this interface:

```typescript
/** @see App\Events\OrderShipped */
export interface OrderShipped {
    orderId: number;
    trackingNumber: string;
    carrier: string;
}
```

Key capabilities include:

- **Models and enums**: a model property publishes `Partial<Model>`, and an enum property uses the enum's `{Name}Type`.
- **Custom event names**: a literal `broadcastAs()` name replaces Laravel's default dotted class name.
- **Overrides and extends**: `#[TsCasts]` overrides property types, and `#[TsExtends]` extends shared interfaces.
- **Echo typing**: `echo-broadcast-events.d.ts` augments Laravel Echo, so `.listen()` callbacks get typed payloads.
- **Name clashes**: two events with the same class name get namespace-prefixed aliases in the combined file.

For property resolution rules and Echo setup, see the [Broadcast Events documentation](https://tolki.abe.dev/ts/broadcast-events.html).

## Inertia

With `inertia.enabled` on, the default, the package reads the `share()` method of your `HandleInertiaRequests` middleware and writes `inertia-config.d.ts`. That file declares a global `Inertia.SharedData` type and augments `@inertiajs/core`, so every page gets typed shared props. This middleware shares three keys:

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

The package writes this declaration file:

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

Key capabilities include:

- **Static `share()` analysis**: every key is typed from your code, up the parent middleware chain, with no request.
- **Typed user**: `$request->user()` and `Auth::user()` publish your auth provider's model as `User | null`.
- **Live config values**: a literal `config('some.key')` is typed from its value in your booted app.
- **Prop wrappers**: `Inertia::defer()`, `optional()`, `merge()`, and the other v2 wrappers type as the value they wrap.
- **Overrides**: `#[TsCasts]` or a `@return array{...}` docblock on `share()` types a key the package can't infer.
- **Validation errors**: `errors` is left to `@inertiajs/core`, and `$withAllErrors` adds an `errorValueType`.

For middleware discovery and the override priority, see the [Inertia documentation](https://tolki.abe.dev/ts/inertia.html).

## Vite env

By default, the package reads the `VITE_` variables in your `.env` and writes a `vite-env.d.ts` that augments Vite's `ImportMetaEnv` interface. `import.meta.env.VITE_APP_NAME` is then typed with no declaration file to maintain. For example, your `.env` holds these variables:

```dotenv
VITE_APP_NAME=MyApp
VITE_APP_URL=https://example.test
```

The package writes this file:

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

Key capabilities include:

- **`VITE_` variables only**: other variables stay out, matching what Vite exposes to client code.
- **Source file order**: `vite_env.source_file` if you set it, then `.env`, then `.env.example` as a fallback.
- **Always `string`**: every variable is typed `string`, which is what Vite provides at runtime.
- **No empty file**: with no `VITE_` variables, or no source file, nothing is written.

For the parsing rules and source-file order, see the [Vite Env documentation](https://tolki.abe.dev/ts/vite-env.html).

## Extending interfaces with `#[TsExtends]` & configs

`#[TsExtends]` and the `ts_extends.*` config arrays add your own interfaces to a generated interface's `extends` clause. Use them for properties the package can't infer, or to share fields across many classes. This model extends a hand-written `HasTimestamps` interface:

```php
#[TsExtends('HasTimestamps', import: '@/types/common')]
class Warehouse extends Model {}
```

The model's interface then extends yours:

```typescript
import type { HasTimestamps } from '@/types/common';
export interface Warehouse extends HasTimestamps { /* columns */ }
```

Key capabilities include:

- **Four features**: works on models, resources, form requests, and broadcast events, by attribute or config.
- **Inherited**: an attribute on a parent class or a trait applies to every class that extends or uses it.
- **Repeatable**: stack several `#[TsExtends]` attributes to extend several interfaces.
- **Generic helpers**: wrap a type in `Pick<>`, `Omit<>`, or `Partial<>`, and list the names to import in `types`.
- **Deduplication and aliasing**: a clause reached twice appears once, and clashing type names get aliases.

For inheritance order, config syntax, and alias rules, see the [Extending Interfaces documentation](https://tolki.abe.dev/ts/extending-interfaces.html).

## Excluding with `#[TsExclude]`

`#[TsExclude]` keeps a class out of the TypeScript output, or one of its methods, accessors, relations, or controller actions. It works on enums, models, resources, form requests, broadcast events, and controllers. Key capabilities include:

- **Whole classes**: an excluded class publishes nothing, and it's left out of barrels and combined files.
- **Single members**: on a method, accessor, relation, or action, it removes only that member.
- **Always wins**: it overrides `#[TsEnumMethod]`, `#[TsEnumStaticMethod]`, and the enum auto-include settings.

For every target and a worked example of each, see the [Excluding Content documentation](https://tolki.abe.dev/ts/excluding-content.html).

## Casing configurations

Three config keys set the casing of generated names, and each accepts `'snake'`, `'camel'`, or `'pascal'`. Relations default to `'snake'`, and enum methods and route actions default to `'camel'`. Key capabilities include:

- **Relation names**: `models.relationship_case` sets relation names, and their `_count` and `_exists` properties follow.
- **Enum method keys**: `enums.method_case` sets the keys of published enum methods.
- **Route exports**: `routes.method_casing` sets each action's export name, never the Laravel route name.
- **Independent settings**: each key affects only its own feature, and there's no global casing setting.

For worked examples of each setting, see the [Casing Configurations documentation](https://tolki.abe.dev/ts/casing-configuration.html).

## JSON enum HTTP API resource

`EnumResource` is a Laravel [JSON resource](https://laravel.com/docs/eloquent-resources) that returns one enum case as a flat object. It follows the same rules as `ts:publish`, so the methods you publish appear in the response. This returns a case of a `PostStatus` enum that publishes `icon()` and `color()` methods:

```php
return new EnumResource(PostStatus::Published);
```

The response is one flat object:

```json
{ "name": "Published", "value": 1, "backed": true, "icon": "check", "color": "green" }
```

Key capabilities include:

- **Same rules as `ts:publish`**: the same methods, with the same `enums.method_case` casing, appear in the response.
- **Standalone or embedded**: return it from a route, or use `EnumResource::make()` inside another resource.
- **`AsEnum` type**: `AsEnum<typeof PostStatus>` from `@tolki/ts` types the response on the frontend.
- **Unit enums**: a unit enum's `value` repeats its case name, and `backed` is `false`.

For the response shape and unit enum behavior, see the [Enum API Resource documentation](https://tolki.abe.dev/ts/enum-api-resource.html).

## Modular publishing

Generated files always mirror your PHP namespaces as a directory tree, with no flat-output mode. `Accounting\Models\Invoice` publishes to `accounting/models/invoice.ts`, so modular and domain-driven apps stay organized, and a single-namespace app gets one `app/` tree. Key capabilities include:

- **Every feature**: models, enums, resources, form requests, broadcast events, and routes follow the same rule.
- **Relative imports**: files import each other through computed relative paths, with no path alias needed.
- **Barrel files**: each namespace directory gets an `index.ts` that re-exports its files.
- **Prefix stripping**: `namespace_strip_prefix` removes a shared prefix, such as `Modules\`, from every output path.

For the path rules, import paths, and barrel format, see the [Modular Publishing documentation](https://tolki.abe.dev/ts/modular-publishing.html).

## Extending & customizing the pipeline

Features run through a pipeline of collector, generator, transformer, writer, and Blade template, though not every feature uses all five. To replace a class stage, extend its built-in class and point the feature's config key at yours, such as `models.transformer_class`. Key capabilities include:

- **Swappable stages**: the collector, generator, transformer, and writer each have a `{feature}.{stage}_class` key.
- **Base classes**: abstract collector, generator, transformer, and writer classes define each stage's contract.
- **Cached custom generators**: add the `RehydratesFromCache` trait to a custom generator to cache it.
- **Template-only changes**: publish and edit the Blade templates to change formatting without writing PHP.

For each feature's stages, the base class contracts, and the template keys, see the [Customizing the Pipeline documentation](https://tolki.abe.dev/ts/customizing-the-pipeline.html).

## Analyzer API

`AstEngine::analyze()` runs the package's type inference on a class method and returns the typed properties and the imports they need. Call it from your own code, such as a custom Artisan command, without running a publish:

```php
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;

$result = resolve(AstEngine::class)->analyze(App\Http\Resources\PostResource::class);
// $result->properties, $result->typeImports, $result->valueImports
```

Key capabilities include:

- **Ready to render**: the properties and both import maps agree, so a module that renders all three compiles.
- **Any class, any method**: `toArray()` by default, or another method, such as `analyze($event, 'broadcastWith')`.
- **Resource semantics**: conditional methods, nested resources, and relation filters type as they do in a publish.
- **Public API**: `analyze()` and `AnalysisResult` are supported, and every other engine class is internal.

For the arguments, the result's fields, and what it can't analyze, see the [Analyzer API documentation](https://tolki.abe.dev/ts/analyzer-api.html).

## Pre-command hook

`LaravelTsPublish::callCommandUsing()` registers a closure that runs right before `ts:publish` does its work. It runs only when the command runs, so it adds nothing to a normal request. Register it in a service provider's `boot()` method:

```php
LaravelTsPublish::callCommandUsing(function () {
    config()->set('ts-publish.models.additional_directories', ['modules/Blog/Models']);
});
```

Key capabilities include:

- **Every invocation**: a full publish, a `--source` rerun, a preview, and the post-migration run all call it.
- **One closure**: calling `callCommandUsing()` again replaces the closure instead of adding a second one.
- **Any config key**: the closure can set any `ts-publish.*` key, including a pipeline `*_class` override.
- **Dynamic directories**: scan the filesystem or a module registry to build `additional_directories` lists.

For worked examples and how to reset the hook between tests, see the [Pre-Command Hook documentation](https://tolki.abe.dev/ts/pre-command-hook.html).

## Cache generation

After the first full publish, `ts:publish` reuses the output of every class whose source file and dependencies haven't changed. The cache clears itself when the package version or your output-affecting config changes. Key capabilities include:

- **Dependency-aware**: a class rebuilds when its file, a parent, a trait, a related model, or its routes change.
- **Bypassed by single-class and preview runs**: `--source` and `--preview=true` never read or write the cache.
- **File or Laravel store**: files by default, or any Laravel cache store you name in `cache.store`.
- **Signed entries**: entries are signed with `cache.key`, or your app key when it's unset, and checked before use.

For what triggers a rebuild, what the cache can't detect, and the storage options, see the [Cache Generation documentation](https://tolki.abe.dev/ts/generating-cache.html).

## Output options

Besides the `.ts` files, `ts:publish` can write three extra files, each with its own `enabled` key. The watcher list is on by default, and the globals and JSON files are off. Key capabilities include:

- **`globals.enabled`**: writes `laravel-ts-global.ts`, which declares every published type in a global namespace.
- **`json.enabled`**: writes `laravel-ts-definitions.json`, every published class as data, keyed by full class name.
- **`watcher.enabled`**: writes the list of collected PHP files that file watchers, such as the Vite plugin, read.
- **`output_to_files`**: `false` skips the automatic publish after `migrate`, and doesn't stop `ts:publish` from writing.

For each file's format, see [Output Files](https://tolki.abe.dev/ts/publishing.html#output-files) in the Publishing Types documentation.

## Configuration reference

Every option lives in `config/ts-publish.php`, grouped by feature, such as `models.*`, `enums.*`, `routes.*`, and `cache.*`. Publish the file with the `vendor:publish` command in [Installation](#installation). Key capabilities include:

- **General settings**: `output_directory`, `timestamps_as_date`, and `custom_ts_mappings` apply across features.
- **Per-feature blocks**: each feature's settings, such as `enabled`, `included`, and `template`, live in its own block.
- **Environment variables**: `TS_PUBLISH_RUN_AFTER_MIGRATE` and the `TS_PUBLISH_CACHE_*` variables set keys from `.env`.

For every key, its type, and its default, see the [Configuration Reference](https://tolki.abe.dev/ts/configuration-reference.html).

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

# Laravel TypeScript Enums, Models, & Resources Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
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
- 🧬 [Extending Interfaces](#extending-interfaces-with-tsextends--configs)
- ❌ [Excluding Content](#excluding-with-tsexclude)
- 🔤 [Casing Configurations](#casing-configurations)
- 🌐 [Enum API Resource](#json-enum-http-api-resource)
- 📂 [Modular Publishing](#modular-publishing)
- 🔧 [Customizing the Pipeline](#extending--customizing-the-pipeline)
- ⚡ [Pre-Command Hook](#pre-command-hook)
- ⚙️ [Configuration Reference](#configuration-reference)

## Installation

**Requires PHP 8.4+ and supports Laravel 13, 12 or 11**

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

For a full installation and setup guide, see the [Installation & Setup](WORKFLOW.md) documentation.

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

This is significantly faster than a full publish on large projects and is used automatically by the [Vite plugin](#enum-metadata-vite-plugin) to republish only the file that changed during development.

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
| `--quiet` / `-q` | No output at all — only the exit code indicates success or failure. Ideal for automated tooling like the [Vite plugin](#enum-metadata-vite-plugin). |
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

In quiet mode, files are still generated normally — only console output is suppressed. The [Vite plugin](#enum-metadata-vite-plugin) passes `--quiet` by default since it only needs the exit code.

## Enums

This package, like others before it ([spatie/typescript-transformer](https://github.com/spatie/typescript-transformer) and [modeltyper](https://github.com/fumeapp/modeltyper)), can convert enums from PHP to TypeScript for each enum case.

However, PHP enums do not solely consist of enum cases, but can also have methods and static methods that have valuable data to use on the frontend. This package allows you to use these features of PHP enums and publish the return values of these methods in TypeScript as well.

By default, this package will only publish the enum cases and their values to TypeScript, but you can use the provided attributes to specify that you want to call certain methods or static methods and publish their return values in TypeScript as well. See below.

Alternatively, you can enable the `enums.auto_include_methods` and `enums.auto_include_static_methods` config options to automatically include all public methods without needing to add attributes. See [Auto-Including All Enum Methods](#auto-including-all-enum-methods) for details.

> [!TIP]
> This package also provides an `EnumResource` JSON resource that lets you return a flattened, instance-specific representation of any enum case from your API routes. See [JSON Enum HTTP API Resource](#json-enum-http-api-resource) for details.

> [!NOTE]
> Whether you use the attributes or the global config options, only **public** methods are ever included. Private and protected methods are always excluded.

### Enum Attributes

To use the more advanced transforming features provided by this package for enums, you'll need to use the PHP Attributes described below.

All attributes can be found at [this link](https://github.com/abetwothree/laravel-ts-publish/tree/main/src/Attributes) and are under the `AbeTwoThree\LaravelTsPublish\Attributes` namespace.

List of enum attributes & descriptions:

| Attribute              | Target         | Description                                                                                                             |
|------------------------|----------------|-------------------------------------------------------------------------------------------------------------------------|
| `#[TsEnumMethod]`      | Method         | Include a method's return values in the TypeScript output. Called per enum case, creates a key/value pair object.       |
| `#[TsEnumStaticMethod]`| Static Method  | Include a static method's return value in the TypeScript output. Called once, added as a property on the enum.          |
| `#[TsEnum]`            | Enum Class     | Rename the enum or add a description when converting to TypeScript. Useful to avoid naming conflicts across namespaces. |
| `#[TsCase]`            | Enum Case      | Rename, change the frontend value, or add a description to an enum case.                                                |
| `#[TsExclude]`         | Class, Method  | Exclude an entire enum or specific enum methods from the TypeScript output. See [Excluding with TsExclude](#excluding-with-tsexclude). |

### Enum Method #[TsEnumMethod]

Using the `TsEnumMethod` attribute to specify that the `label()` method should be called for each enum case value and the return value should be used as the value for the enum case in TypeScript:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;

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

Generated TypeScript declaration type:

```TypeScript
export const Status = {
    Active: 'Active User',
    Inactive: 'Inactive User',
    label: {
        Active: 'Active User',
        Inactive: 'Inactive User',
    }
} as const;
```

The `#[TsEnumMethod]` attribute accepts optional `name`, `description`, and `params` parameters:

| Parameter     | Type     | Default           | Description                                                              |
|---------------|----------|-------------------|--------------------------------------------------------------------------|
| `name`        | `string` | Method name       | Customize the key name used in the TypeScript output                     |
| `description` | `string` | `''`              | Added as a JSDoc comment above the method output                         |
| `params`      | `array`  | `[]`              | Named arguments to pass when invoking the method (see example below)     |

```php
#[TsEnumMethod(name: 'statusLabel', description: 'Human-readable label for this status')]
public function label(): string
{
    return match($this) {
        self::Active => 'Active User',
        self::Inactive => 'Inactive User',
    };
}
```

#### Methods with Required Parameters

Methods that require parameters are **skipped by default** — they will not appear in the generated TypeScript output. This prevents producing misleading `null` values for methods that can't be called without arguments.

To include a method that requires parameters, use the `params` property on the attribute to provide named arguments:

```php
enum Priority: int
{
    case Low = 0;
    case Medium = 1;
    case High = 2;

    #[TsEnumMethod(description: 'Compare with threshold', params: ['threshold' => 1])]
    public function isAboveThreshold(int $threshold): bool
    {
        return $this->value > $threshold;
    }
}
```

Generated TypeScript:

```TypeScript
export const Priority = {
    Low: 0,
    Medium: 1,
    High: 2,
    /** Compare with threshold */
    isAboveThreshold: {
        Low: false,
        Medium: false,
        High: true,
    },
} as const;
```

The `params` values must be constant expressions (scalars, arrays of scalars) since they are defined inside a PHP attribute. The values are spread as named arguments when the method is invoked for each enum case.

> [!NOTE]
> Methods with only optional parameters (parameters with default values) are still included without needing to set `params`, since they can be called without arguments.

### Enum Static Method #[TsEnumStaticMethod]

Using the `TsEnumStaticMethod` attribute to specify that the `options()` static method should be called and the return value should be published in TypeScript:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    #[TsEnumStaticMethod]
    public static function options(): array
    {
        return array_map(fn(self $status) => [
            'value' => $status->value,
            'label' => $status->name,
        ], self::cases());
    }
}
```

Generated TypeScript declaration type:

```TypeScript
export const Status = {
    Active: 'active',
    Inactive: 'inactive',
    options: [
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
    ],
} as const;
```

The `#[TsEnumStaticMethod]` attribute accepts the same optional `name`, `description`, and `params` parameters as `#[TsEnumMethod]`:

| Parameter     | Type     | Default           | Description                                                              |
|---------------|----------|-------------------|--------------------------------------------------------------------------|
| `name`        | `string` | Method name       | Customize the key name used in the TypeScript output                     |
| `description` | `string` | `''`              | Added as a JSDoc comment above the method output                         |
| `params`      | `array`  | `[]`              | Named arguments to pass when invoking the method                         |

```php
#[TsEnumStaticMethod(name: 'allOptions', description: 'Array of all status options')]
public static function options(): array
{
    return array_map(fn(self $status) => [
        'value' => $status->value,
        'label' => $status->name,
    ], self::cases());
}
```

Like `#[TsEnumMethod]`, static methods with required parameters are **skipped by default** unless `params` is provided:

```php
#[TsEnumStaticMethod(description: 'Filter by minimum priority', params: ['minimum' => 1])]
public static function filterByMinimum(int $minimum): array
{
    return array_filter(self::cases(), fn (self $case) => $case->value >= $minimum);
}
```

### Enum Class Name #[TsEnum]

Renaming an enum or adding a description using the `TsEnum` attribute:

| Parameter     | Type     | Default           | Description                                                                          |
|---------------|----------|-------------------|--------------------------------------------------------------------------------------|
| `name`        | `string` | Enum class name   | Override the TypeScript const name                                                   |
| `description` | `string` | `''`              | Added as a JSDoc comment above the enum. Takes priority over any PHPDoc description. |

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;

#[TsEnum('UserStatus', description: 'All possible user account statuses')]
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

Generated TypeScript declaration type:

```TypeScript
/** All possible user account statuses */
export const UserStatus = {
    Active: 'active',
    Inactive: 'inactive',
} as const;
```

### Enum Case Typings #[TsCase]

Renaming an enum case, changing the frontend value, and adding a description using the `TsCase` attribute:

| Parameter     | Type          | Default      | Description                                           |
|---------------|---------------|--------------|-------------------------------------------------------|
| `name`        | `string`      | Case name    | Override the case key name in the TypeScript output    |
| `value`       | `string\|int` | Case value   | Override the case value in the TypeScript output       |
| `description` | `string`      | `''`         | Added as a JSDoc comment above the case                |

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;

enum Status: int
{
    #[TsCase(name: 'active_status', value: true, description: 'The user is active')]
    case Active = 1;

    #[TsCase(name: 'inactive_status', value: false, description: 'The user is inactive')]
    case Inactive = 0;
}
```

Generated TypeScript declaration type:

```TypeScript
export const Status = {
    /** The user is active */
    active_status: true,
    /** The user is inactive */
    inactive_status: false,
} as const;
```

### Enum Value & Key Types

As shown above, the enum generated in TypeScript is a JavaScript object with the `as const` assertion to prevent modification.

However, there are times when you need to validate that a value is a valid enum value or a valid enum case key. For this purpose, this package also generates TypeScript types for the enum values and case keys if the enum is a [PHP backed enum](https://www.php.net/manual/en/language.enumerations.backed.php).

For every enum, a `Type` alias is generated from the case values. For backed enums, a `Kind` alias is also generated from the case names:

| Generated Type   | Source             | Example                          |
|------------------|--------------------|----------------------------------|
| `StatusType`     | Case values        | `'active' \| 'inactive'`        |
| `StatusKind`     | Case names         | `'Active' \| 'Inactive'`        |

> [!NOTE]
> The `Kind` type alias is only generated for backed enums, since unit enums already use case names as their values.

Example:

```TypeScript
export const Status = {
    Active: 'active',
    Inactive: 'inactive',
} as const;

export type StatusType = 'active' | 'inactive';

export type StatusKind = 'Active' | 'Inactive'; // Only published if the enum is a backed enum
```

With those types, you can now validate that a value is a valid enum value or case key:

```TypeScript
import { StatusType, StatusKind } from '@js/types/data/enums';

function setStatus(status: StatusType) {
    // status will only accept 'active' or 'inactive'
}

function setStatusByKey(status: StatusKind) {
    // status will only accept 'Active' or 'Inactive'
}
```

### Enum Metadata & Tolki TypeScript Package

By default, this package will publish three metadata properties on the enum in TypeScript for the cases, methods, and static methods that are published. These properties are `_cases`, `_methods`, and `_static`.

The purpose of these metadata properties is to be able to create an "instance" of the enum from a case value like you'd get on the PHP side. To accomplish this, you need to use the [@tolki/ts](https://tolki.abe.dev/ts/) npm package.

By default, this package configures the usage of the `@tolki/ts` package when enums are published. 

This is what a published enum looks like when using the `@tolki/ts` package on the frontend:

```TypeScript
import { defineEnum } from '@tolki/ts';

export const Status = defineEnum({
    _cases: ['Active', 'Inactive'],
    _methods: ['label'],
    _static: ['options'],
    Active: 'active',
    Inactive: 'inactive',
    label: {
        Active: 'Active User',
        Inactive: 'Inactive User',
    },
    options: [
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
    ],
} as const);
```

The `defineEnum` function from the `@tolki/ts` package is a factory function that will bind PHP-like methods to the enum object.

See more details about [defineEnum here](https://tolki.abe.dev/enums/enum-utilities-list.html#defineenum).

With the `@tolki/ts` package, you can now create an "instance" of the enum from a case value like you'd get on the PHP side using the `from` function:

```TypeScript
import { Status } from '@js/types/data/enums'; // Using example status from the previous example
import { User } from '@js/types/data/models'; // Assuming you have a User model published as well

const user: User = {
    id: 1,
    name: 'John Doe',
    status: 'active',
}

const userStatus = Status.from(user.status);

// userStatus will now have the following structure:
{
    // cases become just value with the matching value to the model
    value: 'active',
    // methods become just the key/value pair for the matching case
    label: 'Active User',
    // static methods stay as is on the enum
    options: [
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
    ],
}

// Then use the userStatus object in your frontend similarly to how you would use an instance of the enum in PHP:

userStatus.value // 'active'
userStatus.label // 'Active User'
userStatus.options // [
                   //     { value: 'active', label: 'Active' },
                   //     { value: 'inactive', label: 'Inactive' },
                   // ]
```

The `defineEnum` function currently also binds the `tryFrom` and `cases` functions to the enum.

### Enum Metadata Vite Plugin

The `@tolki/ts` package also provides a Vite plugin that can call the artisan publish command for you and watch for changes to your enums & models to automatically update the generated TypeScript declaration types on the frontend.

For documentation on how to set up the Vite plugin, [see this link](https://tolki.abe.dev/enums/enum-vite-plugin.html).

### Disabling Enum Metadata or Tolki TypeScript Package

If you don't plan to use the `@tolki/ts` package or don't need the metadata properties for your use case, you can disable the generation of these metadata properties in the config file by setting `enums.metadata_enabled` to `false`:

```php
// config/ts-publish.php

'enums' => [
    'metadata_enabled' => false,
],
```

If you would like to use the metadata but don't want the `@tolki/ts` package, you can disable the usage of that package in the config file by setting `enums.use_tolki_package` to `false`. This will still generate the metadata properties on the enum, but will not wrap the enum in the `defineEnum` function from the `@tolki/ts` package:

```php
// config/ts-publish.php

'enums' => [
    'metadata_enabled' => true,
    'use_tolki_package' => false,
],
```

### Auto-Including All Enum Methods

By default, only **public** methods decorated with the `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` attributes are included in the TypeScript output. If you'd prefer to include all public methods without needing to add the attribute to every method, you can enable automatic inclusion in your config file:

```php
// config/ts-publish.php

'enums' => [
    'auto_include_methods' => true,         // Include all public non-static methods
    'auto_include_static_methods' => true,  // Include all public static methods
],
```

When enabled, every public method declared on the enum will be included in the TypeScript output — you no longer need to add `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` to each method. Built-in enum methods like `cases()`, `from()`, and `tryFrom()` are always excluded automatically.

> [!NOTE]
> Methods with required parameters are automatically skipped in auto-include mode since there is no attribute to provide `params` on. To include a method that requires parameters, add the `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` attribute with the `params` property set.

You can still use `#[TsEnumMethod]` and `#[TsEnumStaticMethod]` to customize the `name`, `description`, or `params` of individual methods when auto-inclusion is enabled:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    // Included automatically with defaults (name: 'label', description: '')
    public function label(): string
    {
        return match($this) {
            self::Active => 'Active User',
            self::Inactive => 'Inactive User',
        };
    }

    // Included automatically, but with a custom description from the attribute
    #[TsEnumMethod(description: 'Get the icon name for the status')]
    public function icon(): string
    {
        return match($this) {
            self::Active => 'check',
            self::Inactive => 'x',
        };
    }
}
```

> [!CAUTION]
> These settings are disabled by default for security reasons — enabling them will expose the return values of all public methods on your enums. Make sure you're comfortable with that before enabling them.

### PHPDoc Descriptions for Enums

This package automatically reads PHPDoc doc blocks and outputs them as JSDoc comments in the generated TypeScript. Descriptions are read from the following locations:

| Location              | Source                                         | JSDoc Placement                        |
|-----------------------|------------------------------------------------|----------------------------------------|
| Enum class            | Doc block above the enum class                 | Above the `export const` declaration   |
| Enum cases            | Doc block above each case                      | Above the case property                |
| Instance methods      | Doc block above the method                     | Above the method property              |
| Static methods        | Doc block above the static method              | Above the static method property       |

Lines starting with `@` (such as `@param`, `@return`, `@phpstan-type`, etc.) are automatically filtered out — only the human-readable description text is included.

**Priority:** If an element has both a PHPDoc doc block and an attribute with a `description` parameter (e.g., `#[TsEnum(description: ...)]`, `#[TsCase(description: ...)]`, `#[TsEnumMethod(description: ...)]`), the **attribute description always takes priority** over the doc block.

```php
/**
 * Represents the priority level of a task.
 *
 * @phpstan-type PriorityValue = int
 */
enum Priority: int
{
    /** Lowest priority level */
    case Low = 0;

    /** Standard priority */
    case Medium = 1;

    /** Highest priority level */
    case High = 2;

    /** Human-readable label for the priority */
    #[TsEnumMethod]
    public function label(): string
    {
        return match($this) {
            self::Low => 'Low Priority',
            self::Medium => 'Medium Priority',
            self::High => 'High Priority',
        };
    }
}
```

Generated TypeScript:

```TypeScript
/** Represents the priority level of a task. */
export const Priority = {
    /** Lowest priority level */
    Low: 0,
    /** Standard priority */
    Medium: 1,
    /** Highest priority level */
    High: 2,
    /** Human-readable label for the priority */
    label: {
        Low: 'Low Priority',
        Medium: 'Medium Priority',
        High: 'High Priority',
    },
} as const;
```

## Models

This package can also convert your Laravel Eloquent models to TypeScript declaration types. This package will go through your models' properties, mutators, and relations to create TypeScript interfaces that match the structure of your model.

### Model Templates & Publishing

By default, this package purposely breaks the model into three separate interfaces for the properties, mutators, and relations to give you more flexibility on which properties you need to use in a concrete situation on your frontend projects. It also generates a fourth interface that extends all three interfaces for when you do want to use all the properties, mutators, and relations together, see below.

> [!NOTE]
> Any mutator added to the `$appends` property on the model will be included in the model properties interface in the split template since those are always included when the model is serialized to JSON.

If that's still not ideal for your situation, you can change the template used to generate the model types. This package comes with two templates for generating model types:

| Template                              | Description                                                                         |
|---------------------------------------|-------------------------------------------------------------------------------------|
| `laravel-ts-publish::model-split`     | **(Default)** Splits into separate interfaces for properties, mutators, and relations |
| `laravel-ts-publish::model-full`      | Combines all properties, mutators, and relations into a single interface              |

Just change the `models.template` in the config file to use the template that best fits your needs:

```php
// config/ts-publish.php

'models' => [
    'template' => 'laravel-ts-publish::model-full',
],
```

You are also free to publish the views to modify them or create your own custom template if you want to change the structure of the generated types even more. Just make sure to update the `models.template` in the config file to point to your new custom template.

#### Example using the default `model-split` template with a model that has properties, mutators, and relations

```php
use App\Enums\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Example database structure for this model:
 * @property int $id
 * @property string $name
 * @property int $is_super_admin
 * @property Status $status
 * @property Post[] $posts
 */
class User extends Model
{
    public function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    protected function admin(): Attribute
    {
        return Attribute::get(fn(): bool => $this->is_super_admin === 1 ? true : false);
    }
}
```

Default generated TypeScript declaration type `user.ts`:

```TypeScript
import { StatusType } from '../enums';
import { Profile, Post } from './';

export interface User {
    id: number;
    name: string;
    is_super_admin: number;
    status: StatusType;
}

export interface UserMutators {
    admin: boolean; // From the accessor method
}

export interface UserRelations {
    profile: Profile | null;
    posts: Post[];
    profile_count: number;
    posts_count: number;
    profile_exists: boolean;
    posts_exists: boolean;
}

export interface UserAll extends User, UserMutators, UserRelations {}
```

Example Inertia form where we use the entire `User` interface for the form data, but only need the `profile` & `profile_exists` properties from the `UserRelations` interface for this specific page:

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { User, UserRelations } from '@js/types/data/models';

interface UserForm extends User, Pick<UserRelations, 'profile_exists'>{
    profile: UserRelations['profile'] | null;
}

const { user } = defineProps<{
    user: UserForm;
}>()

const form = useForm<UserForm>({
    ...user,
})

form.profile // Is Profile or null
form.posts // TS error because posts is not part of the UserForm interface
</script>
```

#### Example using the `model-full` template with a model that has all properties in one interface

```TypeScript
import { StatusType } from '../enums';
import { Profile, Post } from './';

export interface User {
    // Columns
    id: number;
    name: string;
    is_super_admin: number;
    status: StatusType;
    // Mutators
    admin: boolean; // From the accessor method
    // Relations
    profile: Profile | null;
    posts: Post[];
    // Counts
    profile_count: number;
    posts_count: number;
    // Exists
    profile_exists: boolean;
    posts_exists: boolean;
}
```

The same Inertia form example as above would work with this `model-full` template as well since all properties, mutators, and relations are in the same interface.

You will notice the need to call `Omit` with more properties to exclude the relation properties that are not needed for this specific page, but that's the tradeoff with using a single interface for the model instead of splitting it into separate interfaces for the properties, mutators, and relations.

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { User } from '@js/types/data/models';

interface UserForm extends Omit<User, 'admin' | 'profile' | 'posts' | 'profile_count' | 'posts_count' | 'posts_exists'> {
    profile: User['profile'] | null;
}

const { user } = defineProps<{
    user: UserForm;
}>()

const form = useForm<UserForm>({
    ...user,
})

form.profile // Is Profile or null
form.posts // TS error because posts is not part of the UserForm interface
</script>
```

### Nullable Relations

By default, this package detects whether singular relations should be typed as nullable (`| null`) based on the relation type and database schema:

| Relation Type     | Strategy   | Behavior                                                                     |
|-------------------|------------|------------------------------------------------------------------------------|
| `HasOne`          | `nullable` | Always add `null` — the related record may not exist                         |
| `MorphOne`        | `nullable` | Always add `null`                                                            |
| `HasOneThrough`   | `nullable` | Always add `null`                                                            |
| `BelongsTo`       | `fk`       | Add `null` only when the foreign key column is nullable in the database      |
| `MorphTo`         | `morph`    | Add `null` when either the morph type or morph id column is nullable         |
| `HasMany`         | `never`    | Never nullable (returns an empty array, not null)                            |
| `BelongsToMany`   | `never`    | Never nullable                                                               |
| `MorphMany`       | `never`    | Never nullable                                                               |
| `MorphToMany`     | `never`    | Never nullable                                                               |

For example, a `User` model with a `HasOne` profile and a `HasMany` posts relation generates:

```TypeScript
export interface UserRelations {
    profile: Profile | null;  // HasOne — always nullable
    posts: Post[];            // HasMany — never nullable
}
```

A `Post` model with a non-nullable `user_id` FK and a nullable `category_id` FK generates:

```TypeScript
export interface PostRelations {
    author: User;              // BelongsTo — user_id is NOT NULL
    category_rel: Category | null; // BelongsTo — category_id is nullable
}
```

#### Disabling Nullable Relations

To disable nullable relation detection entirely and keep all singular relations non-nullable:

```php
// config/ts-publish.php

'models' => [
    'nullable_relations' => false,
],
```

#### Overriding the Nullability Strategy

You can override the default strategy for any relation type using the `models.relation_nullability_map` config. Keys are fully qualified class names — use the `::class` syntax for safety and IDE autocompletion:

```php
// config/ts-publish.php

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

'models' => [
    'relation_nullability_map' => [
        BelongsTo::class => 'nullable',  // Make all BelongsTo always nullable
        HasOne::class    => 'never',     // Make HasOne never nullable
    ],
],
```

This also supports custom relation types from third-party packages:

```php
use SomePackage\Relations\BelongsToTenant;

'models' => [
    'relation_nullability_map' => [
        BelongsToTenant::class => 'fk',
    ],
],
```

Available strategies: `'nullable'` (always), `'never'` (never), `'fk'` (check FK column), `'morph'` (check morph columns).

See `AbeTwoThree\LaravelTsPublish\RelationMap` for the full default map.

### Model Attributes

Like with enums, this package provides a few PHP attributes that you can use to further customize the generated TypeScript declaration types for your models. All attributes can be found at [this link](https://github.com/abetwothree/laravel-ts-publish/tree/main/src/Attributes) and are under the `AbeTwoThree\LaravelTsPublish\Attributes` namespace.

| Attribute    | Target                           | Description                                                                                                         |
|--------------|----------------------------------|---------------------------------------------------------------------------------------------------------------------|
| `#[TsCasts]` | `casts()` method, `$casts` property, or model class | Specify TypeScript types for model columns. Works similarly to Laravel's `casts` but for TypeScript.                 |
| `#[TsType]`  | Custom cast class                | Specify the TypeScript type for any model property that uses this custom cast class.                                 |
| `#[TsExclude]`| Model class, accessor method, or relation method | Exclude an entire model, specific accessors, or relations from the TypeScript output. See [Excluding with TsExclude](#excluding-with-tsexclude). |

#### Examples using `#[TsCasts]` attribute

##### Using `#[TsCasts]` attribute on `casts()` method

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

class User extends Model
{
    #[TsCasts([
        'metadata' => '{label: string, value: string}[]',
        'is_super_admin' => 'boolean',
    ])]
    public function casts(): array
    {
        return [
            'status' => Status::class,
            'metadata' => 'array',
            'is_super_admin' => 'number',
        ];
    }
}
```

Generated TypeScript declaration type:

```TypeScript
import { StatusType } from '../enums';

export interface User {
    status: StatusType;
    metadata: {label: string, value: string}[];
    is_super_admin: boolean;
}
```

##### Using `#[TsCasts]` attribute on `$casts` property & model class name

Similarly, you can use the `TsCasts` attribute on the `$casts` property or on the model class itself with the same syntax as above to specify TypeScript types for model properties.

On the `$casts` property:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

class User extends Model
{
    #[TsCasts([
        'metadata' => '{label: string, value: string}[]',
        'is_super_admin' => 'boolean',
    ])]
    protected $casts = [
        'status' => Status::class,
        'metadata' => 'array',
        'is_super_admin' => 'number',
    ];
}
```

On the model class itself:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

#[TsCasts([
    'metadata' => '{label: string, value: string}[]',
    'is_super_admin' => 'boolean',
])]
class User extends Model
{
    protected $casts = [
        'status' => Status::class,
        'metadata' => 'array',
        'is_super_admin' => 'number',
    ];
}
```

> [!TIP]
> It is recommended to place the `TsCasts` attribute either on the `casts()` method or the `$casts` property instead of the model class itself to keep the TypeScript type definitions close to where you are defining the casts for the model properties in PHP. However, the `TsCasts` attribute can also be used to define the types of mutators and relations, at which point it may make more sense to place the attribute on the model class itself instead of the `casts()` method or `$casts` property since those only define types for the model properties.

##### Custom types using `#[TsCasts]` attribute

The `TsCasts` attribute can also receive an array as the value for a property to specify a custom type and where that type should be imported from.

This allows you to define a custom TypeScript type that you can reuse across multiple model properties or across multiple models without having to redefine the type for each property on each model.

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

class User extends Model
{
    #[TsCasts([
        'settings' => 'Record<string, unknown>',
        'metadata' => ['type' => 'MetadataType | null', 'import' => '@js/types/custom'],
        'dimensions' => ['type' => 'ProductDimensions', 'import' => '@js/types/product'],
    ])]
    public function casts(): array
    {
        return [
            'settings' => 'array',
            'metadata' => 'array',
            'dimensions' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
```

Generated TypeScript declaration type:

```TypeScript
import { MetadataType } from '@js/types/custom';
import { ProductDimensions } from '@js/types/product';

export interface User {
    id: number;
    name: string;
    settings: Record<string, unknown>;
    metadata: MetadataType | null;
    dimensions: ProductDimensions;
    created_at: string;
    updated_at: string;
}
```

#### Examples using `#[TsType]` attribute

When you have a custom cast class that you use on one or more model properties, you can use the `TsType` attribute on that custom cast class to specify what TypeScript type should be used for any model property that uses that custom cast.

Custom cast class with `TsType` attribute:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

#[TsType('{width: number, height: number, depth: number}')]
class ProductDimensionsCast implements CastsAttributes
{    public function get($model, string $key, $value, array $attributes)
    {
        // Custom logic to cast the value to the ProductDimensions type
    }
}
```

Model using the custom cast class:

```php
class Product extends Model
{
    public function casts(): array
    {
        return [
            'dimensions' => ProductDimensionsCast::class,
        ];
    }
}
```

Generated TypeScript declaration type:

```TypeScript
export interface Product {
    id: number;
    name: string;
    dimensions: {width: number, height: number, depth: number};
}
```

#### Using `#[TsType]` attribute with custom type and import

Similarly to the `TsCasts` attribute, you can also specify the type and import for a custom cast class using the `TsType` attribute:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

#[TsType(['type' => 'ProductDimensions', 'import' => '@js/types/product'])]
class ProductDimensionsCast implements CastsAttributes
{    public function get($model, string $key, $value, array $attributes)
    {
        // Custom logic to cast the value to the ProductDimensions type
    }
}
```

Generated TypeScript declaration type:

```TypeScript
import { ProductDimensions } from '@js/types/product';

export interface Product {
    id: number;
    name: string;
    dimensions: ProductDimensions;
}
```

### PHPDoc Descriptions for Models

Similar to enums, this package automatically reads PHPDoc doc blocks from your model classes and outputs them as JSDoc comments in the generated TypeScript interfaces. Descriptions are read from the following locations:

| Location              | Source                                                          | JSDoc Placement                              |
|-----------------------|-----------------------------------------------------------------|----------------------------------------------|
| Model class           | Doc block above the model class                                 | Above the `export interface` declaration     |
| Columns               | Doc block above the column's Attribute accessor method           | Above the column property                    |
| Mutators              | Doc block above the mutator's Attribute accessor method          | Above the mutator property                   |
| Relations             | Doc block above the relation method                             | Above the relation property                  |

For columns and mutators, the package looks for a doc block on the corresponding accessor method — either new-style (`protected function name(): Attribute`) or old-style (`public function getNameAttribute()`). The new-style accessor is checked first.

Lines starting with `@` (such as `@param`, `@return`, `@phpstan-type`, etc.) are automatically filtered out.

```php
/** Application user account */
class User extends Model
{
    /** User name formatted with first letter capitalized */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value): string => ucfirst((string) $value),
        );
    }

    /** User initials (e.g. "JD" for "John Doe") */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect(explode(' ', $this->name))
                ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
                ->implode(''),
        );
    }

    /** Polymorphic images (avatar gallery, etc.) */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
```

Generated TypeScript using the `model-full` template:

```TypeScript
import type { Image } from './';

/** Application user account */
export interface User
{
    // Columns
    id: number;
    /** User name formatted with first letter capitalized */
    name: string;
    email: string;
    // Mutators
    /** User initials (e.g. "JD" for "John Doe") */
    initials: string;
    // Relations
    /** Polymorphic images (avatar gallery, etc.) */
    images: Image[];
    images_count: number;
    images_exists: boolean;
}
```

### Timestamps as Date Objects

By default, timestamp columns (`date`, `datetime`, `timestamp`, and their immutable variants) are mapped to `string` in TypeScript. If your frontend works with `Date` objects instead, you can enable date mapping:

```php
// config/ts-publish.php

'timestamps_as_date' => true,
```

| Config Value | Generated TypeScript Type |
|--------------|---------------------------|
| `false`      | `created_at: string`      |
| `true`       | `created_at: Date`        |

### Custom TypeScript Type Mappings

This package ships with a comprehensive set of PHP-to-TypeScript type mappings (e.g., `integer` → `number`, `boolean` → `boolean`, `json` → `object`). You can override existing mappings or add new ones using the `custom_ts_mappings` config option:

```php
// config/ts-publish.php

'custom_ts_mappings' => [
    'binary' => 'Blob',
    'json' => 'Record<string, unknown>',   // Override the default 'object' mapping
    'money' => 'number',                    // Add a custom type
],
```

> [!TIP]
> Custom mappings are merged with the built-in map and take precedence. Type keys are case-insensitive. For per-model type overrides, use the `#[TsCasts]` attribute instead.

### Output Options

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

The JSON output from `watcher.enabled` is designed to work with build tools and file watchers (like the [@tolki/ts Vite plugin](#enum-metadata-vite-plugin)) that need to know which PHP source files were collected so they can trigger a re-publish when those files change.

## API Resources

This package can generate TypeScript interfaces from your Laravel [API Resources](https://laravel.com/docs/eloquent-resources) (`JsonResource` classes). It statically analyzes the `toArray()` method to extract property names, types, and optionality — producing a TypeScript interface that matches the shape of your API responses.

By default, the package will look for resources in the `app/Http/Resources` directory. You can customize this with the `resources.additional_directories`, `resources.included`, and `resources.excluded` config options (see [Filtering Resources](#filtering-resources)).

### How It Works

The package uses PHP Parser to statically analyze each resource's `toArray()` method. It resolves property types by inspecting the backing Eloquent model's database schema and cast definitions. The backing model is determined from (in priority order):

1. The `#[TsResource(model:)]` attribute
2. The `@mixin` PHPDoc tag (resolved via use statements)
3. Convention-based guess — reverses Laravel's naming convention (`App\Http\Resources\UserResource` → `App\Models\User`)
4. `#[UseResource]` attribute scan — checks all collected models for a `#[UseResource(ResourceClass::class)]` attribute pointing to this resource (Laravel 12+ only)

Most resources only need `@mixin` or the naming convention. The `#[TsResource(model:)]` attribute is useful when the resource name doesn't match the model, and `#[UseResource]` handles cases where the resource lives outside the standard `Http\Resources` namespace.

### Supported Patterns

The analyzer recognizes the following patterns inside `toArray()`:

#### Direct Property Access

```php
'id' => $this->id,
'name' => $this->name,
'status' => $this->status,       // Enum cast → generates enum type
```

Types are resolved from the model's database columns and cast definitions.

#### Conditional Methods

All conditional methods produce **optional** properties (with `?` in TypeScript):

| Method                                      | Description                       | Generated Type           |
|---------------------------------------------|-----------------------------------|--------------------------|
| `$this->when(cond, value)`                  | Include when condition is true    | Inferred from value      |
| `$this->whenHas('attr')`                    | Include when attribute is present | From model column type   |
| `$this->whenNotNull($this->attr)`           | Include when not null             | From model column type   |
| `$this->whenLoaded('relation')`             | Include when relation is loaded   | From model relation type |
| `$this->whenCounted('relation')`            | Include when count is loaded      | `number`                 |
| `$this->whenAggregated('rel', 'col', 'fn')` | Include when aggregate is loaded  | `number`                 |
| `$this->whenPivotLoaded('table')`           | Include when pivot is loaded      | `unknown`                |

See [Nullable Relations](#nullable-relations) for `whenLoaded` nullability handling.

#### Enum Properties with `EnumResource`

Use `EnumResource::make()` to expose enum-cast properties as rich enum objects:

```php
'status' => EnumResource::make($this->status),
'currency' => EnumResource::make($this->currency),
```

When `enums.use_tolki_package` is enabled (the default), these generate `AsEnum<typeof EnumName>` types with automatic imports. When disabled, they generate the enum's `Type` alias (e.g., `StatusType`).

#### Nested Resources

Reference other resources using `::make()`, `::collection()`, or `new`:

```php
// Single nested resource (optional when inside whenLoaded)
'author' => UserResource::make($this->whenLoaded('user')),

// Using new instead of ::make() — works identically
'author' => new UserResource($this->whenLoaded('user')),

// Collection of nested resources
'tags' => TagResource::collection($this->whenLoaded('tags')),

// Non-conditional nested resource
'owner' => UserResource::make($this->user),
```

Both `SomeResource::make(...)` and `new SomeResource(...)` are fully supported and behave identically — the analyzer resolves the resource type, tracks the FQCN for imports, and detects conditional arguments for optionality.

Self-referencing resources are also supported:

```php
'parent' => CategoryResource::make($this->whenLoaded('parent')),
'children' => CategoryResource::collection($this->whenLoaded('children')),
```

#### Merge Operations

Use `merge` and `mergeWhen` to spread additional properties into the response:

```php
// Unconditional merge — properties are required (not optional)
$this->merge([
    'full_name' => $this->first_name . ' ' . $this->last_name,
    'total_display' => $this->total,
]),

// Conditional merge — properties are optional
$this->mergeWhen($this->is_featured, [
    'weight' => $this->weight,
    'dimensions' => $this->dimensions,
]),
```

Both `merge` and `mergeWhen` also accept closures and arrow functions instead of array literals:

```php
// merge with closure
$this->merge(fn () => [
    'currency_label' => $this->currency,
]),

// mergeWhen with closure
$this->mergeWhen($this->paid_at !== null, fn () => [
    'shipped_at' => $this->shipped_at,
    'tracking' => $this->tracking_number,
]),
```

| Method                          | Optionality    | Description                       |
|---------------------------------|----------------|-----------------------------------|
| `$this->merge([...])`           | Required       | Properties are always present     |
| `$this->mergeWhen(cond, [...])` | Optional (`?`) | Properties included conditionally |

#### Closure & Arrow Function Values

The analyzer resolves closures and arrow functions used as value arguments. Simple closures that return a single expression are analyzed recursively:

```php
// Arrow function — return expression analyzed directly
'status' => $this->when(true, fn () => $this->status),

// Arrow function returning a nested resource
'user' => $this->when(true, fn () => UserResource::make($this->user)),

// Full closure — first return statement is analyzed
'notes' => $this->when(true, function () {
    return $this->notes;
}),
```

This works anywhere a value expression is expected — including `when`, `whenLoaded`, `whenNotNull`, `merge`, and `mergeWhen`.

#### Parent `toArray()` Spread

Extend a parent resource using `...parent::toArray($request)`. Parent properties appear first, and the child can override any key:

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => EnumResource::make($this->status),
        ];
    }
}

class ApiPostResource extends PostResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'status' => $this->status,       // Overrides parent's EnumResource type
        ];
    }
}
```

The child `ApiPostResource` inherits all parent properties (`id`, `title`, `status`), with `status` overridden to use the plain enum value instead of `EnumResource::make()`.

If the parent itself extends `JsonResource` (the base class), the spread automatically delegates to the model's database attributes — see [JsonResource Base Delegation](#jsonresource-base-delegation).

#### Trait Method Spread

Spread trait method return values into `toArray()` with `...$this->traitMethod()`. The analyzer reads `@return array{key: type}` PHPDoc annotations to resolve property types:

```php
trait IncludesMorphValue
{
    /**
     * @return array{morphValue: string}
     */
    protected function includeMorphValue(): array
    {
        return ['morphValue' => $this->resource->getMorphClass()];
    }
}

class PostResource extends JsonResource
{
    use IncludesMorphValue;

    public function toArray(Request $request): array
    {
        return [
            ...$this->includeMorphValue(),
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
}
```

Generates:

```typescript
export interface Post {
    morphValue: string;   // From trait PHPDoc
    id: number;
    title: string;
}
```

Multiline `@return` shapes are also supported:

```php
/**
 * @return array{
 *     firstName: string,
 *     lastName: string,
 *     isActive: bool,
 * }
 */
protected function includeProfile(): array
{
    // ...
}
```

Another option for defining the return types of a trait method is to use the `#[TsResourceCasts]` attribute on the trait method itself with the same syntax as the `#[TsCasts]` attribute for models:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;

trait IncludesExtras
{
    #[TsResourceCasts([
        'location' => ['type' => 'GeoPoint', 'import' => '@/types/geo'],
        'flag' => ['type' => 'string | null', 'optional' => true],
        'extra' => 'Record<string, unknown>',
    ])]
    protected function includeCastedExtras(): array
    {
        return [
            'location' => strtoupper('x'),
            'flag' => strtolower('y'),
        ];
    }
}
```

> [!TIP]
> Trait spreads also flow through parent inheritance. If a parent resource spreads a trait method and a child extends it with `...parent::toArray($request)`, the child inherits the trait-contributed properties.

> [!NOTE]
> When a trait method has no `@return array{...}` PHPDoc or `#[TsResourceCasts]` attribute, its properties will be typed as `unknown`.

#### JsonResource Base Delegation

Resources that have **no `toArray()` method** or whose `toArray()` simply returns `parent::toArray($request)` automatically generate properties from the backing model's database schema:

```php
/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    // No toArray() — properties auto-generated from User model
}
```

You can also spread the base properties and add computed keys:

```php
/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'full_name' => strtoupper($this->name),
        ];
    }
}
```

The model is resolved from `#[TsResource(model:)]`, `@mixin` PHPDoc, or use statements. When no model can be detected, the resource produces an empty interface.

#### Attribute Filters (`only` / `except`)

Resources that use `$this->only([...])` or `$this->except([...])` to filter model attributes are supported — both as a direct return value and as a spread:

```php
// As the return value
public function toArray(Request $request): array
{
    return $this->only(['id', 'name', 'email']);
}

// As a spread in a return array
public function toArray(Request $request): array
{
    return [
        ...$this->except(['password', 'remember_token']),
        'role' => EnumResource::make($this->role),
    ];
}
```

Both methods delegate to the backing model's full database schema and filter by the listed keys. Properties retain their original types from the model.

> [!NOTE]
> Currently only `only` and `except` are supported as attribute filter methods. Other collection-style methods are not analyzed. If you find you need additional methods, open and issue, or better yet, submit a PR with the added functionality! [See FiltersModelAttributes](src/Analyzers/Concerns/FiltersModelAttributes.php)

#### Resource Collections

`ResourceCollection` subclasses are supported. The analyzer resolves `$this->collection` to the singular resource type as an array:

```php
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'has_admin' => true,
        ];
    }
}
```

Generates:

```typescript
import type { UserResource } from './';

export interface UserCollection
{
    data: UserResource[];
    has_admin: unknown;
}
```

The singular resource is resolved from:

1. **Explicit `$collects` property** — if defined on the collection class
2. **Naming convention** — `UserCollection` → `UserResource` (strips "Collection", appends "Resource")

```php
class OrderCollection extends ResourceCollection
{
    // Explicit: use OrderResource as the singular resource
    public $collects = OrderResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
```

When the singular resource cannot be resolved (e.g., `MiscCollection` with no matching `MiscResource`), `$this->collection` falls back to `unknown`.

Larger support for `ResourceCollection` features (e.g., pagination metadata, `additional()` method, etc.) may be added in a future release.

### Example

Given this resource:

```php
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

/**
 * User account resource.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => EnumResource::make($this->role),
            'profile' => $this->whenLoaded('profile'),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'phone' => $this->whenHas('phone'),
            'avatar' => $this->whenNotNull($this->avatar),
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
```

The package generates the following TypeScript interface:

```TypeScript
import { type AsEnum } from '@tolki/ts';

import { Role } from '../enums';
import type { Profile } from '../models';
import type { PostResource } from './';

/** User account resource. */
export interface UserResource
{
    id: number;
    name: string;
    email: string;
    role: AsEnum<typeof Role>;
    profile?: Profile | null;
    posts?: PostResource[];
    phone?: string | null;
    avatar?: string | null;
    posts_count?: number;
}
```

Notice how:

- Direct properties (`id`, `name`, `email`) are **required**
- `whenLoaded`, `whenHas`, `whenNotNull`, and `whenCounted` properties are **optional** (`?`)
- `EnumResource::make()` generates `AsEnum<typeof Role>` with the proper imports
- `PostResource::collection()` is typed as `PostResource[]`
- Bare `whenLoaded('profile')` resolves to the model relation type (`Profile | null`)
- PHPDoc class descriptions are preserved as JSDoc comments

### Resource Attributes

Three attributes are available for configuring resource TypeScript generation:

| Attribute            | Target                    | Description                                                                  |
|----------------------|---------------------------|------------------------------------------------------------------------------|
| `#[TsResource]`      | Resource class            | Override the interface name, specify the backing model, or add a description |
| `#[TsResourceCasts]` | Resource class or method  | Override or add property types with custom TypeScript types                  |
| `#[TsExclude]`       | Resource class            | Exclude the entire resource from the TypeScript output.                      |

See [Excluding with TsExclude](#excluding-with-tsexclude)

#### `#[TsResource]` — Configure Resource Generation

Use this attribute to override the generated interface name, explicitly specify the backing model, or add a description:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use App\Models\User;

#[TsResource(name: 'UserData', model: User::class, description: 'User API response')]
class UserResource extends JsonResource
{
    // ...
}
```

| Parameter     | Type           | Default            | Description                                   |
|---------------|----------------|--------------------|-----------------------------------------------|
| `name`        | `?string`      | Class name         | Override the TypeScript interface name        |
| `model`       | `?class-string`| Auto-detected      | Explicitly specify the backing Eloquent model |
| `description` | `string`       | `''`               | Added as a JSDoc comment above the interface  |

> [!TIP]
> When `name` is set, it also affects the output filename. For example, `#[TsResource(name: 'Address')]` generates `address.ts` instead of `address-resource.ts`.

#### `#[TsResourceCasts]` — Override Property Types

Use this attribute to override inferred types or add virtual properties with custom TypeScript types:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;

#[TsResourceCasts([
    'metadata' => 'Record<string, unknown>',
    'coordinates' => ['type' => 'GeoPoint', 'import' => '@/types/geo'],
    'flagged_at' => ['type' => 'string | null', 'optional' => true],
])]
class CommentResource extends JsonResource
{
    // ...
}
```

Each entry can be:

| Format                | Example                                             | Description                            |
|-----------------------|-----------------------------------------------------|----------------------------------------|
| Plain string          | `'Record<string, unknown>'`                         | Override the type only                 |
| Array with `import`   | `['type' => 'GeoPoint', 'import' => '@/types/geo']` | Custom type with an import statement   |
| Array with `optional` | `['type' => 'string', 'optional' => true]`          | Override the type and mark as optional |

Properties defined in `#[TsResourceCasts]` that don't exist in `toArray()` are appended to the generated interface. Properties that do exist have their types overridden.

Generated TypeScript with the `coordinates` example:

```TypeScript
import type { GeoPoint } from '@/types/geo';

export interface CommentResource
{
    id: number;
    content: string;
    is_flagged: boolean;
    flagged_at?: string | null;
    metadata: Record<string, unknown>;
    author?: UserResource;
    post?: PostResource;
    coordinates: GeoPoint;
}
```

##### On Trait Methods

`#[TsResourceCasts]` can also be applied to **trait methods** that are spread into `toArray()`. This lets you control types for trait-contributed properties without modifying the resource class:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;

trait IncludesLocation
{
    #[TsResourceCasts([
        'location' => ['type' => 'GeoPoint', 'import' => '@/types/geo'],
        'flag' => ['type' => 'string | null', 'optional' => true],
        'extra' => 'Record<string, unknown>',
    ])]
    protected function includeLocation(): array
    {
        return [
            'location' => $this->coordinates,
            'flag' => $this->flag,
        ];
    }
}
```

The attribute works identically to the class-level version — overriding types, marking properties optional, adding imports, and appending new properties. Properties defined in the attribute that don't exist in the method's return array (like `extra` above) are appended.

### Nullable Relations

When `whenLoaded('relation')` resolves a relation type, the package determines whether it should include `| null` based on the relation kind and the database schema.

This is controlled by the `nullable_relations` config option (enabled by default). The strategy for each relation type is:

| Relation Type                         | Strategy    | Description                                          |
|---------------------------------------|-------------|------------------------------------------------------|
| `HasOne`, `MorphOne`, `HasOneThrough` | `nullable`  | Always nullable — the related record may not exist   |
| `BelongsTo`                           | `fk`        | Checks the foreign key column's DB-level nullability |
| `MorphTo`                             | `morph`     | Checks both the morph type and FK column nullability |
| `HasMany`, `BelongsToMany`, etc.      | `never`     | Collection relations — typed as arrays, never null   |

For example, a `BelongsTo` relation with a nullable foreign key:

```php
// Migration: $table->foreignId('user_id')->nullable();

// Resource:
'user' => UserResource::make($this->whenLoaded('user')),
```

Generates `user?: UserResource | null` — optional (from `whenLoaded`) and nullable (from the nullable FK).

You can disable nullable relation detection globally:

```php
// config/ts-publish.php
'models' => [
    'nullable_relations' => false,
],
```

Or override the strategy for specific relation types using `models.relation_nullability_map`:

```php
// config/ts-publish.php
'models' => [
    'relation_nullability_map' => [
        \Illuminate\Database\Eloquent\Relations\HasOne::class => 'never',
    ],
],
```

Valid strategies are `'nullable'`, `'never'`, `'fk'`, and `'morph'`.

### Filtering Resources

You can customize which resources are discovered using the same include/exclude pattern as models and enums:

```php
// config/ts-publish.php

'resources' => [
    // Only publish these specific resources (leave empty to include all)
    'included' => [
        App\Http\Resources\UserResource::class,
        App\Http\Resources\PostResource::class,
    ],

    // Exclude specific resources from publishing
    'excluded' => [
        App\Http\Resources\InternalResource::class,
    ],

    // Search additional directories for resources
    'additional_directories' => [
        'modules/Blog/Http/Resources',
    ],
],
```

> [!TIP]
> Like models and enums, include and exclude settings accept both fully-qualified class names and directory paths.

### Conditional Resource Publishing

You can disable resource publishing entirely in the config file:

```php
// config/ts-publish.php

'resources' => [
    'enabled' => false,
],
```

Or publish only resources using the command flag:

```bash
php artisan ts:publish --only-resources
```

The `--only-resources` flag cannot be combined with `--only-enums` or `--only-models`.

## Extending Interfaces with `#[TsExtends]` & Configs

The `#[TsExtends]` attribute allows you to specify that a generated TypeScript interface should extend one or more other interfaces. This is useful when this package's limitations doesn't include properties on your interfaces. Another use is for sharing common properties across multiple models or resources without duplication.

You can place the `#[TsExtends]` attribute on any model or resource class, their parent classes, or even on traits used by those classes. The specified interfaces will be included in the generated TypeScript `extends` clause for any class that has the attribute directly or inherits it from a parent class or trait.

The `#[TsExtends]` attribute can be place multiple times on the same class or trait to define multiple interfaces that should be extended. The interfaces specified in all `#[TsExtends]` attributes on the class and its parents/traits will be combined into a single `extends` clause.

The `#[TsExtends]` attribute accepts the following parameters:

| Parameter     | Type           | Default            | Description                                                      |
|---------------|----------------|--------------------|------------------------------------------------------------------|
| `extends`     | `string`       | `required`         | The interface to use or the way it should be used.               |
| `import`      | `?string`      | `null`             | The import path for the extended interfaces.                     |
| `types`       | `string[]`     | `[]`               | The names of the interfaces to import from the specified module. |

### Example usage of `#[TsExtends]`

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('ExampleInterface', '@js/types/models')]
class User extends Model
{
    // This model's generated TypeScript interface will extend ExampleInterface from @js/types/models
}
```

The above will generate the following TypeScript interface for the `User` model:

```typescript
import type { ExampleInterface } from '@js/types/models';

export interface User extends ExampleInterface
{
    // ... model properties
}
```

You can also specify the interface extension with TypeScript helpers like `Partial`, `Pick`, or `Omit`. Use the third argument to list the interfaces used in the extension for proper importing and the first argument will be output as-is in the generated TypeScript:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('Partial<ExampleInterface>', '@js/types/resources', ['ExampleInterface'])]
#[TsExtends('Pick<ModularInterface, "id" | "name">', '@js/types/resources', ['ModularInterface'])]
class UserResource extends JsonResource
{
    // This resource's generated TypeScript interface will extend Partial<ExampleInterface> and Pick<ModularInterface, "id" | "name">
}
```

The generated TypeScript interface for `UserResource` will look like this:

```typescript
import type { ExampleInterface, ModularInterface } from '@js/types/resources';

export interface UserResource extends Partial<ExampleInterface>, Pick<ModularInterface, "id" | "name">
{
    // ... resource properties
}
```

### Global Interface Extensions

In some cases, you may want all your models or resources to extend a common interface without having to add `#[TsExtends]` to each class. You can achieve this with the `ts_extends.models` and `ts_extends.resources` config options:

```php
// config/ts-publish.php
'ts_extends' => [
    'models' => [
        'HasTimestamps',
        ['extends' => 'BaseFields', 'import' => '@/types/base'],
        ['extends' => 'Pick<Auditable, "created_by">', 'import' => '@/types/audit', 'types' => ['Auditable']],
    ],
    'resources' => [
        ['extends' => 'BaseResource', 'import' => '@/types/base'],
    ],
],
```

With the above config, all generated model interfaces will extend `HasTimestamps`, `BaseFields`, and `Pick<Auditable, "created_by">`, while all resource interfaces will extend `BaseResource`. The necessary imports will be included automatically.

Example model:

```typescript
import type { BaseFields } from '@/types/base';
import type { Auditable } from '@/types/audit';

export interface User extends HasTimestamps, BaseFields, Pick<Auditable, "created_by">
{
    // ... model properties
}
```

Example resource:

```typescript
import type { BaseResource } from '@/types/base';

export interface UserResource extends BaseResource
{
    // ... resource properties
}
```

## Excluding with `#[TsExclude]`

The `#[TsExclude]` attribute lets you exclude specific items from the TypeScript output. This is especially useful when `enums.auto_include_methods` or `enums.auto_include_static_methods` is enabled and you want to opt out individual enum methods.

`#[TsExclude]` can be applied to:

| Target             | Effect                                                      |
|--------------------|-------------------------------------------------------------|
| Enum class         | Entire enum is excluded from collection and publishing      |
| Enum method        | Method is excluded from the TypeScript output               |
| Model class        | Entire model is excluded from collection and publishing     |
| Model accessor     | Mutator/accessor is excluded from the TypeScript output     |
| Model relation     | Relation is excluded from the TypeScript output             |
| Resource class     | Entire resource is excluded from collection and publishing  |

> [!NOTE]
> `#[TsExclude]` always takes priority — even if you use attributes like `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` on enum methods, the methods will be excluded.

### Excluding an entire enum, model, or resource

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

#[TsExclude]
enum InternalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
}

#[TsExclude]
class AuditLog extends Model
{
    // This model will not be published to TypeScript
}

#[TsExclude]
class InternalResource extends JsonResource
{
    // This resource will not be published to TypeScript
}
```

### Excluding specific enum methods

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    // Included in TypeScript output
    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    // Excluded from TypeScript output
    #[TsExclude]
    public function internalCode(): int
    {
        return match($this) {
            self::Active => 100,
            self::Inactive => 200,
        };
    }
}
```

### Excluding model accessors and relations

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

class User extends Model
{
    // Excluded from TypeScript output
    #[TsExclude]
    protected function secretToken(): Attribute
    {
        return Attribute::make(
            get: fn (): string => 'hidden',
        );
    }

    // Excluded from TypeScript output
    #[TsExclude]
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
```

## Casing Configurations

This package provides two independent config options to control the casing of generated property and method names:

| Config Key                   | Applies To                                    | Default    |
|------------------------------|-----------------------------------------------|------------|
| `models.relationship_case`   | Model relationship names, `_count`, `_exists` | `'snake'`  |
| `enums.method_case`          | Enum method and static method names           | `'camel'`  |

Both accept `'snake'`, `'camel'`, or `'pascal'`.

### Relationship Case Style

Controls relationship names in the generated model TypeScript interfaces:

```php
// config/ts-publish.php

'models' => [
    'relationship_case' => 'snake', // default
],
```

| Config Value | Relationship `hasMany(Post::class)`  | Count                | Exists                |
|--------------|--------------------------------------|----------------------|-----------------------|
| `'snake'`    | `posts: Post[]`                      | `posts_count`        | `posts_exists`        |
| `'camel'`    | `posts: Post[]`                      | `postsCount`         | `postsExists`         |
| `'pascal'`   | `Posts: Post[]`                      | `PostsCount`         | `PostsExists`         |

> [!NOTE]
> For each relationship defined on a model, this package automatically generates `_count` and `_exists` properties alongside the relation itself. These correspond to [Laravel's `withCount` and `withExists`](https://laravel.com/docs/eloquent-relationships#counting-related-models) features and are included in every generated model interface.

### Enum Method Case Style

Controls the casing of enum method and static method names in the generated TypeScript output:

```php
// config/ts-publish.php

'enums' => [
    'method_case' => 'camel', // default
],
```

| Config Value | Method `getLabel()` | Static Method `AllLabels()` |
|--------------|---------------------|-----------------------------|
| `'snake'`    | `get_label`         | `all_labels`                |
| `'camel'`    | `getLabel`          | `allLabels`                 |
| `'pascal'`   | `GetLabel`          | `AllLabels`                 |

> [!TIP]
> This setting applies to all enum methods — both instance methods (via `#[TsEnumMethod]` or `enums.auto_include_methods`) and static methods (via `#[TsEnumStaticMethod]` or `enums.auto_include_static_methods`). You can still override individual method names using the `name` parameter on the attribute.

## JSON Enum HTTP API Resource

This package ships with an `EnumResource` — a Laravel [JSON resource](https://laravel.com/docs/eloquent-resources) that transforms any PHP enum case into a flat, API-friendly array. It runs the enum through the same transformer pipeline used for TypeScript publishing, so every `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` you've configured is automatically included.

The `EnumResource` class is useful when you need to send a single enum instance (e.g., a model's status) to the frontend as a rich object with resolved method values, rather than just the raw string or integer value.

### Basic Usage

In a controller or route:

```php

use AbeTwoThree\LaravelTsPublish\EnumResource;
use App\Enums\Status;

return new EnumResource(Status::Published);
```

From another HTTP API resource to automatically transform an enum property on a model or collection of models:

```php

namespace App\Http\Resources;
 
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use App\Enums\Status;
use App\Enums\MembershipLevel;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Assuming "status" is a model property cast to the Status enum
            'status' => new EnumResource($this->status),
            // Can also create enum resources from any enum case, not just model properties
            'membership_level' => new EnumResource(MembershipLevel::Free),
        ];
    }
}
```

### Enum Case Instance Response

Response:

```json
{
    "name": "Published",
    "value": 1,
    "backed": true,
    "icon": "check",
    "color": "green",
}
```

### Response Shape

Every response includes these base keys:

| Key      | Type            | Description                                                             |
|----------|-----------------|-------------------------------------------------------------------------|
| `name`   | `string`        | The enum case name                                                      |
| `value`  | `string \| int` | The backed value, or the case name for unit enums                       |
| `backed` | `bool`          | Whether the enum is a backed enum                                       |

Instance methods (decorated with `#[TsEnumMethod]` or via the auto-include config setting) are flattened as top-level keys with the resolved value **for the specific case** passed to the resource. Static methods (decorated with `#[TsEnumStaticMethod]` or via the auto-include config setting) are included as top-level keys with the resolved value from the static method.

This allows the `EnumResource` to provide the same data as the published TypeScript enum when you call the `from` method from the `@tolki/ts` package on the enum with the matching case value.

### Unit Enums

Unit enums (enums without a backed type) are also supported. Since they have no backed value, the `value` key will equal the case `name` and `backed` will be `false`:

```php
return new EnumResource(Role::Admin);
```

```json
{
    "name": "Admin",
    "value": "Admin",
    "backed": false
}
```

### Relationship to TypeScript Publishing

The `EnumResource` uses the same `EnumTransformer` pipeline as the `ts:publish` command. This means:

- Only methods marked with `#[TsEnumMethod]` (or all public methods when auto-include is enabled) are included.
- Methods with required parameters but no `params` on the attribute are excluded.
- The `enums.method_case` config setting applies to the method key names in the response.

This ensures the JSON response shape is consistent with the TypeScript types generated by this package.

### Typing API Responses with `AsEnum`

The `@tolki/ts` package exports an `AsEnum` utility type that resolves the `EnumResource` JSON response shape for any published enum. This gives you full type safety when consuming enum API responses on the frontend.

```TypeScript
import type { AsEnum } from '@tolki/ts';
import type { Status } from '@/types/enums';

// Full discriminated union of all cases
type StatusResponse = AsEnum<typeof Status>;
// { name: 'Draft'; value: 0; backed: true; icon: 'pencil'; color: 'gray'; ... }
// | { name: 'Published'; value: 1; backed: true; icon: 'check'; color: 'green'; ... }
```

The optional second type parameter lets you pre-narrow to a specific case by value:

```TypeScript
// Narrowed to a single case
type DraftResponse = AsEnum<typeof Status, 0>;
// { name: 'Draft'; value: 0; backed: true; icon: 'pencil'; color: 'gray'; ... }
```

Use it to type your API responses:

```TypeScript
const response = await fetch(`/api/articles/${id}`);
const article: { id: number; status: AsEnum<typeof Status> } = await response.json();

if (article.status.value === 0) {
    // TypeScript knows this is the Draft case
    console.log(article.status.icon); // 'pencil'
}
```

### Auto-Generated `Resource` Model Interface

When `enums.use_tolki_package` is enabled (the default), any model with enum-cast columns automatically gets a `{Model}Resource` companion set of interfaces. These interfaces replace each enum-backed property with `AsEnum<typeof EnumName>`, so you don't have to compose `Omit` + `AsEnum` manually on model properties or mutators that are cast to enums.

For a Post model that casts the database columns `status`, `visibility`, and `priority` to enums, the publisher will generate a `PostResource` interface that looks like this:

```TypeScript
export interface Post {
    id: number;
    title: string;
    content: string;
    status: StatusType;         // Original enum type
    visibility: VisibilityType | null; // Original enum type
    priority: PriorityType | null;     // Original enum type
}

// Auto-generated — no manual typing needed
export interface PostResource extends Omit<Post, 'status' | 'visibility' | 'priority'>
{
    status: AsEnum<typeof Status>;
    visibility: AsEnum<typeof Visibility> | null;
    priority: AsEnum<typeof Priority> | null;
}
```

Use it to type API responses that use the `EnumResource` class:

```TypeScript
import type { PostResource } from '@js/types/data/models';

const response = await fetch('/api/posts/1');
const post: PostResource = await response.json();

post.status.value; // 0 | 1
post.status.icon;  // 'pencil' | 'check'
```

The interfaces are generated for both the `model-full` and `model-split` templates. In split mode, the template will create a `PostResource` interface for the properties interface and a `PostMutatorsResource` interface for the mutators interface, since mutators can also be enum-cast properties:

```TypeScript
export interface PostResource extends Omit<Post, 'status' | 'visibility' | 'priority'>
{
    status: AsEnum<typeof Status>;
    // ...
}

export interface PostMutators
{
    due_notice: DueAtNoticeType;
}

export interface PostMutatorsResource extends Omit<PostMutators, 'due_notice'>
{
    due_notice: AsEnum<typeof DueAtNotice>;
}
```

Naming conflicts are handled automatically — if two enum FQCNs share the same base name, namespace-prefixed aliases are used for both the type and const imports (e.g., `AppStatus`, `CrmStatus`).

## Modular Publishing

This package organizes all generated TypeScript files into namespace-derived directory trees that mirror your PHP namespace structure. This is especially useful for applications that use a modular architecture (e.g., [InterNACHI/modular](https://github.com/InterNACHI/modular) or a custom module structure).

### Output Structure

The output structure reflects your PHP namespaces:

```text
resources/js/types/data/
├── app/
│   ├── enums/
│   │   ├── role.ts
│   │   ├── membership-level.ts
│   │   └── index.ts
│   ├── models/
│   │   ├── user.ts
│   │   ├── order.ts
│   │   └── index.ts
│   └── http/
│       └── resources/
│           ├── user-resource.ts
│           ├── order-resource.ts
│           └── index.ts
├── accounting/
│   ├── enums/
│   │   ├── invoice-status.ts
│   │   └── index.ts
│   ├── models/
│   │   ├── invoice.ts
│   │   └── index.ts
│   └── http/
│       └── resources/
│           ├── invoice-resource.ts
│           └── index.ts
├── shipping/
│   ├── enums/
│   │   ├── shipment-status.ts
│   │   └── index.ts
│   └── models/
│       ├── shipment.ts
│       └── index.ts
└── global.d.ts
```

Each namespace directory gets its own barrel `index.ts` file that exports all types within that directory.

### How It Works

Modular publishing converts each class's PHP namespace into a kebab-cased directory path. For example:

| PHP Class                           | Output File                                   |
|-------------------------------------|-----------------------------------------------|
| `App\Models\User`                   | `app/models/user.ts`                          |
| `App\Enums\Role`                    | `app/enums/role.ts`                           |
| `Accounting\Models\Invoice`         | `accounting/models/invoice.ts`                |
| `Shipping\Enums\ShipmentStatus`     | `shipping/enums/shipment-status.ts`           |
| `App\Domain\Billing\Models\Invoice` | `app/domain/billing/models/invoice.ts`        |

Import paths between generated files are automatically computed as relative paths based on the namespace directory structure:

```TypeScript
// accounting/models/invoice.ts

import { Payment } from '.';                   // Same namespace (accounting/models)
import { User } from '../../app/models';       // Cross-module import
import { InvoiceStatusType } from '../enums';  // Sibling namespace (accounting/enums)

export interface Invoice {
    id: number;
    user_id: number;
    number: string;
    status: InvoiceStatusType;
    subtotal: number;
    tax: number;
    total: number;
    // ...
}

export interface InvoiceRelations {
    user: User;
    payments: Payment[];
    // ...
}

export interface InvoiceAll extends Invoice, InvoiceRelations {}
```

### Stripping a Namespace Prefix

If your modules live under a common namespace prefix (e.g., `Modules\`), you can strip it from the output path using the `namespace_strip_prefix` config option:

```php
// config/ts-publish.php

'namespace_strip_prefix' => 'Modules\\',
```

| PHP Class                              | Without Strip Prefix                | With `'Modules\\'` Strip Prefix      |
|----------------------------------------|-------------------------------------|---------------------------------------|
| `Modules\Blog\Models\Article`          | `modules/blog/models/article.ts`    | `blog/models/article.ts`             |
| `Modules\Shipping\Enums\Carrier`       | `modules/shipping/enums/carrier.ts` | `shipping/enums/carrier.ts`          |

This keeps the output directory structure clean by removing the redundant prefix.

### Barrel Files

Each namespace directory receives its own barrel `index.ts` file. For example, `accounting/models/index.ts`:

```TypeScript
export * from './invoice';
```

And `app/models/index.ts`:

```TypeScript
export * from './address';
export * from './order';
export * from './product';
export * from './user';
// ... all models in this namespace
```

This allows you to import types from any namespace barrel:

```TypeScript
import { User, Order } from '@js/types/data/app/models';
import { Invoice } from '@js/types/data/accounting/models';
import { InvoiceStatusType } from '@js/types/data/accounting/enums';
```

## Extending & Customizing the Pipeline

This package uses a **Collector → Generator → Transformer → Writer → Template** pipeline. Each stage is fully configurable via the config file, allowing you to extend or replace any component without modifying the package itself:

| Pipeline Stage | Config Key                    | Default Class          | Responsibility                          |
|----------------|-------------------------------|------------------------|-----------------------------------------|
| Collector      | `models.collector_class`      | `ModelsCollector`      | Discovers PHP model classes             |
| Collector      | `enums.collector_class`       | `EnumsCollector`       | Discovers PHP enum classes              |
| Collector      | `resources.collector_class`   | `ResourcesCollector`   | Discovers PHP resource classes          |
| Generator      | `models.generator_class`      | `ModelGenerator`       | Orchestrates transforming and writing   |
| Generator      | `enums.generator_class`       | `EnumGenerator`        | Orchestrates transforming and writing   |
| Generator      | `resources.generator_class`   | `ResourceGenerator`    | Orchestrates transforming and writing   |
| Transformer    | `models.transformer_class`    | `ModelTransformer`     | Converts PHP class into TypeScript data |
| Transformer    | `enums.transformer_class`     | `EnumTransformer`      | Converts PHP enum into TypeScript data  |
| Transformer    | `resources.transformer_class` | `ResourceTransformer`  | Converts PHP resource into TypeScript data |
| Writer         | `models.writer_class`         | `ModelWriter`          | Writes TypeScript model files           |
| Writer         | `enums.writer_class`          | `EnumWriter`           | Writes TypeScript enum files            |
| Writer         | `resources.writer_class`      | `ResourceWriter`       | Writes TypeScript resource files        |
| Writer         | `barrel_writer_class`         | `BarrelWriter`         | Writes barrel `index.ts` files          |
| Writer         | `globals.writer_class`        | `GlobalsWriter`        | Writes global declaration file          |
| Writer         | `json.writer_class`           | `JsonWriter`           | Writes JSON definitions file            |
| Writer         | `watcher.writer_class`        | `WatcherJsonWriter`    | Writes collected files JSON for watchers|
| Template       | `models.template`             | `model-split`          | Blade template for model output         |
| Template       | `enums.template`              | `enum`                 | Blade template for enum output          |
| Template       | `resources.template`          | `resource`             | Blade template for resource output      |

To swap a component, create a class that extends the default and override the config key:

```php
// config/ts-publish.php

'models' => [
    'transformer_class' => App\TypeScript\CustomModelTransformer::class,
],
```

> [!TIP]
> You can also publish and customize the Blade templates directly with `php artisan vendor:publish --tag="laravel-ts-publish-views"` if you only need to change the output format without modifying the pipeline logic.

## Pre-Command Hook

If you need to run custom logic right before the `ts:publish` command executes — such as dynamically configuring directories, adjusting included/excluded models, or performing any setup that requires processing — you can register a pre-command hook using `callCommandUsing`.

This is useful because the closure is only executed when the `ts:publish` command actually runs, not at service provider boot time. This keeps your boot process fast and avoids unnecessary overhead on every request.

### Basic Usage Of `callCommandUsing`

In your `AppServiceProvider` (or any service provider), register a closure in the `boot` method:

```php
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;

public function boot(): void
{
    LaravelTsPublish::callCommandUsing(function () {
        // This only runs when `php artisan ts:publish` is executed
        config()->set('ts-publish.models.additional_directories', [
            'modules/Blog/Models',
            'modules/Shop/Models',
        ]);
        config()->set('ts-publish.resources.additional_directories', [
            'modules/Blog/Http/Resources',
            'modules/Shop/Http/Resources',
        ]);
    });
}
```

### Dynamic Directory Discovery

A common use case is using Symfony Finder to automatically discover module directories:

```php
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use Symfony\Component\Finder\Finder;

public function boot(): void
{
    LaravelTsPublish::callCommandUsing(function () {
        $modelDirs = collect(Finder::create()->directories()->in(base_path('modules'))->name('Models')->depth(1))
            ->map(fn ($dir) => $dir->getRelativePathname())
            ->values()
            ->all();

        $enumDirs = collect(Finder::create()->directories()->in(base_path('modules'))->name('Enums')->depth(1))
            ->map(fn ($dir) => $dir->getRelativePathname())
            ->values()
            ->all();

        $resourceDirs = collect(Finder::create()->directories()->in(base_path('modules'))->name('Resources')->depth(2))
            ->map(fn ($dir) => $dir->getRelativePathname())
            ->values()
            ->all();

        config()->set('ts-publish.models.additional_directories', $modelDirs);
        config()->set('ts-publish.enums.additional_directories', $enumDirs);
        config()->set('ts-publish.resources.additional_directories', $resourceDirs);
    });
}
```

> [!NOTE]
> Only one closure can be registered at a time. Calling `callCommandUsing` again will replace the previous closure.

## Configuration Reference

Below is a quick reference of all available configuration options:

### General Settings

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `run_after_migrate`                   | `bool`     | `true`                               | Re-publish types after running migrations                        |
| `output_to_files`                     | `bool`     | `true`                               | Write generated TypeScript to `.ts` files                        |
| `output_directory`                    | `string`   | `resources/js/types/data`            | Directory where TypeScript files are written                     |
| `namespace_strip_prefix`              | `string`   | `''`                                 | Strip this prefix from namespaces in modular output              |
| `timestamps_as_date`                  | `bool`     | `false`                              | Map date/datetime/timestamp to `Date` instead of `string`        |
| `custom_ts_mappings`                  | `array`    | `[]`                                 | Override or extend PHP-to-TypeScript type mappings               |
| `ts_extends`                          | `array`    | `[]`                                 | Global `extends` clauses for all models/resources                |
| `barrel_writer_class`                 | `string`   | `BarrelWriter`                       | Class that writes barrel `index.ts` files                        |

### Models (`models.*`)

| Config Key                            | Type       | Default                              | Description                                                          |
|---------------------------------------|------------|--------------------------------------|----------------------------------------------------------------------|
| `models.enabled`                      | `bool`     | `true`                               | Enable or disable model publishing                                   |
| `models.namespace`                    | `string`   | `'models'`                           | Namespace label used in the global declaration file                  |
| `models.relationship_case`            | `string`   | `'snake'`                            | Case style for relationships: `snake`, `camel`, or `pascal`          |
| `models.nullable_relations`           | `bool`     | `true`                               | Append `\| null` to singular relation types based on smart detection |
| `models.relation_nullability_map`     | `array`    | `[]`                                 | Override nullability strategy per relation type                      |
| `models.template`                     | `string`   | `laravel-ts-publish::model-split`    | Blade template for model TypeScript output                           |
| `models.included`                     | `array`    | `[]`                                 | Only publish these models (empty = all)                              |
| `models.excluded`                     | `array`    | `[]`                                 | Exclude these models from publishing                                 |
| `models.additional_directories`       | `array`    | `[]`                                 | Extra directories to search for models                               |
| `models.collector_class`              | `string`   | `ModelsCollector`                    | Discovers PHP model classes                                          |
| `models.generator_class`              | `string`   | `ModelGenerator`                     | Orchestrates transforming and writing                                |
| `models.transformer_class`            | `string`   | `ModelTransformer`                   | Converts PHP class into TypeScript data                              |
| `models.writer_class`                 | `string`   | `ModelWriter`                        | Writes TypeScript model files                                        |

### Enums (`enums.*`)

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `enums.enabled`                       | `bool`     | `true`                               | Enable or disable enum publishing                                |
| `enums.namespace`                     | `string`   | `'enums'`                            | Namespace label used in the global declaration file              |
| `enums.method_case`                   | `string`   | `'camel'`                            | Case style for enum methods: `snake`, `camel`, or `pascal`       |
| `enums.auto_include_methods`          | `bool`     | `false`                              | Include all public non-static enum methods without attributes    |
| `enums.auto_include_static_methods`   | `bool`     | `false`                              | Include all public static enum methods without attributes        |
| `enums.metadata_enabled`              | `bool`     | `true`                               | Include `_cases`, `_methods`, `_static` metadata on enums        |
| `enums.use_tolki_package`             | `bool`     | `true`                               | Wrap enums in `defineEnum()` from `@tolki/ts`                    |
| `enums.template`                      | `string`   | `laravel-ts-publish::enum`           | Blade template for enum TypeScript output                        |
| `enums.included`                      | `array`    | `[]`                                 | Only publish these enums (empty = all)                           |
| `enums.excluded`                      | `array`    | `[]`                                 | Exclude these enums from publishing                              |
| `enums.additional_directories`        | `array`    | `[]`                                 | Extra directories to search for enums                            |
| `enums.collector_class`               | `string`   | `EnumsCollector`                     | Discovers PHP enum classes                                       |
| `enums.generator_class`               | `string`   | `EnumGenerator`                      | Orchestrates transforming and writing                            |
| `enums.transformer_class`             | `string`   | `EnumTransformer`                    | Converts PHP enum into TypeScript data                           |
| `enums.writer_class`                  | `string`   | `EnumWriter`                         | Writes TypeScript enum files                                     |

### Resources (`resources.*`)

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `resources.enabled`                   | `bool`     | `true`                               | Enable or disable resource publishing                            |
| `resources.namespace`                 | `string`   | `'resources'`                        | Namespace label used in the global declaration file              |
| `resources.template`                  | `string`   | `laravel-ts-publish::resource`       | Blade template for resource TypeScript output                    |
| `resources.included`                  | `array`    | `[]`                                 | Only publish these resources (empty = all)                       |
| `resources.excluded`                  | `array`    | `[]`                                 | Exclude these resources from publishing                          |
| `resources.additional_directories`    | `array`    | `[]`                                 | Extra directories to search for resources                        |
| `resources.collector_class`           | `string`   | `ResourcesCollector`                 | Discovers PHP resource classes                                   |
| `resources.generator_class`           | `string`   | `ResourceGenerator`                  | Orchestrates transforming and writing                            |
| `resources.transformer_class`         | `string`   | `ResourceTransformer`                | Converts PHP resource into TypeScript data                       |
| `resources.writer_class`              | `string`   | `ResourceWriter`                     | Writes TypeScript resource files                                 |

### Globals (`globals.*`)

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `globals.enabled`                     | `bool`     | `false`                              | Generate a `global.d.ts` namespace file                          |
| `globals.output_directory`            | `?string`  | `null`                               | Directory for the global declaration file                        |
| `globals.filename`                    | `string`   | `laravel-ts-global.d.ts`             | Filename for the global declaration file                         |
| `globals.template`                    | `string`   | `laravel-ts-publish::globals`        | Blade template for global declaration output                     |
| `globals.writer_class`                | `string`   | `GlobalsWriter`                      | Writes global declaration file                                   |

### JSON (`json.*`)

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `json.enabled`                        | `bool`     | `false`                              | Output all definitions as a JSON file                            |
| `json.filename`                       | `string`   | `laravel-ts-definitions.json`        | Filename for the JSON output                                     |
| `json.output_directory`               | `?string`  | `null`                               | Directory for the JSON output                                    |
| `json.writer_class`                   | `string`   | `JsonWriter`                         | Writes JSON definitions file                                     |

### Watcher (`watcher.*`)

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `watcher.enabled`                     | `bool`     | `true`                               | Output collected PHP file paths as JSON (for file watchers)      |
| `watcher.filename`                    | `string`   | `laravel-ts-collected-files.json`    | Filename for the collected files JSON                            |
| `watcher.output_directory`            | `?string`  | `null`                               | Directory for the collected files JSON                           |
| `watcher.writer_class`               | `string`   | `WatcherJsonWriter`                  | Writes collected files JSON for watchers                          |

### Routes (`routes.*`)

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `routes.enabled`                      | `bool`     | `true`                               | Enable or disable route publishing                               |
| `routes.method_casing`                | `string`   | `'camel'`                            | Case style for route method names                                |
| `routes.output_path`                  | `?string`  | `null`                               | Custom output path for route files                               |
| `routes.only`                         | `array`    | `[]`                                 | Only publish these routes (empty = all)                          |
| `routes.except`                       | `array`    | `[]`                                 | Exclude these routes from publishing                             |
| `routes.exclude_middleware`           | `array`    | `[]`                                 | Exclude routes with these middleware                             |
| `routes.only_named`                   | `bool`     | `false`                              | Only publish named routes                                        |
| `routes.collector_class`              | `string`   | `RoutesCollector`                    | Discovers PHP routes                                             |
| `routes.generator_class`              | `string`   | `RouteGenerator`                     | Orchestrates transforming and writing                            |
| `routes.transformer_class`            | `string`   | `RouteTransformer`                   | Converts routes into TypeScript data                             |
| `routes.writer_class`                 | `string`   | `RouteWriter`                        | Writes TypeScript route files                                    |
| `routes.template`                     | `string`   | `laravel-ts-publish::route`          | Blade template for route output                                  |

### Vite Environment (`vite_env.*`)

| Config Key                            | Type       | Default                              | Description                                                          |
|---------------------------------------|------------|--------------------------------------|----------------------------------------------------------------------|
| `vite_env.enabled`                    | `bool`     | `false`                              | Enable Vite environment type generation                              |
| `vite_env.filename`                   | `string`   | `vite-env.d.ts`                      | Filename for the Vite env declaration file                           |
| `vite_env.output_path`               | `?string`  | `null`                               | Custom output path for the Vite env file                              |
| `vite_env.source_file`               | `?string`  | `null`                               | Source `.env` file (defaults to `.env`, falls back to `.env.example`) |

> [!NOTE]
> Pipeline class config keys are listed in the [Extending & Customizing the Pipeline](#extending--customizing-the-pipeline) section above and are included in their respective group tables above.

See the [full configuration file](https://github.com/abetwothree/laravel-ts-publish/blob/main/config/ts-publish.php) for detailed comments on each option.

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

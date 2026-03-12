# Laravel TypeScript Enums & Models Types Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)

This is an extremely flexible package that allows you to create TypeScript declaration types from your Laravel PHP models, enums, and other cast classes.

Every application is different, and this package provides the tools to tailor TypeScript types to your specific needs.

> [!IMPORTANT]
> Laravel TypeScript Publisher is currently in Beta, functionality, options, and API are subject to change prior to the v1.0.0 release.

## Installation

**Requires PHP 8.4+ and Laravel 12 or 11**

You can install the package via composer:

```bash
composer require abetwothree/laravel-ts-publish
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="ts-publish-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-ts-publish-views"
```

## Usage

### Publishing Types

You can publish your TypeScript declaration types using the `ts:publish` Artisan command:

```bash
php artisan ts:publish
```

By default, the generated TypeScript declaration types will be saved to the `resources/js/types/` directory and follow default configuration settings.

Additionally, by default, the package will look for models in the `app/Models` directory and enums in the `app/Enums` directory. You can customize these settings in the published configuration file.

#### Preview Mode

You can preview the generated TypeScript output in the console without writing any files by using the `--preview` flag:

```bash
php artisan ts:publish --preview
```

This is useful for debugging or reviewing what will be generated before committing to file output.

#### Single-File Republishing

You can republish a single enum or model instead of the entire set by using the `--source` option with a fully-qualified class name or file path:

```bash
php artisan ts:publish --source="App\Enums\Status"
php artisan ts:publish --source="app/Enums/Status.php"
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

#### Filtering Models & Enums

You can fully customize which models and enums are included, excluded, or add additional directories to search in. By default, all models in `app/Models` and all enums in `app/Enums` are included.

```php
// config/ts-publish.php

// Only publish these specific models (leave empty to include all)
'included_models' => [
    App\Models\User::class,
    App\Models\Post::class,
],

// Exclude specific models from publishing
'excluded_models' => [
    App\Models\Pivot::class,
],

// Search additional directories for models
'additional_model_directories' => [
    'modules/Blog/Models',
],
```

The same options are available for enums with `included_enums`, `excluded_enums`, and `additional_enum_directories`.

> [!TIP]
> Include and exclude settings accept both fully-qualified class names and directory paths. When a directory is provided, all matching classes within it will be discovered automatically.

## Casing Configurations

This package provides two independent config options to control the casing of generated property and method names:

| Config Key           | Applies To                                    | Default    |
|----------------------|-----------------------------------------------|------------|
| `relationship_case`  | Model relationship names, `_count`, `_exists` | `'snake'`  |
| `enum_method_case`   | Enum method and static method names           | `'camel'`  |

Both accept `'snake'`, `'camel'`, or `'pascal'`.

### Relationship Case Style

Controls relationship names in the generated model TypeScript interfaces:

```php
// config/ts-publish.php

'relationship_case' => 'snake', // default
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

'enum_method_case' => 'camel', // default
```

| Config Value | Method `getLabel()` | Static Method `AllLabels()` |
|--------------|---------------------|-----------------------------|
| `'snake'`    | `get_label`         | `all_labels`                |
| `'camel'`    | `getLabel`          | `allLabels`                 |
| `'pascal'`   | `GetLabel`          | `AllLabels`                 |

> [!TIP]
> This setting applies to all enum methods — both instance methods (via `#[TsEnumMethod]` or `auto_include_enum_methods`) and static methods (via `#[TsEnumStaticMethod]` or `auto_include_enum_static_methods`). You can still override individual method names using the `name` parameter on the attribute.

## Enums

This package, like these others before it, ([spatie/typescript-transformer](https://github.com/spatie/typescript-transformer) or [modeltyper](https://github.com/fumeapp/modeltyper)) can convert enums from PHP to TypeScript for each enum case.

However, PHP enums do not solely consist of enum cases, but can also have methods and static methods that have valuable data to use on the frontend. This package allows you to use these features of PHP enums and publish the return values of these methods in TypeScript as well.

By default, this package will only publish the enum cases and their values to TypeScript, but you can use the provided attributes to specify that you want to call certain methods or static methods and publish their return values in TypeScript as well. See below.

Alternatively, you can enable the `auto_include_enum_methods` and `auto_include_enum_static_methods` config options to automatically include all public methods without needing to add attributes. See [Auto-Including All Enum Methods](#auto-including-all-enum-methods) for details.

> [!NOTE]
> Whether you use the attributes or the global config options, only **public** methods are ever included. Private and protected methods are always excluded.

### Enum Attributes

To use the more advanced transforming features provided by this package for enums you'll need to use the PHP Attributes described below.

All attributes can be found at [this link](https://github.com/abetwothree/laravel-ts-publish/tree/main/src/Attributes) and are under the `AbeTwoThree\LaravelTsPublish\Attributes` namespace.

List of enum attributes & descriptions:

| Attribute              | Target         | Description                                                                                                             |
|------------------------|----------------|-------------------------------------------------------------------------------------------------------------------------|
| `#[TsEnumMethod]`      | Method         | Include a method's return values in the TypeScript output. Called per enum case, creates a key/value pair object.       |
| `#[TsEnumStaticMethod]`| Static Method  | Include a static method's return value in the TypeScript output. Called once, added as a property on the enum.          |
| `#[TsEnum]`            | Enum Class     | Rename the enum or add a description when converting to TypeScript. Useful to avoid naming conflicts across namespaces. |
| `#[TsCase]`            | Enum Case      | Rename, change the frontend value, or add a description to an enum case.                                                |

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

The `#[TsEnumMethod]` attribute accepts optional `name` and `description` parameters:

| Parameter     | Type     | Default           | Description                                       |
|---------------|----------|-------------------|---------------------------------------------------|
| `name`        | `string` | Method name       | Customize the key name used in the TypeScript output |
| `description` | `string` | `''`              | Added as a JSDoc comment above the method output   |

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

The `#[TsEnumStaticMethod]` attribute also accepts the same optional `name` and `description` parameters as `#[TsEnumMethod]`:

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

### Enum Class Name #[TsEnum]

Renaming an enum or adding a description using the `TsEnum` attribute:

| Parameter     | Type     | Default           | Description                                              |
|---------------|----------|-------------------|----------------------------------------------------------|
| `name`        | `string` | Enum class name   | Override the TypeScript const name                       |
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
import { StatusType, StatusKind } from '@js/types/enums';

function setStatus(status: StatusType) {
    // status will only accept 'active' or 'inactive'
}

function setStatusByKey(status: StatusKind) {
    // status will only accept 'Active' or 'Inactive'
}
```

### Enum Metadata & Tolki Enum Package

By default, this package will publish three metadata properties on the enum in TypeScript for the cases, methods, and static methods that are published. These properties are `_cases`, `_methods`, and `_static`.

The purpose for these metadata properties is to be able create an "instance" of the enum from a case value like you'd get on the PHP side. To accomplish this, you need to use the [@tolki/enum](https://tolki.abe.dev/enums/) npm package.

By default, this packages configures the usage of the `@tolki/enum` package when enums are published. 

This is what a published enum looks like when using the `@tolki/enum` package on the frontend:

```TypeScript
import { defineEnum } from '@tolki/enum';

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

The `defineEnum` function from the `@tolki/enum` package is a factory function that will bind PHP like methods to the enum object.

See more details about [defineEnum here](https://tolki.abe.dev/enums/enum-utilities-list.html#defineenum).

With the `@tolki/enum` package, you can now create an "instance" of the enum from a case value like you'd get on the PHP side using the `from` function:

```TypeScript
import { Status } from '@js/types/enums'; // Using example status from the previous example
import { User } from '@js/types/models'; // Assuming you have a User model published as well

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

The `defineEnum` function currently also binds a `tryFrom` & `cases` functions to the enum.

### Enum Metadata Vite Plugin

The `@tolki/enum` package also provides a Vite plugin that can call the artisan publish command for you and watch for changes to your enums & models to automatically update the generated TypeScript declaration types on the frontend.

For documentation on how to set up the Vite plugin, [see this link](https://tolki.abe.dev/enums/enum-vite-plugin.html).

### Disabling Enum Metadata or Tolki Enum Package

If you don't plan to use the `@tolki/enum` package or don't need the metadata properties for your use case, you can disable the generation of these metadata properties in the config file by setting `enum_metadata_enabled` to `false`:

```php
// config/ts-publish.php

'enum_metadata_enabled' => false,
```

If you would like to use the metadata but don't want the `@tolki/enum` package, you can disable the usage of that package in the config file by setting `enums_use_tolki_package` to `false`. This will still generate the metadata properties on the enum, but will not wrap the enum in the `defineEnum` function from the `@tolki/enum` package:

```php
// config/ts-publish.php

'enum_metadata_enabled' => true,
'enums_use_tolki_package' => false,
```

### Auto-Including All Enum Methods

By default, only **public** methods decorated with the `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` attributes are included in the TypeScript output. If you'd prefer to include all public methods without needing to add the attribute to every method, you can enable automatic inclusion in your config file:

```php
// config/ts-publish.php

'auto_include_enum_methods' => true,        // Include all public non-static methods
'auto_include_enum_static_methods' => true,  // Include all public static methods
```

When enabled, every public method declared on the enum will be included in the TypeScript output — you no longer need to add `#[TsEnumMethod]` or `#[TsEnumStaticMethod]` to each method. Built-in enum methods like `cases()`, `from()`, and `tryFrom()` are always excluded automatically.

You can still use `#[TsEnumMethod]` and `#[TsEnumStaticMethod]` to customize the `name` or `description` of individual methods when auto-inclusion is enabled:

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

If that's still not ideal for your situation, you can change the template used to generate the model types. This package comes with two templates for generating model types:

| Template                              | Description                                                                         |
|---------------------------------------|-------------------------------------------------------------------------------------|
| `laravel-ts-publish::model-split`     | **(Default)** Splits into separate interfaces for properties, mutators, and relations |
| `laravel-ts-publish::model-full`      | Combines all properties, mutators, and relations into a single interface              |

Just change the `model_template` in the config file to use the template that best fits your needs:

```php
// config/ts-publish.php

'model_template' => 'laravel-ts-publish::model-full',
```

You are also free to publish the views to modify them or create your own custom template if you want to change the structure of the generated types even more. Just make sure to update the `model_template` in the config file to point to your new custom template.

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
    profile: Profile;
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
import { User, UserRelations } from '@js/types/models';

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
    profile: Profile;
    posts: Post[];
    // Counts
    profile_count: number;
    posts_count: number;
    // Exists
    profile_exists: boolean;
    posts_exists: boolean;
}
```

Same Inertia form example as above would work with this `model-full` template as well since all properties, mutators, and relations are in the same interface.

You will notice the need to call `Omit` with more properties to exclude the relation properties that are not needed for this specific page, but that's the tradeoff with using a single interface for the model instead of splitting it into separate interfaces for the properties, mutators, and relations.

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { User } from '@js/types/models';

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

### Model Attributes

Like with enums, this package provides a few PHP attributes that you can use to further customize the generated TypeScript declaration types for your models. All attributes can be found at [this link](https://github.com/abetwothree/laravel-ts-publish/tree/main/src/Attributes) and are under the `AbeTwoThree\LaravelTsPublish\Attributes` namespace.

| Attribute    | Target                           | Description                                                                                                         |
|--------------|----------------------------------|---------------------------------------------------------------------------------------------------------------------|
| `#[TsCasts]` | `casts()` method, `$casts` property, or model class | Specify TypeScript types for model columns. Works similarly to Laravel's `casts` but for TypeScript.                 |
| `#[TsType]`  | Custom cast class                | Specify the TypeScript type for any model property that uses this custom cast class.                                 |

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

### Type-Only Imports

By default, model files use `import type { ... }` instead of `import { ... }` for all imported types. This is the correct syntax for stricter TypeScript configurations that enable `verbatimModuleSyntax` or `isolatedModules`:

```TypeScript
import type { StatusType } from '../enums';
import type { Profile, Post } from './';

export interface User {
    id: number;
    status: StatusType;
}
```

If your project doesn't require type-only imports, you can disable this with:

```php
// config/ts-publish.php

'use_type_imports' => false,
```

This only affects model file imports (enum types, model interfaces, and custom `#[TsCasts]` imports). The enum `import { defineEnum }` value import from `@tolki/enum` is unaffected.

### Output Options

This package provides several output formats that can be enabled independently:

| Config Key                    | Default | Description                                                                 |
|-------------------------------|---------|-----------------------------------------------------------------------------|
| `output_to_files`             | `true`  | Write individual `.ts` files with barrel `index.ts` exports                 |
| `output_globals_file`         | `false` | Generate a `global.d.ts` file with a global TypeScript namespace            |
| `output_json_file`            | `false` | Output all generated definitions as a JSON file                             |
| `output_collected_files_json` | `true`  | Output a JSON list of collected PHP file paths (useful for file watchers)   |

When `output_globals_file` is enabled, a global declaration file is created that makes all your types available without explicit imports:

```php
// config/ts-publish.php

'output_globals_file' => true,
'global_filename' => 'laravel-ts-global.d.ts',
'models_namespace' => 'models',
'enums_namespace' => 'enums',
```

The JSON output from `output_collected_files_json` is designed to work with build tools and file watchers (like the [@tolki/enum Vite plugin](#enum-metadata-vite-plugin)) that need to know which PHP source files were collected so they can trigger a re-publish when those files change.

## Modular Publishing

By default, this package outputs all generated TypeScript files into flat `enums/` and `models/` directories:

```
resources/js/types/
├── enums/
│   ├── article-status.ts
│   ├── invoice-status.ts
│   ├── role.ts
│   └── index.ts
├── models/
│   ├── user.ts
│   ├── invoice.ts
│   ├── shipment.ts
│   └── index.ts
└── global.d.ts
```

For applications that use a modular architecture (e.g., [InterNACHI/modular](https://github.com/InterNACHI/modular) or a custom module structure), you can enable **modular publishing** to organize TypeScript output into namespace-derived directory trees that mirror your PHP namespace structure.

### Enabling Modular Publishing

Set `modular_publishing` to `true` in your config file:

```php
// config/ts-publish.php

'modular_publishing' => true,
```

With modular publishing enabled, the output structure changes to reflect your PHP namespaces:

```
resources/js/types/
├── app/
│   ├── enums/
│   │   ├── role.ts
│   │   ├── membership-level.ts
│   │   └── index.ts
│   └── models/
│       ├── user.ts
│       ├── order.ts
│       └── index.ts
├── accounting/
│   ├── enums/
│   │   ├── invoice-status.ts
│   │   └── index.ts
│   └── models/
│       ├── invoice.ts
│       └── index.ts
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

In modular mode, each namespace directory receives its own barrel `index.ts` file. For example, `accounting/models/index.ts`:

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
import { User, Order } from '@js/types/app/models';
import { Invoice } from '@js/types/accounting/models';
import { InvoiceStatusType } from '@js/types/accounting/enums';
```

## Extending & Customizing the Pipeline

This package uses a **Collector → Generator → Transformer → Writer → Template** pipeline. Each stage is fully configurable via the config file, allowing you to extend or replace any component without modifying the package itself:

| Pipeline Stage | Config Key                | Default Class        | Responsibility                          |
|----------------|---------------------------|----------------------|-----------------------------------------|
| Collector      | `model_collector_class`   | `ModelsCollector`    | Discovers PHP model/enum classes        |
| Collector      | `enum_collector_class`    | `EnumsCollector`     | Discovers PHP enum classes              |
| Generator      | `model_generator_class`   | `ModelGenerator`     | Orchestrates transforming and writing   |
| Generator      | `enum_generator_class`    | `EnumGenerator`      | Orchestrates transforming and writing   |
| Transformer    | `model_transformer_class` | `ModelTransformer`   | Converts PHP class into TypeScript data |
| Transformer    | `enum_transformer_class`  | `EnumTransformer`    | Converts PHP enum into TypeScript data  |
| Writer         | `model_writer_class`      | `ModelWriter`        | Writes TypeScript model files           |
| Writer         | `enum_writer_class`       | `EnumWriter`         | Writes TypeScript enum files            |
| Writer         | `barrel_writer_class`     | `BarrelWriter`       | Writes barrel `index.ts` files          |
| Writer         | `globals_writer_class`    | `GlobalsWriter`      | Writes global declaration file          |
| Template       | `model_template`          | `model-split`        | Blade template for model output         |
| Template       | `enum_template`           | `enum`               | Blade template for enum output          |

To swap a component, create a class that extends the default and override the config key:

```php
// config/ts-publish.php

'model_transformer_class' => App\TypeScript\CustomModelTransformer::class,
```

> [!TIP]
> You can also publish and customize the Blade templates directly with `php artisan vendor:publish --tag="laravel-ts-publish-views"` if you only need to change the output format without modifying the pipeline logic.

## Configuration Reference

Below is a quick reference of all available configuration options:

| Config Key                            | Type       | Default                              | Description                                                      |
|---------------------------------------|------------|--------------------------------------|------------------------------------------------------------------|
| `run_after_migrate`                   | `bool`     | `true`                               | Re-publish types after running migrations                        |
| `output_to_files`                     | `bool`     | `true`                               | Write generated TypeScript to `.ts` files                        |
| `output_directory`                    | `string`   | `resources/js/types/`                | Directory where TypeScript files are written                     |
| `use_type_imports`                    | `bool`     | `true`                               | Use `import type` instead of `import` in model files             |
| `modular_publishing`                  | `bool`     | `false`                              | Organize output into namespace-derived directory trees           |
| `namespace_strip_prefix`              | `string`   | `''`                                 | Strip this prefix from namespaces in modular mode                |
| `relationship_case`                   | `string`   | `'snake'`                            | Case style for relationships: `snake`, `camel`, or `pascal`      |
| `enum_method_case`                    | `string`   | `'camel'`                            | Case style for enum methods: `snake`, `camel`, or `pascal`       |
| `timestamps_as_date`                  | `bool`     | `false`                              | Map date/datetime/timestamp to `Date` instead of `string`        |
| `custom_ts_mappings`                  | `array`    | `[]`                                 | Override or extend PHP-to-TypeScript type mappings               |
| `auto_include_enum_methods`           | `bool`     | `false`                              | Include all public non-static enum methods without attributes    |
| `auto_include_enum_static_methods`    | `bool`     | `false`                              | Include all public static enum methods without attributes        |
| `enum_metadata_enabled`               | `bool`     | `true`                               | Include `_cases`, `_methods`, `_static` metadata on enums        |
| `enums_use_tolki_package`             | `bool`     | `true`                               | Wrap enums in `defineEnum()` from `@tolki/enum`                  |
| `output_globals_file`                 | `bool`     | `false`                              | Generate a `global.d.ts` namespace file                          |
| `global_directory`                    | `string`   | `resources/js/types/`                | Directory for the global declaration file                        |
| `global_filename`                     | `string`   | `laravel-ts-global.d.ts`             | Filename for the global declaration file                         |
| `models_namespace`                    | `string`   | `'models'`                           | Namespace label used in the global declaration file              |
| `enums_namespace`                     | `string`   | `'enums'`                            | Namespace label used in the global declaration file              |
| `output_json_file`                    | `bool`     | `false`                              | Output all definitions as a JSON file                            |
| `json_filename`                       | `string`   | `laravel-ts-definitions.json`        | Filename for the JSON output                                     |
| `json_output_directory`               | `string`   | `resources/js/types/`                | Directory for the JSON output                                    |
| `output_collected_files_json`         | `bool`     | `true`                               | Output collected PHP file paths as JSON (for file watchers)      |
| `collected_files_json_filename`       | `string`   | `laravel-ts-collected-files.json`    | Filename for the collected files JSON                            |
| `collected_files_json_output_directory` | `string` | `resources/js/types/`                | Directory for the collected files JSON                           |
| `model_template`                      | `string`   | `laravel-ts-publish::model-split`    | Blade template for model TypeScript output                       |
| `enum_template`                       | `string`   | `laravel-ts-publish::enum`           | Blade template for enum TypeScript output                        |
| `globals_template`                    | `string`   | `laravel-ts-publish::globals`        | Blade template for global declaration output                     |
| `included_models`                     | `array`    | `[]`                                 | Only publish these models (empty = all)                          |
| `excluded_models`                     | `array`    | `[]`                                 | Exclude these models from publishing                             |
| `additional_model_directories`        | `array`    | `[]`                                 | Extra directories to search for models                           |
| `included_enums`                      | `array`    | `[]`                                 | Only publish these enums (empty = all)                           |
| `excluded_enums`                      | `array`    | `[]`                                 | Exclude these enums from publishing                              |
| `additional_enum_directories`         | `array`    | `[]`                                 | Extra directories to search for enums                            |

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

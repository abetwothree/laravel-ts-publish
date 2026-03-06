# Laravel TypeScript Enums & Models Types Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)

This is an extremely flexible package that allows you to create TypeScript declaration types from your Laravel PHP models, enums, and other cast classes.

Every application is different, and this package provides the tools to tailor TypeScript types to your specific needs.

## Installation

You can install the package via composer:

```bash
composer require abetwothree/laravel-ts-publish
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-ts-publish-config"
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

By default, the generated TypeScript declaration types will be saved to the `resources/ts` directory and follow default configuration settings.

Additionally, by default, the package will look for models in the `app/Models` directory and enums in the `app/Enums` directory. You can customize these settings in the published configuration file.

You can fully customize which models and enums are included, excluded, or add additional directories to search for models and enums in. See the [configuration file](https://github.com/abetwothree/laravel-ts-publish/blob/main/config/ts-publish.php) for more details on how to do this.

### Enums

This package, like these others before it, ([spatie/typescript-transformer](https://github.com/spatie/typescript-transformer) or [modeltyper](https://github.com/fumeapp/modeltyper)) can convert enums from PHP to TypeScript for each enum case.

However, PHP enums do not solely consist of enum cases, but can also have methods and static methods that have valuable data to use on the frontend. This package allows you to use these features of PHP enums and publish the return values of these methods in TypeScript as well.

By default, this package will only publish the enum cases and their values to TypeScript, but you can use the provided attributes to specify that you want to call certain methods or static methods and publish their return values in TypeScript as well. See below.

#### Enum Attributes

To use the more advanced transforming features provided by this package for enums you'll need to use the PHP Attributes described below.

All attributes can be found at [this link](https://github.com/abetwothree/laravel-ts-publish/tree/main/src/Attributes) and are under the `AbeTwoThree\LaravelTsPublish\Attributes` namespace.

List of enum attributes & descriptions:

| Attribute            | Description                                                                                                         |
|----------------------|---------------------------------------------------------------------------------------------------------------------|
| `TsEnumMethod`       | Attribute to specify that you want the values of this method on the frontend. This package will call this method with each enum case and create a key/value pair JavaScript object with each returned value per case. |
| `TsEnumStaticMethod` | Attribute to specify that you want the values of this static method on the frontend. This package will call this static method once and add the return value to the enum keyed by the function name. |
| `TsEnum`             | Attribute to rename the enum when converting to TypeScript. Useful to avoid naming conflicts if you have more than one enum in different namespaces with the same name. |
| `TsCase`.            | Attribute to rename, change the frontend value, or provide a description for an enum case. Useful for when the enum case name or value in PHP is not ideal for the frontend. |

#### Enum Method Example #[TsEnumMethod]

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

#### Enum Static Method Example #[TsEnumStaticMethod]

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

#### Enum Example #[TsEnum]

Renaming an enum using the `TsEnum` attribute:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;

#[TsEnum('UserStatus')]
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

Generated TypeScript declaration type:

```TypeScript
export const UserStatus = {
    Active: 'active',
    Inactive: 'inactive',
} as const;
```

#### Enum Case Example #[TsCase]

Renaming an enum case, changing the frontend value, and adding a description using the `TsCase` attribute:

```php
use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
enum Status: string
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

#### Enum Types

As shown above, the enum generated in TypeScript is a JavaScript object with the `as const` assertion to prevent modification.

However, there are times when you need to validate that a value is a valid enum value or a valid enum case key. For this purpose, this package also generates TypeScript types for the enum values and case keys if the enum is a [PHP backed enum](https://www.php.net/manual/en/language.enumerations.backed.php).

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

#### Enum Metadata & Tolki Enum Package

By default, this package will publish three metadata properties on the enum in TypeScript for the cases, methods, and static methods that are published. These properties are `_cases`, `_methods`, and `_static`.

Example:

```TypeScript
export const Status = {
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
} as const;
```

The purpose for these metadata properties is to be able link your model on the frontend with the published TypeScript enum. To accomplish this, you would use the [@tolki/enum](https://github.com/abetwothree/tolki/tree/master/packages/enum) npm package.

Example using the `@tolki/enum` package to simplify the enum structure and make specific for the current model values on the frontend:

```TypeScript
import { toEnum } from '@tolki/enum';
import { Status } from '@js/types/enums'; // Using example status from the previous example
import { User } from '@js/types/models'; // Assuming you have a User model published as well

const user: User = {
    id: 1,
    name: 'John Doe',
    status: 'active',
}

const userStatus = toEnum(Status, user.status);

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

If you don't plan to use the `@tolki/enum` package or don't need the metadata properties for your use case, you can disable the generation of these metadata properties in the config file with by setting `enum_metadata_enabled` to `false`.

### Models

This package can also convert your Laravel Eloquent models to TypeScript declaration types. This package will run parse through your models' properties, mutators, and relations to create a TypeScript declaration type that matches the structure of your model.

#### Model Templates & Publishing

By default, this package purposely breaks the model into three separate interfaces for the properties, mutators, and relations to give you more flexibility on which properties you need to use in a concrete situation on your frontend projects. It also generates a fourth interface that extends all three interfaces for when you do want to use all the properties, mutators, and relations together. See below.

If that's still not ideal for your situation, you can change the template used to generate the model types. This package comes with two templates for generating model types. 

- `laravel-ts-publish::model-split`: The default template that splits the model into three separate interfaces for the properties, mutators, and relations.
- `laravel-ts-publish::model-full`: A template that combines all properties, mutators, and relations into a single interface.

Just change the `model_template` in the config file to use the template that best fits your needs.

You are also free to publish the views to modify them or create your own custom template if you want to change the structure of the generated types even more. Just make sure to update the `model_template` in the config file to point to your new custom template.

##### Example using the default `model-split` template with a model that has properties, mutators, and relations

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

##### Example using the `model-full` template with a model that has all properties in one interface

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

#### Model Attributes

Like with enums, this package provides a few PHP attributes that you can use to further customize the generated TypeScript declaration types for your models. All attributes can be found at [this link](https://github.com/abetwothree/laravel-ts-publish/tree/main/src/Attributes) and are under the `AbeTwoThree\LaravelTsPublish\Attributes` namespace.

| Attribute            | Description                                                                                                         |
|----------------------|---------------------------------------------------------------------------------------------------------------------|
| `TsCasts`            | Attribute to specify what the TypeScript type should be for a model column. Works similarly to Laravel's built in `casts` property or method on models but for TypeScript types. |
| `TsType`             | Attribute to place on any custom cast class to specify what the TypeScript type should be when that cast is used on a model property. |

##### Examples using `TsCasts` attribute to specify TypeScript types for model properties

###### Using `TsCasts` attribute on `casts()` method

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

###### Using `TsCasts` attribute on `$casts` property & model class name

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

It is recommended to place the `TsCasts` attribute either on the `casts()` method or the `$casts` property instead of the model class itself to keep the TypeScript type definitions close to where you are defining the casts for the model properties in PHP.

###### Custom types using `TsCasts` attribute

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

##### Examples using `TsType` attribute on custom cast classes

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

## Testing

```bash
composer test
```

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

# Laravel TypeScript Publisher

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-ts-publish/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-ts-publish/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-ts-publish.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-ts-publish)

This package allows you to create TypeScript declaration types from your PHP models, enums, and other cast classes.

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

#### Enum Method Example

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

#### Enum Static Method Example

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

#### Enum Example

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

#### Enum Metadata

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

const User = {
    id: 1,
    name: 'John Doe',
    status: 'active',
}

const userStatus = toEnum(Status, User.status);

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

# Upgrade guide from version 1 to version 2

## Overview

From a high-level view, the main goal for the V2 version of this package was to match all functionality that Laravel Wayfinder provides, but I wanted to do things slightly differently.

List of new features

* Route functions based on routes & controllers
  * Includes return types for Inertia routes, Vue components, and pagination with automatic model/resource embedded
* Form requests interfaces
* Broadcast channels
* Broadcast events interfaces
* Laravel Echo TypeScript augmentation
* Global inertia route response parameters
* Vite env types augmentation file based on .env settings prefixed with `VITE_`
* Intelligent caching for faster reruns of the full `ts:publish` command

See the data examples for concrete examples of what’s been added for V2
https://github.com/abetwothree/laravel-ts-publish/tree/main/workbench/resources/js/types/data

## Breaking Changes

### Configuration changes

The configuration in V1 was mostly flat. In V2, with the larger number of features, each main feature has its own configuration group.

For most settings from V1, you’ll need to prefix them with the group name (like `models.enabled`) and remove the group name from the config (from `enum_template` to `enums.template`). 

See the diff for the config between the V1 branch and V2 branches for full details. 
https://github.com/abetwothree/laravel-ts-publish/compare/1.x...2.x

If you had published the config file, make sure to republish it with the force flag

```
php artisan vendor:publish --tag="ts-publish-config" --force
```

### NPM Package

To support functional routing, you’ll need to install the new `@tolki/ts` package that goes along with this Laravel package. 

```bash
npm install @tolki/ts
```

With that, you can uninstall the previous `@tolki/enum` package as the `@tolki/ts` package has been written to support both the V1 functional enums and the new V2 functional routing functions.

```bash
npm uninstall @tolki/enum
```

Additionally, if you were using the Vite plugin, update the package plugin import path in your Vite config file to look like below.

```typescript
import { laravelTsPublish } from "@tolki/ts/vite";
```

Keep in mind that the Vite plugin from the `@tolki/enum` package calls the `ts:publish` command with the  `—only-enums` option. The `@tolki/ts` Vite plugin calls the `ts:publish` command with the `—only-functional` option instead to publish enums and routes when building assets.

### Templates

If you have published and modified the Blade templates, you’ll need to publish them again and update them once again to your project’s needs.

```bash
php artisan vendor:publish --tag="laravel-ts-publish-views" --force
```

### Attribute `TsResourceCasts` removed

**`TsResourceCasts` attribute removed** — The `TsResourceCasts` attribute (`AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts`) has been removed. 

Replace all usages with `TsCasts` (`AbeTwoThree\LaravelTsPublish\Attributes\TsCasts`), which now handles resources, trait methods, models, and form requests uniformly. The constructor signature and array format are identical — only the class name changes.

  ```php
  // Before
  use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
  #[TsResourceCasts(['field' => 'string'])]
  
  // After
  use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
  #[TsCasts(['field' => 'string'])]
  ```

### Pipeline customization

If you updated or extended any of the files in the ****Collector → Generator → Transformer → Writer → Template**** pipeline for any of the V1 files, please make sure your changes continue to be compatible and work as expected. You should also register them in the config file to overwrite the package default functionality.

### Modular publishing only

In V1, the default publishing method was a flat directory style with a configuration for modular publishing. With the larger set of functionality, this is no longer the case. The TypeScript content always publishes in a modular format, and there is no setting to publish content in a flat directory anymore.

Supporting publishing for both styles required a lot of nearly duplicate code paths and was error-prone. Especially now that this package publishes 7 large groups of features instead of 3, this became something to choose a direction on.

You’ll need to update your import paths in your code to match the PHP namespace for each model, enum, or resource.

It is recommended that you delete the `data` folder holding all your previous types data and then run `php artisan ts:publish --fresh` command to make sure the files you see are the most up to date based on the V2 changes.

For example, assuming you have a model in the following PHP namespace:

```php
<?php

namespace App\Models\Users;

class User extends AuthUserModel
{
    //
}
```

```typescript
// From this
import type { User } from '@data/models'
// To this
import type { User } from '@data/app/models/users'
```

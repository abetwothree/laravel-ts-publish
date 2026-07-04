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

## Recommended upgrade flow

1. Update to the v2 package release in Composer.
2. Republish config and views with `--force`.
3. Install `@tolki/ts` and remove `@tolki/enum`.
4. Update your Vite plugin import to `@tolki/ts/vite`.
5. Remove your old generated `data` folder.
6. Run a fresh publish with `php artisan ts:publish --fresh`.
7. Fix import paths in your app code to match modular namespace output.

## Breaking Changes

### Configuration changes

The configuration in V1 was mostly flat. In V2, with the larger number of features, each main feature has its own configuration group.

For most settings from V1, you’ll need to prefix them with the group name (like `models.enabled`) and remove the group name from the config (from `enum_template` to `enums.template`).

See the diff for the config between the V1 branch and V2 branches for full details.
https://github.com/abetwothree/laravel-ts-publish/compare/1.x...2.x

If you had published the config file, make sure to republish it with the force flag

```bash
php artisan vendor:publish --tag="ts-publish-config" --force
```

#### Full configuration update list

Below is the full key migration map from the old flat config shape to the v2 grouped shape.

Republishing the config file is the recommended path and then use your project's git diff to confirm any customizations that need to be reapplied.

<details>
<summary>Show full configuration key-by-key migration table</summary>

<br />

##### 1) Pipeline class overrides moved under each feature group

| Old key | New grouped key |
|---|---|
| `model_collector_class` | `models.collector_class` |
| `model_generator_class` | `models.generator_class` |
| `model_transformer_class` | `models.transformer_class` |
| `model_writer_class` | `models.writer_class` |
| `enum_collector_class` | `enums.collector_class` |
| `enum_generator_class` | `enums.generator_class` |
| `enum_transformer_class` | `enums.transformer_class` |
| `enum_writer_class` | `enums.writer_class` |
| `resource_collector_class` | `resources.collector_class` |
| `resource_generator_class` | `resources.generator_class` |
| `resource_transformer_class` | `resources.transformer_class` |
| `resource_writer_class` | `resources.writer_class` |

##### 2) Shared writer overrides renamed/grouped

| Old key | New grouped key |
|---|---|
| `barrel_writer_class` | `barrel_writer_class` (same key, still supported as shared override) |
| `globals_writer_class` | `globals.writer_class` |
| `json_writer_class` | `json.writer_class` |
| `watcher_json_writer_class` | `watcher.writer_class` |

##### 3) Template key migration

| Old key | New grouped key |
|---|---|
| `model_template` | `models.template` |
| `enum_template` | `enums.template` |
| `resource_template` | `resources.template` |
| `globals_template` | `globals.template` |

Also new template keys were introduced for new features:

| New v2 template keys |
|---|
| `routes.template` |
| `form_requests.template` |
| `broadcast_channels.template` |
| `broadcast_events.template` |
| `broadcast_events.index_template` |
| `broadcast_events.echo_augmentation.template` |

##### 4) Feature enable flags moved from flat keys to grouped keys

| Old key | New grouped key |
|---|---|
| `publish_enums` | `enums.enabled` |
| `publish_models` | `models.enabled` |
| `publish_resources` | `resources.enabled` |
| `output_globals_file` | `globals.enabled` |
| `output_json_file` | `json.enabled` |
| `output_collected_files_json` | `watcher.enabled` |

New feature toggles added in v2:

| New v2 feature toggles |
|---|
| `routes.enabled` |
| `form_requests.enabled` |
| `broadcast_channels.enabled` |
| `broadcast_events.enabled` |
| `inertia.enabled` |
| `vite_env.enabled` |
| `cache.enabled` |

##### 5) Namespace and casing options moved under feature groups

| Old key | New grouped key |
|---|---|
| `models_namespace` | `models.namespace` |
| `enums_namespace` | `enums.namespace` |
| `resources_namespace` | `resources.namespace` |
| `relationship_case` | `models.relationship_case` |
| `enum_method_case` | `enums.method_case` |
| `nullable_relations` | `models.nullable_relations` |
| `relation_nullability_map` | `models.relation_nullability_map` |

##### 6) Include/exclude/additional directories migrated per feature

| Old key | New grouped key |
|---|---|
| `additional_model_directories` | `models.additional_directories` |
| `included_models` | `models.included` |
| `excluded_models` | `models.excluded` |
| `additional_enum_directories` | `enums.additional_directories` |
| `included_enums` | `enums.included` |
| `excluded_enums` | `enums.excluded` |
| `additional_resource_directories` | `resources.additional_directories` |
| `included_resources` | `resources.included` |
| `excluded_resources` | `resources.excluded` |

The same include/exclude/additional pattern is now also used by:

| New v2 groups using same pattern |
|---|
| `form_requests.*` |
| `broadcast_events.*` |

##### 7) Enum metadata/options renamed and regrouped

| Old key | New grouped key |
|---|---|
| `enum_metadata_enabled` | `enums.metadata_enabled` |
| `enums_use_tolki_package` | `enums.use_tolki_package` |
| `auto_include_enum_methods` | `enums.auto_include_methods` |
| `auto_include_enum_static_methods` | `enums.auto_include_static_methods` |

##### 8) Output file naming/output directory keys grouped

| Old key | New grouped key |
|---|---|
| `global_filename` | `globals.filename` |
| `global_directory` | `globals.output_directory` |
| `json_filename` | `json.filename` |
| `json_output_directory` | `json.output_directory` |
| `collected_files_json_filename` | `watcher.filename` |
| `collected_files_json_output_directory` | `watcher.output_directory` |

##### 9) Modular publishing setting removed

| Old key | Status in v2 |
|---|---|
| `modular_publishing` | Removed. Modular output is always on. |

##### 10) New top-level config groups in v2

These groups did not exist in the v1 config shape:

| New v2 top-level group |
|---|
| `cache.*` |
| `routes.*` |
| `form_requests.*` |
| `broadcast_channels.*` |
| `broadcast_events.*` |
| `inertia.*` |
| `vite_env.*` |

Important new nested group:

| New v2 nested group |
|---|
| `broadcast_events.echo_augmentation.*` |

##### 11) Keys that stayed the same

These keys are still top-level and did not require migration:

| Key | Status |
|---|---|
| `run_after_migrate` | Unchanged (still top-level) |
| `output_to_files` | Unchanged (still top-level) |
| `output_directory` | Unchanged (still top-level) |
| `namespace_strip_prefix` | Unchanged (still top-level) |
| `custom_ts_mappings` | Unchanged (still top-level) |
| `timestamps_as_date` | Unchanged (still top-level) |

`ts_extends` still exists, but v2 adds additional sections beyond models/resources:

| `ts_extends` key | v2 note |
|---|---|
| `ts_extends.form_requests` | New section in v2 |
| `ts_extends.broadcast_events` | New section in v2 |

</details>

### NPM Package

To support functional routing as well as functional enums, you’ll need to install the new `@tolki/ts` package that goes along with this Laravel package.

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

Keep in mind that the Vite plugin from the `@tolki/enum` package calls the `ts:publish` command with the `--only-enums` option. The `@tolki/ts` Vite plugin calls the `ts:publish` command with the `--only-functional` option instead to publish enums and routes when building assets.

Long term, this new `@tolki/ts` package will allow for further features related to other sections of Laravel to be added seamlessly.

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

## New command options and behavior

V2 adds additional `ts:publish` selective flags and cache controls:

```bash
# Functional-only output (enums + routes)
php artisan ts:publish --only-functional

# Feature-specific output
php artisan ts:publish --only-routes
php artisan ts:publish --only-form-requests
php artisan ts:publish --only-broadcast-channels
php artisan ts:publish --only-broadcast-events

# Cache rebuild
php artisan ts:publish --fresh
```

Important behavior notes:

* Only one `--only-*` flag can be used per command.
* `--only-functional` takes precedence and ignores other `--only-*` flags.
* `--fresh` forces a full regeneration and cache rebuild.

## New config groups in v2

In addition to reorganizing existing model/enum/resource keys, v2 adds new config groups:

* `routes.*`
* `form_requests.*`
* `broadcast_channels.*`
* `broadcast_events.*`
* `inertia.*`
* `vite_env.*`
* `cache.*`

If you had a published v1 config, republish and re-apply your customizations to the new grouped structure.

## New generated files you should expect

Depending on which features are enabled, v2 now generates additional files beyond enums/models/resources:

* Route controller helper files
* Form request TypeScript interfaces
* Event parameter TypeScript interfaces
* `broadcast-channels.ts`
* `broadcast-events.ts`
* `echo-broadcast-events.d.ts` (when Echo augmentation is enabled)
* `inertia-config.d.ts`
* `vite-env.d.ts` (or your configured filename)

Make sure these generated declaration files are included by your `tsconfig` include patterns.

## Generation cache

V2 introduces a generation cache to skip unchanged classes after the initial run.

* Use `php artisan ts:publish --fresh` after upgrading.
* Use the `--fresh` anytime you need to guarantee a full rebuild.

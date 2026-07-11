---
name: generating-typescript-types
description: >
    Use when working in a Laravel app that has abetwothree/laravel-ts-publish installed and
    frontend TypeScript types under resources/js/types are missing, stale, or need regenerating —
    after adding or editing a PHP enum, Eloquent model, API resource, form request, route,
    broadcast channel, or broadcast event; when a property or method must be hidden from the
    generated output; when a generated type is wrong or missing a new column/relation; or when
    `php artisan ts:publish` or its Vite plugin doesn't behave as expected.
compatibility: Requires abetwothree/laravel-ts-publish (PHP 8.4+, Laravel 12/13) installed via Composer.
---

## Workflow

1. After adding/editing one PHP enum, model, resource, form request, route, or broadcast
   class, regenerate just that class — much faster than a full rebuild:
    ```bash
    php artisan ts:publish --source="Fully\Qualified\ClassName"
    # or a file path:
    php artisan ts:publish --source="app/Models/Post.php"
    ```
2. To check output before writing files, add `--preview=true` (see gotcha below — bare `--preview` does nothing).
3. If types look wrong/stale across the whole app (config change, first run, suspected stale cache), do a full rebuild: `php artisan ts:publish --fresh`.
4. To hide a specific accessor/relation/method/property — or an entire class — from the output, add `#[TsExclude]` to it. It always wins over every other attribute or config.
5. To shape what a member generates as, use the attributes in the table below.
6. If a `--source` run doesn't seem to "take," run a full `php artisan ts:publish` — barrel `index.ts` files only refresh on full runs (see gotcha below).

## Command flags

| Flag                                                                                                                                                       | Effect                                                                                                                                                                       |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--source="FQCN\|path"`                                                                                                                                    | Republish one class only. A path is resolved relative to `base_path()`, e.g. `app/Models/Post.php`. Bypasses the cache. Does **not** rewrite barrel `index.ts` files.        |
| `--preview=true`                                                                                                                                           | Print generated TypeScript to the console; writes no files. **Must include `=true`** — bare `--preview` is silently ignored.                                                 |
| `--fresh`                                                                                                                                                  | Ignore and rebuild the generation cache. No-op with `--source` or `--preview`.                                                                                               |
| `--only-enums` / `--only-models` / `--only-resources` / `--only-routes` / `--only-form-requests` / `--only-broadcast-channels` / `--only-broadcast-events` | Restrict to one type. Mutually exclusive — passing two of these errors out.                                                                                                  |
| `--only-functional`                                                                                                                                        | Publish only type-erasure-safe output (enums, routes, form requests, broadcast channels/events); skips models & resources. Overrides the other `--only-*` flags if combined. |
| `--quiet` / `-q`                                                                                                                                           | No console output at all (files still write); used by the Vite plugin.                                                                                                       |
| `-v` / `--verbose`                                                                                                                                         | Detailed per-file tables (cases, methods, columns, relations).                                                                                                               |

If a `--only-*` flag requests a type disabled in `config/ts-publish.php`, an interactive run
prompts to override; a non-interactive run (CI, queued job, the post-migration hook) silently
respects the config and skips it.

## Key attributes

| Attribute                                   | Use on                                         | Effect                                                                                                                                                                                                 |
| ------------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `#[TsExclude]`                              | class or member                                | Drop it from output entirely. Always wins.                                                                                                                                                             |
| `#[TsEnumMethod]` / `#[TsEnumStaticMethod]` | enum method                                    | Expose that method to TS. **Required** unless `enums.auto_include_methods` / `auto_include_static_methods` is `true` (both default `false`) — a plain public enum method is otherwise invisible to TS. |
| `#[TsEnum]` / `#[TsCase]`                   | enum / case                                    | Rename it or add a JSDoc description.                                                                                                                                                                  |
| `#[TsCasts]` / `#[TsType]`                  | column, mutator, relation, cast class          | Override or add a TS type, including a custom import.                                                                                                                                                  |
| `#[TsExtends]`                              | model, resource, form request, broadcast event | Add an `extends` clause to the generated interface (repeatable, inherited from parents/traits).                                                                                                        |
| `#[TsResource(model:)]` / `#[UseResource]`  | `JsonResource`                                 | Point at the backing model when `@mixin` or naming convention doesn't already resolve it.                                                                                                              |

## Output layout & imports

Files are written under `models.namespace`/`enums.namespace`/etc. paths inside `output_directory` (default `resources/js/types/data/`), mirroring the class's PHP namespace, kebab-cased segment by segment: `App\Models\User` -> `app/models/user.ts`. Every namespace directory gets a barrel `index.ts` re-exporting everything in it — but barrels are only written on a full `ts:publish` run, never a `--source` run.

### Importing types, enum objects, & routes

#### TypeScript imports

When importing types created by this tool, unless configured differently, you should import from the configured `@data` alias (or whatever you have set in your `ts:publish` config) rather than relative paths. It should almost match the PHP file namespace in kebab-case, segment by segment.

e.g.:

```ts
import type { User } from "@data/app/models/user";
import type { StatusType } from "@data/app/enums/status";
import type { UserResource } from "@data/app/http/resources/user-resource";

defineProps<{
    user: User;
    status: StatusType;
    userResource: UserResource;
}>();
```

#### Enum object imports

When importing enum objects created by this tool, you should also import from the configured `@data` alias, e.g.:

```ts
import { UserRole } from "@data/app/enums/user-role";

UserRole.Admin; // Accessing the enum case value
```

#### Routes functions imports

When importing route functions created by this tool, you should also import from the configured `@data` alias, e.g.:

```ts
import { router } from "@inertiajs/react";

// Imports the entire UserController object with all its route functions
import UserController from "@data/app/http/controllers/user-controller";

UserController.index(); // Accessing a route function from the controller
UserController.show(1); // Accessing another route function from the controller

router.post(UserController.update(3, updateData));
```

```ts
import { router } from "@inertiajs/vue";

// Import a specific route function from the controller
import {
    index,
    show,
    update,
} from "@data/app/http/controllers/user-controller";

index(); // Accessing the imported route function
show(1); // Accessing another imported route function
update(3, updateData); // Accessing the imported update route function
```

## Gotchas

- **`--preview` needs `=true`.** It's declared as `{--preview=false}`, so a bare `--preview` flag (no `=value`) resolves to `null`, and `filter_var(null, FILTER_VALIDATE_BOOLEAN)` is `false` — the run silently writes real files instead of previewing.
- **`--source` skips barrel files and always bypasses the cache.** Expect only the target file to change; the aggregated `index.ts` files, and anything cache-related, need a full run.
- **Auto-include is off by default.** `enums.auto_include_methods` / `auto_include_static_methods` both default to `false`; tag methods with `#[TsEnumMethod]` / `#[TsEnumStaticMethod]` or they won't appear in the generated enum object.
- **Runs automatically after migrations** unless disabled (`run_after_migrate` config or `TS_PUBLISH_RUN_AFTER_MIGRATE=false`).
- **Vite plugin runs via Node's `child_process.exec()`** — shell aliases like a bare `sail` don't resolve. If Vite runs on the host with Sail, point the plugin's `command` at `./vendor/bin/sail artisan ts:publish` instead of `sail artisan ts:publish`.
- **`EnumResource`** (`AbeTwoThree\LaravelTsPublish\EnumResource`) returns a flattened JSON representation of a single enum case for API responses, running through the same `#[TsEnumMethod]`/`#[TsEnumStaticMethod]` pipeline — pair it with the `AsEnum<T>` type from `@tolki/ts` on the frontend instead of hand-writing the shape.

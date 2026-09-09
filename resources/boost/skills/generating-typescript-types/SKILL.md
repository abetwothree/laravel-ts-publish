---
name: generating-typescript-types
description: >
    Use in any Laravel app with abetwothree/laravel-ts-publish installed whenever work crosses the PHP/TypeScript
    boundary: adding or changing a model, column, JSON cast, enum or status/kind/priority value set, form request,
    controller action, route, Inertia page/props, API resource, or broadcast channel/event; writing frontend code
    that needs a backend type, enum value or label, URL, form payload type, channel name, or morph class; when files under
    resources/js/types/data look wrong, stale, or contain unknown; or when ts:publish / the @tolki/ts Vite plugin
    misbehaves. Read it before hand-writing any interface, string-literal union, label/color map, PHP class name,
    or "/path/${id}" string for backend data.
compatibility: abetwothree/laravel-ts-publish (PHP 8.4+, Laravel 12/13); @tolki/ts for enums and routes.
---

# Laravel TypeScript Publish

`php artisan ts:publish` turns the PHP side of the app into TypeScript: enums become functional objects,
controller actions become route helpers, and models, API resources, form requests, Inertia props and
broadcast payloads become interfaces. Everything lands under `output_directory` (default
`resources/js/types/data/`, usually aliased `@data/*`), mirroring PHP namespaces in kebab-case (broadcast
event files are the one exception: their directory is kebab-cased but the file keeps the PHP class name,
`app/events/TaskCompleted.ts`). The
package's whole purpose is that **a fact about the data lives once, in PHP**, and the frontend reads it
from the generated files. Any hand-written duplicate of that fact on the frontend is the bug this skill
exists to prevent.

| PHP source                                         | Generated                                                                                                                                                                                                                                                                                                            | Frontend uses                                                                                |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `App\Enums\TaskPriority` (+ `#[TsEnumMethod]`)     | `app/enums/task-priority.ts`: `TaskPriority` object, `TaskPriorityType`, `TaskPriorityKind`                                                                                                                                                                                                                          | `TaskPriority.High`, `.from(v).label`, `.cases()`, `TaskPriorityType`                        |
| `App\Models\Task` (schema, casts, `@property`)     | `app/models/task.ts`: `Task`, `TaskMutators`, `TaskRelations`, `TaskAll` (+ the enum-resolved companions, only when `enums.use_tolki_package` is on and the model has enum-typed members: an enum column or `$appends` entry gives `TaskResource`, an enum-typed non-appended accessor gives `TaskMutatorsResource`) | `Task`, `Task & Pick<TaskRelations, 'assignee'>`, `Task['settings']`                         |
| `App\Models\Task` + a `ModelMetadataProvider` (opt-in)  | `app/models/task_meta.ts`: `TaskModelMetadata`, a runtime const of backend-owned values (the morph class by default)                                                                                                                                                                                          | `TaskModelMetadata.morphClass` for a polymorphic field                                       |
| `App\Http\Controllers\TaskController`              | `app/http/controllers/task-controller.ts`: one `defineRoute()` per action, `TaskController` default export, `{Action}PageProps`                                                                                                                                                                                      | `TaskController.edit(task.id)`, `.url()`, `.form()`, `InferPageProps`, `InferRequestPayload` |
| `App\Http\Requests\UpdateTaskRequest` (`rules()`)  | `app/http/requests/update-task-request.ts` interface                                                                                                                                                                                                                                                                 | `useForm<UpdateTaskRequest>`, attached to the route automatically                            |
| `App\Http\Resources\TaskApiResource` (`toArray()`) | `app/http/resources/task-api-resource.ts` interface                                                                                                                                                                                                                                                                  | typing API responses                                                                         |
| `HandleInertiaRequests::share()`                   | `inertia-config.d.ts`: global `Inertia.SharedData` + `@inertiajs/core` augmentation                                                                                                                                                                                                                                  | `usePage().props` typed                                                                      |
| `routes/channels.php`, `ShouldBroadcast` events    | `broadcast-channels.ts`, `app/events/*.ts`, `broadcast-events.ts`, `echo-broadcast-events.d.ts`                                                                                                                                                                                                                      | `BroadcastChannels.teams(id)`, `BroadcastEvents.X`, typed Echo                               |

## Before touching a feature: confirm its phase is enabled

Each feature is a phase with an `enabled` key in `config/ts-publish.php` (published copy in the app, else
the package default: everything on except `model_metadata`, `globals`, `json`). Read the file, or run:

```bash
php artisan tinker --execute="dump(collect(['enums','models','model_metadata','resources','routes','form_requests','broadcast_channels','broadcast_events','inertia','vite_env'])->mapWithKeys(fn (\$k) => [\$k => config(\"ts-publish.\$k.enabled\")])->all())"
```

Also note `enums.use_tolki_package` (gates every `AsEnum<>`), `enums.auto_include_methods`, and the
`routes.only/except/exclude_middleware` filters. If a phase is **off**: do not import from its directory,
do not annotate PHP for it, and do not flip it on as a side effect of an unrelated task. Derive what you need
from the types that _are_ generated (`Pick<Task, 'id' | 'title'>` beats a hand-written interface), and say that
enabling the phase would generate the real thing. If it is **on**: the generated object/type is the only
acceptable source for that data.

One exception, and it is not a loophole: when the thing you were asked to build **is** that phase's output,
enabling the phase is the task rather than a side effect of it. `model_metadata` is off by default and a
provider cannot be set up without it, so a request to publish a backend value to the frontend at runtime means
turn it on and name the config change in your summary. Refusing there produces the hand-written TypeScript
table this skill exists to delete.

## The loop

1. Make the change in PHP so the generator can read it (the per-feature reference says exactly how).
2. Republish: `php artisan ts:publish --source="App\Models\Task"` for one class (FQCN or path);
   plain `php artisan ts:publish` after adding a **new** class (a `--source` run never rewrites the barrel
   `index.ts`) or after a migration (which also auto-publishes unless `run_after_migrate` is off).
   `--preview=true` prints instead of writing; a bare `--preview` writes.
3. Open the regenerated `.ts` and read the members you changed. A property that came out `unknown`,
   `unknown[]`, `object`, or `Record<string, unknown>` is a PHP-side gap; fix the PHP and republish
   (checklist in [references/models.md](references/models.md)). Never edit generated files.
4. Consume it on the frontend through the alias (`@data/app/enums`, `@data/app/models`,
   `@data/app/http/controllers`, `@data/app/http/requests`, ...), then type-check (`vue-tsc --noEmit`,
   `tsc --noEmit`, or the project's script).

## Rules

**1. A closed set of values is a PHP backed enum, and its per-case presentation lives on the enum.**
Statuses, kinds, priorities, roles, tiers: create `App\Enums\X` even for two cases, cast the column to it,
validate with `Rule::enum()`, and put `label()`, `color()` / `badgeClass()`, `icon()`, `description()`
on the enum with `#[TsEnumMethod]`. A Tailwind class string keyed by case is enum data, exactly like a
label: it is used in Blade, mail, exports and tests as well as in one component, and putting it on the
enum means a new case cannot be added without its color. A `Record<XType, string>` map or a
`type X = 'a' | 'b'` in a `.ts`/`.vue` file is the duplication to remove, not "presentation staying near
the markup". On the frontend compare with `X.Case`, resolve with `X.from(value)` / `X.tryFrom(value)`,
build selects from `X.cases()`, type with `XType`. Details: [references/enums.md](references/enums.md).
Casting a column to an enum also grows the model file by the `{Model}Resource` companions, so expect new
interfaces in the diff.

**2. Shapes come from PHP; the frontend derives, never redeclares.** A JSON/array column gets a
class-level `@property array{...} $settings` (or `@phpstan-type` + `@phpstan-import-type`) on the model,
which PHPStan reads too, and the frontend uses `Task['settings']`. A payload is `UpdateTaskRequest` /
`InferRequestPayload<typeof update>`. A page's props are `InferPageProps<typeof edit>` or
`{Action}PageProps` — but **neither** can go inside a Vue `defineProps<...>()`, which `@vue/compiler-sfc`
expands statically and which resolves neither a conditional type (`InferPageProps`) nor the ambient global
`Inertia.SharedData` that every `{Action}PageProps` intersects; there, spell the props as a literal of
`EditPageProps['key']` members (`vue-tsc` passes on the other forms and then `vite build` fails).
An API response is the generated resource interface (`TaskApiResource` from `@data/app/http/resources`), not
the model's `{Model}Resource` companion. Compose with `Pick`, `NonNullable`, `&`; do
not write `interface TaskSettings`, `interface Props`, `interface TaskForm`, or `as any`.
Details: [references/models.md](references/models.md), [references/form-requests.md](references/form-requests.md),
[references/inertia.md](references/inertia.md), [references/api-resources.md](references/api-resources.md).

**3. If enabled, URLs come from route helpers; payload types come from the request.**

If URL routes are not enabled, follow the convention of the application for URLs by scanning existing frontend code and see how URLs are constructed and used throughout the project.

If enabled, never write `'/tasks'` or `` `/tasks/${id}` ``, `route('tasks.update')` (Ziggy), or `@/actions` (Wayfinder). Use `TaskController.update(task.id)` (`{ url, method }`, accepted as-is by Inertia's `router.*`, `<Link href>`, `<Form action>`, `useForm().submit()`), `.url(task.id)` for a string, `.form(task.id)` for a plain `<form>`, and extra keys for query strings (`index({ completed: true })`). Pass the key, not the model interface. Type the form from the generated request; when a model column and the request field disagree because the column is not an enum yet (`status: string` vs `'todo' | 'done'`), make the enum if the task allows, otherwise narrow at the boundary (`props.task.status as UpdateTaskRequest['status']`) rather than dropping the type. Replace hardcoded URLs in any file you are already editing.
Details: [references/routes.md](references/routes.md).

**4. Regenerate, read, type-check.** Green `ts:publish` output is not proof: read the `.ts`. A gap is fixed in
PHP (docblock, cast, rule, `#[TsCasts]` as last resort), not with a TS cast or `// @ts-expect-error`.
Details: [references/publishing.md](references/publishing.md).

## Where to look

| Working on                                                                            | Read                                                       |
| ------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| An enum, a status/kind/priority, labels, badges, selects, `EnumResource`              | [references/enums.md](references/enums.md)                 |
| A model, column, JSON/array cast, accessor, relation, `unknown` in a model            | [references/models.md](references/models.md)               |
| A controller, link, form submit, query string, `InferPageProps`/`InferRequestPayload` | [references/routes.md](references/routes.md)               |
| A `FormRequest`, `rules()`, `useForm` typing                                          | [references/form-requests.md](references/form-requests.md) |
| `HandleInertiaRequests::share()`, `Inertia::render()` props, `usePage()`              | [references/inertia.md](references/inertia.md)             |
| A morph class, a backend value needed at runtime, `{model}_meta.ts`, a metadata provider | [references/model-metadata.md](references/model-metadata.md) |
| A `JsonResource` / `toArray()`, API response typing                                   | [references/api-resources.md](references/api-resources.md) |
| `routes/channels.php`, a `ShouldBroadcast` event, Echo listeners                      | [references/broadcasting.md](references/broadcasting.md)   |
| Commands, flags, config keys, output layout, attributes, troubleshooting              | [references/publishing.md](references/publishing.md)       |

## Quick reference

```bash
php artisan ts:publish                                   # everything enabled (cached; only changed classes regenerate)
php artisan ts:publish --source="App\Enums\TaskPriority" # one class, bypasses cache, no barrel rewrite
php artisan ts:publish --fresh                           # rebuild the cache
php artisan ts:publish --preview=true                    # console only (the =true is required)
php artisan ts:publish --only-enums | --only-routes | --only-model-metadata | --only-functional | ...
```

```ts
import { TaskPriority } from '@data/app/enums';
import type { TaskPriorityType } from '@data/app/enums';
import type { Task, TaskRelations } from '@data/app/models';
import { TaskController } from '@data/app/http/controllers';
import { update } from '@data/app/http/controllers/task-controller';
import type { UpdateTaskRequest } from '@data/app/http/requests';
import type { InferPageProps, InferRequestPayload, AsEnum } from '@tolki/ts';
import { BroadcastChannels } from '@data/broadcast-channels';
```

Key attributes (`AbeTwoThree\LaravelTsPublish\Attributes`): `#[TsEnumMethod]` / `#[TsEnumStaticMethod]`
(publish an enum method; required unless auto-include is on), `#[TsEnum]` / `#[TsCase]` (rename,
describe), `#[TsExclude]` (drop a class or member; always wins), `#[TsCasts]` (override a type; last
resort), `#[TsType]` (type for a custom cast class), `#[TsExtends]` (extend a hand-written interface),
`#[TsResource]` (resource name/model).

## Rationalizations to refuse

| Excuse                                                                                | Reality                                                                                                                                                                                                      |
| ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| "CSS classes / icons are presentation, they belong next to the markup"                | They are per-case data. On the enum they reach Blade, mail, tests and every component; a case cannot ship without them.                                                                                      |
| "A `Record<XType, string>` map is type-safe; adding a case fails vue-tsc"             | The generated object is equally type-safe with no second copy, and the missing arm is caught in PHP instead: a non-exhaustive `match` is a PHPStan error, and at publish time that case publishes as `null`. |
| "It's only two values / only used here, a string column is simpler"                   | Two values with a label is already a duplicated set. The enum is one file and the generator does the rest.                                                                                                   |
| "The request type won't accept the model's `string`, so I'll leave `useForm` untyped" | Make the column an enum, or narrow at the boundary. Untyped forms are what the package removes.                                                                                                              |
| "I'll add `#[TsCasts]` / a `.ts` interface for the JSON shape"                        | `@property array{...}` on the model types PHP and TS from one line and PHPStan checks it. `#[TsCasts]` is the last resort.                                                                                   |
| "I'll just use `'/tasks/' + id` here, it's one link"                                  | The helper already exists and carries verbs, bindings and query encoding. One hardcoded link becomes ten.                                                                                                    |
| "The generated prop is `unknown`, I'll cast it in the component"                      | `unknown` means the PHP expression was not readable; move `load()` to its own line, add a docblock, republish.                                                                                               |
| "I'll toggle the phase on in config to see what it generates"                         | Toggling a phase to explore is a project decision; that feature's reference already shows the output. Enabling it *is* the task only when the task is that phase's output (model metadata, say); then enable it and say so. |
| "I'll read the package source to see what it infers"                                  | The references already say what is read and what degrades to `unknown`; check them first, source second.                                                                                                     |

## Red flags

`type Status = 'a' | 'b'` in a `.ts` file; `const LABELS = {...}` or `Record<...Type, string>` keyed by
case values; `interface Task`, `interface Props`, `interface TaskForm`, `interface Settings` for backend
data; `'/resource/' + id` or a template-literal path; `route(` (Ziggy); `as any`, `as unknown as`,
`@ts-expect-error` around generated types; `.form.post('/x')` / `axios.post('/x')` with a literal path;
editing anything under `resources/js/types/data/`; `enum X {}` in TypeScript mirroring a PHP enum; a PHP class
name (`'App\\Models\\Task'`) written into a `.ts` file.
Each of these means: stop, find the generated source of that fact, and use it.

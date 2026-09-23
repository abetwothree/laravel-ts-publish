# Enums

PHP enums are the single source of truth for any fixed set of values. `ts:publish` turns each one into a
functional TypeScript object with the same cases, the same opted-in methods, and PHP-like
`from()` / `tryFrom()` / `cases()` helpers. Backend code, frontend code and tests on both sides read the
same cases, so a new case or a renamed label is one PHP edit followed by a republish.

**Gate:** `config('ts-publish.enums.enabled')` must be `true` (default). If it is `false`, do not import
from `@data/**/enums`; use the raw `string | number` value on the frontend and say so.

## When to create a PHP enum

Reach for a backed enum whenever a column, request field, prop, or config value has a closed set of
values: statuses, kinds, priorities, roles, visibility, units, tiers, step names. That is true even when
the set has two members and even when "we only need it in one place". A string column with
`in:draft,published` validation and a hand-written `{ draft: 'Draft' }` label map on the frontend is the
exact duplication this package exists to remove.

Put the presentation the frontend needs (`label()`, `color()`, `icon()`, `description()`, sort order,
option lists) on the enum as methods and opt them in with `#[TsEnumMethod]`. They are then available in
PHP, in the generated TypeScript, and in `EnumResource` JSON responses, all from one definition.

```php
namespace App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/** How urgently a task needs attention */
enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    #[TsEnumMethod]
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    /** Tailwind classes for the badge */
    #[TsEnumMethod]
    public function color(): string
    {
        return match ($this) {
            self::Low => 'bg-gray-100 text-gray-800',
            self::Medium => 'bg-blue-100 text-blue-800',
            self::High => 'bg-red-100 text-red-800',
        };
    }

    /** @return list<array{value: string, label: string}> */
    #[TsEnumStaticMethod]
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
```

When `enums.auto_include_methods` is enabled, the `#[TsEnumMethod]` attribute is not needed on enum methods.

When `enums.auto_include_static_methods` is enabled, the `#[TsEnumStaticMethod]` attribute is not needed on enum methods.

When both of those config settings are true, you're more like to use the `#[TsExclude]` attribute to exclude methods rather include one enum method at a time.

Wire it into the rest of the backend the normal Laravel way. Every one of these is read by the generator:

| Where                           | Write                                                         | Generated effect                                                                                       |
| ------------------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Model cast                      | `'priority' => TaskPriority::class` in `casts()`              | `priority: TaskPriorityType` on `Task`, `AsEnum<...>` on `TaskResource`                                |
| Form request rule               | `'priority' => ['required', Rule::enum(TaskPriority::class)]` | `priority: 'low' \| 'medium' \| 'high'` on the request interface                                       |
| Migration                       | `$table->string('priority')->default('medium')`               | column exists so the model interface includes it                                                       |
| Route parameter                 | `public function byPriority(TaskPriority $priority)`          | `_enumValues: ['low', 'medium', 'high']` on the route arg                                              |
| API resource, Inertia `share()` | `'priority' => EnumResource::make($this->priority)`           | `AsEnum<typeof TaskPriority>` (see [api-resources.md](api-resources.md))                               |
| Inertia `render()` page prop    | `'priority' => $task->priority`                               | `priority: TaskPriorityType` — page props get **no** `AsEnum` rewrite, even for `EnumResource::make()` |
| Broadcast event prop            | `public TaskPriority $priority`                               | `priority: TaskPriorityType`                                                                           |

Then republish: `php artisan ts:publish --source="App\Enums\TaskPriority"` for the enum file, and a plain
`php artisan ts:publish` once the model/request/controller edits are in (a `--source` run never rewrites
barrels, so the first new enum in a namespace needs the full run to get `app/enums/index.ts`).

**Published methods are executed, not read.** `ts:publish` invokes each `#[TsEnumMethod]` once per case (and
each `#[TsEnumStaticMethod]` once) inside the CLI process and bakes the return value into the `.ts` as a
literal. A body touching `__()`/`trans()`, `config()`, `now()` or the database therefore freezes the publish
run's locale, environment and clock into the file, while `EnumResource` re-runs the same method per request and
answers in the request's locale — the two drift apart. Keep published methods to pure `match ($this)` over
constants; translate on the frontend and let the method return a stable key.

**What a method may return.** Scalars, or arrays of scalars. Only a PHP _list_ becomes a TS array, so a
key-preserving `array_filter(self::cases(), ...)` publishes as an object — wrap it in `array_values()`. An enum
instance collapses to its backing value (`self::cases()` gives `['low','high']`, not objects), and anything else
is `(array)`-cast, so call `->toArray()` on a Collection and never return a Carbon, a model or a DTO.

## What gets generated

`App\Enums\TaskPriority` becomes `app/enums/task-priority.ts` plus an entry in `app/enums/index.ts`:

```ts
import { defineEnum } from '@tolki/ts';

/**
 * How urgently a task needs attention
 *
 * @see App\Enums\TaskPriority
 */
export const TaskPriority = defineEnum({
    Low: 'low',
    Medium: 'medium',
    High: 'high',
    backed: true,
    label: { Low: 'Low', Medium: 'Medium', High: 'High' },
    /** Tailwind classes for the badge */
    color: { Low: 'bg-gray-100 text-gray-800', Medium: 'bg-blue-100 text-blue-800', High: 'bg-red-100 text-red-800' },
    options: [{ value: 'low', label: 'Low' }, { value: 'medium', label: 'Medium' }, { value: 'high', label: 'High' }],
    _cases: ['Low', 'Medium', 'High'],
    _methods: ['label', 'color'],
    _static: ['options'],
} as const);

export type TaskPriorityType = 'low' | 'medium' | 'high';   // case values
export type TaskPriorityKind = 'Low' | 'Medium' | 'High';   // case names (backed enums only)
```

- Case keys hold the backing value. Unit enums use the case name as the value and get no `Kind` alias.
- An instance method becomes an object keyed by case name (`label.Low`). A static method becomes one
  top-level value, computed once at publish time.
- `_cases` / `_methods` / `_static` are runtime metadata for `defineEnum()`; never read them directly.
- PHPDoc on the enum, a case, or a method becomes JSDoc. `@`-lines are stripped.
- Method keys follow `enums.method_case` (`camel` by default), so PHP `badgeColor()` is `badgeColor`.

## Using the generated enum on the frontend

Import the object (a value) and the aliases (types) from the enum's namespace barrel. The path mirrors the
PHP namespace, kebab-cased: `App\Enums\TaskPriority` -> `@data/app/enums` (or `@data/app/enums/task-priority`).

```ts
import { TaskPriority } from '@data/app/enums';
import type { TaskPriorityType, TaskPriorityKind } from '@data/app/enums';
import type { Task } from '@data/app/models';

// Compare and assign with the case constant, never a string literal.
task.priority === TaskPriority.High;
form.priority = TaskPriority.Medium;

// Look a method up by case name when you already know the case.
TaskPriority.label.High;                       // 'High'
TaskPriority.color.Low;                        // 'bg-gray-100 text-gray-800'

// Resolve a raw value coming from the backend (a model column, a request payload, a query string).
const priority = TaskPriority.from(task.priority);   // throws on an unknown value
priority.name;                                       // 'High'
priority.value;                                      // 'high'
priority.label;                                      // 'High'   (instance methods are flattened)
priority.color;                                      // 'bg-red-100 text-red-800'
priority.options;                                    // static methods come through unchanged

// tryFrom() returns null instead of throwing. Its parameter is the case-value union, not `string`, so a
// value TypeScript only knows as `string` (a query param, a fetch response) needs a cast at that boundary:
TaskPriority.tryFrom(query.priority as TaskPriorityType)?.label ?? 'Any';

// An int-backed enum generates a numeric union and from()/tryFrom() compare with ===, so a value that has been
// through the DOM, a query string or FormData is a string and never matches. Coerce before resolving:
// Status.tryFrom(Number(raw) as StatusType)

// cases() is the idiomatic way to build a <select>, a filter list, or a legend.
TaskPriority.cases().map((c) => ({ value: c.value, label: c.label }));

// Type props, refs, form fields and function parameters with the alias, not a hand-written union.
defineProps<{ priority: TaskPriorityType }>();
function setPriority(next: TaskPriorityType) {}

// Initialising an Inertia useForm field with a case constant narrows it to that one literal, so every other
// case then fails to assign. Widen to the generated alias — an annotation, not an escape hatch:
//   const form = useForm({ priority: TaskPriority.Medium as TaskPriorityType });
```

The method maps are keyed by case **name** (`Low`), while columns and payloads carry the case **value**
(`low`). Reaching for `TaskPriority.label[task.priority]` is a type error for that reason; resolve with
`from()` / `tryFrom()` first, or index by name when you have a `TaskPriorityKind`.

A template badge looks like this in any framework:

```vue
<span :class="TaskPriority.from(task.priority).color">
    {{ TaskPriority.from(task.priority).label }}
</span>
```

For a `<select>` bound to a form, iterate `TaskPriority.cases()` and bind each option's `value`.

Tailwind v4 finds class names by scanning source files and skips anything gitignored. If the generated
directory is gitignored — the common setup, but check — classes that only appear in an enum method's return
value (`bg-red-100 text-red-800`) need an explicit `@source "../js/types/data";` in `app.css`, relative to that
file. Either way, verify: build once and grep the built stylesheet for one of the classes. A badge that looks
right in the editor and unstyled in production is this and nothing else.

### Type companions

| Export                        | Meaning                                                                                                                    |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `{Enum}Type`                  | Union of case values. Use for model columns, request fields, query args                                                    |
| `{Enum}Kind`                  | Union of case names. Use when you index a method map by name                                                               |
| `AsEnum<typeof Enum>`         | The resolved instance shape (`name`, `value`, `backed`, every method). Use when the backend already sent an `EnumResource` |
| `AsEnum<typeof Enum, 'high'>` | Narrowed to one case                                                                                                       |
| `{Model}Resource`             | Auto-generated model interface whose enum columns are `AsEnum<...>`; see [models.md](models.md)                            |

`AsEnum`, `defineEnum`, `from`, `tryFrom`, `cases` all come from `@tolki/ts`; the types-only package is
`@tolki/types`.

`from(value)` with a literal (`from('high')`) returns the one-case shape and is assignable to
`AsEnum<typeof TaskPriority>`. With a union-typed input (`from(task.priority)` where the column is
`TaskPriorityType`) it returns a single object whose members are unions (`label: 'Low' | 'Medium' | 'High'`),
which reads fine (`.label`, `.color`) but is not assignable to `AsEnum<>`; keep `AsEnum` for payloads the
backend serialized through `EnumResource`, and let `from()` infer its own type on the client.

### Standalone helpers

`from(TaskPriority, 'high')`, `tryFrom(...)` and `cases(TaskPriority)` are exported from `@tolki/ts` for
enums published with `enums.use_tolki_package = false` (then the object is a plain `as const` without the
bound helpers). With the default config prefer the bound `TaskPriority.from()` form.

## Attributes

All under `AbeTwoThree\LaravelTsPublish\Attributes`.

| Attribute                                       | Target        | Effect                                                                                                                                                                                                                                                  |
| ----------------------------------------------- | ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[TsEnumMethod(name:, description:, params:)]` | public method | Publish the method, invoked once per case. Required unless `enums.auto_include_methods` is `true`.                                                                                                                                                      |
| `#[TsEnumStaticMethod(...)]`                    | public static | Publish the static result once as a top-level key. Required unless `auto_include_static_methods`.                                                                                                                                                       |
| `#[TsEnum(name:, description:)]`                | enum          | Rename the const/type/file (`UserStatus` -> `user-status.ts`, `UserStatusType`) and/or set the JSDoc. `name` is a **required** constructor argument, so a description-only use still repeats the name: `#[TsEnum('TaskPriority', description: '...')]`. |
| `#[TsCase(name:, value:, description:)]`        | case          | Rename the key, change the frontend value, or set the JSDoc.                                                                                                                                                                                            |
| `#[TsExclude]`                                  | enum, method  | Drop it. Wins over `#[TsEnumMethod]` and over auto-include.                                                                                                                                                                                             |

- Methods with **required parameters are skipped** unless you pass `params: ['threshold' => 1]`; the
  values must be constant expressions. Optional-parameter methods are included as-is.
- Auto-include only reaches **public**, non-`__`-prefixed methods, and always skips `cases()`/`from()`/`tryFrom()`.
  An explicit `#[TsEnumMethod]` / `#[TsEnumStaticMethod]` bypasses that guard, so a `private` or `protected`
  method carrying the attribute **is** published; only `#[TsExclude]` overrides it.
- A method that throws for one case (a non-exhaustive `match`) does not fail the run: that case publishes as
  `null`, with no warning and a zero exit. Keep the `match` exhaustive and let PHPStan check it.
- Attribute `description` beats PHPDoc. A `name:` on a method still goes through `enums.method_case`.
- With `auto_include_methods` on, **every** public method's return value is published; check the config
  before adding a method that returns something private, and `#[TsExclude]` it if so.

## `EnumResource` (JSON API)

`AbeTwoThree\LaravelTsPublish\EnumResource` serializes one case to the same flat shape `from()` produces:

```php
return new EnumResource(TaskPriority::High);
// { "name": "High", "value": "high", "backed": true, "label": "High", "color": "bg-red-100 text-red-800", "options": [...] }
```

Inside a `JsonResource::toArray()`, or in `HandleInertiaRequests::share()`, use
`EnumResource::make($this->priority)`; the generated property becomes `AsEnum<typeof TaskPriority>` with the
enum imported, and you type the consuming side with `AsEnum<typeof TaskPriority>` or the model's
`{Model}Resource` interface, never by hand.

The rewrite does **not** reach an `Inertia::render()` props array: there, `EnumResource::make(TaskPriority::High)`
publishes the bare `TaskPriorityType`. Send the case through a resource when the page needs the resolved
object, or keep the prop as `{Enum}Type` and call `TaskPriority.from(...)` on the client.

## Tests

Both sides can assert against the same definition:

```php
$this->assertSame('high', TaskPriority::High->value);
$response->assertJsonPath('priority.value', TaskPriority::High->value);
```

```ts
expect(TaskPriority.from('high').label).toBe('High');
expect(TaskPriority.cases()).toHaveLength(3);
```

## Config that changes the output

| Key                                                      | Default | Effect                                                                                                                                                                                       |
| -------------------------------------------------------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `enums.enabled`                                          | `true`  | Phase on/off                                                                                                                                                                                 |
| `enums.metadata_enabled`                                 | `true`  | Emit `backed` and `_cases` / `_methods` / `_static`. The `defineEnum()` wrapper needs **both** this and `use_tolki_package`, so turning this off also removes `from()`/`tryFrom()`/`cases()` |
| `enums.use_tolki_package`                                | `true`  | Wrap in `defineEnum()`; also gates every `AsEnum<>` the package emits                                                                                                                        |
| `enums.auto_include_methods`                             | `false` | Publish all public instance methods without attributes                                                                                                                                       |
| `enums.auto_include_static_methods`                      | `false` | Publish all public static methods without attributes                                                                                                                                         |
| `enums.method_case`                                      | `camel` | `snake` / `camel` / `pascal` for method keys                                                                                                                                                 |
| `enums.included` / `excluded` / `additional_directories` | `[]`    | Discovery filters (FQCNs or directories); default dir is `app/Enums`                                                                                                                         |

## Common mistakes

- Adding a `status` string column plus `in:a,b,c` validation and a frontend label map. Make the enum.
- Writing `type Priority = 'low' | 'medium' | 'high'` or `const PRIORITY_LABELS = {...}` in a `.ts`/`.vue`
  file when the enum exists in PHP. Import `TaskPriorityType` and `TaskPriority.label` instead.
- Forgetting `#[TsEnumMethod]` and then wondering why `label` is missing on the frontend.
- Comparing `task.priority === 'high'`; use `TaskPriority.High`.
- Indexing a method map by value (`TaskPriority.label[task.priority]`); resolve with `from()` first.
- Editing the enum and not republishing; run `php artisan ts:publish --source=...` and re-read the `.ts`.

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

Wire it into the rest of the backend the normal Laravel way. Every one of these is read by the generator:

| Where                  | Write                                                         | Generated effect                                                         |
| ---------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Model cast             | `'priority' => TaskPriority::class` in `casts()`              | `priority: TaskPriorityType` on `Task`, `AsEnum<...>` on `TaskResource`  |
| Form request rule      | `'priority' => ['required', Rule::enum(TaskPriority::class)]` | `priority: 'low' \| 'medium' \| 'high'` on the request interface         |
| Migration              | `$table->string('priority')->default('medium')`               | column exists so the model interface includes it                         |
| Route parameter        | `public function byPriority(TaskPriority $priority)`          | `_enumValues: ['low', 'medium', 'high']` on the route arg                |
| API resource / Inertia | `'priority' => EnumResource::make($this->priority)`           | `AsEnum<typeof TaskPriority>` (see [api-resources.md](api-resources.md)) |
| Broadcast event prop   | `public TaskPriority $priority`                               | `priority: TaskPriorityType`                                             |

Then republish: `php artisan ts:publish --source="App\Enums\TaskPriority"` for the enum file, and a plain
`php artisan ts:publish` once the model/request/controller edits are in (a `--source` run never rewrites
barrels, so the first new enum in a namespace needs the full run to get `app/enums/index.ts`).

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

// tryFrom() returns null instead of throwing; pair it with ?? for user-controlled input.
TaskPriority.tryFrom(query.priority)?.label ?? 'Any';

// cases() is the idiomatic way to build a <select>, a filter list, or a legend.
TaskPriority.cases().map((c) => ({ value: c.value, label: c.label }));

// Type props, refs, form fields and function parameters with the alias, not a hand-written union.
defineProps<{ priority: TaskPriorityType }>();
function setPriority(next: TaskPriorityType) {}
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

Tailwind v4 detects class names by scanning source files and skips anything gitignored. The generated
directory usually is gitignored, so classes that only appear in an enum method's return value
(`bg-red-100 text-red-800`) need an explicit `@source "../js/types/data";` line in `app.css` (path
relative to the CSS file), or they will be missing from the built CSS while looking fine in the editor.

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

| Attribute                                       | Target        | Effect                                                                                             |
| ----------------------------------------------- | ------------- | -------------------------------------------------------------------------------------------------- |
| `#[TsEnumMethod(name:, description:, params:)]` | public method | Publish the method, invoked once per case. Required unless `enums.auto_include_methods` is `true`. |
| `#[TsEnumStaticMethod(...)]`                    | public static | Publish the static result once as a top-level key. Required unless `auto_include_static_methods`.  |
| `#[TsEnum(name:, description:)]`                | enum          | Rename the const/type/file (`UserStatus` -> `user-status.ts`, `UserStatusType`) or set the JSDoc.  |
| `#[TsCase(name:, value:, description:)]`        | case          | Rename the key, change the frontend value, or set the JSDoc.                                       |
| `#[TsExclude]`                                  | enum, method  | Drop it. Wins over `#[TsEnumMethod]` and over auto-include.                                        |

- Methods with **required parameters are skipped** unless you pass `params: ['threshold' => 1]`; the
  values must be constant expressions. Optional-parameter methods are included as-is.
- Only public methods are ever published. `cases()`, `from()`, `tryFrom()` are always skipped.
- Attribute `description` beats PHPDoc. A `name:` on a method still goes through `enums.method_case`.
- With `auto_include_methods` on, **every** public method's return value is published; check the config
  before adding a method that returns something private, and `#[TsExclude]` it if so.

## `EnumResource` (JSON API)

`AbeTwoThree\LaravelTsPublish\EnumResource` serializes one case to the same flat shape `from()` produces:

```php
return new EnumResource(TaskPriority::High);
// { "name": "High", "value": "high", "backed": true, "label": "High", "color": "bg-red-100 text-red-800", "options": [...] }
```

Inside a `JsonResource::toArray()` or an Inertia `share()`/`render()` array use `EnumResource::make($this->priority)`;
the generated property becomes `AsEnum<typeof TaskPriority>` with the enum imported. Type the consuming
side with `AsEnum<typeof TaskPriority>` or the model's `{Model}Resource` interface, never by hand.

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

| Key                                                      | Default | Effect                                                                |
| -------------------------------------------------------- | ------- | --------------------------------------------------------------------- |
| `enums.enabled`                                          | `true`  | Phase on/off                                                          |
| `enums.metadata_enabled`                                 | `true`  | Emit `_cases` / `_methods` / `_static` (needed by `from()` etc.)      |
| `enums.use_tolki_package`                                | `true`  | Wrap in `defineEnum()`; also gates every `AsEnum<>` the package emits |
| `enums.auto_include_methods`                             | `false` | Publish all public instance methods without attributes                |
| `enums.auto_include_static_methods`                      | `false` | Publish all public static methods without attributes                  |
| `enums.method_case`                                      | `camel` | `snake` / `camel` / `pascal` for method keys                          |
| `enums.included` / `excluded` / `additional_directories` | `[]`    | Discovery filters (FQCNs or directories); default dir is `app/Enums`  |

## Common mistakes

- Adding a `status` string column plus `in:a,b,c` validation and a frontend label map. Make the enum.
- Writing `type Priority = 'low' | 'medium' | 'high'` or `const PRIORITY_LABELS = {...}` in a `.ts`/`.vue`
  file when the enum exists in PHP. Import `TaskPriorityType` and `TaskPriority.label` instead.
- Forgetting `#[TsEnumMethod]` and then wondering why `label` is missing on the frontend.
- Comparing `task.priority === 'high'`; use `TaskPriority.High`.
- Indexing a method map by value (`TaskPriority.label[task.priority]`); resolve with `from()` first.
- Editing the enum and not republishing; run `php artisan ts:publish --source=...` and re-read the `.ts`.

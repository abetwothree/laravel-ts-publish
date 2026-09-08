# Models

Each Eloquent model becomes a set of TypeScript interfaces built from the database schema, the casts, the
accessors and the relations. The generator reads the same PHPDoc PHPStan/Larastan reads, so the way to
sharpen a type is almost always a docblock on the PHP side, not a TypeScript override.

**Gate:** `config('ts-publish.models.enabled')` (default `true`). `model_metadata.enabled` (default
`false`) is a separate phase for the runtime `{model}_meta.ts` companions.

## What gets generated

`App\Models\Task` -> `app/models/task.ts` (+ `app/models/index.ts` barrel). With the default
`model-split` template:

```ts
import { type AsEnum } from '@tolki/ts';

import { TaskPriority } from '../enums';
import type { TaskPriorityType } from '../enums';
import type { Team, User } from '.';

/** @see App\Models\Task */
export interface Task {                 // database columns, in table order, plus $appends accessors
    id: number;
    team_id: number;
    assignee_id: number | null;
    title: string;
    status: string;
    priority: TaskPriorityType;         // enum cast -> {Enum}Type (what JSON carries)
    settings: { notify_on_complete: boolean; color: string | null; reminder_days: number[] } | null;
    due_at: string | null;              // datetime casts are string unless timestamps_as_date
    created_at: string | null;
    updated_at: string | null;
}

export interface TaskResource extends Omit<Task, 'priority'> {   // enum columns as resolved instances
    priority: AsEnum<typeof TaskPriority>;
}

export interface TaskMutators {          // accessors that are NOT in $appends
    /** Whether the task is past its due date and still open */
    is_overdue: boolean;
}

export interface TaskRelations {         // every relation + _count + _exists
    team: Team;                          // BelongsTo with NOT NULL fk
    assignee: User | null;               // BelongsTo with nullable fk
    team_count: number;
    assignee_count: number;
    team_exists: boolean;
    assignee_exists: boolean;
}

export interface TaskAll extends Task, TaskMutators, TaskRelations {}
export interface TaskAllResource extends TaskResource, TaskMutators, TaskRelations {}
```

- `{Model}` is what `$model->toArray()` / an Inertia prop / a JSON response of the bare model carries.
- `{Model}Mutators` holds non-appended accessors; an accessor listed in `$appends` / `#[Appends]` moves
  into `{Model}`.
- `{Model}Relations` has one entry per relation method plus `_count` and `_exists` (from `withCount` /
  `withExists`); names follow `models.relationship_case` (`snake` by default).
- `{Model}Resource` variants exist only when the model has enum-typed members and
  `enums.use_tolki_package` is on; use them for a payload the backend already serialized through
  `EnumResource` (each enum member arrives as `{ name, value, backed, ...methods }`). For a plain model
  payload keep `{Model}` and resolve on the client with `Status.from(model.status)` where you need a
  label; the result of `from()` on a union-typed value is one object with union-typed members, not an
  `AsEnum<>` union, so do not annotate it as `{Model}Resource['status']`.
- `models.template = 'laravel-ts-publish::model-full'` merges everything into one interface instead.
- `$hidden` / `#[Hidden]` columns are published unless `models.exclude_hidden` is `true` (then they also
  disappear from derived resource shapes). `#[Table]`, `#[Visible]`, `#[Appends]`, `#[Connection]`,
  `#[RouteKey]` (Laravel 13) are honoured.

## Using model types on the frontend

```ts
import type { Task, TaskRelations, TaskAll, TaskResource } from '@data/app/models';

// A page/component prop for a model with one eager-loaded relation
defineProps<{ task: Task & Pick<TaskRelations, 'assignee'> }>();

// A list with counts
type Row = Task & Pick<TaskRelations, 'assignee' | 'comments_count'>;

// Everything, when you really load everything
defineProps<{ task: TaskAll }>();

// A column's own type, so a form field or helper follows the model
let settings: Task['settings'];
```

Compose with `Pick` from the split interfaces instead of redeclaring fields. Model interfaces are
type-only; importing them costs nothing at runtime.

## Column type waterfall

For each column: `#[TsCasts]` override -> the cast (`casts()` / `$casts`, including `#[TsType]` on a custom
cast class) -> the raw DB column type. Then a class-level `@property` docblock refines any result that is
still vague.

| PHP                                                      | TypeScript                                        |
| -------------------------------------------------------- | ------------------------------------------------- |
| int/bigint/decimal/float/double/numeric column or cast   | `number`                                          |
| `boolean` cast, `tinyint(1)`                             | `boolean` (bare `tinyint` is `number`)            |
| string/text/char/uuid/enum column, `hashed`, `encrypted` | `string`                                          |
| date/datetime/timestamp/`Carbon` cast                    | `string` (or `Date` with `timestamps_as_date`)    |
| `array`, `collection`, `json` column with no docblock    | `unknown[]` / `object` -> add a `@property` shape |
| Enum class cast                                          | `{Enum}Type` (+ `AsEnum` on `{Model}Resource`)    |
| `AsEnumCollection::of(Status::class)`                    | `StatusType[]`                                    |
| `AsCollection::of(LineItemDto::class)`                   | `{ ...dto shape }[]`                              |
| `AsArrayObject` family                                   | `unknown[] \| Record<string, unknown>`            |
| Custom `CastsAttributes` with `#[TsType('X')]`           | `X` (with import when given)                      |
| Nullable column                                          | `\| null` appended                                |

`custom_ts_mappings` in config overrides a DB/cast type globally (`'binary' => 'Blob'`).

## Typing JSON / array columns: use `@property` (preferred over `#[TsCasts]`)

A column cast to `'array'` generates as `unknown[]`. Give it a shape with a class-level `@property` tag,
which PHPStan/Larastan also read, so the same line types `$task->settings` in PHP and `task.settings` in TS:

```php
/**
 * @property array{notify_on_complete: bool, color: string|null, reminder_days: list<int>}|null $settings
 * @property array<string, string>|null $labels
 * @property list<string>|null $tags
 */
class Task extends Model
{
    protected function casts(): array
    {
        return ['settings' => 'array', 'labels' => 'array', 'tags' => 'array'];
    }
}
```

```ts
settings: { notify_on_complete: boolean; color: string | null; reminder_days: number[] } | null;
labels: Record<string, string> | null;
tags: string[] | null;
```

Rules that matter:

- The tag only applies when the waterfall result is vague (`unknown`, `unknown[]`, `object`,
  `Record<string, unknown>`); it never overrides an enum cast, an accessor return type, or a custom cast.
- `array{...}` -> object literal with optional keys kept (`key?:`); `list<T>` / `array<int, T>` -> `T[]`;
  `array<string, T>` -> `Record<string, T>`; `array<array-key, T>` / `array<mixed, T>` -> `T[] | Record<string, T>`.
- A shape worth naming: declare `@phpstan-type PresetConfig array{...}` on the DTO/class that owns it, then
  `@phpstan-import-type PresetConfig from PresetDto` + `@property PresetConfig|null $config` on the model.
  The alias expands inline; nothing imports the DTO.
- `@property-read` works the same. Tags on used traits and parent classes are walked too; the subclass wins.
- `#[TsCasts(['settings' => '...'])]` on `casts()`, `$casts`, or the class still works and wins over
  everything, but the docblock is preferred because static analysis checks it and it needs no TS string.
  Reach for `#[TsCasts]` when the type lives in your own `.ts` file:
  `'dimensions' => ['type' => 'ProductDimensions', 'import' => '@/types/product']`.

## Accessors (mutators)

```php
/** Estimated reading time formatted */
protected function readingTime(): Attribute          // -> reading_time: string  (from the closure's return type)
{
    return Attribute::get(fn (): string => ...);
}

/** @return Attribute<Collection<int, LineItem>, never> */   // parameterize generics to avoid unknown[]
protected function lineItems(): Attribute { ... }             // -> line_items: LineItem[]

/** @return Attribute<?string, string> */                     // write-only: Get type from the generic
protected function trackingCode(): Attribute { return Attribute::make(set: ...); }
```

- Type the closure (`fn (): string`) or the `Attribute<Get, Set>` generic; `@return`/`@phpstan-return`
  on the method is read too. Untyped closures degrade to `unknown`.
- A write-only `Attribute::make(set:)` with no `Get` generic and no same-named column is omitted.
- Old-style `getFooAttribute()` accessors are supported; the same docblock rules apply.
- An `Arrayable` DTO returned from an accessor or cast infers its shape from its typed public
  properties when `toArray()` has no `@return array{...}`.

## Relations

Every relation method returning a Laravel relation type is published; singular relations get `| null` by
strategy (`HasOne`/`MorphOne`/`HasOneThrough`: always; `BelongsTo`: when the FK column is nullable;
`MorphTo`: when the morph columns are nullable; collections: never). Override with
`models.nullable_relations = false` or `models.relation_nullability_map`.

- `morphTo()` targets are found by scanning every model for the reverse `morphOne`/`morphMany`; narrow
  with `/** @return MorphTo<User|Team, $this> */`.
- A relation onto a framework model (`Notifiable` gives `notifications(): DatabaseNotification[]`)
  imports from `illuminate/notifications`, which is only generated when that class is listed in
  `models.additional_directories` (FQCNs are accepted there). Add
  `\Illuminate\Notifications\DatabaseNotification::class` to it, or `#[TsExclude]` the relation, or the
  import will not resolve.
- Two models with the same basename in different namespaces are imported under aliases automatically.

## Attributes

| Attribute                             | On                                   | Effect                                                                           |
| ------------------------------------- | ------------------------------------ | -------------------------------------------------------------------------------- |
| `#[TsCasts([...])]`                   | `casts()`, `$casts`, or the class    | Override/add column, accessor, or relation types; `['type','import','optional']` |
| `#[TsType('X')]` / `#[TsType([...])]` | a custom `CastsAttributes` class     | Type used wherever that cast is applied                                          |
| `#[TsExtends('Iface', import: ...)]`  | class, parent, or trait (repeatable) | Adds `extends` to the interface; also `ts_extends.models` in config              |
| `#[TsExclude]`                        | class, accessor, or relation method  | Drop it (class-level also drops its metadata companion)                          |

## Model metadata (`{model}_meta.ts`)

Off by default. When `model_metadata.enabled` is `true` each model also gets a runtime companion:

```ts
export const TaskModelMetadata = { morphClass: 'task' } as const satisfies { morphClass: string };
```

Use it for polymorphic payloads (`form.commentable_type = TaskModelMetadata.morphClass`) instead of
copying PHP class names into the frontend. A custom `model_metadata.provider_class` can add keys; type them
with `@return array{...}` on `provide()` or `#[TsCasts]`. Do not import `_meta` files when the phase is
disabled; they will not exist.

## Republishing

`php artisan ts:publish --source="App\Models\Task"` after editing a model. Column changes come from the
database, so run the migration first (the package republishes after `migrate` automatically unless
`run_after_migrate` is off). A new model needs a full `php artisan ts:publish` for the barrel.

## Still seeing `unknown`?

| Symptom                                                | Fix                                                                                         |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------- |
| `settings: unknown[]` on an `array` cast               | class-level `@property array{...} $settings`                                                |
| `items: unknown[]` from `Attribute<Collection, never>` | `Attribute<Collection<int, Item>, never>`                                                   |
| `AsCollection` / `AsEnumCollection` gives `unknown[]`  | `AsCollection::of(Dto::class)` / `AsEnumCollection::of(Enum::class)`                        |
| `morphTo` is `unknown \| null`                         | `@return MorphTo<A\|B, $this>`                                                              |
| accessor is `unknown`                                  | type the closure return or add `@return Attribute<T, never>`                                |
| a column is missing entirely                           | it is not in the DB schema yet: migrate, then republish                                     |
| a relation type import does not resolve                | the related model is not published: check `models.included/excluded/additional_directories` |
| type is right in PHPStan but TS still vague            | the waterfall resolved something non-vague already; use `#[TsCasts]`                        |

## Common mistakes

- Writing `interface TaskSettings {...}` in a `.ts` file and casting `task.settings as TaskSettings`.
  Put the shape on the model docblock and republish.
- Declaring frontend props as `any`/`Record<string, unknown>` for a model instead of importing `Task`.
- Redeclaring a model's fields in a hand-written interface; extend/pick the generated one instead.
- Forgetting to migrate before republishing and then hunting for the missing column.
- Using `{Model}Resource` for a plain model payload; the raw column is `{Enum}Type`, not an instance.

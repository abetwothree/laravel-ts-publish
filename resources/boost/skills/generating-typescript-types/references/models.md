# Models

Each Eloquent model becomes a set of TypeScript interfaces built from the database schema, the casts, the
accessors and the relations. The generator reads the same PHPDoc PHPStan/Larastan reads, so the way to
sharpen a type is almost always a docblock on the PHP side, not a TypeScript override.

**Gate:** `config('ts-publish.models.enabled')` (default `true`). The runtime `{model}_meta.ts`
companions are a separate phase with its own gate and its own rules:
[model-metadata.md](model-metadata.md).

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

- `{Model}` is the bare-model payload — columns plus `$appends` accessors — that `$model->toArray()`, an Inertia
  prop or a JSON response carries, with one exception: `$hidden` columns are published unless
  `models.exclude_hidden` is `true`, so the interface can claim a `password` the wire never sends.
- `{Model}Mutators` holds non-appended accessors; an accessor listed in `$appends` / `#[Appends]` moves
  into `{Model}`.
- `{Model}Relations` has one entry per relation method plus `_count` and `_exists` (from `withCount` /
  `withExists`); names follow `models.relationship_case` (`snake` by default).
- The `Resource` variants exist only when `enums.use_tolki_package` is on and the model has enum-typed members,
  and which one you get depends on where the enum sits: an enum-typed **column or `$appends` entry** gives
  `{Model}Resource`, an enum-typed **non-appended accessor** gives `{Model}MutatorsResource` instead (so a model
  whose only enum member is a plain accessor has no `{Model}Resource` at all), and `{Model}AllResource` appears
  whenever either does; use them for a payload the backend already serialized through
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
type Row = Task & Pick<TaskRelations, 'assignee' | 'assignee_count'>;   // only keys TaskRelations declares

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

| PHP                                                      | TypeScript                                     |
| -------------------------------------------------------- | ---------------------------------------------- |
| int/bigint/decimal/float/double/numeric column or cast   | `number`                                       |
| `boolean` cast, `tinyint(1)`                             | `boolean` (bare `tinyint` is `number`)         |
| string/text/char/uuid/enum column, `hashed`, `encrypted` | `string`                                       |
| date/datetime/timestamp/`Carbon` cast                    | `string` (or `Date` with `timestamps_as_date`) |

**A `datetime` column and an `<input type="date">` agree on the type and disagree at runtime.** The column
publishes as `string` and the request field as `string`, so `vue-tsc` is green, but the value is a full
ISO-8601 timestamp (`2026-09-10T00:00:00.000000Z`) and a date input silently renders nothing for it — then
submits an empty string, which `ConvertEmptyStringsToNull` turns into `null` and a `nullable|date` rule
happily accepts. **Every save wipes the column.** Slice when seeding the form
(`props.task.due_at?.slice(0, 10)`) or bind an `<input type="datetime-local">`; `timestamps_as_date` does not
help, it only swaps the TypeScript type for `Date`.
| `array`, `collection`, `json` column with no docblock | `unknown[]` / `object` -> add a `@property` shape |
| Enum class cast | `{Enum}Type` (+ `AsEnum` on `{Model}Resource`) |
| `AsEnumCollection::of(Status::class)` | `StatusType[]` |
| `AsCollection::of(LineItemDto::class)` | `{ ...dto shape }[]` |
| `AsArrayObject` family | `unknown[] \| Record<string, unknown>` |
| Custom `CastsAttributes` with `#[TsType('X')]` | `X` (with import when given) |
| Nullable column | `\| null` appended |

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
  `Record<string, unknown>`). It never overrides a type already resolved specifically — an enum cast, a custom
  cast class, a typed accessor return — but it does refine a _vague_ one, including an accessor declared
  `fn (): array`.
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
- **`withPivot()` columns are never published.** A `BelongsToMany` generates as `Related[]` with no `pivot`
  member, and `#[TsCasts]` cannot retype a relation, so there is no PHP-side fix: intersect at the use site
  (`type Member = User & { pivot: Pick<TeamUser, 'role'> }`) and keep the column list in PHP.
- **`models.included` / `models.excluded` silently drop relations.** A relation whose related model is filtered
  out disappears from `{Model}Relations` along with its `_count`/`_exists`, with no import and no warning. A
  relation missing from a model you did not touch means the _related_ model is not published.

## Attributes

| Attribute                             | On                                   | Effect                                                                                                                                                                                                                                                     |
| ------------------------------------- | ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[TsCasts([...])]`                   | `casts()`, `$casts`, or the class    | Override the type of a **column or accessor that is already published** (`['type','import','optional']`). It cannot retype a relation, and it cannot add a key the schema/accessors do not already produce — an entry matching neither is silently ignored |
| `#[TsType('X')]` / `#[TsType([...])]` | a custom `CastsAttributes` class     | Type used wherever that cast is applied                                                                                                                                                                                                                    |
| `#[TsExtends('Iface', import: ...)]`  | class, parent, or trait (repeatable) | Adds `extends` to the interface; also `ts_extends.models` in config                                                                                                                                                                                        |
| `#[TsExclude]`                        | class, accessor, or relation method  | Drop it (class-level also drops its metadata companion)                                                                                                                                                                                                    |

## Republishing

`php artisan ts:publish --source="App\Models\Task"` after editing a model. Column changes come from the
database, so run the migration first (the package republishes after `migrate` automatically unless
`run_after_migrate` is off). A new model needs a full `php artisan ts:publish` for the barrel.

## Still seeing `unknown`?

| Symptom                                                                                                                                          | Fix                                                                                                     |
| ------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- |
| `settings: unknown[]` on an `array` cast                                                                                                         | class-level `@property array{...} $settings`                                                            |
| `items: unknown[] \| Record<string, unknown>` from `Attribute<Collection, never>` (a bare Eloquent `Collection` gives `Record<string, unknown>`) | `Attribute<Collection<int, Item>, never>`                                                               |
| `AsCollection` / `AsEnumCollection` gives `unknown[]`                                                                                            | `AsCollection::of(Dto::class)` / `AsEnumCollection::of(Enum::class)`                                    |
| `morphTo` is bare `unknown` (no `\| null`)                                                                                                       | `@return MorphTo<A\|B, $this>`                                                                          |
| accessor is `unknown`                                                                                                                            | type the closure return or add `@return Attribute<T, never>`                                            |
| a column is missing entirely                                                                                                                     | it is not in the DB schema yet: migrate, then republish                                                 |
| a relation type import does not resolve                                                                                                          | the related model is not published: check `models.included/excluded/additional_directories`             |
| PHPStan is right but TS shows a different **specific** type                                                                                      | the waterfall already resolved something non-vague, so the `@property` tag is ignored; use `#[TsCasts]` |
| PHPStan is right but TS is still **vague**                                                                                                       | the tag's own type is vague too (`array<string, mixed>`); spell a concrete `array{...}` shape           |

## Common mistakes

- Writing `interface TaskSettings {...}` in a `.ts` file and casting `task.settings as TaskSettings`.
  Put the shape on the model docblock and republish.
- Declaring frontend props as `any`/`Record<string, unknown>` for a model instead of importing `Task`.
- Redeclaring a model's fields in a hand-written interface; extend/pick the generated one instead.
- Forgetting to migrate before republishing and then hunting for the missing column.
- Using `{Model}Resource` for a plain model payload; the raw column is `{Enum}Type`, not an instance.

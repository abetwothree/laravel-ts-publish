# API resources

A `JsonResource` becomes an interface derived statically from its `toArray()`, with property types
resolved against the backing model's schema and casts. Whatever an API endpoint returns already has a
TypeScript shape; do not hand-maintain a second one.

**Gate:** `config('ts-publish.resources.enabled')` (default `true`). Default scan dir: `app/Http/Resources`.

## Backend: write `toArray()` the analyzer can read

```php
/**
 * Task as exposed to the API.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'priority' => EnumResource::make($this->priority),
            'settings' => $this->settings,
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'comments_count' => $this->whenCounted('comments'),
            'is_overdue' => $this->is_overdue,
            $this->mergeWhen($request->user()?->isAdmin(), fn () => ['internal_note' => $this->internal_note]),
        ];
    }
}
```

```ts
import { type AsEnum } from '@tolki/ts';
import { TaskPriority } from '../../enums';
import type { CommentResource, UserResource } from '.';

/**
 * Task as exposed to the API.
 *
 * @see App\Http\Resources\TaskResource
 */
export interface TaskResource {
    id: number;
    title: string;
    priority: AsEnum<typeof TaskPriority>;
    settings: { notify_on_complete: boolean; color: string | null; reminder_days: number[] } | null;
    assignee?: UserResource;
    comments?: CommentResource[];
    comments_count?: number;
    is_overdue: boolean;
    internal_note?: string | null;
}
```

The backing model is resolved from `#[TsResource(model:)]`, then the resource's `@mixin`/`@extends`
docblock (or the nearest ancestor's), a typed `$resource` property, the naming convention
(`TaskResource` -> `App\Models\Task`), or a `#[UseResource]` attribute on a model. Add `@mixin Task` when
the name does not match; it also gives the IDE and PHPStan the same knowledge.

### Patterns that type correctly

| In `toArray()`                                                                                                                                                                                    | Result                                                                                                                                                                                                                                                                                                                                                                                   |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$this->column`, `$this->accessor`, a local variable assigned once from one                                                                                                                       | model column/accessor type; a property the resource class declares itself wins over a same-named model attribute (`$this->resource->x` still reads the model)                                                                                                                                                                                                                            |
| `$this->status->label()`, `$this->published_at->toDateString()`, `Priority::from(1)->label()`, `resolve(PriceQuoteService::class)->quote()`, `$this->stats?->views`, `$post?->author?->name` | the called method's native or `@return` type, or the property's type, on whatever class the receiver holds: enum, model, Carbon, value object or service (a non-public method only on `$this`). A method whose declared return is vague (a bare `: array`, or none) types from the array literal it returns; with no literal, or with a value in it that names a class, enum or model, the vague type stands. A return that serializes differently from its type (`DateTime`, a `CarbonInterval` such as `diff()`'s), `Model::toArray()`, and a model the package does not publish stay `unknown` |
| A local or closure-local assigned once, after `if (! $x instanceof Post) { return null; }`, or read in `$x instanceof Post ? $x->title : null` | reads through the variable (`$x->title`, `$x->method()`) type against `Post` after the guard, or inside the true arm; the variable's own value (`'x' => $x`) keeps its un-narrowed type. `$v = $this->owner instanceof Post \|\| $this->owner instanceof User ? $this->owner : null` narrows what is read through `$v` (`$v?->getKey()`), not `$v` itself. A positive `if ($x instanceof Post) { ... }` narrows nothing, but a test on `$this->resource` narrows the backing model in either polarity, a positive `if` included |
| `->map(fn (Comment $c) => [...])`, `->values()`, `->all()`, `concat()` of the same collection, `collect(explode(...))->map()`, `data_get($this->author, 'name')` | the element type, carried to the end of the chain (`data_get()` reads as `$this->author?->name`); a typed `map()` parameter also types multi-step and `?->` reads (`$c->user?->name` is `string \| null`) |
| `EnumResource::make($this->enum)` / `::collection(...)`                                                                                                                                           | `AsEnum<typeof Enum>` / `AsEnum<...>[]`                                                                                                                                                                                                                                                                                                                                                  |
| `SomeResource::make($x)`, `new SomeResource($x)` (also with `->resolve()`), `SomeResource::collection($x)`                                                                                         | `SomeResource`, `SomeResource[]` (self-references fine)                                                                                                                                                                                                                                                                                                                                  |
| `$this->owner->toResource()` (explicit class, `#[UseResource]`, or convention)                                                                                                                    | that resource; a convention guess for an unpublished resource is `unknown`. On a `morphTo` union, such as a `whenLoaded('reviewable', fn ($r) => $r->toResource())` parameter, the union of each target's resource (`ArtistResource \| VenueResource`), or `unknown` if any target has none |
| `when()`, `unless()`, `whenLoaded()`, `whenHas()`, `whenAppended()`, `whenNotNull()`, `whenNull()`, `whenCounted()`, `whenAggregated()`, `whenExistsLoaded()`, `whenPivotLoaded()`, `transform()` | optional `?`; pass an explicit default argument and it becomes required with the default's type unioned in. `whenHas()`, `whenAppended()` and `whenExistsLoaded()` type from the value you pass, as Laravel returns it (`whenHas('title', fn ($t) => strlen($t))` is `number`); a value they cannot type keeps the named attribute's type. A value closure's parameter holds what Laravel passes it: the relation for `whenLoaded()`, the attribute for `whenHas()`, the count for `whenCounted()`, the value for `transform()`. Where Laravel passes nothing, as `whenAppended()` does, an optional parameter holds its default |
| `$this->merge([...])` / closure                                                                                                                                                                   | required keys spread in                                                                                                                                                                                                                                                                                                                                                                  |
| `mergeWhen()` / `mergeUnless()`                                                                                                                                                                   | optional keys spread in                                                                                                                                                                                                                                                                                                                                                                  |
| `...parent::toArray($request)` or `return parent::toArray($request)`                                                                                                                              | parent shape, or every model column when the parent is `JsonResource`                                                                                                                                                                                                                                                                                                                    |
| no `toArray()` at all                                                                                                                                                                             | inherited from the nearest ancestor that has one, else all model columns                                                                                                                                                                                                                                                                                                                 |
| `...$this->traitMethod()` / `return $this->method()`                                                                                                                                              | resolved transitively, and the target's body is read wherever it is declared — a trait method in its own file included. When each `return` is an array literal, `[]` or a variable the method builds, every one counts: a key one branch lacks, such as behind a `return []` guard or set only inside an `if`, is optional. If any `return` is something else (a method call, a ternary, `array_merge()`), only the first `return` is read. `@return array{...}` or `@return array<string, V>` only fills keys the body left `unknown`; `#[TsCasts]` overrides outright. A key built from literal text around a loop variable (`$data["{$name}_tag"]`) publishes ``[key: `${string}_tag`]: string \| undefined``, typed by the body, else by `@return array<string, V>`, else `unknown \| undefined`. A backslash in that literal text is doubled, as TypeScript needs to read it as one (`"{$name}\\unit"` publishes ``[key: `${string}\\unit`]``), and a backtick in it publishes no key. A named key the pattern covers joins that union when its type can (not `unknown`, no class or enum, no backslash in a string literal). Beside one that cannot, beside an overlapping pattern, or under an extends clause, a `@return` fill goes back to `unknown \| undefined`, while a value the body typed stays as it is and can fail `tsc` (TS2411) beside that key: type the key or rename it out of the pattern. The one exception is the **entry** `toArray()`: a resource that inherits `toArray()` from a trait is treated as having none, and falls back to every model column |
| `$this->only([...])` / `$this->except([...])`, also through `$this->resource`                                                                                                                     | spread: the named attributes, a typed key the schema lacks included (a `withCount()` `comments_count`, an `@property` name) / their complement. As a value: `Pick<Model, ...>` when every key is a real column, else an inline shape of the keys that type (`Record<string, unknown>` when none does). A runtime key list (`only($request->input('fields'))`) is `Record<string, unknown>` as a value and adds no keys when spread. A model that overrides `only()`/`except()` with a specific return type (`only($attributes): string`) publishes that return as the value instead. An override with no return type, or one too vague to name a value (`: array`, `: ?array`, `: mixed`, `: iterable`, `: array\|string`, `@return array<string, mixed>`), keeps the answers above |
| `$this->relation->only([...])` / `->except([...])`, also `$this->resource->relation`                                                                                                              | a single model: `Pick<RelatedModel, ...>` when every key is a real column, else an inline shape; a runtime key list is `Record<string, unknown>`. A to-many relation: its own type (`Comment[]`) for any key list, because Laravel filters those models by primary key, except when spelled `$this->resource?->comments->only(...)`, which is `unknown`. `?->` adds `\| null` |
| A `Collection` member's `->only([...])` / `->except([...])`, where its collection class runs `Illuminate\Support\Collection`'s own filter: a `'collection'`, `'encrypted:collection'`, `AsCollection` or `AsEncryptedCollection` column, or an accessor, cast getter or method returning a `Collection` | `Record<string, unknown>` for any key list and any element type, because a collection's filters select entries by key (`only()` keeps the listed keys, `except()` drops them). `?->` adds `\| null`. It stays `unknown` for an `AsEnumCollection` column, for one of those cast columns read through a receiver other than `$this`/`$this->resource` (another model, or a local holding the model), and for a collection class that overrides `only()`/`except()`: a `using()` class or returned subclass that overrides them, a cast that builds an Eloquent collection, or a method returning an Eloquent collection. An accessor returning an Eloquent collection publishes a list of its models (`Comment[]`), as a to-many relation does, whatever key type it declares |
| `[...$model->toArray(), 'flag' => true]`                                                                                                                                                          | `Omit<Model, 'flag'> & { flag: boolean }`. Inside a `collect(...)->map()` closure the spread names no model (or the enclosing `whenLoaded()` one), so map the relation itself: `$this->comments->map(fn ($c) => [...$c->toArray(), 'flag' => true])` |
| `fn () => $this->x`, `function () { return $this->x; }`                                                                                                                                           | analyzed through the closure                                                                                                                                                                                                                                                                                                                                                             |
| `ResourceCollection` with `$this->collection`                                                                                                                                                     | `SingularResource[]`, `Record<string, R>` with `#[PreserveKeys]` / `$preserveKeys`                                                                                                                                                                                                                                                                                                       |

An expression the analyzer cannot follow is `unknown`, and so is a variable reassigned or assigned in a branch;
literals, operators and helpers such as `route()` or `->count()` type without a row here. A ternary or `?:` arm
it cannot type is dropped, so `$cond ? $untypable : null` publishes `null`. Type it with a `@return` on the
method it calls, `#[TsCasts]` as a last resort, or restructure so the expression is one of the above.

**Only `toArray()` decides the keys** — plus the methods it returns, spreads or inherits; a method it merely
calls is read only to type that one value. Top-level keys added by
`->additional(['meta' => ...])`, by a `with(Request $request)` method, or by a collection's pagination metadata
never reach the interface at all. They are silently **missing**, not `unknown`, so the "look for `unknown`" check
will not find them: move the key into `toArray()`, or type the envelope with `ResourcePagination` /
`JsonResourcePaginator` from `@tolki/types`.

`whenNotNull($this->x)` strips `| null`. A **bare** `whenLoaded('rel')` resolves to the relation type and keeps
the nullability strategy from `models.*` (`profile?: Profile | null`), but wrapping it in a resource
(`UserResource::make($this->whenLoaded('user'))`) yields the resource type with **no** `| null` — the wrap
replaces the relation's own type. `whenPivotLoaded` is `unknown`.

### Attributes

| Attribute                                                      | Effect                                                                                |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| `#[TsResource(name:, model:, description:)]`                   | Rename the interface and file (`name: 'Task'` -> `task.ts`), pin the model, set JSDoc |
| `#[TsCasts([...])]` on the class or a trait method             | Override a property's type/optionality, add an import, or append a virtual property   |
| `#[TsExtends(...)]` / `ts_extends.resources`                   | `extends` clauses                                                                     |
| `#[TsExclude]`                                                 | Skip the resource (its file and barrel entry)                                         |
| `#[Collects]`, `$collects`, `#[PreserveKeys]`, `$preserveKeys` | Collection element type and keyed `data`                                              |

`models.exclude_hidden` also governs resources: a `$hidden` column you name explicitly (`'password' => $this->password`,
`only(['password'])`) stays; one reached through `except()`, `parent::toArray()`, or no `toArray()` is dropped.

## Frontend

```ts
import type { TaskResource } from '@data/app/http/resources';
import type { AsEnum } from '@tolki/ts';
import { TaskPriority } from '@data/app/enums';

const { data: { data: task } } = await axios.get<{ data: TaskResource }>(TaskController.show.url(taskId));
task.priority.label;                       // already resolved: EnumResource sent name/value/label/color
task.priority.value === TaskPriority.High; // compare with the case constant

// A paginated resource collection from @tolki/types
import type { JsonResourcePaginator } from '@tolki/types';
type Page = JsonResourcePaginator<TaskResource>;
```

A resource collection (`TaskResource::collection(...)`) is `TaskResource[]` inside a `data` key when
wrapping is on. Resource interfaces are type-only; the enum object import is what carries `AsEnum`.

## Republishing

`php artisan ts:publish --source="App\Http\Resources\TaskResource"`; a new resource needs the full run for
the barrel. The `AstEngine::analyze()` API returns the same property list programmatically if you need it.

## Common mistakes

- Writing `interface ApiTask {...}` next to a fetch call when `TaskResource` is generated.
- Importing `{Model}Resource` from `@data/app/models` when you meant the API resource. A model with enum columns
  publishes an enum-resolved companion of exactly that name, so `import type { UserResource } from '@data/app/models'`
  gives you the model companion, not `App\Http\Resources\UserResource`. Import API resources from
  `@data/app/http/resources`, never both names in one file, and use `#[TsResource(name: 'UserData')]` to keep them apart.
- Returning `$this->priority` bare and then reconstructing labels on the frontend; use `EnumResource::make()`
  (or resolve with `TaskPriority.from()` on the client).
- Forgetting `@mixin Model` on a resource whose class name does not match its model, then seeing `unknown`.
- Reassigning a local variable in `toArray()`; the analyzer refuses to guess which write is live.
- Building the array in a variable and ending `toArray()` with `return $data;`. `toArray()`'s keys come from the
  array literals it returns, so the interface publishes empty. Return the literal, or build it in a method that
  `toArray()` returns (`return $this->payload();`) or spreads, where a returned variable is read.

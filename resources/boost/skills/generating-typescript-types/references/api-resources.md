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
    assignee?: UserResource | null;
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

| In `toArray()`                                                                                                                                                                                    | Result                                                                                                     |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `$this->column`, `$this->accessor`, a local variable assigned once from one                                                                                                                       | model column/accessor type                                                                                 |
| `EnumResource::make($this->enum)` / `::collection(...)`                                                                                                                                           | `AsEnum<typeof Enum>` / `AsEnum<...>[]`                                                                    |
| `SomeResource::make($x)`, `new SomeResource($x)`, `SomeResource::collection($x)`                                                                                                                  | `SomeResource`, `SomeResource[]` (self-references fine)                                                    |
| `$this->owner->toResource()` (explicit class, `#[UseResource]`, or convention)                                                                                                                    | that resource; a convention guess for an unpublished resource is `unknown`                                 |
| `when()`, `unless()`, `whenLoaded()`, `whenHas()`, `whenAppended()`, `whenNotNull()`, `whenNull()`, `whenCounted()`, `whenAggregated()`, `whenExistsLoaded()`, `whenPivotLoaded()`, `transform()` | optional `?`; pass an explicit default argument and it becomes required with the default's type unioned in |
| `$this->merge([...])` / closure                                                                                                                                                                   | required keys spread in                                                                                    |
| `mergeWhen()` / `mergeUnless()`                                                                                                                                                                   | optional keys spread in                                                                                    |
| `...parent::toArray($request)` or `return parent::toArray($request)`                                                                                                                              | parent shape, or every model column when the parent is `JsonResource`                                      |
| no `toArray()` at all                                                                                                                                                                             | inherited from the nearest ancestor that has one, else all model columns                                   |
| `...$this->traitMethod()` / `return $this->method()`                                                                                                                                              | resolved transitively; needs `@return array{...}` or `#[TsCasts]` on the method                            |
| `$this->only([...])` / `$this->except([...])`                                                                                                                                                     | the named columns / their complement                                                                       |
| `$this->relation->only([...])` / `->except([...])`                                                                                                                                                | `Pick<RelatedModel, ...>` when every key is a real column, else an inline shape                            |
| `[...$model->toArray(), 'flag' => true]`                                                                                                                                                          | `Omit<Model, 'flag'> & { flag: boolean }`                                                                  |
| `fn () => $this->x`, `function () { return $this->x; }`                                                                                                                                           | analyzed through the closure                                                                               |
| `ResourceCollection` with `$this->collection`                                                                                                                                                     | `SingularResource[]`, `Record<string, R>` with `#[PreserveKeys]` / `$preserveKeys`                         |

Anything else, and a variable reassigned or assigned in a branch, is `unknown`; type it with `#[TsCasts]`
or restructure so the expression is one of the above.

`whenNotNull($this->x)` strips `| null`; `whenLoaded('rel')` bare resolves to the relation type with the
nullability strategy from `models.*`; `whenPivotLoaded` is `unknown`.

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

const { data } = await axios.get<{ data: TaskResource }>(TaskController.show.url(task.id));
data.priority.label;                       // already resolved: EnumResource sent name/value/label/color
data.priority.value === TaskPriority.High; // compare with the case constant

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
- Returning `$this->priority` bare and then reconstructing labels on the frontend; use `EnumResource::make()`
  (or resolve with `TaskPriority.from()` on the client).
- Forgetting `@mixin Model` on a resource whose class name does not match its model, then seeing `unknown`.
- Reassigning a local variable in `toArray()`; the analyzer refuses to guess which write is live.

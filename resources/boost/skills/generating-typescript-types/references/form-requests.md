# Form requests

Each `FormRequest` becomes an interface describing the validated payload, derived from `rules()`. The
same rules that reject a bad request also type the `useForm()` / axios body on the frontend, and the route
helper for any action that type-hints the request carries the interface (`InferRequestPayload`).

**Gate:** `config('ts-publish.form_requests.enabled')` (default `true`). Route payload annotation also
needs `routes.enabled`.

## Backend: write rules the analyzer can read

Put validation in a `FormRequest` (not inline `$request->validate()`), type-hint it in the controller
action, and prefer rule objects and explicit presence rules:

```php
class UpdateTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'settings' => ['sometimes', 'array'],
            'settings.notify_on_complete' => ['required', 'boolean'],
            'settings.color' => ['nullable', 'string', 'hex_color'],
            'settings.reminder_days' => ['sometimes', 'array'],
            'settings.reminder_days.*' => ['integer', 'min:0'],
            'tags' => ['array'],
            'tags.*' => ['string'],
        ];
    }
}
```

```ts
/** @see App\Http\Requests\UpdateTaskRequest */
export interface UpdateTaskRequest {
    title?: string;
    status?: 'todo' | 'in_progress' | 'done';
    /** @constraint exists */
    assignee_id?: number | null;
    /** @format date */
    due_at?: string | null;
    /** @format hex_color settings.color */
    settings?: { notify_on_complete: boolean; color?: string | null; reminder_days?: number[] };
    tags?: string[];
}
```

The analyzer instantiates the request without an HTTP request and calls `rules()`. `Auth::user()->isAdmin()`
style method calls are stubbed (return `false`); reading a property (`$this->user()->id`) or session
state throws inside the analyzer and the whole request falls back to `type X = Record<string, unknown>`
with a `@dynamic` JSDoc. Keep rules static, or move the dynamic part into `withValidator()` /
`after()` hooks.

## Rule to type mapping (first match wins)

| Rule                                                                                                     | Type                                                                                           |
| -------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `Rule::file()`, `Rule::dimensions()`, `file`, `image`, `mimes`, `mimetypes`, `extensions`                | `File`                                                                                         |
| `Rule::anyOf([...])`                                                                                     | union of each inner set                                                                        |
| `Rule::enum(E::class)` (+ `->only()`/`->except()`)                                                       | union of the enum's backing values                                                             |
| `Rule::in([...])` / `in:a,b`                                                                             | `'a' \| 'b'`; numeric literals unquoted when a numeric rule is a sibling                       |
| `string`, `email`, `url`, `uuid`, `ulid`, `date`, `date_format`, `json`, `regex`, `ip`, `hex_color`, ... | `string`                                                                                       |
| `integer`, `int`, `numeric`, `decimal`, `digits`, `digits_between`                                       | `number`                                                                                       |
| `boolean`, `accepted`, `declined` (+ `_if` forms)                                                        | `boolean`                                                                                      |
| `array`, `list`                                                                                          | `unknown[]`, upgraded to `T[]` by a `field.*` rule or to an object by nested `field.key` rules |
| `required_array_keys:a,b` / `in_array_keys:a,b` / `array:a,b` / `array_keys:a,b`                         | keyed object with `unknown` values (`?` where presence is not guaranteed)                      |
| anything else                                                                                            | `unknown`                                                                                      |

Presence and nullability:

| Rule                                               | Effect                                                                                                                                                                                                                         |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `required` (any `required*`, `Rule::requiredIf()`) | no `?`                                                                                                                                                                                                                         |
| `sometimes`                                        | `?`, even with `required`                                                                                                                                                                                                      |
| no `required`/`sometimes` at all                   | `?` (presence must be declared, as in Laravel)                                                                                                                                                                                 |
| `present`, `present_with`, `present_if`, ...       | `?` as well: only `required*` removes the `?`, so a key Laravel guarantees present (but allows `null`/`[]`) still generates optional; intersect on the frontend (`& { settings: NonNullable<...> }`) when you need it required |
| `nullable`                                         | `\| null` appended, also after a `#[TsCasts]` override                                                                                                                                                                         |
| `missing`, `prohibited`                            | field (or nested key and its children) omitted                                                                                                                                                                                 |

Nested rules compose into their parent: `tags.*` -> `tags: string[]`; `order.items.*.sku` ->
`order: { items: { sku: string }[] }`; a wildcard beside named keys -> `{ default?: string } & Record<string, string>`;
`items.0.name` -> `items: { name: string }[]`. A dotted key is never emitted as a quoted top-level property.
Escaped dots (`'v1\.0'`) stay a literal field name.

JSDoc metadata rides along: `@format email`, `@format date`, `@constraint exists|unique`,
`@metadata required-conditionally`, `@not a, b`; nested ones are hoisted onto the parent with the full key.

## Attributes

- `#[TsCasts(['status' => "'draft' | 'published'", 'attributes' => ['type' => 'PostAttributes', 'import' => '@/types/posts'], 'rating' => ['type' => 'number', 'optional' => true]])]`
  on the class rewrites the **type and optionality** of fields `rules()` declares. Keys that match no rule
  add nothing; dotted keys (`'order.id'`, `'tags.*'`) match nothing; override the parent instead.
- `#[TsExtends(...)]` and `ts_extends.form_requests` add `extends` clauses.
- `#[TsExclude]` on the class drops it. There is no field-level exclusion; use `prohibited`/`missing`.

## Frontend: type the form from the request

```ts
import { useForm } from '@inertiajs/vue3';
import type { InferRequestPayload } from '@tolki/ts';
import type { UpdateTaskRequest } from '@data/app/http/requests';
import { update } from '@data/app/http/controllers/task-controller';

// Either the interface directly...
const form = useForm<UpdateTaskRequest>({ title: task.title, status: task.status, assignee_id: task.assignee_id, due_at: task.due_at });

// ...or read it off the route helper, which stays correct if the action's request class changes.
const form2 = useForm<InferRequestPayload<typeof update>>({ ... });

form.submit(update(task.id));         // Inertia v2/v3 accept the { url, method } pair; form.put(update.url(task.id)) on v1
```

For a partial form use `Pick<UpdateTaskRequest, 'title' | 'status'>`; for nested settings use
`NonNullable<UpdateTaskRequest['settings']>`. A field typed `File` maps to an `<input type="file">` and
needs `forceFormData` in Inertia.

`$request->validated('settings.color')` in an Inertia action types the page prop from the same rules
(nested dotted keys included; wildcard paths and keys under a `#[TsCasts]`-overridden parent stay `unknown`).

## Republishing

`php artisan ts:publish --source="App\Http\Requests\UpdateTaskRequest"` after editing rules, then republish
the controller (or run the full command) so the route helper's `annotateRequestPayload` picks up a newly
type-hinted request. The Vite plugin does the `--source` run for you on save during `vite dev`.

## Common mistakes

- Hand-writing `interface TaskForm { title: string; ... }` when `UpdateTaskRequest` already exists.
- Inline `$request->validate([...])` in the controller: nothing is generated and the route has no payload type.
- Forgetting `required`/`sometimes` and then wondering why every field is optional.
- `'settings' => ['array']` with no `settings.*` rules, then complaining the frontend type is `unknown[]`.
- Reading request state inside `rules()` (dynamic fallback) instead of `withValidator()`.

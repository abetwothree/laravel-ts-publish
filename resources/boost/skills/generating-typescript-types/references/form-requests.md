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
            'settings' => ['sometimes', 'array', 'required_array_keys:notify_on_complete,color,reminder_days'],
            'settings.notify_on_complete' => ['required_with:settings', 'boolean'],
            'settings.color' => ['nullable', 'string', 'hex_color'],
            'settings.reminder_days' => ['present_with:settings', 'array'],
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

The analyzer really does call `rules()`, against a fake `POST /` request, and stubs the **Auth facade** with a
user whose every method call returns `false`. So `Auth::user()->isAdmin()` and `auth()->user()->id` are safe.
What is not stubbed is the request itself: it has no user resolver, so `$this->user()` is `null` and
`$this->user()->id` throws. **Any** throw from `rules()` drops the whole class to
`type X = Record<string, unknown>` with a `@dynamic` JSDoc. Computed rules are fine as long as nothing they
touch needs real request or session state; move what does into `withValidator()` / `after()`. A database query
does **not** degrade the class — it runs against the live connection, so `Rule::in(Category::pluck('slug')->all())`
bakes the rows present at publish time into the union and needs a republish when they change.

**Two traps in that nested block, both verified.** A bare `required` on a nested key is an _implicit_ rule: it
fires even when the parent is absent, so `'settings.notify_on_complete' => ['required', ...]` makes the whole
block mandatory on every request. Reaching for `required_with:settings` fixes that and breaks the other way,
because the `required*` family counts `[]` and `''` as absent — so a legitimately empty `reminder_days: []` is
rejected. Use `required_with` only for keys that can never be empty (a boolean), `present_with` for keys that
can, and `required_array_keys:` on the parent for all-or-nothing. `nullable` and `required*` do not compose on
one key: `required_with` still runs under `nullable` and rejects an explicit `null`, so "present but may be
null" is only expressible as `nullable` on the key plus `required_array_keys` on the parent.

Every branch inside `rules()` is evaluated once in one fixed context — method `POST`, no route parameters
(`$this->route('task')` is `null`), empty input, and a stub user whose every method returns `false`. An
`if ($this->isMethod('PUT'))` or `if (Auth::user()->isAdmin())` arm therefore contributes nothing, and its fields
are **silently absent** from the interface with no `@dynamic` marker. Give each verb its own request class and
keep role-gated fields out of branches. `prepareForValidation()` never runs either, so a key the server merges
and then validates lands in the interface as a field the frontend must supply — keep it out of `rules()`, or mark
it `['optional' => true]` with `#[TsCasts]`.

## Rule to type mapping (first match wins)

| Rule                                                                                                     | Type                                                                                                                                                                                                                                                                 |
| -------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Rule::file()`, `Rule::dimensions()`, `file`, `image`, `mimes`, `mimetypes`, `extensions`                | `File`                                                                                                                                                                                                                                                               |
| `Rule::anyOf([...])`                                                                                     | union of each inner set                                                                                                                                                                                                                                              |
| `Rule::enum(E::class)` (+ `->only()`/`->except()`)                                                       | union of the enum's backing values                                                                                                                                                                                                                                   |
| `Rule::in([...])` / `in:a,b`                                                                             | `'a' \| 'b'`. `Rule::in([1, 2, 3])` emits unquoted `1 \| 2 \| 3` straight from the PHP value types; the string form `in:1,2,3` unquotes only when a numeric rule is a sibling **and** the literal round-trips (`['numeric', 'in:007,2.50']` stays `'007' \| '2.50'`) |
| `string`, `email`, `url`, `uuid`, `ulid`, `date`, `date_format`, `json`, `regex`, `ip`, `hex_color`, ... | `string`                                                                                                                                                                                                                                                             |
| `integer`, `int`, `numeric`, `decimal`, `digits`, `digits_between`                                       | `number`                                                                                                                                                                                                                                                             |
| `boolean`, `accepted`, `declined` (+ `_if` forms)                                                        | `boolean`                                                                                                                                                                                                                                                            |
| `array`, `list`                                                                                          | `unknown[]`, upgraded to `T[]` by a `field.*` rule or to an object by nested `field.key` rules                                                                                                                                                                       |
| `required_array_keys:a,b` / `in_array_keys:a,b` / `array:a,b` / `array_keys:a,b`                         | keyed object with `unknown` values (`?` where presence is not guaranteed)                                                                                                                                                                                            |

The `unknown`-valued fallback for those four key-list rules applies only when there are **no** nested
`field.key` rules. When there are, the nested rules win and shape the object properly, so `required_array_keys:`
combines safely with them — it is the only rule that enforces all-or-nothing on a JSON block.
| anything else | `unknown` |

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
`order?: { items?: { sku: string }[] }` — the presence table applies at every level, so a parent reached only by a
deeper rule composes optional until it declares its own `required`; a wildcard beside named keys ->
`{ default?: string } & Record<string, string>`;
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

Adding a rule object such as `Rule::enum(...)` to an array that `make:request` scaffolded means the stub's
`@return array<string, array<int, string>>` docblock no longer describes it — widen or drop the docblock, or
PHPStan flags the file you just improved.

Adding a rule object such as `Rule::enum(...)` to an array that `make:request` scaffolded means the stub's
`@return array<string, array<int, string>>` docblock no longer describes it — widen it (or drop it) or PHPStan
will flag the file you just improved.

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

An `unknown` field is build-breaking here, not just imprecise: Inertia constrains `useForm<T>` to
`FormDataType<T>`, which maps `unknown` to `never`, so a single `unknown` member — or a whole `@dynamic`
`Record<string, unknown>` request — fails with `TS2344 ... is not assignable to type 'never'`. Fix the rule in
PHP; do not reach for `as any` or a hand-written form interface.

For a partial form use `Pick<UpdateTaskRequest, 'title' | 'status'>`; for nested settings use
`NonNullable<UpdateTaskRequest['settings']>`. A field typed `File` maps to an `<input type="file">`; Inertia
switches the request to `FormData` on its own as soon as the payload holds a `File`, so `forceFormData` is
only for forcing multipart when it does not.

`$request->validated('settings.color')` in an Inertia action types the page prop from the same rules
(nested dotted keys included). A path with a `*` segment stays `unknown`. A key beneath a `#[TsCasts]`-overridden
ancestor is the one place the two disagree: the prop still composes from the underlying rules while the
interface shows the override, so read the overridden parent instead.

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

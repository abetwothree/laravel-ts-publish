# Inertia

With `inertia.enabled` (default `true`) the package types both halves of an Inertia page: the shared props
from `HandleInertiaRequests::share()` become the global `Inertia.SharedData` type plus an `@inertiajs/core`
module augmentation, and each `Inertia::render()` in a controller action becomes a `{Action}PageProps` type
attached to that action's route helper. Page components therefore never need a hand-written props type.

**Gate:** `config('ts-publish.inertia.enabled')`; page props also need `routes.enabled`. Shared data needs
an `Inertia\Middleware` subclass under `app/` (or `inertia.inertia_middleware_path`).

## Shared data (`inertia-config.d.ts`)

```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => ['user' => $request->user()],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state'),
            'role' => EnumResource::make($request->user()?->role ?? Role::Guest),
        ];
    }
}
```

```ts
import { type AsEnum } from '@tolki/ts';
import { Role } from './app/enums';
import type { User } from './app/models';

declare global {
    namespace Inertia {
        type SharedData = { appName: string, auth: { user: User | null }, sidebarOpen: boolean, role: AsEnum<typeof Role> };
    }
}
declare module '@inertiajs/core' {
    export interface InertiaConfig { sharedPageProps: { ... same ... }; errorValueType: string[]; }
}
export {};
```

- `$request->user()`, `auth()->user()`, `Auth::user()` type through `auth.defaults.guard` -> provider ->
  model, so `User | null` with the import written for you.
- `config('literal.key')` types from the live config value; `$request->url()/path()/integer()/boolean()/string()/hasCookie()`
  type from Laravel's signatures. `$request->cookie('x')` is the one that declines — Laravel declares
  `@return string|array|null`, too vague to accept — so it types `unknown`; override that key with `#[TsCasts]`.
  `Inertia::defer()/optional()/lazy()` make the key optional; `always()/merge()/deepMerge()`
  pass the wrapped type through.
- `errors` is left to `@inertiajs/core`; `errorValueType: string[]` is added when `$withAllErrors = true`.
- Anything the analyzer cannot read (`$request->session()->get()`, an opaque method call) is `unknown`.
  Fix it with a `@return array{flash: array{success: string|null}}` docblock on `share()` or
  `#[TsCasts(['flash' => '{ success: string | null }'])]` on the class.
- `usePage().props` is typed through the augmentation; `usePage<{ extra: string }>()` still works for
  page-specific additions, but prefer the route's page-props type below.

## Page props (`{Action}PageProps` on the route)

```php
public function edit(Task $task): Response
{
    $task->load('assignee');

    return Inertia::render('Tasks/Edit', [
        'task' => $task,
        'assignees' => User::query()->orderBy('name')->get(),
        'stats' => Inertia::defer(fn () => $this->stats->for($task)),
    ]);
}
```

```ts
export type EditPageProps = Inertia.SharedData & { task: Task, assignees: User[], stats?: unknown };
export const edit = annotatePageProps<EditPageProps>()(defineRoute({ ..., component: 'Tasks/Edit' }));
```

Read it in the page component:

```ts
// React / Svelte / plain TS: the helper type is fine anywhere TypeScript alone evaluates it.
import type { InferPageProps } from '@tolki/ts';
import { edit } from '@data/app/http/controllers/task-controller';
export default function Edit(props: InferPageProps<typeof edit>) {}

// Vue <script setup>: defineProps<T>() is expanded by @vue/compiler-sfc to emit runtime props, and that
// compiler cannot resolve conditional types (InferPageProps) or the global Inertia.SharedData that every
// {Action}PageProps intersects. vue-tsc passes but `vite build` / `vite dev` fail with
// "Unresolvable type reference or unsupported built-in utility type". Give it a type literal whose
// members index into the generated type:
import type { EditPageProps } from '@data/app/http/controllers/task-controller';
const props = defineProps<{ task: EditPageProps['task']; assignees: EditPageProps['assignees'] }>();
```

`Pick<EditPageProps, ...>`, `Omit<EditPageProps, keyof Inertia.SharedData>`, and `EditPageProps` by name
fail the same way in `defineProps`; a literal with `EditPageProps['key']` members (or the model interfaces
themselves, `{ task: Task; assignees: User[] }`) compiles, and `vue-tsc` still errors when the PHP side
drops a key. Everywhere outside `defineProps` (a `computed`, a store, a `.ts` helper) use
`InferPageProps<typeof edit>` or `EditPageProps` directly. Both include `Inertia.SharedData`.

What the analyzer resolves without annotations:

| Expression in the props array                                                  | Type                                                                                              |
| ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------- |
| a route-bound model parameter (`$task`)                                        | `Task`                                                                                            |
| `Model::find()/first()/firstWhere()`                                           | `Model \| null`                                                                                   |
| `findOrFail()/sole()/create()/firstOrCreate()`                                 | `Model`                                                                                           |
| `all()`, `->get()` on a query chain rooted at the model                        | `Model[]`                                                                                         |
| `->paginate()/simplePaginate()/cursorPaginate()`                               | `LengthAwarePaginator<Model>` etc.                                                                |
| `->count()`, `->exists()`                                                      | `number`, `boolean`                                                                               |
| `SomeResource::make($x)`, `::collection($x)`, `new SomeCollection($paginator)` | the resource interface(s), paginated when wrapped                                                 |
| `EnumResource::make($x)`, an enum case, or an enum-typed value                 | `{Enum}Type` — page props are **not** rewritten to `AsEnum<>`; only shared data and resources are |
| `$request->user()`, typed `Request` reads, `$request->validated('key')`        | as in shared data / from the form request rules                                                   |
| `compact('a', 'b')`, `array_merge($base, [...])`, a ternary-assigned array     | read as the literal they stand for                                                                |
| `Inertia::defer()/optional()/lazy()`                                           | wrapped type, key optional                                                                        |
| an Inertia UI Table (`SomeTable::make()`)                                      | `TableResource<Model>` from `@inertiaui/table-vue` or `-react`                                    |
| two renders of the same component                                              | merged; keys only one branch sets are optional                                                    |
| conditional renders of different components                                    | `component: { a: 'X', b: 'Y' }` and a union of page-props types                                   |

A paginated or collection prop is **never a bare array** — iterate `props.tasks.data`. `paginate()` gives
`LengthAwarePaginator<Task>` (`data` plus `current_page`/`last_page`/`total`/`links` at the top level),
`TaskResource::collection($paginator)` gives `JsonResourcePaginator<TaskResource>` (`data` + `meta` + `links`), an
unpaginated `::collection()` gives `AnonymousResourceCollection<TaskResource>` (`{ data }` only), and a named
collection gives `TaskCollection & ResourcePagination`. Page numbers live at the top level for the Eloquent
paginators and under `meta` for the resource ones.

An action that renders **different components** in different branches publishes no `{Action}PageProps` at all —
the exports are `{Action}{Variant}PageProps` (`ConditionalAuthenticatedPageProps`, `ConditionalGuestPageProps`),
and `InferPageProps<typeof conditional>` is their union, so narrow it or import the one variant the component
actually receives.

What degrades to `unknown` (never fails the run):

- A method chained on the bound model inline: `'task' => $task->load('assignee')`. Call `load()` on its
  own line and pass `$task`.
- A service/repository call the analyzer cannot reflect: the receiver is not a `$this->` property declared with a
  concrete class, or the method has no return type and no `@return array{...}`. A concrete-class property whose
  method declares a real type or an `array{...}` docblock **does** resolve, and so does a closure whose body it
  can read.
- A variable assigned inside a branch rather than at the top level of the action, or written more than once.
- A query builder assigned to a variable before `paginate()` (`$q = Post::query(); $q->paginate()`).
- A key made optional by `Inertia::defer()` is genuinely **missing** from the first response, so render it inside
  `<Deferred data="stats">` with a fallback rather than reading `props.stats`, and never silence the `?` with `!`.
  `optional()` / `lazy()` keys never arrive on their own: request them with `router.reload({ only: ['tally'] })`.
- Anything the props table above does not list.

Eager-loaded relations are not reflected: `Task::with('assignee')->get()` is `Task[]`, not
`(Task & Pick<TaskRelations, 'assignee'>)[]`. Widen a prop on the action when that matters:

Composing in the component is the normal answer when only some relations are loaded
(`defineProps<{ tasks: (Task & Pick<TaskRelations, 'assignee'>)[] }>()`) — that is rule 2's `Pick`/`&` composition,
not a hand-written type. Reserve the `#[TsCasts]` route for when the payload really is the whole `TaskAll`, since
its `type` must be a single identifier plus an `import`, which means exporting an alias from a `.ts` file of your
own — and `TaskAll` claims relations and counts that were never loaded.

```php
#[TsCasts(['tasks' => ['type' => 'TaskAll[]', 'import' => '@data/app/models']])]
public function index(): Response
```

`#[TsCasts]` on the action replaces the listed keys in `{Action}PageProps`; a `type` with a single
identifier and an `import` path gets its `import type` line written. For a composed type, export an alias
from a `.ts` file of your own and import that. `#[TsExclude]` on the action removes the route and its
props entirely.

## Frontend patterns

```ts
import { usePage, router, useForm, Link } from '@inertiajs/vue3';
import type { InferPageProps } from '@tolki/ts';
import { TaskController } from '@data/app/http/controllers';
import { index, update } from '@data/app/http/controllers/task-controller';
import type { IndexPageProps } from '@data/app/http/controllers/task-controller';

const props = defineProps<{ tasks: IndexPageProps['tasks']; showCompleted: IndexPageProps['showCompleted'] }>();  // Vue: see above
const page = usePage();                     // page.props.auth.user is User | null, page.props.appName is string

router.visit(TaskController.index({ completed: true }), { preserveState: true });   // { url, method } is accepted as-is
router.reload({ only: ['tasks'] });

const form = useForm<InferRequestPayload<typeof update>>({ ... });
form.submit(update(props.task.id));         // verb comes from the route; form.put(update.url(props.task.id)) also works
```

- Filters that round-trip through the query string: build the URL with the route helper
  (`index({ completed: true })`), read the value back from the controller (`$request->boolean('completed')`)
  and pass it as a prop so the checkbox state is typed.
- `component` on a route is the page name (`'Tasks/Edit'`); `route.withComponent(...)` tags a call result
  with it for logging or manual resolution.

## Republishing

Shared data regenerates on a full `php artisan ts:publish`; a changed action needs
`php artisan ts:publish --source="App\Http\Controllers\TaskController"`. The output directory for
`inertia-config.d.ts` follows `inertia.output_directory`, then `routes.output_directory`, then the global one.

## Common mistakes

- `defineProps<{ task: any }>()` or a hand-written `interface Props` for a page that has a generated
  `{Action}PageProps`.
- `defineProps<InferPageProps<typeof edit>>()` in a `.vue` file: type-checks, then breaks the Vite build.
- Passing `$task->load(...)` inline and then typing the prop `unknown` on the frontend instead of fixing the PHP.
- Casting `usePage().props as ...`; the augmentation already types it.
- Keeping a hand-written `SharedData` in `resources/js/types/index.d.ts` and importing it instead of the generated
  global `Inertia.SharedData`. It does not collide (different scopes, no error) — it just goes stale silently. What
  does conflict is a hand-written `declare module '@inertiajs/core'` setting `sharedPageProps`: it merges with the
  generated augmentation and the hand-written entry wins, so `usePage().props` keeps the old shape.

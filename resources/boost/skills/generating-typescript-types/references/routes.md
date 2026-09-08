# Routes

Every publishable controller action becomes a `defineRoute()` helper: a callable that builds the URL, knows
its HTTP verbs, binds models and enums, appends query strings, spoofs form methods, and (with Inertia and
form requests enabled) carries the page-props and request-payload types. It is the Wayfinder equivalent for
this package; there is no `route()` helper, no Ziggy, and no reason to hand-write a `/tasks/${id}` string.

**Gate:** `config('ts-publish.routes.enabled')` must be `true` (default). Check `routes.only`, `routes.except`,
`routes.exclude_middleware` and `routes.only_named` too: a route filtered out by those has no helper. The
`@tolki/ts` npm package must be installed; route files import `defineRoute` from it at runtime.

## What gets generated

One file per controller at the namespace-derived path: `App\Http\Controllers\TaskController` ->
`app/http/controllers/task-controller.ts`. Each action is a named export (cased by `routes.method_casing`,
`camel` by default) and the controller is the default export:

```ts
import { defineRoute, annotatePageProps, annotateRequestPayload } from '@tolki/ts';

import type { Task, Team, User } from '../../models';
import type { UpdateTaskRequest } from '../requests/update-task-request';

export type EditPageProps = Inertia.SharedData & { task: Task, assignees: User[] };

export const edit = annotatePageProps<EditPageProps>()(defineRoute({
    name: 'tasks.edit',
    url: '/tasks/{task}/edit',
    methods: ['get', 'head'] as const,
    args: [{name: 'task', required: true, _routeKey: 'id'}] as const,
    component: 'Tasks/Edit',
}));

export const update = annotateRequestPayload<UpdateTaskRequest>()(defineRoute({
    name: 'tasks.update',
    url: '/tasks/{task}',
    methods: ['put', 'patch'] as const,
    args: [{name: 'task', required: true, _routeKey: 'id'}] as const,
}));

export const destroy = defineRoute({
    name: 'tasks.destroy',
    url: '/tasks/{task}',
    methods: ['delete'] as const,
    args: [{name: 'task', required: true, _routeKey: 'id'}] as const,
});

/** @see App\Http\Controllers\TaskController */
const TaskController = { index, create, store, edit, update, destroy };
export default TaskController;
```

- Exports are named after the **controller method**, not the route name. A method whose cased name is a
  reserved word (`delete`) is suffixed `Method` (`deleteMethod`).
- Every `GET` route also lists `head`. Several routes to one method collapse into one export; a named one wins.
- An invokable controller's default export is the action itself: `NamedInvokableController()`. Extra public
  actions hang off it (`Invokable.extra(...)`).
- The controllers barrel re-exports **default exports only**: `export { default as TaskController } from './task-controller'`.
  Named actions collide (`index`, `show`) so they are not re-exported from the barrel.
- Framework and vendor controllers that have routes (`Illuminate\Routing\RedirectController` for
  `Route::redirect()`, Inertia's dev tools, Passport, Fortify) are published too, under `illuminate/...`,
  `inertia/...`, etc. Filter them with `routes.only` / `routes.except` / `routes.exclude_middleware` when
  they are noise.
- Inertia page props (`annotatePageProps`) and form-request payloads (`annotateRequestPayload`) are attached
  automatically when those phases are enabled; you never call those helpers yourself.

## Importing

```ts
// Whole controller (default export) through the barrel or the file
import { TaskController } from '@data/app/http/controllers';
import TaskController from '@data/app/http/controllers/task-controller';

// Individual actions (tree-shakeable), aliased when two controllers share names
import { update, destroy } from '@data/app/http/controllers/task-controller';
import { index as teamsIndex } from '@data/app/http/controllers/team-controller';

// Type helpers
import type { InferPageProps, InferRequestPayload } from '@tolki/ts';
```

## Calling a route

Every action is a callable returning `{ url, method, toString() }` for the route's first declared verb.

```ts
TaskController.edit({ task: task.id });     // named
TaskController.edit(task.id);               // positional
TaskController.edit([task.id]);             // positional array
TaskController.edit(task.id).url;           // '/tasks/42/edit'
TaskController.edit.url(task.id);           // just the string
`${TaskController.edit(task.id)}`;          // toString() gives the URL in a template literal
String(TaskController.index);               // toString() on the route itself: URL with no params

TaskController.update.put(task.id);         // { url: '/tasks/42', method: 'put' }
TaskController.update.patch(task.id);       // one method per declared verb
TaskController.destroy.delete(task.id);
TaskController.index.get();

TaskController.edit.definition;             // the raw metadata object
```

Multi-parameter routes take the same forms: `UserPostController.show({ user: user.id, post: post.id })`,
`show(2, 42)`, `show([2, 42])`.

**Pass the key, not the generated model interface.** The runtime accepts a whole object and reads its
`id` / route key, but the arg types in `@tolki/types` require an index signature
(`{ id } & Record<string, unknown>`), which a generated **interface** such as `Task` does not have, so
`edit(task)`, `edit({ task })`, `edit.url(task)` and `edit.url([task])` fail `tsc`/`vue-tsc` with
TS2769. `edit(task.id)`, `edit({ task: task.id })`, and `edit({ ...task })` all type-check. Prefer the
key; it is also what the URL needs.

### Model binding

A model-typed parameter gets `_routeKey` (from `getRouteKeyName()`, `$primaryKey`, or Laravel 13's
`#[RouteKey('slug')]`). Pass the value of that key (`post.slug`); at runtime an object is also read via
`value[_routeKey]` then `value.id`, with a clear error when neither exists. No model type is imported for
a binding.

### Enum binding

A backed-enum parameter gets `_enumValues: ['low', 'medium', 'high']`. Pass the raw value, the case
constant (`TaskPriority.High`), or a resolved instance (`TaskPriority.from('high')`; the helper reads `.value`).

### Optional parameters, `where`, domains

- `{param?}` becomes `required: false`; omit it and the segment disappears with no double slashes.
- `->where('id', '[0-9]+')` is carried as `where` and validated at call time; a mismatch throws
  `Route error: 'id' parameter 'abc' does not match required format '[0-9]+'.`
- Domain routes compile to protocol-relative URLs: `DomainController.index()` -> `'//api.example.com/domain'`.
- Required parameter missing: throws `Route error: 'task' parameter is required.`

### Query strings

Keys that are not route parameters become query parameters. Booleans encode as `0`/`1`; arrays as
`tags[0]=a&tags[1]=b`; nested objects as `filter[status]=done`; `null`/`undefined` are skipped.

```ts
TaskController.index({ completed: true, page: 2 });         // '/tasks?completed=1&page=2'
TaskController.edit(task.id, { tab: 'history' });           // trailing options object on any calling form
TaskController.edit({ task: task.id, tab: 'history' });     // extra keys in the named object
TaskController.index({ _query: { task: 'x' } });            // _query: escape hatch when a key collides with a param name
TaskController.index({ mergeQuery: { page: 1 } });          // start from window.location.search, set/replace page; null removes a key
```

The trailing object holds the query keys directly; there is no `query:` wrapper. A trailing argument is
treated as options only when you pass more arguments than the route declares and it contains none of the
parameter names.

### Route defaults

`setRouteDefaults({ locale: 'en' })`, `addRouteDefault('locale', 'fr')`, `getRouteDefaults()`,
`resetRouteDefaults()` from `@tolki/ts` mirror `URL::defaults()`; a missing parameter with a default is
filled in automatically, and a caller-provided value wins.

## Forms

`.form(...)` returns `{ action, method, toString() }` for an HTML `<form>`. `method` is always `'get'` or
`'post'`; for PUT/PATCH/DELETE/HEAD routes the helper appends `_method=PUT` (etc.) to the action, matching
Wayfinder and Laravel's method spoofing.

```ts
TaskController.store.form();                     // { action: '/tasks', method: 'post' }
TaskController.update.form(task.id);             // { action: '/tasks/42?_method=PUT', method: 'post' }
TaskController.update.form.patch(task.id);       // choose a non-primary verb explicitly
TaskController.destroy.form(task.id);            // { action: '/tasks/42?_method=DELETE', method: 'post' }
```

```vue
<form v-bind="TaskController.update.form(task.id)">   <!-- Vue: spreads action + method -->
<Form {...TaskController.update.form(task.id)}>       <!-- React / Inertia <Form> -->
```

## With Inertia (`router`, `useForm`, `<Link>`)

The call result is `{ url, method }`, which Inertia v2/v3 accepts directly (`UrlMethodPair`) in
`router.visit/get/post/put/patch/delete`, `<Link href>`, `<Form action>`, and `useForm().submit()`:

```ts
import { router, useForm } from '@inertiajs/vue3';   // or @inertiajs/react / @inertiajs/svelte
import type { InferRequestPayload } from '@tolki/ts';
import { TaskController } from '@data/app/http/controllers';
import { update } from '@data/app/http/controllers/task-controller';

const form = useForm<InferRequestPayload<typeof update>>({ title: task.title, status: task.status });

form.submit(TaskController.update(task.id), { preserveScroll: true });   // uses the route's verb (put)
form.submit(TaskController.update.patch(task.id));                       // pick a specific verb
form.put(TaskController.update.url(task.id));                            // string form also works

router.visit(TaskController.index({ completed: true }));
router.delete(TaskController.destroy(task.id), { onSuccess: () => router.visit(TaskController.index()) });
```

```vue
<Link :href="TaskController.edit(task.id)">Edit</Link>
<Link :href="TaskController.destroy(task.id)" as="button">Delete</Link>   <!-- method comes from the pair -->
<Form :action="TaskController.update(task.id)">...</Form>                   <!-- Inertia <Form> component -->
```

On Inertia 1.x, which has no `UrlMethodPair`, pass `.url` / `.url()` and set the method yourself.

`InferRequestPayload<typeof update>` is the generated form-request interface (see
[form-requests.md](form-requests.md)); `InferPageProps<typeof edit>` is the page-props type (see
[inertia.md](inertia.md)). Both are `never` when the action has no request/render to read, which is the
signal to check the controller signature rather than to hand-write the type.

For `fetch`/axios: `axios.put(TaskController.update.url(task.id), payload)`; the `method` is on the call
result if you want to pass both (`const { url, method } = TaskController.update(task.id)`).

## Backend side: what makes a good route helper

- Type-hint models and backed enums in the action signature so bindings get `_routeKey` / `_enumValues`.
- Type-hint a `FormRequest` in `store`/`update` so the helper carries the payload type.
- Name routes (`->name('tasks.update')`); unnamed routes still publish unless `routes.only_named` is on,
  but the name is what `routes.only`/`except` patterns match (`'tasks.*'`, `'!tasks.destroy'`).
- One public method per action; a method you do not want published gets `#[TsExclude]`; a whole controller
  gets it on the class.
- After editing a controller, its form requests, or `routes/*.php`, republish: `php artisan ts:publish --source="App\Http\Controllers\TaskController"`
  refreshes that file; a route added to a **new** controller needs a full `php artisan ts:publish` so the
  barrel picks it up. Route definitions are part of the cache fingerprint, so a URI/verb/name change
  republishes on the next full run without `--fresh`.

## Common mistakes

- Hardcoding `'/tasks'` or `` `/tasks/${task.id}` `` in a `<Link>`, `router.visit`, `fetch`, or `useForm` call.
- Calling `route('tasks.update', task)` (Ziggy) or importing from `@/actions` or `@/routes` (Wayfinder);
  neither exists in an app using this package.
- Importing an action by name from the barrel (`import { update } from '@data/app/http/controllers'`);
  the barrel only exports controllers. Import the controller, or the action from the controller file.
- Passing `{ query: {...} }` as the trailing options; keys go directly in the object.
- Passing a whole `Task` interface value as the argument and then adding `as any` to silence TS2769;
  pass `task.id`.
- Hand-writing a request payload type for `useForm` instead of `InferRequestPayload<typeof action>` or
  the request interface from `@data/app/http/requests`.
- Assuming a helper exists for a route the config filters out; check `routes.*` and the generated file.

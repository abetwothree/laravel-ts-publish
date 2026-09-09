# Model metadata (`{model}_meta.ts`)

A model interface is type-only: it describes a payload and disappears at compile time. A **metadata
companion** is the opposite. It is a real runtime module published beside the interface, holding values the
backend owns, so the frontend can read a PHP-side fact at runtime instead of hard-coding it. The default
payload is the model's morph class, which is what makes a polymorphic form field possible without typing
`'App\Models\Task'` into a `.ts` file.

**Gate:** `config('ts-publish.model_metadata.enabled')` — default **`false`**, and independent of
`models.enabled`. `--only-models` never publishes companions; `--only-model-metadata` publishes only
companions.

**When the phase is off, which is the default:**

- If the task is about something else and you merely noticed a hardcoded class name, do not enable the
  phase. Follow the app's existing convention and say that enabling `model_metadata` would generate the
  value properly.
- If the task **is** to publish a backend fact to the frontend at runtime (a morph class, a table name, a
  route key, a per-model flag), enabling the phase is part of that task, not a side effect of it. Turn it
  on, name the change in your summary, and carry on. There is no way to set up a provider with the phase
  off, and refusing here produces the hand-written TypeScript lookup table this package exists to delete.

## What gets generated

One file per model, beside the interface, plus an entry in the same barrel:

```ts
// resources/js/types/data/app/models/task_meta.ts
export const TaskModelMetadata = {
    morphClass: 'App\\Models\\Task',
} as const satisfies {
    morphClass: string;
};
```

- Export name is `{Model}ModelMetadata`; the file is `{kebab-model}_meta.ts`. `Str::kebab()` never emits an
  underscore, so in practice a companion does not collide with an interface file (`PostMeta` → `post-meta.ts`,
  while `Post`'s companion is `post_meta.ts`).
- The companion shares the model's barrel, so both come from one import path:
  `import { TaskModelMetadata } from '@data/app/models';`.
- `morphClass` is whatever `getMorphClass()` returns: the FQCN, or the alias once the app registers a morph
  map (`Relation::morphMap(['task' => Task::class])` publishes `'task'`). A model absent from the map keeps
  its FQCN.
- The companion set follows the **model** finder settings unless a `model_metadata` key overrides them, so a
  model reached through `models.additional_directories` (`Illuminate\Notifications\DatabaseNotification`, for
  example) gets a companion too. A provider is handed every published model, including framework ones it
  knows nothing about — see [Writing a provider](#writing-a-provider).

## Using a companion on the frontend

```ts
import { TaskModelMetadata, UserModelMetadata } from '@data/app/models';

form.commentable_type = TaskModelMetadata.morphClass;   // no PHP class name in the .ts file
```

**Pair the morph class with the validation rule so the compiler checks both halves.** Derive the rule from the
same source the companion reads, and the generated request field becomes a union of exactly those literals:

```php
// StoreCommentRequest::rules()
'commentable_type' => ['required', Rule::in([(new Task)->getMorphClass(), (new Team)->getMorphClass()])],
```

```ts
// generated: commentable_type: 'App\\Models\\Task' | 'App\\Models\\Team'
const form = useForm<StoreCommentRequest>({
    commentable_type: TaskModelMetadata.morphClass,   // assignable, and checked
    commentable_id: props.task.id,
    body: '',
});
```

Now removing `Task` from the rule stops the frontend compiling. Writing the rule as
`Rule::in([Task::class, Team::class])` looks equivalent and is not: it emits the FQCN while a registered morph
map makes the companion emit `'task'`, and the assignment fails with `TS2322` in exactly the scenario this phase
exists for. Go through `getMorphClass()` on both sides.

**Every value is a literal type, not its widened type.** `as const` is what keeps the values precise, and
the `satisfies` clause validates without widening. So a `bool` key returning `false` has the TypeScript type
`false`, and `morphClass` has the type `'App\\Models\\Task'`, not `string`. This is a feature for
`morphClass` and a trap everywhere else, because the failure is a compile error on code that reads correct:

```ts
if (TaskModelMetadata.softDeletes === true) { }   // TS2367: types 'false' and 'true' have no overlap
const morphs = [TaskModelMetadata.morphClass, UserModelMetadata.morphClass];
morphs.includes(someString);                      // TS2345: string is not assignable to '"App\\Models\\Task" | ...'
```

Neither `#[TsCasts]` nor a docblock fixes this: both change only the `satisfies` clause while the value stays
literal. Nor does a widened local, because TypeScript narrows on assignment
(`let f: boolean = meta.softDeletes; f === true` still fails). Use the value instead of comparing it:

```ts
if (TaskModelMetadata.softDeletes) { }                       // truthiness
if (!TaskModelMetadata.softDeletes) { }                      // negation
takesBoolean(TaskModelMetadata.softDeletes);                 // passing it where a boolean is expected
const morphs: string[] = [TaskModelMetadata.morphClass];     // widen at the declaration, not the use
export const isTask = (v: string) => v === TaskModelMetadata.morphClass;   // compare against the literal
```

Using a published key name to **index** another object is the one case none of those cover, and it fails with a
different code:

```ts
record[meta.routeKeyName];   // TS7053: expression of type '"id"' can't be used to index type ...
```

That value is being used correctly, so route it through a `keyof` helper rather than a cast:

```ts
const valueAt = <T, K extends keyof T>(row: T, key: K): T[K] => row[key];
valueAt(record, meta.routeKeyName);
```

Reaching for `as string`, `as boolean` or `as any` when *comparing* a value means it is being used the wrong way
round.

## Writing a provider

The default provider publishes `morphClass` and nothing else. To publish more, implement the contract and
point the config at your class. **Replacing the provider replaces the whole payload**, so re-return
`morphClass` yourself if you still want it.

```php
<?php

declare(strict_types=1);

namespace App\TypeScript;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AppModelMetadataProvider implements ModelMetadataProvider
{
    public function provide(Model $model): array
    {
        return [
            'morphClass' => (string) $model->getMorphClass(),
            'identifiers' => [
                'primaryKey' => $model->getKeyName(),
                'routeKey' => $model->getRouteKeyName(),
            ],
            'softDeletes' => in_array(SoftDeletes::class, class_uses_recursive($model), true),
        ];
    }
}
```

```php
// config/ts-publish.php
'model_metadata' => [
    'enabled' => true,
    'provider_class' => App\TypeScript\AppModelMetadataProvider::class,
],
```

- `ModelMetadataProvider` is an **interface** with one method, `provide(Model $model): array`. You
  `implements` it. The packaged config comment says "extend" the contract; following that literally is a
  parse-time fatal (`Class cannot extend interface`).
- Keys must be strings. One provider serves every published model.
- `class_uses_recursive()` above is a Laravel global helper, so only `SoftDeletes` needs a `use` statement.
- It is built by the container, with no parameters passed, so an autowireable constructor dependency (a
  concrete type-hint, or an interface the app binds) is injected. A scalar, array, union, or unbound interface
  throws `BindingResolutionException` from validation that runs **before any model is processed**, which aborts
  the whole `ts:publish` run, not just this phase. The provider is re-resolved per use; bind it as a singleton
  if construction is expensive.
- **Write it to survive every model it will be handed**, not just your own. It runs against framework models
  pulled in through `models.additional_directories`, so a call like `$model->yourAppSpecificMethod()` throws
  for those and costs you a failed companion. Guard with `instanceof` / `method_exists()`, or narrow the set
  with `model_metadata.included` / `excluded`. Setting either one **replaces** the inherited `models.*` list
  rather than adding to it — even when set to `[]` — so restate the `models.excluded` entries you still want
  honoured, or those models start getting companions.
- The provider's file is a cache dependency and a watched path, so a **full** `ts:publish` after editing it
  regenerates every companion. It is not a valid `--source` target, so the per-file save path the Vite plugin
  uses in dev fails with `Class is not a publishable enum, model, resource, controller, form request, or
  broadcast event`. Re-run the full command after editing a provider.

## Typing the payload

Every returned key must end up with a type. **There is no `unknown` fallback here** — an untyped key fails
the model. For each key the type is the first of:

| Precedence                            | Use it for                                                                     |
| ------------------------------------- | ------------------------------------------------------------------------------ |
| 1. `#[TsCasts]` on `provide()`        | Pointing a key at a type from your own `.ts` file (the only way to add an import) |
| 2. `@return array{...}` on `provide()` | Pinning the contract so PHPStan checks it and inference cannot drift            |
| 3. Body inference over `provide()`     | Everything else, which in practice is most of it                                |

**Start with no annotation and add one only where the publish fails.** Inference is stronger than it looks
and already resolves, with no docblock at all:

```php
'morphClass'   => (string) $model->getMorphClass(),   // string  (casts, and Laravel's own @return docblocks)
'table'        => $model->getTable(),                 // string
'routeKeyName' => $model->getRouteKeyName(),          // string
'perPage'      => $model->getPerPage(),               // number
'softDeletes'  => in_array($t, class_uses_recursive($model), true),   // boolean
'identifiers'  => ['primaryKey' => $model->getKeyName()],             // { primaryKey: string }  (nested shapes work)
```

Nested `array{...}` docblock shapes work too and become inline object literals; `array<string, T>` becomes
`Record<string, T>` and `list<T>` becomes `T[]`.

What inference does **not** resolve, so these need tier 1 or 2:

- **A helper with no declared return type.** `'x' => $this->helper($model)` on `private function helper($model)`
  infers nothing. Adding the return type to the helper is usually the better fix than annotating the key.
- **An attribute read on the parameter.** `$model->status` is not typed — the parameter is bound to the
  abstract `Model`, which has no schema. A cast works (`(string) $model->status` is `string`).
- **An enum case written inline.** `'visibility' => Visibility::Public` fails outright. Route it through a
  helper with a declared enum return type instead, and the import is written for you:

  ```php
  'visibility' => $this->visibilityFor($model),          // -> visibility: VisibilityType
  private function visibilityFor(Model $model): Visibility { ... }
  ```

  ```ts
  import type { VisibilityType } from '../enums';
  ```

- **A class or enum named only in a docblock**, which carries no import path, and **any model-typed value**,
  which the generator refuses to import. Both need `#[TsCasts]` with an explicit `import`.

`#[TsCasts]` on `provide()` takes the same shape it takes everywhere else in the package:

```php
#[TsCasts(['branding' => ['type' => 'Branding', 'import' => '@/types/branding']])]
```

```ts
import type { Branding } from '@/types/branding';
```

The import may equally be a relative path to something this package generates (`'../enums'`, or `'.'` for a
sibling model interface). The declared type is never checked against the runtime value, so a wrong one publishes
green and then fails `vue-tsc` inside the generated `as const satisfies` block, in a file you must not edit.

Optionality decides whether the payload **has to** return the key; every key a payload does return is required
in that companion's `satisfies` shape. Spell it `key?:` in the docblock, or in the attribute as
`['type' => 'X', 'optional' => true]` — a `#[TsCasts]` entry always needs `type`, and
`['optional' => true]` alone is a fatal `Undefined array key "type"`. A cast entry that omits `optional`
forces the key **required** unless the docblock already spelled it `key?:`.

## Values you may return

`null`, scalars, arrays, enums (backed → value, pure → name), `stdClass`, and any `Arrayable` or
`JsonSerializable`. Everything else fails the model by design, naming the property path.

- **An integer must be JS-safe.** Outside ±(2^53 − 1) it is rejected with
  `property [bigId] exceeds JavaScript's safe integer range (±9007199254740991); return it as a string and
  declare the key as string`.
- Rejected: closures, resources, other objects, non-finite floats, cycles, and nesting deeper than 64.
- **Empty containers.** PHP cannot tell `[]` from `{}`. A bare `[]` is emitted as `{}` only where the
  resolved type is object-like (an object literal, an index signature, or `Record<...>`), and as `[]`
  otherwise — including under an imported `#[TsCasts]` alias, which cannot be inspected. With no declared
  type at all, `[]` infers as `never[]`, which nothing can ever be added to. Return `(object) []` when you
  mean an empty object. One package bug to know: the object-like test matches any type that merely *starts*
  with `Record<`, so an empty value typed `Record<string, number>[]` is emitted as `{}` and the generated file
  then fails `tsc`. Do not return an empty array for an array-of-`Record` key.

## When a publish fails

The three messages you will actually see, all prefixed `Model metadata for model [App\Models\Task]`:

| Message                                                     | Cause                                                             |
| ----------------------------------------------------------- | ----------------------------------------------------------------- |
| `returned keys without inferred or declared types: [k]`     | Tier 3 could not type `k` and no docblock or `#[TsCasts]` covers it |
| `is missing required keys: [k]`                             | The docblock declares `k` without `?`, **or** a `#[TsCasts]` entry names `k` without `optional`, and the payload omitted it. A mistyped attribute key lands here rather than being ignored the way an unmatched `#[TsCasts]` key is on a model |
| `property [path] ...`                                       | A value was rejected (see above), named by its path                |

A failure is isolated per model: every other file is still written, the failing model keeps its **previous**
`{model}_meta.ts` and its barrel export as last known good, and the command exits **non-zero** (on stderr
under `--quiet`, so CI and the Vite plugin see it). A green tree with exit 1 is the intended outcome, so read
the exit code, not just the file list. A `--source` run is different rather than worse: it resolves one class, so a throw there just fails that run
with no failure record and no barrel, leaving a fresh interface beside a stale companion. It is the right way to
iterate on one model while a full run is red.

## Republishing

```bash
php artisan ts:publish --source="App\Models\Task"   # writes task.ts AND task_meta.ts
php artisan ts:publish --only-model-metadata        # companions only
php artisan ts:publish --only-functional            # companions yes, interfaces no (what vite build runs)
```

**The run that first enables the phase must be one that rewrites barrels.** Every companion is new, and
`--source` never writes a barrel, so importing `TaskModelMetadata` from `@data/app/models` after a `--source`
run fails with `TS2305: has no exported member`. A full `ts:publish` or `--only-model-metadata` both rewrite the
model barrels (the latter preserves the interface exports), so either works; `--source` alone does not.

A companion counts as **functional** output because it is a real module, which is why `--only-functional`
includes it and skips the interfaces. Changing the provider's payload republishes the affected companions
without `--fresh`: the generator hashes the payload, so registering a morph map is enough to update every
file it touches.

## Common mistakes

- Writing `'App\\Models\\Task'` (or a `Record<string, {table: string}>` lookup map) into a `.ts` file while
  the phase is available. That is the duplication this phase removes.
- Comparing a companion value with `=== true` / `=== false`, then silencing the resulting TS2367 with a cast.
  Use truthiness.
- Assuming an untyped key degrades to `unknown` like the rest of the package. It fails the model instead.
- Annotating everything up front. Publish first, annotate only the keys that fail.
- Replacing the provider and losing `morphClass` because the new payload never returned it.
- Importing `_meta` files when the phase is off. Disabling it prunes the barrel exports but leaves the old
  `_meta.ts` files on disk as orphans, so a deep import keeps compiling against a stale file.
- Publishing an enum the **enum phase does not publish**. The companion's import is written from the enum's
  namespace with no check that the file exists, so an enum hidden by `enums.excluded`, `#[TsExclude]`, or a
  directory outside `enums.additional_directories` produces a companion importing a module nothing wrote.
  Publish the enum, or give the key an explicit `#[TsCasts]` import.
- Assuming `#[TsExclude]` and `model_metadata.excluded` do the same thing. `#[TsExclude]` on the model drops
  the interface **and** the companion; `model_metadata.excluded` drops only the companion.

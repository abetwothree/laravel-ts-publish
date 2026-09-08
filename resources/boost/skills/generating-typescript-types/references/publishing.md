# Publishing, configuration, output layout, troubleshooting

## Find out what is enabled

Every feature is a phase with its own `enabled` key in `config/ts-publish.php`. The published copy in the
app (if present) wins; otherwise the package defaults apply (`vendor/abetwothree/laravel-ts-publish/config/ts-publish.php`).
Read the file, or ask the booted app so env overrides and merged defaults are included:

```bash
php artisan tinker --execute="dump(collect(['enums','models','model_metadata','resources','routes','form_requests','broadcast_channels','broadcast_events','inertia','vite_env','globals','json'])->mapWithKeys(fn (\$k) => [\$k => config(\"ts-publish.\$k.enabled\")])->all(), config('ts-publish.output_directory'))"
```

Defaults: everything `true` except `model_metadata`, `globals`, `json` (`false`). Also relevant:
`enums.use_tolki_package` (gates every `AsEnum<>`), `enums.auto_include_methods`, `models.exclude_hidden`,
`routes.only/except/exclude_middleware/only_named`, `*.included/excluded/additional_directories`.

A disabled phase has no output. Do not import from its directory, do not annotate PHP for it, and do not
flip the config on without asking; mention that enabling it would generate the thing you needed.

Config merging is **one level deep**, so the app's published `config/ts-publish.php` replaces the packaged block
for a phase outright: a key the app's copy predates is absent rather than defaulted, and a phase block missing
from the published file reads as disabled. Never infer a value from the package's own config file once the app
has published one — ask the booted app with the snippet above.

## Commands

```bash
php artisan ts:publish                                   # everything enabled, cached: only changed classes regenerate
php artisan ts:publish --source="App\Models\Task"        # one class (FQCN or path relative to base_path), bypasses cache, never rewrites barrels
php artisan ts:publish --source="app/Enums/Status.php"
php artisan ts:publish --fresh                           # ignore + rebuild the cache (no-op with --source / --preview)
php artisan ts:publish --preview=true                    # print to console, write nothing (bare --preview is ignored: it writes files)
php artisan ts:publish --only-enums                      # or --only-models --only-model-metadata --only-resources --only-routes
                                                         #    --only-form-requests --only-broadcast-channels --only-broadcast-events (one at a time)
php artisan ts:publish --only-functional                 # everything except model/resource interfaces (what vite build runs)
php artisan ts:publish -v                                # per-file tables; -q for exit code only
```

- `ts:publish` runs automatically after `php artisan migrate` unless `run_after_migrate` /
  `TS_PUBLISH_RUN_AFTER_MIGRATE=false`.
- The `@tolki/ts` Vite plugin runs `--source="<changed file>"` on save during `vite dev` and a full
  `--only-functional` run before `vite build`. It uses `child_process.exec`, so with Sail on the host set
  `command: './vendor/bin/sail artisan ts:publish'`.
- An `--only-*` flag for a phase disabled in config prompts interactively and is skipped silently in CI.
- A model-metadata provider that throws keeps that model's last companion and exits non-zero.
- Generated files import **across** phases (a route file imports its form request; a model imports `../enums`), so
  an `--only-*` run only holds together on a tree the other phases already populated. `--only-routes` on a clean
  checkout writes route files pointing at request files nothing wrote. Run a full `ts:publish` first in CI, or any
  time the output directory was deleted.

## Output layout

Root: `output_directory` (default `resources/js/types/data/`). Every class lands at its PHP namespace,
kebab-cased per segment, minus `namespace_strip_prefix`:

| PHP                                         | File                                                                         | Barrel                                                 |
| ------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------ |
| `App\Enums\TaskPriority`                    | `app/enums/task-priority.ts`                                                 | `app/enums/index.ts` (`export *`)                      |
| `App\Models\Task`                           | `app/models/task.ts` (+ `task_meta.ts` if metadata)                          | `app/models/index.ts` (`export *`)                     |
| `App\Http\Resources\TaskResource`           | `app/http/resources/task-resource.ts`                                        | `app/http/resources/index.ts`                          |
| `App\Http\Requests\StoreTaskRequest`        | `app/http/requests/store-task-request.ts`                                    | `app/http/requests/index.ts`                           |
| `App\Http\Controllers\TaskController`       | `app/http/controllers/task-controller.ts`                                    | `app/http/controllers/index.ts` (default exports only) |
| `App\Events\TaskCompleted`                  | `app/events/TaskCompleted.ts` (PascalCase kept)                              | `app/events/index.ts`                                  |
| `Modules\Billing\Models\Invoice`            | `modules/billing/models/invoice.ts`                                          | per directory                                          |
| channels / events index / echo augmentation | `broadcast-channels.ts`, `broadcast-events.ts`, `echo-broadcast-events.d.ts` | none                                                   |
| Inertia shared data / Vite env              | `inertia-config.d.ts`, `vite-env.d.ts`                                       | none                                                   |
| watcher manifest                            | `laravel-ts-collected-files.json`                                            | none                                                   |

Imports use the app's alias for that directory. Look in `tsconfig.json` `paths` and `vite.config.*`
`resolve.alias` (`@data/*` in the Tolki docs, sometimes `@js/types/data/*`); if there is none, use a
relative path. Generated files import each other relatively, so aliases are only for app code.

Barrels are rebuilt for every namespace a run publishes into and are never touched by `--source`. The
directory is normally gitignored and regenerated in CI/deploy (`post-update-cmd`), and excluded from lint.

## Cross-cutting attributes (all in `AbeTwoThree\LaravelTsPublish\Attributes`)

| Attribute                                                            | Targets                                                                                                                                                 | Notes                                                                         |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `#[TsExclude]`                                                       | enum/model/resource/request/event/controller class; enum method; model accessor or relation; controller action                                          | Always wins; class-level removes it from barrels too                          |
| `#[TsCasts([...])]`                                                  | model `casts()`/`$casts`/class, resource class or trait method, request class, event class, Inertia middleware, controller action, metadata `provide()` | `'key' => 'TsType'` or `['type' => ..., 'import' => ..., 'optional' => true]` |
| `#[TsType(...)]`                                                     | custom cast class                                                                                                                                       | Type used wherever the cast is applied                                        |
| `#[TsExtends('X', import: '@/types/x', types: ['X'])]`               | model/resource/request/event class, parents, traits                                                                                                     | Repeatable; `ts_extends.*` config does it globally                            |
| `#[TsEnum]`, `#[TsCase]`, `#[TsEnumMethod]`, `#[TsEnumStaticMethod]` | enums                                                                                                                                                   | See enums.md                                                                  |
| `#[TsResource(...)]`, `#[UseResource]` (Laravel)                     | resources / models                                                                                                                                      | See api-resources.md                                                          |

Casing: `models.relationship_case` (relations, default snake), `enums.method_case` (default camel),
`routes.method_casing` (default camel). `timestamps_as_date` swaps `string` for `Date` on date casts.
`custom_ts_mappings` overrides DB/cast types globally.

## Verify after every publish

Green output is not proof. Open the regenerated `.ts` and read the members you care about:

```bash
sed -n 1,60p resources/js/types/data/app/models/task.ts
grep -n "unknown" resources/js/types/data/app/models/task.ts resources/js/types/data/app/http/controllers/task-controller.ts
```

Then type-check the frontend (`vue-tsc --noEmit`, `tsc --noEmit`, or the project's script). A property
that came out `unknown`, `unknown[]`, `object`, or `Record<string, unknown>` is a PHP-side fix (see the
per-feature references), not a `// @ts-expect-error`.

## Troubleshooting

| Symptom                                               | Cause / fix                                                                                                                                                                                                                                                                                                       |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--preview` wrote files                               | Needs `--preview=true`                                                                                                                                                                                                                                                                                            |
| New class missing from `index.ts`                     | `--source` never rewrites barrels; run a full `ts:publish`                                                                                                                                                                                                                                                        |
| A new column is missing                               | Columns are read from the live DB, and the schema is **not** part of the cache fingerprint. `php artisan migrate` republishes with `--fresh` for you; if the migration already ran, or the schema changed out of band, run `ts:publish --fresh` yourself — a plain republish is a cache hit that rewrites nothing |
| Stale output after a config change                    | Not a `--fresh` case: a `ts-publish` config change busts the cache on its own. `--fresh` is for a hand-edited generated file or an out-of-band schema change. Note that no run **deletes** files, so a rename or a new exclusion leaves orphans to remove by hand                                                 |
| `Cannot find module '../../illuminate/notifications'` | Add `\Illuminate\Notifications\DatabaseNotification::class` to `models.additional_directories`                                                                                                                                                                                                                    |
| Vite plugin says `sail: command not found`            | `laravelTsPublish({ command: './vendor/bin/sail artisan ts:publish' })`                                                                                                                                                                                                                                           |
| `Inertia.SharedData` unknown to TypeScript            | `inertia-config.d.ts` (or the output dir) is outside `tsconfig` `include`                                                                                                                                                                                                                                         |
| Echo callback payload is `any`                        | `echo_augmentation.enabled`, and the `.d.ts` must be in `include`. Only `@laravel/echo-vue`/`-react`/`-svelte` are detected; plain `@laravel/echo` is the fallback and its `.listen()` is not typed by the augmentation                                                                                           |
| Route helper for a vendor controller shows up         | Expected. Those routes are usually unnamed, so `routes.except` will not match them: use `routes.exclude_middleware`, `routes.only_named`, or an allowlist in `routes.only` (which itself drops every unnamed route)                                                                                               |
| Duplicate identifier for two enums with one basename  | Rename one with `#[TsEnum(name:)]`                                                                                                                                                                                                                                                                                |
| Request published as `Record<string, unknown>`        | `rules()` threw during analysis (the analyzer calls it against a fake request with only the Auth facade stubbed). `$this->user()` is `null` there; move state-dependent logic into `withValidator()`/`after()`                                                                                                    |
| Form request type on a route is `never`               | The action does not type-hint the `FormRequest`, or `form_requests.enabled` is off                                                                                                                                                                                                                                |
| Companion `_meta.ts` missing                          | `model_metadata.enabled` is `false` by default                                                                                                                                                                                                                                                                    |
| Running `ts:publish` in a package's `workbench/`      | No DB there; run the package tests instead                                                                                                                                                                                                                                                                        |

For pipeline customization (`*_class` keys, templates via `vendor:publish --tag=laravel-ts-publish-views`),
the pre-command hook (`LaravelTsPublish::callCommandUsing()`), cache internals, and the `AstEngine::analyze()`
API, read the package README.

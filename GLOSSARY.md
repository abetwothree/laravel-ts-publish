# laravel-ts-publish

A Laravel package that publishes TypeScript from an application's PHP code, so the frontend is typed from the same classes, schema, and routes the backend runs on.

## Language

### Publishing

**Publish**:
To turn a Laravel application's code into TypeScript. A run publishes files, a class publishes declarations, and a property publishes as a type. Copying the package's config or templates out with `vendor:publish` is not publishing.
_Avoid_: generate, emit, convert, recreate, export

**Feature**:
One category of Laravel code the package publishes, such as models, enums, API resources, routes, form requests, broadcasting, Inertia, Vite env, or model metadata.
_Avoid_: file category, content type, output type, phase

**Functional output**:
Published TypeScript that exists at runtime, such as enum objects and route helpers, as opposed to type-only declarations.

**Namespace**:
A PHP namespace, which modular publishing mirrors as a directory. Say "TypeScript namespace" for the TypeScript construct.

**Modular publishing**:
The rule that published files mirror PHP namespaces as a directory tree.
_Avoid_: flat output

**Barrel**:
The `index.ts` in each published directory that re-exports every file beside it.
_Avoid_: index file

**Combined file**:
A single file that gathers every item of one feature across namespaces, such as all broadcast events or all broadcast channels.
_Avoid_: index file

**Globals file**:
An optional file that declares every published type in a global TypeScript namespace, so code can use them without imports.

**Augmentation file**:
A declaration file that extends a third-party library's types, such as Inertia's, Laravel Echo's, or Vite's, with the application's published types.

**Watcher list**:
The list of collected PHP files that file watchers, such as the Vite plugin, read to know when to publish again.

### Feature output

**API resource**:
A Laravel `JsonResource` class, published as an interface for the JSON it returns.
_Avoid_: resource

**Enum-resolved interface**:
A parallel model interface, such as `{Model}Resource`, that types each enum column as a resolved `AsEnum<>` instance instead of a raw value.
_Avoid_: resource, model resource

**Accessor**:
A model attribute computed by a getter instead of stored in a column. `{Model}Mutators` is only the name of the interface that holds them.
_Avoid_: mutator

**Model metadata**:
The opt-in feature that publishes a runtime companion beside each model interface, carrying values the backend owns.
_Avoid_: metadata

**Companion**:
The `{model}_meta.ts` module that model metadata publishes for one model.
_Avoid_: meta file

**Enum metadata**:
The `_cases`, `_methods`, and `_static` arrays an enum can publish for runtime introspection.
_Avoid_: metadata

**Enum runtime helper**:
One of the built-in functions a published enum gets, such as `.from()`, `.tryFrom()`, and `.cases()`.
_Avoid_: helper

**Route helper**:
The published function for one controller action, which builds its URL and binds its parameters.
_Avoid_: helper

**Broadcast channel**:
A name an application authorizes in `routes/channels.php` for events to broadcast on.
_Avoid_: channel

**Channel builder**:
A published function that builds one broadcast channel's name from its placeholders.
_Avoid_: accessor

### Typing

**Override**:
A type the application states for a class or property, which replaces whatever the package would infer.

**Custom import**:
An import named inside an override, so a hand-written TypeScript type can be used in published output.

**Import channel**:
One kind of class name a published type carries so its file can import it, such as model, enum, or custom-import names.
_Avoid_: channel, FQCN channel

**Index signature**:
A published key pattern, such as ``[key: `${string}_tag`]``, that types every runtime key it matches.
_Avoid_: signature key, pattern key

**Subject**:
The class or method being analyzed, such as an API resource or a controller action. A warning names the subject it came from.

**Inference**:
Working out a property's type from PHP code when no override or docblock states it.

**Vague type**:
A declared PHP type too broad to publish usefully, such as `mixed` or `array`, which sends inference to look further.

**Unknown regression**:
A property that published a real type and now publishes `unknown`.

**Known gap**:
A case the package knows it publishes wrongly or as `unknown`, recorded until it is fixed or declared a non-goal.

### Development

**Workbench**:
The Laravel test application inside this repo that tests publish from.

**Golden workbench output**:
The TypeScript published from the workbench, committed to the repo and diffed to catch regressions.
_Avoid_: committed type trees, generated tree, output examples

**Workbench fixture**:
A class in the workbench, written to exercise one case, that publishes into the golden workbench output.
_Avoid_: fixture

**Test fixture**:
A class under `tests/Fixtures/` that tests use directly and that never reaches the golden workbench output. Where a
new one goes: [Test fixtures](docs/testing/test-fixtures.md).
_Avoid_: fixture

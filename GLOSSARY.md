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
A Laravel `JsonResource` class, published as an interface for the JSON it returns. A JSON:API resource publishes the resource object it sends as `data`.
_Avoid_: resource

**Resource object**:
The `{ id, type, attributes, relationships?, links?, meta? }` member a JSON:API resource sends as `data`, and as each `included` entry.
_Avoid_: document

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

**Published enum**:
An enum a run writes a file for, because enum publishing is on and the configured enum collector lists it. A `->value` or `->name` read publishes an enum's alias only for a published enum, since only its file can be imported.
_Avoid_: collected enum, exported enum

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

**Runtime class**:
The class `$this` is at run time — for a collection, the one Laravel instantiates — whose `collects()`, `$wrap` and keys apply to every ancestor body it runs.
_Avoid_: leaf, publishing collection, called class

**Declaring class**:
The class that declares the method body being read: `self` names it, `parent` names its parent, and a private member it declares is the one `$this->` reads in that body, even where the runtime class redeclares it. For a trait's method it is the class that uses the trait, not the trait.
_Avoid_: defining class, lexical class, scope class

**Resource proxy**:
An API resource's `$this->resource`: the model or value the API resource wraps, which every member the API resource does not declare forwards to. No other subject has one, so elsewhere `$this->resource` is an ordinary property.
_Avoid_: wrapper, resource property

**Inference**:
Working out a property's type from PHP code when no override or docblock states it.

**Vague type**:
A declared PHP type too broad to publish usefully, such as `mixed` or `array`, which sends inference to look further.

**Narrowing**:
Proving a value's class for the reads a test covers, such as an `instanceof` check in an `if` or a ternary.
_Avoid_: type guard, refinement

**Dropped arm**:
A union arm inference cannot type, left out of the union rather than widening it to `unknown`, so the property publishes the arms that are left.
_Avoid_: lost arm, untyped branch

**Re-index**:
What an API resource does to an array whose keys all pass `is_numeric()`: Laravel sends its values as a JSON list.
_Avoid_: reindex, renumber (renumbering is what `array_merge()` does to int keys)

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

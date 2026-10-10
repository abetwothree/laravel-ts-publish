# Test fixtures

`tests/Fixtures/` holds the classes tests use directly, which never reach the golden workbench output. It is laid out
like a Laravel application, so a new fixture goes in the folder its kind would live in inside an app. The namespace
follows the folder.

| Folder | Holds |
|---|---|
| `Models/`, `Casts/`, `Enums/`, `Events/`, `ValueObjects/`, `Services/` | the Laravel kind the folder names |
| `Http/Resources/`, `Http/Requests/`, `Http/Controllers/` | API resources and collections, form requests, Inertia controllers |
| `Tables/` | Inertia UI tables |
| `TsPublish/` | subclasses and implementations of this package's extension points, in the folder of their parent in `src/`: a `ModelTransformer` subclass in `TsPublish/Transformers/` |
| `Packages/InertiaUiTable/` | a stand-in for the `inertiaui/table` package, autoloaded as `InertiaUI\Table\` from `composer.json` |
| `Stubs/` | `.php.stub` sources a test requires or copies; never autoloaded |

A trait goes in a `Concerns/` folder beside the classes that use it. Fixtures that one test loads by path, such as
`tests/Unit/Ast/Fixtures/`, stay beside that test.

A fixture's folder depth sets the relative import paths its published output carries: a resource in `Http/Resources/`
reaches the workbench through `../../../../../../workbench/`, a model in `Models/` through `../../../../../workbench/`.
Moving a fixture changes the paths its tests expect.

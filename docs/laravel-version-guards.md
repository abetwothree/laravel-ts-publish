# Version-guarded Laravel classes

This package supports `illuminate/contracts: ^13.0||^12.0`. A class that exists only in the newer release is named by
string FQCN behind `class_exists()` instead of a `use` import, so no code path touches it on the older release. Every
such reference has a row here, and [`LaravelVersionGuardsTest`](../tests/Unit/LaravelVersionGuardsTest.php) fails
when a guard in `src/` has no row.

When the support floor rises above a row's minimum version, that row's guard is dead. Replace the string with a `use`
import, delete the `class_exists()` branch, drop the `->skip()` from its tests, and remove the row.

| Class | Min Laravel | Guarded at | Tests skipped at | Convert when floor ≥ |
| --- | --- | --- | --- | --- |
| `Illuminate\Database\Eloquent\Attributes\UseResource` | `12.29.0` | `src/Ast/ModelClassResolver.php`, `src/Ast/Handlers/ToResourceHandler.php` | `tests/Unit/Transformers/ResourceTransformerTest.php`, `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` | `12.29.0` |
| `Illuminate\Database\Eloquent\Attributes\UseResourceCollection` | `12.29.0` | `src/Ast/Handlers/ToResourceHandler.php` | `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` | `12.29.0` |
| `Illuminate\Http\Resources\Attributes\Collects` | `13.0.0` | `src/Analyzers/Concerns/InspectsResourceCalls.php` | `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php`, `tests/Unit/Ast/Handlers/InertiaResourcePropHandlerTest.php`, `tests/Unit/Transformers/ResourceTransformerTest.php`, `tests/Unit/Writers/JsonWriterTest.php`, `tests/Unit/Writers/GlobalsWriterTest.php` (see below) | `13.0.0` |
| `Illuminate\Http\Resources\Attributes\PreserveKeys` | `13.0.0` | `src/Analyzers/Concerns/ChecksPreserveKeys.php` | `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Table` | `13.0.0` | none (test-only, see below) | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Hidden` | `13.0.0` | none (test-only, see below) | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Visible` | `13.0.0` | none (test-only, see below) | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Appends` | `13.0.0` | none (test-only, see below) | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Connection` | `13.0.0` | none (test-only, see below) | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Validation\Rules\ArrayKeys` | `13.24.0` | `src/Analyzers/FormRequest/FormRequestRulesAnalyzer.php` | `tests/Unit/Analyzers/FormRequestRulesAnalyzerTest.php` | `13.24.0` |
| `Illuminate\Database\Eloquent\Attributes\RouteKey` | `13.21.0` | `src/Transformers/RouteTransformer.php` (overridesRouteKey()) | `tests/Unit/Transformers/RouteTransformerTest.php` | `13.21.0` |

The `PreserveKeys` guard covers only the `#[PreserveKeys]` attribute, which `collectionPreservesKeys()` reads through
`ReflectionClass::getAttributes()`. The older `public $preserveKeys = true;` property is read through
`ReflectionClass::getDefaultProperties()` on every supported version, so it needs no guard and does not change when the
row is converted.

The `Collects` row's test guards are not written as `class_exists()`. They spell the condition as
`->skip(fn () => ! version_compare(app()->version(), '13', '>='))`, so grep for that form, not the FQCN, when you
convert the row. Each covers a `PostFlatCollection` assertion. That fixture's collected resource is reachable only
through the attribute, so on Laravel 12 `resolveCollectedResourceClass()` returns null and the type degrades.
`PreserveKeysFlatCollection` uses the `$collects` property and needs no guard. `PostCollection` carries the attribute
but also follows the `FooCollection` to `FooResource` naming convention, so it resolves either way.

The five `Attributes\{Table,Hidden,Visible,Appends,Connection}` rows have no `src/` guard. Laravel applies those
attributes itself in `Model::__construct()`, and this package reads only the results, through `getTable()`,
`getAppends()` and the inspector's `attributeIsHidden()`, as
[ModelAttributeResolver](./components/model-attribute-resolver.md) describes.

Their only `class_exists()` is the `->skip()` on each attribute's test in `ModelTransformerTest.php`. The scanner does
not read `tests/`, so the enforcement test neither needs these rows nor checks them. A test-only guard still earns a
row, because its `->skip()` must go with the row once the floor reaches `13.0.0`.

The workbench fixture models `use`-import these five attributes, and that is safe on Laravel 12. A `use` statement is
a compile-time alias that loads nothing, and PHP never instantiates a class attribute unless something calls
`newInstance()` on it.

## How each minimum version was established

Only one Laravel version is installed locally, so each minimum comes from `laravel/framework`'s release tags, read
through GitHub's contents API (`GET /repos/laravel/framework/contents/{path}?ref={tag}`). A tag shows what shipped in
that release more reliably than changelog prose:

- **`UseResource` and `UseResourceCollection`**: absent through `v12.28.1`, present from `v12.29.0`, where both shipped
  in laravel/framework#56966. An exact minimum.
- **`ArrayKeys`**: absent from the 12.x line and through `v13.23.0`, present from `v13.24.0`. An exact minimum.
- **`Collects`, `PreserveKeys`, `Table`, `Hidden`, `Visible`, `Appends` and `Connection`**: absent at `v12.0.0` and
  `v12.69.2`, present at `v13.0.0`. Recorded as `13.0.0`, the first tag proven to contain them, since no 12.x release
  was found to carry them.
- **`RouteKey`**: absent through `v13.20.0`, present from `v13.21.0`. An exact minimum.

## Scanner coverage and blind spots

`LaravelVersionGuardsTest` finds a guard by matching source text, not by parsing PHP. It sees `class_exists()` called
on a quoted `Illuminate\…` string, either directly or through a variable assigned that string earlier in the same
file. It misses these forms:

- A class or `const` reference, such as `class_exists(self::FOO)` or `class_exists(FOO)`.
- A `match` or `switch` arm that produces the FQCN, rather than a flat assignment.
- An FQCN built by interpolation or concatenation, such as `"Illuminate\\{$segment}"`.
- `class_exists` called indirectly, through a variable holding the function name (`$fn = 'class_exists'; $fn($x);`)
  or through `call_user_func('class_exists', ...)`.

Add the row by hand for a guard written in any of these forms, because the test will not catch a missing one.

## Not in this registry

These string FQCNs exist for reasons other than version support, so never convert them:

- `src/RelationMap.php` builds a relation class name from a type string.
- `src/Support/TolkiTypes.php` maps always-present framework classes by name.
- `src/Ast/Handlers/ModelFinderHandler.php` names
  `Illuminate\Pagination\{LengthAwarePaginator,Paginator,CursorPaginator}` in its `PAGINATORS` map, the same
  always-present-class-by-name shape as `TolkiTypes.php`.
- `src/Ast/Handlers/InertiaWrapperHandler.php` and `src/Ast/InertiaRenderLocator.php` name the dev-only
  `Inertia\ResponseFactory` by string behind `class_exists()`. That is not a Laravel version guard, but the same rule
  applies for the same reason. Importing a `require-dev` package from `src/` would declare a hard dependency this
  package does not have.

# Version-guarded Laravel classes

This package supports `illuminate/contracts: ^13.0||^12.0`. A class that exists only in the newer
release cannot be `use`-imported — the import is resolved at class-load time and fatals on the older
one. Such classes are referenced by string FQCN behind `class_exists()` instead.

Every one of those references is listed here. **Adding a guarded reference without adding a row is a
test failure** (`tests/Unit/LaravelVersionGuardsTest.php`).

When the support floor rises above a row's minimum version, that row's guard is dead: replace the
string with a `use` import, delete the `class_exists()` branch, drop the `->skip()` from its tests,
and remove the row.

| Class | Min Laravel | Guarded at | Tests skipped at | Convert when floor ≥ |
| --- | --- | --- | --- | --- |
| `Illuminate\Database\Eloquent\Attributes\UseResource` | `12.29.0` | `src/Ast/ModelClassResolver.php`, `src/Ast/Handlers/ToResourceHandler.php` | `tests/Unit/Transformers/ResourceTransformerTest.php`, `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` | `12.29.0` |
| `Illuminate\Database\Eloquent\Attributes\UseResourceCollection` | `12.29.0` | `src/Ast/Handlers/ToResourceHandler.php` | `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` | `12.29.0` |
| `Illuminate\Http\Resources\Attributes\Collects` | `13.0.0` | `src/Analyzers/Concerns/InspectsAstNodes.php` | `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php`, `tests/Unit/Ast/Handlers/InertiaResourcePropHandlerTest.php`, `tests/Unit/Transformers/ResourceTransformerTest.php`, `tests/Unit/Writers/JsonWriterTest.php`, `tests/Unit/Writers/GlobalsWriterTest.php` — see below | `13.0.0` |
| `Illuminate\Http\Resources\Attributes\PreserveKeys` | `13.0.0` | `src/Analyzers/Concerns/ChecksPreserveKeys.php` | `tests/Unit/Analyzers/ResourceAstAnalyzerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Table` | `13.0.0` | none — test-only, see below | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Hidden` | `13.0.0` | none — test-only, see below | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Visible` | `13.0.0` | none — test-only, see below | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Appends` | `13.0.0` | none — test-only, see below | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Database\Eloquent\Attributes\Connection` | `13.0.0` | none — test-only, see below | `tests/Unit/Transformers/ModelTransformerTest.php` | `13.0.0` |
| `Illuminate\Validation\Rules\ArrayKeys` | `13.24.0` | `src/Analyzers/FormRequest/FormRequestRulesAnalyzer.php` | `tests/Unit/Analyzers/FormRequestRulesAnalyzerTest.php` | `13.24.0` |
| `Illuminate\Database\Eloquent\Attributes\RouteKey` | `13.0.0` | `src/Transformers/RouteTransformer.php` (overridesRouteKey()) | `tests/Unit/Transformers/RouteTransformerTest.php` | `13.0.0` |

The `PreserveKeys` row's guard covers only the `#[PreserveKeys]` *attribute* form, read via
`ReflectionClass::getAttributes()` in `collectionPreservesKeys()`. Laravel's older
`public $preserveKeys = true;` *property* form needs no guard at all: it's read via
`ReflectionClass::getDefaultProperties()`, which works identically on every supported version, so
that branch of `collectionPreservesKeys()` is never conditional. If the floor rises and this row is
converted, only the attribute branch becomes a plain `use` import — the property branch is already
unconditional and does not change.

The `Collects` row is the one whose test guards are **not** written as `class_exists()`. All six spell
the same condition as `->skip(fn () => ! version_compare(app()->version(), '13', '>='))`, because each
covers a `PostFlatCollection` assertion — the only fixture whose collected resource is reachable *solely*
through the attribute, so `resolveCollectedResourceClass()` returns null on Laravel 12 and the type degrades.
Grep for the `version_compare` form, not the FQCN, when converting this row. Sibling fixtures such as
`PreserveKeysFlatCollection` use the `$collects` property and need no guard; `PostCollection` carries the
attribute but also satisfies the `FooCollection` → `FooResource` naming convention, so it resolves either way.

The five `Attributes\{Table,Hidden,Visible,Appends,Connection}` rows have no `src/` guard because
nothing in this package resolves them: Laravel applies them itself in `Model::__construct()`, and
this package only ever reads the results back through model instance calls
(`getTable()`, `getAppends()`, the inspector's `attributeIsHidden()`) — see
[docs/components/model-attribute-resolver.md](./components/model-attribute-resolver.md). Their
`class_exists()` reference lives solely in the `->skip()` guard on each attribute's test in
`ModelTransformerTest.php`; the scanner below does not scan `tests/`, so it cannot see these guards
and the enforcement test neither depends on nor is satisfied by these five rows. They are recorded
here anyway per the "test-only guards still earn a row" rule: each is a live version guard — the
`->skip()` — that must be dropped along with its row once the Laravel 12 floor rises to `13.0.0`.

## How each minimum version was established

The installed vendor tree is `13.24`, so Laravel 12's tree cannot be read locally. `UseResource`
and `Collects` were verified empirically against `laravel/framework`'s GitHub tags via the contents
API (`GET /repos/laravel/framework/contents/{path}?ref={tag}`), which reflects exactly what shipped
in each release — more reliable than changelog prose.

- **`UseResource`**: binary-searched across all 112 published `v12.*` tags for
  `src/Illuminate/Database/Eloquent/Attributes/UseResource.php`. Absent through `v12.28.1`, present
  starting `v12.29.0` (released 2025-09-16, landed via laravel/framework#56966) and at every release
  since, including `v13.0.0`. This is an exact minimum, not a range.
- **`Collects`**: the entire `src/Illuminate/Http/Resources/Attributes` directory 404s at both
  `v12.0.0` and `v12.66.0` (the newest published 12.x at research time) — the directory does not
  exist anywhere in the 12.x line. It exists at `v13.0.0` (released 2026-03-17). Recorded as `13.0.0`
  because that is the first tag proven to contain it; no 12.x release was found to carry it.

The same method was applied ahead of time to five attributes in `Illuminate\Database\Eloquent\Attributes`
(`Table`, `Hidden`, `Visible`, `Appends`, `Connection`) plus `Illuminate\Http\Resources\Attributes\PreserveKeys`:
all six are absent at `v12.66.0` and present at `v13.0.0`, the same shape as `Collects`. That one
finding backs every row derived from it, with no re-derivation: `Min Laravel: 13.0.0` /
`Convert when floor ≥: 13.0.0` on the five `Attributes\{Table,Hidden,Visible,Appends,Connection}` rows
above, the `PreserveKeys` row once its `src/` guard was added, and the `RouteKey` row — `RouteKey` lives
in the same `Illuminate\Database\Eloquent\Attributes` directory as
`Table`/`Hidden`/`Visible`/`Appends`/`Connection`, so the directory-level
absent-at-`v12.66.0`/present-at-`v13.0.0` finding covers it without a fresh tag search.

- **`ArrayKeys`**: binary-searched across all 32 published `v13.*` tags for
  `src/Illuminate/Validation/Rules/ArrayKeys.php`, after first confirming it 404s at both `v12.0.0`
  and `v12.66.0` (the entire 12.x line lacks it, same shape as `Collects`). Absent through
  `v13.23.0`, present starting `v13.24.0` (the fluent `Rule::arrayKeys()` factory in `Rule.php`
  appears in the same tag) and at every release since. This is an exact minimum, not a range —
  the same shape as `UseResource`.
- **`UseResourceCollection`**: lives beside `UseResource` in the same
  `Illuminate\Database\Eloquent\Attributes` directory, so checked directly rather than assumed:
  `src/Illuminate/Database/Eloquent/Attributes/UseResourceCollection.php` 404s at `v12.28.0` and
  `v12.28.1`, and is present at `v12.29.0` and `v13.0.0` — the identical cutover to `UseResource`,
  consistent with both attributes shipping in the same PR (laravel/framework#56966).

## Scanner coverage and blind spots

`tests/Unit/LaravelVersionGuardsTest.php` finds a guard by pattern-matching source text, not by
parsing PHP — it cannot see every possible way to write one. It detects:

- `class_exists('Illuminate\Some\Fqcn')` / `class_exists("Illuminate\Some\Fqcn")` — single- or
  double-quoted, called directly.
- `$var = 'Illuminate\Some\Fqcn'; ... class_exists($var)` — single- or double-quoted, assigned to a
  variable first and passed to `class_exists()` later in the same file.

It does **not** detect, and will silently miss:

- A class or `const` reference — `class_exists(self::FOO)` or `class_exists(FOO)`.
- A `match`/`switch` arm that produces the FQCN conditionally rather than via a flat assignment.
- String interpolation or concatenation building the FQCN, e.g. `"Illuminate\\{$segment}"`.
- `class_exists` invoked indirectly through a variable holding the function name
  (`$fn = 'class_exists'; $fn($x);`) or `call_user_func('class_exists', ...)`.

A guard written in any of these forms must be added to the registry by hand — the test will not
catch a missing row for it.

## Not in this registry

String FQCNs that exist for reasons other than version support, and must not be converted:

- `src/RelationMap.php` — builds a relation class name dynamically from a type string.
- `src/Support/TolkiTypes.php` — maps always-present framework classes by name.
- `src/Ast/Handlers/ModelFinderHandler.php` — its `PAGINATORS` map names
  `Illuminate\Pagination\{LengthAwarePaginator,Paginator,CursorPaginator}` by string, the same
  always-present-class-by-name shape as `TolkiTypes.php`.
- `src/Ast/Handlers/InertiaWrapperHandler.php` and `src/Ast/InertiaRenderLocator.php` — name the dev-only
  `Inertia\ResponseFactory` by string behind `class_exists()`. Not a Laravel version guard, but the same
  rule applies for the same reason: a `require-dev` package must never be imported from `src/`, or the
  import declares a hard dependency this package does not have.

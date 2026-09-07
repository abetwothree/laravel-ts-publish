# Support helpers: `JsEmitter`, `TsTypeString`, `TsNaming`

> No user-facing counterpart: all three classes and their facades are `@internal`. What they produce
> is verified indirectly, by [the type-inference gates](../testing/type-inference-gates.md) reading the
> generated tree their callers write.

`AbeTwoThree\LaravelTsPublish\Support\JsEmitter`, `…\Support\TsTypeString` and `…\Support\TsNaming`
are three helper classes carved out of `LaravelTsPublish`, each reached through its own `@internal`
facade in `AbeTwoThree\LaravelTsPublish\Facades\`. They exist because `LaravelTsPublish` had become
one class doing three jobs that have nothing to do with its actual subject — the PHP-type →
TypeScript-type engine. None of the three reflects on a class, reads the config maps, or asks what a
type *is*: they emit JavaScript source, rewrite TypeScript type strings, and turn PHP names into
TypeScript names.

The extraction ran under one hard constraint, from `abetwothree/laravel-ts-publish#69`: every
`LaravelTsPublish::` name that worked before still works. 220 call sites in `src/` and 107 in
`resources/views/` depended on it, plus an unknowable number outside the package. That constraint is
what produced the delegation layer below, and it is why moving a helper here is never simply a move.

## Which class owns a new helper

Ask what the helper's **input domain** is, not what happens to call it:

| The question the helper answers | Owner |
| --- | --- |
| **"What does this go into a generated file as?"** — a PHP value or docblock text turned into the literal, key, identifier or comment a `.ts` file carries | `JsEmitter` |
| **"What is true of this TypeScript type string, or what does it become?"** — type string in, answer or rewritten type string out | `TsTypeString` |
| **"What is this called, and where does it live?"** — an FQCN, a file path or an array key resolved to a name, a directory, or another path | `TsNaming` |
| **"What *is* this, as a type?"** — a PHP type, a `ReflectionX` or a docblock resolved to a `TypeScriptTypeInfo` | stays on `LaravelTsPublish` |

Read the question, not the signature: two of the clusters have members whose return type alone would
misfile them. `JsEmitter::enumScalar()` hands back a PHP `int|string` and `parseDocBlockDescription()`
plain text — neither is JavaScript source, but each is the normalizing step immediately upstream of one
(`toJsLiteral()` and `formatJsDoc()` respectively), and splitting them off would leave half of one
emission decision in another class. `TsNaming::resolveRelativePath()` returns a PHP file path and
`resolveClassFromFile()` a PHP FQCN — neither is a TypeScript name, but both answer "where does this
live, what is it called" with no inference involved, which is the same question `namespaceToPath()`
answers from the other direction.

The last row is the real test. If answering the question needs reflection, the config maps, or a
recursion back into `toTsType()`, it belongs to the engine however string-shaped its signature looks —
`splitPhpDocUnionType()` and `splitTopLevelUnion()` are both "split a string on a delimiter", and only
the second one is a helper.

### `JsEmitter`

PHP values and docblock text in, JavaScript source out: `validJsObjectKey()`, `safeJsIdentifier()`,
`toJsLiteral()`, `enumScalar()`, `routeArgsToJs()`, `sanitizeJsDoc()`, `formatJsDoc()`,
`parseDocBlockDescription()`, plus the private `RESERVED_JS_IDENTIFIERS` list `safeJsIdentifier()`
reads. No state, no dependencies, no config.

`validJsObjectKey()`'s `$allowIndexSignature` flag is the one member with a trap in it: a generated
`[key: number]` / `[key: string]` is legal only in a type position, so every value-position caller must
leave the default alone. See [the arbiter](#the-arbiter-is-the-generated-tree) for what happened when a
delegation dropped it.

### `TsTypeString`

Structural questions about a TypeScript type string, and rewrites of one: `extractImportableTypes()`,
`shapeValueHasUnimportableToken()`, `aliasPropertyType()`, `qualifyGlobalType()`,
`splitTopLevelUnion()`, `hoistNull()`, `typeNameOccursIn()`, `substituteEnumType()`,
`rewriteAsEnumToType()`, `isVagueTsType()`, plus the public `TS_PRIMITIVES` list several of them filter
against. No state. Its only outward dependency is `TsTypeShape::splitTopLevel()`, which
`splitTopLevelUnion()` wraps.

**The retained type engine calls into it, and that direction is one-way.** Six sites across five engine
methods — `toTsType()`, `arrayableShapeType()`, `publicPropertyShapeType()`,
`methodOrDocblockReturnTypes()` (twice) and `resolveArrayShapeString()` — reach for
`extractImportableTypes()`, `shapeValueHasUnimportableToken()` or `isVagueTsType()`. `TsTypeString`
never calls back. That asymmetry is deliberate and is what made this class safe to lift out at all; a
helper that needed to ask the engine a question would be in the same bind the docblock sub-engine is in
(below). Keep it that way: a new `TsTypeString` member that wants `toTsType()` is a sign it is not a
type-string helper.

### `TsNaming`

PHP names in, TypeScript names and import paths out: `resourceTypeName()`, `namespaceToPath()`,
`relativeImportPath()`, `sortImportPaths()`, `resolveRelativePath()`, `resolveClassFromFile()`,
`keyCase()`, plus the `protected importSortGroup()` that classifies a path into `sortImportPaths()`'s
three groups — an implementation detail with no delegation and no facade surface.

It holds the only state of the three: `$resourceTypeNames`, a per-instance FQCN → published interface
name cache that `resourceTypeName()` fills, since resolving a name means reading a `#[TsResource]`
attribute off the class. That cache is the entire reason the container binding below is not pure
decoration.

## What stayed on `LaravelTsPublish`, and why the docblock engine could not follow

What is left is the engine and the things only the engine uses: `toTsType()` with its cast, shape and
reflection helpers; the docblock sub-engine; the engine's return shape
(`emptyTypeScriptInfo()`/`omittedTypeScriptInfo()`/`mergeTypeScriptInfos()`); the config maps
(`typesMap()`/`relationsMap()`/`relationStrategy()`); `callCommandUsing()`/`callCommandWith()`; and the
delegations.

The docblock sub-engine is the largest remaining block and the obvious next candidate. It cannot be
extracted the way these three were, because it and `toTsType()` are **mutually recursive**. Five edges
close the cycle:

Docblock → engine:

- `resolveDocblockTypePart()` calls `toTsType()`
- `resolveDocblockContainerValue()` calls `toTsType()`
- `resolvePhpDocTypeToTs()` calls `toTsType()`

Engine → docblock:

- `arrayableShapeType()` calls `parseDocblockReturnArrayShape()`
- `methodOrDocblockReturnTypes()` calls `docblockReturnTypes()`

Lift the docblock methods into a class of their own and three of them are left reaching for a
`toTsType()` that is no longer theirs to call. The way out is an injected resolver — the new class
takes something that resolves a PHP type to a `TypeScriptTypeInfo`, and `LaravelTsPublish` supplies
itself — which is a design change with its own behavioural surface, not the mechanical move the three
helpers above were. It was left whole rather than half-done for that reason, and anyone picking it up
should treat "break the cycle" as the task and "move ~25 methods" as its consequence.

## The delegation rule

Two rules, pointing in opposite directions:

- **Inside this package, call the helper's own facade.** `JsEmitter::toJsLiteral(…)`,
  `TsTypeString::hoistNull(…)`, `TsNaming::resourceTypeName(…)` — in `src/` and in
  `resources/views/` alike. No Blade view references `LaravelTsPublish` at all any more. Never route a
  new call through `LaravelTsPublish` for one of these helpers.
- **`LaravelTsPublish` answers anyway.** 25 methods on it forward to the three facades, plus the
  `TS_PRIMITIVES` alias — 26 names in total, the full pre-extraction helper surface.

Every one of those 25 delegations has **zero callers inside `src/` and `resources/views/`**. That is
the finished state, not an oversight: they are the compatibility surface `#69` required, and their
only consumers live outside the package. A "this method has no callers" search result is therefore
not evidence that one is dead code, and none of them may be deleted on that reasoning.

### `LaravelTsPublishDelegationTest` is the only thing holding them up, and must never be swept

`tests/Unit/LaravelTsPublishDelegationTest.php` is the sole pin on all 26 names. Each assertion reads
`LaravelTsPublish::x(…)` on the left against `NewFacade::x(…)` on the right and asserts byte equality.
**Rewriting the left-hand side to the new facade — the obvious thing to do when sweeping callers onto
the helpers — turns every assertion into `Facade::x(…) === Facade::x(…)`**, a tautology that passes
with all 25 delegations deleted. The three caller-migration commits skipped this file deliberately.

Each equality test is paired with a second test asserting the chosen input is one the helper actually
*transforms* — `validJsObjectKey('foo-bar')` really becoming `"foo-bar"`, not passing through
unchanged — because an input a helper returns verbatim makes the equality assertion vacuous. Those
second tests are also where defaulted and positional parameters are pinned: `$allowIndexSignature`,
`$importableNames`, `qualifyGlobalType()`'s `$skipNamespace` and `$aliasResolution`, `keyCase()`'s
`$case`, and `relativeImportPath()`'s argument order. A delegation that drops a default or swaps two
arguments still returns a plausible string, which is exactly why each one needs a case that separates
them.

**Those transform guards carry more weight than their name suggests.** Direct unit coverage in the new
`tests/Unit/Support/*Test.php` files is not symmetric: `JsEmitter` has a `describe()` block per member,
but `TsTypeString` has none for `shapeValueHasUnimportableToken()` or `substituteEnumType()`, and
`TsNaming` none for `resolveRelativePath()`. All three are covered — the first by the `toTsType()` shape
blocks that stayed in `LaravelTsPublishTest.php`, the other two by the delegation test's transform
guards — so this is asymmetry, not a hole. But `substituteEnumType()` and `resolveRelativePath()` now
have their only *direct* assertions inside a file whose stated job is pinning the compatibility surface,
which means a future decision to trim that file when the delegations are finally dropped would take
their coverage with it. Move them into the owning helper's test file before trimming, not after.

### `resolveRelativePath()` is the one `public static` delegation

`TsNaming::resolveRelativePath()` is an ordinary instance method; the delegation on
`LaravelTsPublish` is declared `public static`. The asymmetry is deliberate. Before the caller sweep,
eight sites in `src/` called it statically on the concrete class — seven in `WatcherJsonWriter`, one in
`CoreTransformer` — and dropping `static` turned each into a PHPStan `method.staticCall` error. Those
eight now call `TsNaming` directly, so the modifier earns nothing inside the package; it stays because
an outside caller written against the static form would break without it. The delegation test pins both
shapes, the static call on the concrete class and the ordinary call through the facade.

## `TS_PRIMITIVES` is an alias, not a copy

A facade forwards *calls* through `__callStatic`, and a constant read is not a call, so a facade cannot
carry a constant. `LaravelTsPublish::TS_PRIMITIVES` is therefore a language-level alias resolved against
the concrete class rather than the facade:

```php
public const array TS_PRIMITIVES = TsTypeStringService::TS_PRIMITIVES;
```

One declaration under two names, so the two cannot drift. The honest limit: the delegation test asserts
the two constants are *equal*, which is not the same as asserting there is one declaration. Replace the
alias with a byte-identical literal array and that assertion still passes — nothing in the suite would
notice until someone edited one copy and not the other.

## The singleton bindings are insurance, not current behaviour

`LaravelTsPublishServiceProvider::register()` binds all three helpers as singletons. This is
counter-intuitive enough to be worth stating flatly: **they change nothing about how the package
behaves today.**

`Facade::resolveFacadeInstance()` memoises what it resolves into `Facade::$resolvedInstance`, keyed by
accessor, and every in-package call and every delegation goes through a facade. One instance per
application lifetime is therefore guaranteed by the *facade*, not by the container. Measured across the
full suite with and without the three bindings: identical helper construction counts (1,526) and an
identical `resourceTypeName()` cache profile (35,567 hits, 5,437 misses).

What the bindings do change is `app(TsNaming::class)` and constructor injection, neither of which
consults `Facade::$resolvedInstance`. Unbound, each such resolution hands back a fresh `TsNaming` whose
`$resourceTypeNames` cache starts empty and never warms. They also matter for a long-lived worker,
where `Facade::clearResolvedInstances()` runs between requests while `$app->instances` survives — the
facade's memo is dropped and the container's is not. So they are three lines of currently-inert
insurance, and the delegation test pins each one (`app(X::class)` identical to `app(X::class)`) so that
nobody removes them as dead configuration.

## The arbiter is the generated tree

**Every commit on the branch that performed this extraction** — the three class extractions, the follow-up
that moved `isVagueTsType()`, the three caller sweeps, and the docs commit — left
`workbench/resources/js/types/` **byte-identical**. Not "the tree matched at the end": it matched at every
single step. That property is what makes a refactor of this size auditable, because the suite proves the
helpers behave and only an unchanged tree proves the *package* behaves. Any change here that is supposed
to be behaviour-preserving and moves the tree has not preserved behaviour, whatever the suite reports.

It earned its keep. A delegation that silently dropped a defaulted argument —
`validJsObjectKey()`'s `$allowIndexSignature` — passed the entire test suite while rewriting 12
generated files, turning `[key: number]: Tag;` into `"[key: number]": Tag;`. A quoted index signature
is not an index signature; every one of those shapes had become an ordinary property with an odd name,
and nothing but the tree said so. The defaulted- and positional-parameter pins in the delegation test
exist because of it.

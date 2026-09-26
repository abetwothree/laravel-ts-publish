# Support helpers: `JsEmitter`, `TsTypeString`, `TsNaming`

[`JsEmitter`](../../src/Support/JsEmitter.php), [`TsTypeString`](../../src/Support/TsTypeString.php) and
[`TsNaming`](../../src/Support/TsNaming.php) emit JavaScript source, answer and rewrite TypeScript type strings, and
turn PHP names into TypeScript names. They were split out of [`LaravelTsPublish`](../../src/LaravelTsPublish.php),
which keeps the PHP-type-to-TypeScript engine, and each is reached through its own `@internal` facade in
`src/Facades/`. Every `LaravelTsPublish::` helper name still works through a delegation, the compatibility promise
from `abetwothree/laravel-ts-publish#69`, so moving a helper here is never only a move.

## Where things live

The helpers and the test that pins them live in these files:

- [`Support\JsEmitter`](../../src/Support/JsEmitter.php): PHP values and docblock text in, JavaScript source out.
- [`Support\TsTypeString`](../../src/Support/TsTypeString.php): TypeScript type strings in, answers or rewrites out.
- [`Support\TsNaming`](../../src/Support/TsNaming.php): FQCNs, paths and keys in, TypeScript names and paths out.
- [`Support\StringSerialization`](../../src/Support/StringSerialization.php): whether `json_encode()` writes a string
  for a class. Static, with no facade.
- [`LaravelTsPublishDelegationTest`](../../tests/Unit/LaravelTsPublishDelegationTest.php): the only pin on the
  delegations and on the helpers' container bindings.

## Which class owns a new helper

Ask what the helper's input domain is, not what calls it:

| The question the helper answers | Owner |
| --- | --- |
| What does this PHP value or docblock text become in a `.ts` file: a literal, key, identifier or comment? | `JsEmitter` |
| What is true of this TypeScript type string, or what does it become? | `TsTypeString` |
| What is this FQCN, file path or array key called, and where does it live? | `TsNaming` |
| Does `json_encode()` write a string for this class, or for this method's declared return? | `StringSerialization` |
| What is this PHP type, `ReflectionX` or docblock, as a `TypeScriptTypeInfo`? | stays on `LaravelTsPublish` |

Read the question, not the signature. `JsEmitter::enumScalar()` returns a PHP `int|string`, not JavaScript, but it
prepares the input of `toJsLiteral()`, and moving it would split one emission decision across two classes.
`splitPhpDocUnionType()` and `splitTopLevelUnion()` both split a string on `|`, but only the second splits a
TypeScript type string. The first reads PHPDoc, the engine's input, so it stays on the engine.

The last row is the real test. A helper whose answer needs the engine's config maps or a call back into `toTsType()`
belongs to the engine, however string-shaped its signature looks. Reflection alone does not decide it.
`TsNaming::resourceTypeName()` reads a `#[TsResource]` attribute, but only to get a name.

### `JsEmitter`

`validJsObjectKey()`'s `$allowIndexSignature` flag is a trap. A generated `[key: number]` or `[key: string]` is legal
only in a type position, so every value-position caller must leave the default alone.
[The arbiter](#the-arbiter-is-the-generated-tree) shows what a dropped default does.

`castTargets()` decides which published key a `#[TsCasts]` key retypes:

- A cast key equal to a published key retypes that key.
- Failing that, a cast key equal to another spelling of an index signature's name retypes that signature. The other
  spellings are the name with each `\\` read as `\` (a single-quoted paste), with each `\r` read as a raw CR (a
  double-quoted paste), or with both, read escape by escape from the left.
- Where two cast keys name one signature, the exact spelling wins, else the first. The loser's target is null.
- A spelling two signatures share retypes neither.

`retargetCasts()` applies those decisions to every map that runs parallel to the casts, so a loser's optional flag and
import are dropped with its type. `castsByKey()` does both steps for a map whose entries are whole. The resource,
broadcast-event and Inertia paths call them before any cast lookup.

### `TsTypeString`

The engine calls `TsTypeString`, and `TsTypeString` never calls back. Its only outward call is
`TsTypeShape::splitTopLevel()`. That one-way dependency is what made the class safe to lift out. A new member that
wants `toTsType()` is not a type-string helper, and would be in the same bind as the
[docblock sub-engine](#what-stayed-on-laraveltspublish-and-why-the-docblock-engine-could-not-follow).

`JsEmitter::isIndexSignatureKey()`, `TsTypeString::isUnknownOnly()` and `TsTypeString::orUndefined()` are each the
one home for their test or spelling, so a new caller uses them rather than a local regex.

### `StringSerialization`

Its callers in `src/Ast/` use it to decline a receiver rule where `toTsType()` says `string` but `json_encode()` writes
an object. It lives in `src/Support/` because it asks a type question and never touches a `PhpParser` node. The
question a class answers decides its home, not where its callers sit. It has no facade and no delegation because it
was never part of the pre-extraction surface.

It stays `@internal`. [`InternalBoundaryTest`](../../tests/Architecture/InternalBoundaryTest.php) sweeps `src/Ast/` by
directory, so it names `StringSerialization` explicitly, and another class that leaves `src/Ast/` but should stay
internal needs the same entry.

## What stayed on `LaravelTsPublish`, and why the docblock engine could not follow

What stays is the engine, what only the engine uses, and the delegations. The docblock sub-engine is the largest
remaining block and the obvious next candidate, but it cannot move the way the three helpers did, because it and
`toTsType()` are mutually recursive. Five calls close the cycle:

- **Docblock to engine**: `resolveDocblockTypePart()`, `resolveDocblockContainerValue()` and `resolvePhpDocTypeToTs()`
  each call `toTsType()`.
- **Engine to docblock**: `arrayableShapeType()` calls `parseDocblockReturnArrayShape()`, and
  `methodOrDocblockReturnTypes()` calls `docblockReturnTypes()`.

A class holding the docblock methods would need an injected resolver that turns a PHP type into a `TypeScriptTypeInfo`,
with `LaravelTsPublish` supplying itself. That is a design change with its own behavior to verify, not a mechanical
move, so the sub-engine was left whole. Treat "break the cycle" as the task, and moving the methods as its consequence.

## The delegation rule

Two rules point in opposite directions:

- **In-package code**: call the helper's own facade, such as `JsEmitter::toJsLiteral(…)` or
  `TsNaming::resourceTypeName(…)`, in `src/` and in `resources/views/`. Never route a new call through
  `LaravelTsPublish`.
- **Outside callers**: `LaravelTsPublish` still answers. 25 of its methods forward to the three facades, and
  `TS_PRIMITIVES` aliases `TsTypeString::TS_PRIMITIVES`, which makes 26 names, the whole pre-extraction helper
  surface. That surface is frozen, so a helper added since gets no delegation.

None of the 25 delegations has a caller in `src/` or `resources/views/`. That is the finished state, not an oversight:
they are the compatibility surface `#69` required, and their callers live outside the package. A "no callers" search
result is not evidence of dead code, and none of them may be deleted on that basis.

### `LaravelTsPublishDelegationTest` is the only thing holding them up, and must never be swept

Each assertion compares `LaravelTsPublish::x(…)` on the left with `Facade::x(…)` on the right. Rewriting the left
side to the new facade, the obvious move in a caller sweep, turns every assertion into
`Facade::x(…) === Facade::x(…)`, which passes with all 25 delegations deleted.

Each equality test is paired with one asserting that its input is one the helper transforms, such as
`validJsObjectKey('foo-bar')` becoming `"foo-bar"`, because an input returned verbatim makes the equality vacuous.
Those guards also pin defaulted and positional parameters: `$allowIndexSignature`, `$importableNames`,
`qualifyGlobalType()`'s `$skipNamespace` and `$aliasResolution`, `keyCase()`'s `$case`, and `relativeImportPath()`'s
argument order. A delegation that drops a default or swaps two arguments still returns a plausible string.

The guards are also the only direct assertions on `TsTypeString::substituteEnumType()` and
`TsNaming::resolveRelativePath()`. Move those into the owning helper's test file before you trim this one.

### `resolveRelativePath()` is the one `public static` delegation

`TsNaming::resolveRelativePath()` is an instance method, but its delegation is declared `public static`. Keep the
`static`. Outside code that calls it statically on the concrete class breaks without it, though nothing inside the
package needs it. The delegation test pins both the static call and the call through the facade.

### `TS_PRIMITIVES` is an alias, not a copy

A facade forwards calls, and reading a constant is not a call, so the concrete class declares
`TS_PRIMITIVES = TsTypeStringService::TS_PRIMITIVES`. That is one declaration under two names, so the two cannot
drift. Keep it an alias. The delegation test asserts only that the two constants are equal, so a byte-identical
literal copy would pass until someone edited one side.

## The singleton bindings are insurance, not current behavior

`LaravelTsPublishServiceProvider::register()` binds all three helpers as singletons, which changes nothing about how
the package behaves. Every in-package call goes through a facade, and `Facade::resolveFacadeInstance()` already keeps
one instance per accessor.

The bindings matter where the facade's memo is not consulted. Unbound, `app(TsNaming::class)` and constructor
injection would each get a fresh `TsNaming` with an empty `$resourceTypeNames` cache, and a fresh `TsTypeString` with
an empty `qualifyGlobalType()` memo. In a long-lived worker, `Facade::clearResolvedInstances()` runs between requests
while the container's instances survive. The delegation test pins each binding, so nobody deletes them as dead
configuration.

## The arbiter is the generated tree

A change here that is meant to preserve behavior must leave `workbench/resources/js/types/` byte-identical at every
step, not only at the end. The suite proves the helpers behave, and only an unchanged tree proves the package does.

The suite checks the calls it was written for. During the extraction, a delegation that dropped `validJsObjectKey()`'s
`$allowIndexSignature` default passed the whole suite while turning `[key: number]: Tag;` into `"[key: number]": Tag;`
in the generated files. A quoted index signature is an ordinary property with an odd name, and only the tree showed
it. The delegation test's default and argument-order pins exist because of it.

## Related

These pages hold the neighboring rules:

- [Type inference gates](../testing/type-inference-gates.md), which check what these helpers produce through the
  generated tree. The helpers have no user-facing docs.
- [Known gaps](../known-gaps.md), whose entry on overriding a moved helper on a `LaravelTsPublish` subclass says why a
  subclass override leaves the output unchanged, and which container binding does change it.
- [Receiver types § Following a method's return type](receiver-types.md#following-a-methods-return-type), where
  `StringSerialization`'s callers decline.

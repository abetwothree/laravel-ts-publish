# Type inference gates

Two scripts in `.github/scripts/` check the generated TypeScript for the two ways type inference fails silently.
[`unknown-regression-gate.py`](../../.github/scripts/unknown-regression-gate.py) fails when a property that had a real
type starts emitting `unknown`, and [`unimportable-token-gate.sh`](../../.github/scripts/unimportable-token-gate.sh)
compiles the generated trees and fails when a token is emitted without its import or two imports collide. A green test
suite does not prove the types are right, since a helper's unit test can pass while the pipeline emits a wrong type, so
the gates read the output itself. They do not overlap, because a leaked token is a new property with a plausible type,
which the regression gate cannot see. [Performance gate](performance-gate.md) covers speed.

## Running the gates

The `type-inference-gates` job in [`run-tests.yml`](../../.github/workflows/run-tests.yml) regenerates the trees under
`workbench/resources/js/types/data` and fails when `git diff` shows a committed file there changed. That check
ignores untracked files, so a newly generated file that was never committed passes it. The job then runs both gates,
plus the regression gate's `--parsetest` and the token gate's `--selftest`. CI never runs the regression gate's
`--selftest` range, so run it yourself after changing that script.

Locally, the token gate runs `npx tsc`, so install the npm dependencies first with `npm ci`, as CI does. Then
regenerate the trees and run both gates:

```bash
composer test
.github/scripts/unimportable-token-gate.sh 0 0 0
python3 .github/scripts/unknown-regression-gate.py "$(git merge-base origin/main HEAD)" HEAD   # after committing the trees
```

The token gate compiles the files on disk, so it checks the trees `composer test` wrote. The regression gate compares
two commits, so commit the regenerated trees before you run it, or it never sees them. Pass the merge-base, as CI does.
The default base, `a6c268da`, guards only the properties that existed at that commit.

## `unknown-regression-gate.py`

The gate parses every `.ts` file in the generated trees at two revisions, and fails when a property whose type
contained no `unknown` at the base contains one at the head:

```bash
python3 .github/scripts/unknown-regression-gate.py [BASE_REV] [HEAD_REV]
```

`HEAD_REV` defaults to `HEAD`, and `BASE_REV` to `a6c268da`, the commit the type-inference work branched from. CI
passes the merge-base with `origin/main` instead, or `HEAD~1` when that merge-base is missing or is `HEAD`. The
property counts the script prints are informational. The `PASS` or `FAIL` line is the result, and a failure lists the
first 40 regressed properties with their base and head types.

### How properties are keyed

A key is the file, the enclosing interface or namespace path, and the property name, so `Invoice.status` and
`InvoiceResource.status` stay apart, and so do the nested namespaces in `laravel-ts-global.ts`. Single-line
`type X = …;` aliases are keyed like properties, including one inside `declare global`.

An inline `{ … }` object also gives each member a key beside the parent's: `prop.member` for one object, and
`prop[i].member` for each arm of a union of objects. Member keys stop an already-`unknown` sibling from masking a
member's regression, and the parent key catches a whole object that collapses to `unknown`. `detect_regressions()`
skips the parent when all its head members existed at the base, so a regression reports once. A member new at the
head has no base key, so an object that gains an `unknown` member fails on its parent. Treat that as a real question,
not noise. A `Pick<Model, K>` value has no `{` to split and stays one key, and the model's own file checks its members.

### Self-tests

Run both after any change to the script:

```bash
python3 .github/scripts/unknown-regression-gate.py --parsetest
python3 .github/scripts/unknown-regression-gate.py 0faf1f5 3880323 --selftest
```

`--parsetest` checks the parser on cases lifted from the trees and on a synthetic regression for each splitting rule,
and CI runs it on every push that runs the workflow. `--selftest` runs over a range that turned `TrackingEvent.status`
from `string` into `unknown`, and inverts the exit code. It must report 16 regressions, four per tree: the model and
its resource, each in its own file and in `laravel-ts-global.ts`. A `PASS` means the script is broken. That range
regresses only top-level properties, so `--parsetest` is the only guard on the alias, parent and member splitting.

### When the regression gate fails

A property degraded. Find its real type. Accepting `unknown` is not a fix, and neither is restoring the old type,
which can be wrong too. When `TrackingEvent.status` degraded, its old `string` hid a broken cast, and the real type was
`ShipmentStatusType`.

## `unimportable-token-gate.sh`

The gate runs `npx tsc --noEmit` over each generated tree and counts these diagnostics in files under `workbench/` or
`tests/`:

- **TS2304 and TS2552 (cannot find name)**: a token emitted without its import. TS2552 replaces TS2304 when a similar
  name exists.
- **TS2305 and TS2724 (no exported member)**: an imported name its module does not export.
- **TS2300 (duplicate identifier)**: two imports resolving to one local name, which
  [`ImportNameRegistry`](../components/import-name-registry.md) exists to prevent.
- **TS2440**: an import colliding with a local declaration of the same name.
- **TS2344 (does not satisfy the constraint)**: a token used where its type rejects it, such as a
  [`Pick<Model, K>`](../components/resource-ast-analyzer.md#when-a-pick-reference-is-emitted) key the model interface
  does not declare.
- **TS6196 (declared but never used)**: an unused type import, the trace a dropped `extends` clause or an overridden
  cast leaves.

A leaked `toResource()` convention guess, the failure `PublishedResourceRegistry` prevents, shows up as TS2305 or
TS2724 in the modular files, or as a relative TS2307 when the run writes nothing to the guessed class's directory. In
`laravel-ts-global.ts` it shows up as TS2304 or TS2552. The gate counts each of them. The analyzer page covers
[guess gating](../components/resource-ast-analyzer.md#toresource-convention-guesses-are-gated-on-the-published-set).
TS2307 has [its own two counts](#the-ts2307-sub-gates), and a leaked DOM-named token has
[a count of its own](#the-dom-global-count).

`tsconfig.json` checks `default-example`, and `tsconfig.testing.json`, `tsconfig.full-template-example.json` and
`tsconfig.split-template-example.json` check the other trees, one program each, because every tree's
`laravel-ts-global.ts` declares the same globals. `TSCONFIGS` takes a space-separated list of configs, and the gate
fails if any of them fails.

The testing tree includes `analysis-probe/`, which `tests/Feature/AnalyzeApiProbeTest.php` writes from
`AstEngine::analyze()`'s result with nothing in between to repair an import, so this gate also checks that what
`analyze()` returns compiles. `"skipLibCheck": false` makes `tsc` check every `.d.ts` body, including the ones the
trees ship, and `node_modules` too, which is why the counts read only `workbench/` and `tests/` paths.

Each argument is a baseline that arms one more gate, and fewer arguments leave the rest report-only:

```bash
.github/scripts/unimportable-token-gate.sh          # report only
.github/scripts/unimportable-token-gate.sh 0        # gate the main count and the DOM-global count
.github/scripts/unimportable-token-gate.sh 0 0      # also gate relative-specifier TS2307s
.github/scripts/unimportable-token-gate.sh 0 0 0    # also gate bare-specifier TS2307s, as CI does
TSCONFIGS=tsconfig.testing.json .github/scripts/unimportable-token-gate.sh 0 0 0
```

Every baseline is `0`, and none has a legitimate non-zero cause, so raising one is never the fix. Each tree prints its
counts, a histogram of the names behind them, and a `PASS` or `FAIL` line per armed gate. A `FAIL` line is followed by
the diagnostics behind it. A zero main or DOM-global count still prints one histogram line, `1` with no name after it,
because `uniq -c` counts the empty match. That line is not a diagnostic.

### The DOM-global count

`tsconfig.json` sets no `lib`, so `tsc` loads the DOM lib, and a leaked token named like a DOM global, such as
`Comment`, binds to that global and compiles. The gate runs each config again with `--lib esnext` and counts each
cannot-find-name that only this second program reports, minus the names in `DOM_GLOBALS`. The package writes one DOM
name without an import on purpose, `File`, for an uploaded file in a form request, and `DOM_GLOBALS` lists it. A
fixture that maps a type to another DOM name, such as the shipped config's commented `'binary' => 'Blob'`, adds it to
`DOM_GLOBALS` by hand. `Date`, `Record`, `Pick` and the other names the package writes without an import come from the
ES lib, which both programs load. The first argument arms this count with no baseline of its own, so any reading above
`0` fails, after the TS2307 sub-gates report.

### The TS2307 sub-gates

The second and third arguments gate TS2307 (cannot find module) in two counts, kept apart on purpose:

- **Relative specifier (`./`, `../`)**: resolves only against a file this package writes, so an unresolved one is
  always a defect, an import of a file or directory the run never writes.
- **Bare specifier (`@/types/geo`)**: names a module in the consuming app, which a stub stands in for. An unresolved
  one needs a new stub, or is an import nothing should have emitted.

The fixes differ, fixing the emitter or adding a stub, and one pooled count would let a new broken relative import
hide inside an unrelated change to the bare count.

### The app-side stubs

The trees import modules that belong to the consuming app, named by `#[TsCasts]`, `#[TsExtends]` and `#[TsType]`. The
`paths` in `tsconfig.json` point those aliases at hand-written stubs:

| Alias | Stub |
| --- | --- |
| `@/types/*` | `tests/types/stubs/app/*.d.ts` |
| `@js/types/*` | `tests/types/stubs/js/*.d.ts` |
| `@workbench/types` | `tests/types/stubs/workbench/index.d.ts` |
| None: bare global names | `tests/types/stubs/globals.d.ts` |

Because the stubs resolve, the gate checks identity rather than a total: an unresolved module, a name a stub does not
export, or a bad `Pick` key fails at once. A total could not, since one old diagnostic disappearing while a new leak
appeared kept it level. Three rules keep the stubs honest:

1. **Each module declares exactly the names the trees import**: no wildcard `declare module`, no `any`, and nothing
   that makes an arbitrary specifier resolve. Nothing checks this by machine, so widening a stub to silence a TS2305
   moves the leak instead of fixing it.
2. **The stubs are written by hand, never generated from the output**: stubs derived from the trees would check the
   trees against themselves and always pass.
3. **Members exist only where a type operator needs them**: `Auditable` and `Routable` carry the keys the trees
   `Pick`, because `Pick<T, K>` requires `K extends keyof T`, and `Timestamps` carries the columns the trees `Omit`.
   Everything else is an empty `interface`, which an `extends` position needs. Members are typed `unknown`, since the
   package does not know the app's shape, and a derived interface can still redeclare one with a real type.

`globals.d.ts` needs `export {}` beside `declare global { … }`, because a global augmentation is legal only in a
module (TS2669 otherwise), and `moduleDetection: "force"` never makes a declaration file one. It declares
`CustomObject` and `ExtendableInterface`, which the trees emit bare because their sources, a docblock array shape and a
`#[TsExtends]` with no import argument, carry no FQCN or path. When a fixture imports a new app-side name, add it to a
stub by hand. Raising a baseline instead would let a swapped token through.

### Proving each gate fires

After changing the script, plant a defect for each gate and check that it exits `1` on the expected line. Each
control's last command restores what it changed.

A module nothing stubs fails the bare-specifier gate:

```bash
mv tests/types/stubs/app/geo.d.ts /tmp/geo.bak
.github/scripts/unimportable-token-gate.sh 0 0 0   # FAIL - bare-specifier TS2307 count rose from 0 to …
mv /tmp/geo.bak tests/types/stubs/app/geo.d.ts
```

A name the stub does not export fails the main count with TS2305, or TS2724 if you rename the export instead:

```bash
printf 'export interface GeoPoint {}\n' > tests/types/stubs/app/geo.d.ts
.github/scripts/unimportable-token-gate.sh 0 0 0   # FAIL - token count rose from 0 to …, histogram GeoBounds
git checkout -- tests/types/stubs/app/geo.d.ts
```

A `Pick` key the stub does not declare fails the main count with TS2344, once per `Pick<Routable, …>` site. The
histogram prints these as whole diagnostics, because its `sed` patterns match only the cannot-find-name,
duplicate-identifier, local-conflict, no-exported-member and never-used messages:

```bash
printf 'export interface Routable {\n    update: unknown;\n}\n' > tests/types/stubs/app/routing.d.ts
.github/scripts/unimportable-token-gate.sh 0 0 0   # FAIL - token count rose from 0 to …
git checkout -- tests/types/stubs/app/routing.d.ts
```

An import colliding with a local declaration fails the main count with TS2440. The collision has to be in a generated
file, so this control edits the committed tree:

```bash
printf '\nexport interface GeoPoint {\n    lat: unknown;\n}\n' \
  >> workbench/resources/js/types/data/default-example/app/http/resources/address.ts
.github/scripts/unimportable-token-gate.sh 0 0 0   # FAIL - token count rose from 0 to 1, histogram GeoPoint
git checkout -- workbench/resources/js/types/data/default-example/app/http/resources/address.ts
```

An import of a missing file fails the relative-specifier gate:

```bash
printf "import type { Nope } from './deliberately-missing';\nexport type Control = Nope;\n" > tests/types/relative-subgate-control.ts
.github/scripts/unimportable-token-gate.sh 0 0 0   # FAIL - relative-specifier TS2307 count rose from 0 to 1
rm tests/types/relative-subgate-control.ts
```

A leaked `Comment` fails the DOM-global count, which allows the `File` beside it:

```bash
printf "export interface Control {\n    node: Comment;\n    upload: File;\n}\n" > tests/types/dom-global-control.ts
.github/scripts/unimportable-token-gate.sh 0 0 0   # FAIL - 1 token(s) named like a DOM global…, histogram Comment
rm tests/types/dom-global-control.ts
```

`--selftest` automates the relative-specifier control, for a `.ts` and a `.d.ts` file, and the DOM-global control,
and CI runs it on every push that runs the workflow:

```bash
.github/scripts/unimportable-token-gate.sh --selftest
```

A negative baseline fails with nothing to clean up, but it exercises only the comparison, not the `tsc` run or the
pattern that feeds it, so it does not replace the controls above:

```bash
.github/scripts/unimportable-token-gate.sh 0 -1 0   # FAIL - relative-specifier TS2307 count rose from -1 to 0
```

### Fails closed

A `tsc` run that checks nothing prints no diagnostics, which a count would read as a pass. So the gate fails when
`tsc` prints an error with no `file(line,col):` prefix, such as a config error, when it exits non-zero with no
diagnostic, or when it reports a syntax error (TS1xxx), after which nothing is type-checked. The DOM-less program gets
the same setup check. The gate still passes when `tsc` checks fewer files than intended, as long as it checks some.

### When the token gate fails

A token reached the output without its import, or two names collided, and the generated TypeScript does not compile.
Never add the import by hand. Make the type resolution that produced the token carry its FQCN through to the import
machinery, or degrade to `unknown`. The usual cause is a `return` that fires before a guard, and the durable fix is one
check over all fields that accepts or rejects them together, rather than two reordered branches.

## The `@tolki/ts` type guard

`tests/types/tolki-assertions.ts` fails the build when the types `@tolki/ts` exports degrade to `any`. Releases 0.2.0
and 1.0.1 shipped `dist/enums.d.ts` and `dist/routes.d.ts` importing `'../packages/types/src/index.ts'`, a path
missing from the tarball, and `dist/index.d.ts` re-exports only those two files. Under `skipLibCheck: true` the failed
import is silent and every exported type is `any`, so `AsEnum<typeof Status>` accepted any property.

The guard puts `@ts-expect-error` on deliberate constraint violations, `AsEnum<string>` and `RouteCallResult<number>`.
Real types raise TS2344 there, which satisfies the directive, while `any` raises nothing, so TypeScript reports TS2578
(unused `@ts-expect-error` directive), which survives `any`. An `IsAny<T>` conditional cannot do this job, because a
failed import propagates an error type through the conditional, so the assertion passes in the state it should catch.

The token gate does not count TS2578. CI's `Gate - the @tolki/ts type surface resolves` step fails when
`tsc --listFiles` does not include `tolki-assertions.ts`, so keep it in the `include` of `tsconfig.json`, and it fails
on any diagnostic under `tests/types/`. The same check runs locally:

```bash
npx tsc --noEmit -p tsconfig.json 2>&1 | grep "^tests/types/"   # must print nothing
```

`patches/@tolki+ts+1.0.1.patch`, applied by `patch-package` from the `postinstall` hook, rewrote both imports to
`@tolki/types`. `@tolki/ts` 1.0.2, the locked version, ships that fix, so the patch changes nothing. `patch-package`
finds the change already present and only warns about the version mismatch. If the guard step fails, suspect the
installed `@tolki/ts` release, whatever its failure message says about the patch. Keep `@tolki/types` as a direct
dependency whether or not the patch stays, because the trees import its paginator and collection types. To check
whether a release carries the broken import, read its pristine tarball in a temporary directory, not `node_modules`,
where `postinstall` has already run:

```bash
cd "$(mktemp -d)" && npm pack @tolki/ts@1.0.2 && tar xzf tolki-ts-1.0.2.tgz && head -1 package/dist/enums.d.ts
```

## What the gates do not cover

The gates miss these cases:

- **Shapes no fixture produces**: both gates read the workbench corpus, so a defect that only an unexercised shape
  reaches passes both. When you add an inference path, add a fixture for its hazardous shape too.
- **A leaked token named like `File` or an ES-lib global**: the DOM-global count skips `File` everywhere, and names
  such as `Error`, `Map`, `Date` and `Promise` bind to the ES lib in both programs, so a class with one of those names
  emitted without its import passes every count. No workbench class has such a name.
- **Diagnostics outside the counted codes**: the main count reads only the codes listed above. TS2307 and the TS1xxx
  syntax errors fail the gate on their own, but `tsc` can reject a file with any other code and the gate still passes.
  Examples are TS6133 (an unused value import), TS6192 (an import line of two or more names, none used) and TS2308 (a
  barrel that re-exports one name from two files). Two enums in one namespace, one named like the other's type name,
  produce TS2308, as [its known gap](../known-gaps.md#an-enum-named-like-another-enums-type-name-collides-with-it)
  describes.
- **A property that disappears**: the regression gate walks only the keys present at the head, so a removed property
  produces no signal, and a property that moves to another interface is a removal plus an addition. When a change can
  remove a property, such as excluding columns, widening `$hidden` or deleting a fixture, diff the regenerated trees
  and account for each property that disappears. A removal check would have to report rather than fail, since removing
  a property is often correct.
- **An object that already holds an `unknown` member**: `detect_regressions()` tests the base side with
  `"unknown" not in b[k]` on the parent's whole rendered value, so one `unknown` member disarms the parent key, and
  when the object then collapses to `unknown` no member key is left to match.
- **Reordered union arms**: `prop[i]` indexes arms by position, so swapping two arms can mask a regression. This limit
  is accepted. Do not fix it with content-hashed arm keys.

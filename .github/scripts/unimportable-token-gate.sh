#!/usr/bin/env bash
# Standing gate: the generated TypeScript must not gain any new "Cannot find
# name" (TS2304, or TS2552 when a similarly-named global exists) error — that
# is precisely the signature of a type token emitted without its import. It
# also counts TS2300 "Duplicate identifier" errors: two different imports
# resolving to the same local name, e.g. two unrelated Rate models both
# aliased to RateRate (see docs/components/import-name-registry.md).
# TS2440 is that same collision against a *local* declaration rather than a
# second import, which TypeScript reports under its own code and not as TS2300.
# TS2344 rounds out the set: a token that IS imported but named where its own
# type rejects it, e.g. Pick<Model, K> over a key the interface never declares.
# TS2305/TS2724 cover the name that IS imported from a module that resolves but
# never exports it - the shape that only became reachable once the stubs landed.
# TS6196 (a type import never used), TS6133 (a value import, local or parameter never read) and TS6192 (an import line
# with no name used) catch the import a dropped extends clause or an overridden cast leaves behind.
# TS2320 is one interface extending two that declare the same key with different types, as a model's
# combined interface does when a column and a relation share a name.
#
# TS18016 is a leaked open marker, `#$open: unknown`. The publish strips every marker and `$gapN` arm, so a leak count
# also greps the files tsc lists for both, which catches a spelling tsc accepts, such as one in a comment.
#
# A leaked token named like a DOM global (`Comment`) binds to the DOM type and raises none of those, so a second
# program per tree runs without the DOM lib and counts, on its own line, each name only it cannot find, minus
# DOM_GLOBALS. The first argument arms that count too.
#
# The unknown-regression gate cannot catch either shape: a leaked or colliding
# token is a NEW property with a plausible-looking type, not an existing
# property degrading to `unknown`.
#
# Every baseline is 0. The app-side modules the generated tree imports (`#[TsCasts]` /
# `#[TsExtends]` targets) are stubbed under tests/types/stubs and wired up by tsconfig
# `paths`, so an unresolved module is now always a defect rather than a known absence.
#
# Runs once per generated tree, each as its own tsc program (see TSCONFIGS below): every tree's
# laravel-ts-global.ts declares the same names under `declare global`, so merging trees into one
# program reports well over a hundred TS2300 duplicate identifiers - a fixture artifact, not a defect.
#
# Usage: unimportable-token-gate.sh [BASELINE_COUNT] [RELATIVE_BASELINE] [BARE_BASELINE]
#        With no argument it prints the current counts and the offending names. RELATIVE_BASELINE
#        gates TS2307s whose specifier is relative (./ or ../) - a module this package itself writes.
#        BARE_BASELINE gates every other TS2307 - a bare specifier such as @/types/geo, now backed by
#        a stub. All three are 0 and none has a legitimate non-zero cause, so raising any of them is
#        never the fix: a rise means a token leaked, a name moved, or a stub needs the new export
#        added by hand. Nothing machine-checks that a stub declares only the names the tree actually
#        imports, so widening one to silence a TS2305 is that same escape relocated, not a fix.
#        Each argument activates its own gate; passing fewer leaves the rest
#        report-only, same as BASELINE_COUNT and RELATIVE_BASELINE already did before BARE_BASELINE.
#
#        TSCONFIGS (env var, space-separated) selects which tsconfig files the gate runs against;
#        it defaults to all four generated trees (tsconfig.json plus the three tsconfig.<tree>.json
#        files above). Set it to one file to gate a single tree in isolation while debugging.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

# DOM-lib names this package writes on purpose without an import, as an extended regex alternation. `File` is
# FormRequestRulesAnalyzer's type for an uploaded file. A fixture that maps a type to another DOM name (the shipped
# config's commented `'binary' => 'Blob'`) adds that name here by hand; any other DOM-only name is a leaked token.
DOM_GLOBALS='File'

if [[ "${1:-}" == "--selftest" ]]; then
  # Plant an unresolvable relative import in a .ts and a .d.ts, run the real gate, demand it FAILs
  # on the relative sub-gate, clean up. A gate that cannot fail is not a gate.
  ts="tests/types/__selftest_relative.ts"; dts="tests/types/__selftest_relative.d.ts"; dom="tests/types/__selftest_dom.ts"
  clash="tests/types/__selftest_clash.ts"
  unused="tests/types/__selftest_unused.ts"; unused_dep="tests/types/__selftest_unused_dep.ts"
  leak="tests/types/__selftest_leak.ts"; noted="tests/types/__selftest_leak_comment.ts"
  near="tests/types/__selftest_near.ts"
  trap 'rm -f "$ts" "$dts" "$dom" "$clash" "$unused" "$unused_dep" "$leak" "$noted" "$near"' EXIT
  for scratch in "$ts" "$dts"; do
    printf "import type { Nope } from './does-not-exist';\nexport type SelfTest = Nope;\n" > "$scratch"
    if TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1; then
      echo "FAIL - selftest: the gate passed with an unresolvable relative import in $scratch"
      cat /tmp/token-gate-selftest.out; exit 1
    fi
    # The report line always says "... relative specifier ...: 0" whether or not it fired, so a bare
    # substring match is vacuous. Require a nonzero count and a FAIL line naming the relative gate,
    # so a different guard failing instead can never read as this sub-gate's success.
    if ! grep -qE "relative specifier in generated tree: [1-9]" /tmp/token-gate-selftest.out \
        || ! grep -qE "^FAIL.*relative" /tmp/token-gate-selftest.out; then
      echo "FAIL - selftest: the gate failed, but not on the relative sub-gate, for $scratch"
      cat /tmp/token-gate-selftest.out; exit 1
    fi
    rm -f "$scratch"
  done
  echo "PASS - selftest: relative-specifier sub-gate fires for .ts and .d.ts"

  # A leaked `Comment` compiles against the DOM, so every other count stays 0 and only the DOM sub-gate can fire. The
  # same file names `File`, which DOM_GLOBALS allows, so the count must be exactly 1 and the histogram must not name it.
  printf "export interface SelfTestDom {\n    node: Comment;\n    upload: File;\n}\n" > "$dom"
  if TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1; then
    echo "FAIL - selftest: the gate passed with a DOM-named token and no import in $dom"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  if ! grep -qE "names only the DOM lib declares, in generated tree: 1$" /tmp/token-gate-selftest.out \
      || ! grep -qE "^FAIL.*DOM" /tmp/token-gate-selftest.out \
      || ! grep -qE "^ +1 +Comment$" /tmp/token-gate-selftest.out \
      || grep -qE "^ +[0-9]+ +File$" /tmp/token-gate-selftest.out; then
    echo "FAIL - selftest: the gate failed, but not on the DOM sub-gate for Comment alone, for $dom"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  rm -f "$dom"
  echo "PASS - selftest: DOM-global sub-gate fires for a leaked Comment and allows File"

  # An interface extending two that give one key different types is TS2320, which only the main count reads: it must
  # count exactly 1, and the histogram must name that interface.
  cat > "$clash" <<'TS'
interface Left {
    key: string;
}
interface Right {
    key: number;
}
export interface SelfTestClash extends Left, Right {}
TS
  if TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1; then
    echo "FAIL - selftest: the gate passed with an interface extending two that clash, in $clash"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  if ! grep -qE "^TS2300/.* in generated tree: 1$" /tmp/token-gate-selftest.out \
      || ! grep -qE "^FAIL - token count" /tmp/token-gate-selftest.out \
      || ! grep -qE "^ +1 +SelfTestClash$" /tmp/token-gate-selftest.out; then
    echo "FAIL - selftest: the gate failed, but not on the main count for SelfTestClash alone, for $clash"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  rm -f "$clash"
  echo "PASS - selftest: the main count fires for an interface extending two that clash (TS2320)"

  # An import line whose every name goes unused is TS6192, whose message names nothing: the main count must read
  # exactly 1, and the histogram must print the code's own label.
  printf 'export interface SelfTestAlpha {}\nexport interface SelfTestBeta {}\n' > "$unused_dep"
  printf "import type { SelfTestAlpha, SelfTestBeta } from './__selftest_unused_dep';\nexport type SelfTestUnused = string;\n" > "$unused"
  if TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1; then
    echo "FAIL - selftest: the gate passed with an import line whose names all go unused, in $unused"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  if ! grep -qE "^TS2300/.* in generated tree: 1$" /tmp/token-gate-selftest.out \
      || ! grep -qE "^FAIL - token count" /tmp/token-gate-selftest.out \
      || ! grep -qE "^ +1 +\(import line with no name used\)$" /tmp/token-gate-selftest.out; then
    echo "FAIL - selftest: the gate failed, but not on the main count for one unused import line, for $unused"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  rm -f "$unused" "$unused_dep"
  echo "PASS - selftest: the main count fires for an import line with no name used (TS6192)"

  # A leaked gap arm is TS2304 and a leaked open marker TS18016, so the main count and the leak count must each read 2.
  cat > "$leak" <<'TS'
export type SelfTestGap = string | $gap1;
export interface SelfTestOpen {
    k: string;
    #$open: unknown;
}
TS
  if TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1; then
    echo "FAIL - selftest: the gate passed with a leaked gap arm and open marker in $leak"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  if ! grep -qE '^TS2300/.* in generated tree: 2$' /tmp/token-gate-selftest.out \
      || ! grep -qE '^Gap arms .* in generated tree: 2$' /tmp/token-gate-selftest.out \
      || ! grep -qE '^FAIL - token count' /tmp/token-gate-selftest.out \
      || ! grep -qE '^ +1 +\(private identifier outside a class body\)$' /tmp/token-gate-selftest.out \
      || ! grep -qE '^ +1 +#\$open$' /tmp/token-gate-selftest.out; then
    echo "FAIL - selftest: the gate failed, but not on both counts for one gap arm and one open marker, for $leak"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  rm -f "$leak"
  echo "PASS - selftest: a leaked gap arm and open marker fail the main count (TS2304, TS18016) and the leak count"

  # In a comment tsc accepts both spellings, so every tsc count stays 0 and only the leak count can fail the gate.
  printf '// string | $gap1, { #$open: unknown }\nexport type SelfTestNoted = string;\n' > "$noted"
  if TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1; then
    echo "FAIL - selftest: the gate passed with a gap arm and open marker in a comment in $noted"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  if ! grep -qE '^TS2300/.* in generated tree: 0$' /tmp/token-gate-selftest.out \
      || ! grep -qE '^Gap arms .* in generated tree: 2$' /tmp/token-gate-selftest.out \
      || ! grep -qE '^FAIL - 2 gap arm' /tmp/token-gate-selftest.out; then
    echo "FAIL - selftest: the gate failed, but not on the leak count alone, for $noted"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  rm -f "$noted"
  echo "PASS - selftest: the leak count alone fails a gap arm and open marker that tsc accepts"

  # Keys the publish may print, `$gap` with no digits and `$open`, are not leaks: the gate must pass and read 0.
  printf 'export interface SelfTestNear {\n    $gap: string;\n    $open: unknown;\n}\n' > "$near"
  if ! TSCONFIGS=tsconfig.json "$0" 0 0 0 > /tmp/token-gate-selftest.out 2>&1 \
      || ! grep -qE '^Gap arms .* in generated tree: 0$' /tmp/token-gate-selftest.out \
      || ! grep -qE '^PASS - no gap arm' /tmp/token-gate-selftest.out; then
    echo "FAIL - selftest: the gate did not pass the near spellings, the keys \$gap and \$open, in $near"
    cat /tmp/token-gate-selftest.out; exit 1
  fi
  rm -f "$near"
  echo "PASS - selftest: a tree with only near spellings passes with no leak"
  exit 0
fi

have_baseline=0
have_relative_baseline=0
have_bare_baseline=0
if [ $# -ge 1 ]; then baseline=$1; have_baseline=1; fi
if [ $# -ge 2 ]; then relative_baseline=$2; have_relative_baseline=1; fi
if [ $# -ge 3 ]; then bare_baseline=$3; have_bare_baseline=1; fi

# "file(line,col) name" for each cannot-find-name diagnostic in a file this package writes, sorted for comm.
missing_names() {
  printf '%s\n' "$1" | grep -E "^(workbench|tests)/" | grep -E "error TS(2304|2552): Cannot find name '" \
    | sed -E "s/^([^:]+): error TS[0-9]+: Cannot find name '([^']+)'.*/\1 \2/" | LC_ALL=C sort -u
}

# One tsc program per generated tree - see the TSCONFIGS comment above for why they cannot be merged.
gate_one() {
  local cfg="$1"
  echo "== $cfg =="

  local out status
  out=$(npx tsc --noEmit -p "$cfg" 2>&1)
  status=$?

  # Without these the gate fails OPEN: a tsc that never type-checked anything emits no TS2304 lines,
  # which reads as a pass. Config and CLI failures print unanchored, while a real diagnostic always
  # carries its `file(line,col):` prefix.
  local setup_errs
  setup_errs=$(printf '%s\n' "$out" | grep -E "^error TS[0-9]+" || true)

  if [ -n "$setup_errs" ] || { [ "$status" -ne 0 ] && ! printf '%s\n' "$out" | grep -qE "error TS[0-9]+"; }; then
    echo "FAIL - tsc exited $status without type-checking the generated tree, so there is nothing to gate on"
    printf '%s\n' "$out"
    return 1
  fi

  # The other fail-open shape, and the one that actually happened: a SYNTAX error in a generated file.
  # tsc emits no semantic diagnostics for a program it could not parse, so every count below reads 0
  # and all three sub-gates PASS on a tree nothing checked. TS1xxx is the syntactic range.
  local syntax_errs
  syntax_errs=$(printf '%s\n' "$out" | grep -E "error TS1[0-9]{3}:" || true)

  if [ -n "$syntax_errs" ]; then
    echo "FAIL - the generated tree does not parse, so nothing after the parse error was type-checked"
    printf '%s\n' "$syntax_errs"
    return 1
  fi

  # node_modules declarations are checked now too; count only diagnostics in files this package writes.
  local owned
  owned=$(printf '%s\n' "$out" | grep -E "^(workbench|tests)/" || true)

  local errs count
  errs=$(printf '%s\n' "$owned" | grep -E "error TS(2300|2304|2305|2320|2344|2440|2552|2724|6133|6192|6196|18016)" || true)
  count=$(printf '%s' "$errs" | grep -c . || true)

  echo "TS2300/TS2304/TS2305/TS2320/TS2344/TS2440/TS2552/TS2724/TS6133/TS6192/TS6196/TS18016 (duplicate identifier / cannot find name / unexported name / clashing extends / bad type argument / import-local conflict / value import, local or parameter never read / import line with no name used / unused type import / private identifier outside a class body) in generated tree: $count"
  if [ "$count" -gt 0 ]; then
    printf '%s\n' "$errs" | sed -E "s/.*(Cannot find name|Duplicate identifier|conflicts with local declaration of) '([^']+)'.*/  \2/; s/.*has no exported member (named )?'([^']+)'.*/  \2/; s/.*'([^']+)' is declared but (never used|its value is never read)\.?.*/  \1/; s/.*Interface '([^']+)' cannot simultaneously extend.*/  \1/; s/.*All imports in import declaration are unused.*/  (import line with no name used)/; s/.*Private identifiers are not allowed outside class bodies.*/  (private identifier outside a class body)/" | sort | uniq -c | sort -rn
  fi

  # A relative specifier (./ or ../) only ever resolves against a file this package itself writes, so an
  # unresolved one is never an app-side alias a stub could cover - it is
  # always the failure mode PublishedResourceRegistry exists to prevent. Kept as its own zero-tolerance
  # count rather than folded into the bare one below: pooling the two would let a new broken relative
  # import hide inside ordinary bare-alias churn, invisible until the combined total crossed baseline.
  local rel_errs rel_count
  rel_errs=$(printf '%s\n' "$owned" | grep -E "error TS2307" | grep -E "Cannot find module '\.{1,2}/" || true)
  rel_count=$(printf '%s' "$rel_errs" | grep -c . || true)

  echo "TS2307 (cannot find module) with a relative specifier in generated tree: $rel_count"
  if [ "$rel_count" -gt 0 ]; then
    printf '%s\n' "$rel_errs" | sed -E "s/.*Cannot find module '([^']+)'.*/  \1/"
  fi

  # Everything else with TS2307: a bare specifier such as @/types/geo, naming a module that lives in
  # the consuming app. Those are stubbed under tests/types/stubs and mapped by tsconfig `paths`, so an
  # unresolved one means a new alias needs a stub, or an import was emitted that nothing should have.
  local bare_errs bare_count
  bare_errs=$(printf '%s\n' "$owned" | grep -E "error TS2307" | grep -vE "Cannot find module '\.{1,2}/" || true)
  bare_count=$(printf '%s' "$bare_errs" | grep -c . || true)

  echo "TS2307 (cannot find module) with a bare specifier in generated tree: $bare_count"
  if [ "$bare_count" -gt 0 ]; then
    printf '%s\n' "$bare_errs" | sed -E "s/.*Cannot find module '([^']+)'.*/  \1/"
  fi

  # The same program without the DOM lib. A name it cannot find that the program above found is one only the DOM
  # declares; unless DOM_GLOBALS lists it, it is a token emitted without its import that bound to the DOM's type.
  local nodom_out nodom_status
  nodom_out=$(npx tsc --noEmit -p "$cfg" --lib esnext 2>&1)
  nodom_status=$?

  if printf '%s\n' "$nodom_out" | grep -qE "^error TS[0-9]+" \
      || { [ "$nodom_status" -ne 0 ] && ! printf '%s\n' "$nodom_out" | grep -qE "error TS[0-9]+"; }; then
    echo "FAIL - tsc without the DOM lib exited $nodom_status without type-checking the generated tree"
    printf '%s\n' "$nodom_out"
    return 1
  fi

  local dom_errs dom_count
  dom_errs=$(LC_ALL=C comm -13 <(missing_names "$out") <(missing_names "$nodom_out") | grep -vE " ($DOM_GLOBALS)$" || true)
  dom_count=$(printf '%s' "$dom_errs" | grep -c . || true)

  echo "TS2304/TS2552 (cannot find name) for names only the DOM lib declares, in generated tree: $dom_count"
  if [ "$dom_count" -gt 0 ]; then
    printf '%s\n' "$dom_errs" | sed -E 's/.* //' | sort | uniq -c | sort -rn
  fi

  # Every file under workbench/ or tests/ that tsc reads. An empty list would read as no leak, so it fails closed.
  local files
  files=$(npx tsc -p "$cfg" --listFilesOnly 2>&1 | sed "s|^$PWD/||" | grep -E "^(workbench|tests)/" || true)

  if [ -z "$files" ]; then
    echo "FAIL - tsc listed no file under workbench/ or tests/, so there is nothing to grep for a leaked gap or marker"
    return 1
  fi

  local leak_errs leak_count
  leak_errs=$(printf '%s\n' "$files" | tr '\n' '\0' | xargs -0 grep -HnoE '\$gap[0-9]+|#\$open' || true)
  leak_count=$(printf '%s' "$leak_errs" | grep -c . || true)

  echo "Gap arms (\$gapN) and open markers (#\$open) in generated tree: $leak_count"
  if [ "$leak_count" -gt 0 ]; then
    printf '%s\n' "$leak_errs" | sed -E 's/.*://' | sort | uniq -c | sort -rn
  fi

  if [ "$have_baseline" -eq 1 ]; then
    if [ "$count" -gt "$baseline" ]; then
      echo "FAIL - token count rose from $baseline to $count: a token lost its import, two names or keys clash, a type argument is rejected, an import, local or parameter goes unused, or an open marker leaked"
      printf '%s\n' "$errs"
      return 1
    fi
    echo "PASS - no new unimportable or colliding tokens (baseline $baseline)"

    # No baseline of its own: DOM_GLOBALS already holds every DOM name this package means to write. A failure here
    # still lets the TS2307 sub-gates report below before the tree fails.
    local dom_failed=0
    if [ "$dom_count" -gt 0 ]; then
      echo "FAIL - $dom_count token(s) named like a DOM global were emitted without their import, so they compile against the DOM's own type"
      printf '%s\n' "$dom_errs"
      dom_failed=1
    else
      echo "PASS - no token named like a DOM global emitted without its import"
    fi

    # No baseline either: the publish strips every gap arm and open marker, so any reading is a leak.
    local leak_failed=0
    if [ "$leak_count" -gt 0 ]; then
      echo "FAIL - $leak_count gap arm(s) or open marker(s) reached the generated tree, which the publish always strips"
      printf '%s\n' "$leak_errs"
      leak_failed=1
    else
      echo "PASS - no gap arm or open marker in the generated tree"
    fi

    if [ "$have_relative_baseline" -eq 1 ]; then
      if [ "$rel_count" -gt "$relative_baseline" ]; then
        echo "FAIL - relative-specifier TS2307 count rose from $relative_baseline to $rel_count: an import points at a file this package never writes"
        printf '%s\n' "$rel_errs"
        return 1
      fi
      echo "PASS - no new relative-specifier TS2307s (baseline $relative_baseline)"

      if [ "$have_bare_baseline" -eq 1 ]; then
        if [ "$bare_count" -gt "$bare_baseline" ]; then
          echo "FAIL - bare-specifier TS2307 count rose from $bare_baseline to $bare_count: an import points at a module nothing declares"
          printf '%s\n' "$bare_errs"
          return 1
        fi
        echo "PASS - no new bare-specifier TS2307s (baseline $bare_baseline)"
      fi
    fi

    if [ "$dom_failed" -eq 1 ] || [ "$leak_failed" -eq 1 ]; then
      return 1
    fi
  fi

  return 0
}

rc=0
for cfg in ${TSCONFIGS:-tsconfig.json tsconfig.testing.json tsconfig.full-template-example.json tsconfig.split-template-example.json}; do
  gate_one "$cfg" || rc=1
done
exit $rc

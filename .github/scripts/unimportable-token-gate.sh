#!/usr/bin/env bash
# Standing gate: the generated TypeScript must not gain any new "Cannot find
# name" (TS2304, or TS2552 when a similarly-named global exists) error — that
# is precisely the signature of a type token emitted without its import. It
# also counts TS2300 "Duplicate identifier" errors: two different imports
# resolving to the same local name, e.g. two unrelated MailPrice models both
# aliased to MailPriceMailPrice (see docs/components/import-name-registry.md).
# TS2440 is that same collision against a *local* declaration rather than a
# second import, which TypeScript reports under its own code and not as TS2300.
# TS2344 rounds out the set: a token that IS imported but named where its own
# type rejects it, e.g. Pick<Model, K> over a key the interface never declares.
# TS2305/TS2724 cover the name that IS imported from a module that resolves but
# never exports it - the shape that only became reachable once the stubs landed.
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

have_baseline=0
have_relative_baseline=0
have_bare_baseline=0
if [ $# -ge 1 ]; then baseline=$1; have_baseline=1; fi
if [ $# -ge 2 ]; then relative_baseline=$2; have_relative_baseline=1; fi
if [ $# -ge 3 ]; then bare_baseline=$3; have_bare_baseline=1; fi

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
  errs=$(printf '%s\n' "$owned" | grep -E "error TS(2300|2304|2305|2344|2440|2552|2724)" || true)
  count=$(printf '%s' "$errs" | grep -c . || true)

  echo "TS2300/TS2304/TS2305/TS2344/TS2440/TS2552/TS2724 (duplicate identifier / cannot find name / unexported name / bad type argument / import-local conflict) in generated tree: $count"
  printf '%s\n' "$errs" | sed -E "s/.*(Cannot find name|Duplicate identifier|conflicts with local declaration of) '([^']+)'.*/  \2/; s/.*has no exported member (named )?'([^']+)'.*/  \2/" | sort | uniq -c | sort -rn

  # A relative specifier (./ or ../) only ever resolves against a file this package itself writes, so an
  # unresolved one is never an app-side alias a stub could cover - it is
  # always the failure mode PublishedResourceRegistry exists to prevent. Kept as its own zero-tolerance
  # count rather than folded into the bare one below: pooling the two would let a new broken relative
  # import hide inside ordinary bare-alias churn, invisible until the combined total crossed baseline.
  local rel_errs rel_count
  rel_errs=$(printf '%s\n' "$owned" | grep -E "error TS2307" | grep -E "Cannot find module '\.{1,2}/" || true)
  rel_count=$(printf '%s' "$rel_errs" | grep -c . || true)

  echo "TS2307 (cannot find module) with a relative specifier in generated tree: $rel_count"
  printf '%s\n' "$rel_errs" | sed -E "s/.*Cannot find module '([^']+)'.*/  \1/"

  # Everything else with TS2307: a bare specifier such as @/types/geo, naming a module that lives in
  # the consuming app. Those are stubbed under tests/types/stubs and mapped by tsconfig `paths`, so an
  # unresolved one means a new alias needs a stub, or an import was emitted that nothing should have.
  local bare_errs bare_count
  bare_errs=$(printf '%s\n' "$owned" | grep -E "error TS2307" | grep -vE "Cannot find module '\.{1,2}/" || true)
  bare_count=$(printf '%s' "$bare_errs" | grep -c . || true)

  echo "TS2307 (cannot find module) with a bare specifier in generated tree: $bare_count"
  printf '%s\n' "$bare_errs" | sed -E "s/.*Cannot find module '([^']+)'.*/  \1/"

  if [ "$have_baseline" -eq 1 ]; then
    if [ "$count" -gt "$baseline" ]; then
      echo "FAIL - token count rose from $baseline to $count: a token was emitted without its import, or two imports collided on one name"
      printf '%s\n' "$errs"
      return 1
    fi
    echo "PASS - no new unimportable or colliding tokens (baseline $baseline)"

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
  fi

  return 0
}

rc=0
for cfg in ${TSCONFIGS:-tsconfig.json tsconfig.testing.json tsconfig.full-template-example.json tsconfig.split-template-example.json}; do
  gate_one "$cfg" || rc=1
done
exit $rc

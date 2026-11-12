#!/usr/bin/env bash
# The gap gate: with TS_PUBLISH_GAP_GATE set, every key of every body a `@return` shapes reads in the gap mode, as if
# its docblock typed it. Each frame strips its gaps before its own merge, so the generated trees must not change a byte.
#
# It resets the generated trees, runs the suite under the switch without the `gap-mode` group (the tests that pin which
# reads run in the mode), requires it green, requires the self-test (tests/Unit/Ast/GapGateTest.php) to pass rather
# than skip, diffs the trees against HEAD, and resets them again.
#
# Usage: .github/scripts/gap-gate.sh   (from anywhere in the repository; exit 0 PASS, 1 FAIL)
set -uo pipefail

cd "$(git rev-parse --show-toplevel)" || exit 1

trees=workbench/resources/js/types
selftest=tests/Unit/Ast/GapGateTest.php

reset_trees() {
    git checkout -- "$trees" && git clean -fdq -- "$trees"
}

strip_ansi() {
    sed 's/\x1b\[[0-9;]*[A-Za-z]//g'
}

reset_trees
status=0

if ! TS_PUBLISH_GAP_GATE=1 composer test -- --exclude-group=gap-mode; then
    echo "FAIL - the suite is not green under the gap gate"
    status=1
fi

if ! git diff --exit-code --stat -- "$trees"; then
    echo "FAIL - the gap gate changed the generated trees"
    status=1
fi

untracked=$(git ls-files --others --exclude-standard -- "$trees")

if [ -n "$untracked" ]; then
    printf 'FAIL - the gap gate wrote files the trees do not hold:\n%s\n' "$untracked"
    status=1
fi

# A skipped self-test would leave a green run that never switched the mode on.
self=$(TS_PUBLISH_GAP_GATE=1 composer test -- "$selftest" 2>&1 | strip_ansi)

if ! grep -Eq 'Tests:[[:space:]]+1 passed' <<<"$self"; then
    printf 'FAIL - the self-test did not pass under the gap gate:\n%s\n' "$self"
    status=1
fi

reset_trees

if [ "$status" -eq 0 ]; then
    echo "PASS - every key read in the gap mode leaves the generated trees byte-identical"
fi

exit "$status"

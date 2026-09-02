#!/usr/bin/env bash
# Docs cite symbols, not lines: `Foo.php:123` rots on the next edit to Foo.php and nothing notices - any
# path prefix or none (`src/Foo.php:123`, `workbench/config/Foo.php:123`, bare `Foo.php:123`) is covered.
# Generated-tree anchors (workbench/resources/js/types/**.ts) are exempt - they regenerate with the tree.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

hits=$(grep -rnE '\b[A-Za-z0-9_/.-]+\.php:[0-9]+' docs --include='*.md' --exclude-dir=superpowers || true)

if [ -n "$hits" ]; then
  echo "FAIL - docs cite PHP source by line number; cite the symbol instead:"
  echo "$hits"
  exit 1
fi

echo "PASS - no PHP line-number anchors in docs"

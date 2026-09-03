#!/usr/bin/env bash
# Publish-speed workload + A/B gate.
#   publish-bench.sh --local              3-run local timing report (no gate)
#   publish-bench.sh <baseDir> <headDir>  CI gate: fail when head_median > base_median * MAX_RATIO
set -euo pipefail

MAX_RATIO="${MAX_RATIO:-1.25}"
WORKLOAD='php -d memory_limit=-1 vendor/bin/pest tests/Feature/Commands/TsPublishCommandTest.php --filter="writes files to disk"'

if [[ "${1:-}" == "--local" ]]; then
  bash -c "$WORKLOAD" > /dev/null 2>&1 # warmup
  for i in 1 2 3; do
    s=$(php -r 'echo microtime(true);')
    bash -c "$WORKLOAD" > /dev/null 2>&1
    php -r "echo microtime(true) - $s, PHP_EOL;"
  done | sort -n | awk 'NR==2 {printf "publish-bench median: %.2fs (3 runs, warmup discarded)\n", $1}'
  exit 0
fi

if [[ $# -ne 2 ]]; then
  echo "usage: publish-bench.sh --local | <baseDir> <headDir>" >&2
  exit 2
fi

if ! command -v hyperfine > /dev/null 2>&1; then
  echo "FAIL - hyperfine is not installed. Install it (e.g. 'brew install hyperfine' locally, or" \
       "'sudo apt-get install -y hyperfine' in CI) before running the A/B gate." >&2
  exit 1
fi

BASE_DIR="$1"; HEAD_DIR="$2"
hyperfine --warmup 1 --runs 5 --export-json /tmp/publish-bench.json \
  --command-name base "cd '$BASE_DIR' && $WORKLOAD" \
  --command-name head "cd '$HEAD_DIR' && $WORKLOAD"
MAX_RATIO="$MAX_RATIO" php -r '
    $r = json_decode(file_get_contents("/tmp/publish-bench.json"), true)["results"];
    $base = $r[0]["median"]; $head = $r[1]["median"]; $max = (float) getenv("MAX_RATIO");
    $ok = $head / $base <= $max;
    printf("%s - base %.2fs  head %.2fs  ratio %.3f (max %.2f)\n", $ok ? "PASS" : "FAIL", $base, $head, $head / $base, $max);
    exit($ok ? 0 : 1);
'

# Shared by local CI and disposable integration; timings never contain arguments.
timed() (
  name=$1
  shift
  started=$(python3 -c 'import time; print(time.monotonic_ns())')
  trap 'result=$?; python3 scripts/ci-timing.py "$name" "$started" "$result" || exit 1; exit "$result"' EXIT
  "$@"
)

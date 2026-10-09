#!/usr/bin/env bash
# Smoke load test with curl only (no extra tools). Usage: scripts/load-test.sh [base_url] [requests] [concurrency]
# Reports the median and 95th percentile response time and the errors per page. Run it against
# a production-like server (APP_DEBUG=false, opcache on); numbers from a dev container are only a rough guide.
# Goal from the requirements: answer in under 3 s with 50-100 users at once.
set -euo pipefail
BASE="${1:-http://localhost}"; N="${2:-200}"; C="${3:-50}"
for path in / /catalogo "/catalogo?q=borges" /carrito /seguimiento; do
  out=$(seq "$N" | xargs -P "$C" -I{} curl -s -o /dev/null -w '%{http_code} %{time_total}\n' "$BASE$path")
  total=$(echo "$out" | wc -l); bad=$(echo "$out" | awk '$1>=400||$1==0' | wc -l)
  echo "$out" | awk '{print $2}' | sort -n | awk -v p="$path" -v t="$total" -v b="$bad" '{a[NR]=$1} END {printf "%-22s n=%d errores=%d  p50=%.2fs  p95=%.2fs  max=%.2fs\n", p, t, b, a[int(NR*0.5)], a[int(NR*0.95)], a[NR]}'
done

#!/usr/bin/env bash
#
# Phase 13 of the Bday_Aero roadmap: "make the guardrail a policy, not a
# habit." The RDS CPU-spike root cause (deep-dive §2) was posts queries
# bypassing the cache layer — Bday_Query_Cache::query()/remember() and
# their thin wrapper bday_get_posts() (core/data/helpers.php). Every call
# site in the theme was audited during this phase and found to already be
# compliant (either calling bday_get_posts()/Bday_Query_Cache directly, or
# — the two allowlisted files below — a raw new WP_Query()/get_posts()
# call that's already nested inside a Bday_Query_Cache::remember()
# closure, so it IS cached, just not through the thin wrapper). This
# script freezes that state: it fails if a *new* file introduces a raw
# WP_Query()/get_posts()/get_pages() call, so a regression back to the
# original incident's pattern is a CI failure, not something only caught
# by the next RDS CPU spike.
#
# Deliberately a plain grep, not a PHPStan/PHPCS custom rule — no such
# static-analysis tooling exists anywhere in this repo yet, and adding one
# just for this single check would be a heavier lift than the "policy, not
# habit" framing calls for. If PHP static analysis is adopted here later,
# this check is a natural candidate to fold into it.
#
# Usage: bin/check-query-cache-discipline.sh (run from the theme root, or
# anywhere — it resolves paths relative to this script's own location).

set -euo pipefail

THEME_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$THEME_ROOT"

# Files allowed to call new WP_Query()/get_posts()/get_pages() directly:
#   - core/data/class-query-cache.php: this IS the cache wrapper; its own
#     implementation has to call the raw WordPress functions somewhere.
#   - addons/aero-paywall/includes/class-premium-map.php: every raw call
#     in this file is nested inside a Bday_Query_Cache::remember()
#     closure (verified during Phase 13 — sync_to_system_b() and
#     sync_restriction_rules_to_system_b() each wrap their own query work
#     in remember()), so it's cached, just not via the thin wrapper.
ALLOWLIST=(
  "core/data/class-query-cache.php"
  "addons/aero-paywall/includes/class-premium-map.php"
)

is_allowlisted() {
  local file="$1"
  for allowed in "${ALLOWLIST[@]}"; do
    if [ "$file" = "$allowed" ]; then
      return 0
    fi
  done
  return 1
}

# \b before get_posts/get_pages correctly excludes bday_get_posts() —
# there's no word boundary between the preceding "_" and "g" in
# "bday_get_posts(", both being word characters.
PATTERN='new WP_Query\(|\bget_posts\(|\bget_pages\('

violations=0

while IFS= read -r -d '' file; do
  rel="${file#./}"
  if is_allowlisted "$rel"; then
    continue
  fi
  # Excludes doc-comment/line-comment prose that merely *mentions*
  # get_posts()/WP_Query (several files here explain the discipline in
  # their own docblocks) — only a match on an actual code line counts.
  matches="$(grep -nE "$PATTERN" "$file" | grep -vE '^[0-9]+:[[:space:]]*(\*|//|/\*)' || true)"
  if [ -n "$matches" ]; then
    violations=$((violations + 1))
    echo "::error file=$rel::Uncached query call — route this through bday_get_posts() or Bday_Query_Cache instead"
    echo "  $rel"
    echo "$matches" | sed 's/^/    /'
  fi
done < <(find . -name '*.php' -not -path './assets/build/*' -not -path './vendor/*' -not -path './node_modules/*' -print0)

if [ "$violations" -gt 0 ]; then
  echo ""
  echo "Found $violations file(s) with an uncached WP_Query/get_posts/get_pages call."
  echo "Route new post queries through bday_get_posts() (core/data/helpers.php) or"
  echo "Bday_Query_Cache::query()/remember() directly. If this is a genuine,"
  echo "already-cached exception (like class-premium-map.php's remember()-wrapped"
  echo "calls), add it to the ALLOWLIST array at the top of this script — with a"
  echo "comment explaining why it's actually cached."
  exit 1
fi

echo "Query-cache discipline check passed — no uncached WP_Query/get_posts/get_pages calls found."

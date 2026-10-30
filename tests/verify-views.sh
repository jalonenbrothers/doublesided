#!/bin/bash
# verify-views.sh — real-Apache content checks for Phase 1 (view modes, BUG1/2/3).
# Run AFTER apache-smoke.sh with the dev stack up (ds-apache4 :18098).
set -e
base="http://127.0.0.1:18098"
pass=0; fail=0
check() {
  local name=$1; shift
  if "$@" >/dev/null 2>&1; then echo "ok  $name"; pass=$((pass+1));
  else echo "FAIL $name"; fail=$((fail+1)); fi
}
page() { curl -s "$base$1"; }

# each view mode returns 200 with exactly one pressed toggle
for v in "" "?view=side-a" "?view=side-b" "?view=evidence" "?view=timeline" "?view=compact"; do
  html=$(page "/issue/will-ai-take-our-jobs$v")
  n=$(printf '%s' "$html" | grep -o 'aria-pressed="true"' | wc -l)
  if [ "$n" = "1" ]; then echo "ok  view '$v' -> one pressed toggle"; pass=$((pass+1));
  else echo "FAIL view '$v' -> pressed=$n"; fail=$((fail+1)); fi
done

# unknown view falls back to merged (still one pressed)
html=$(page "/issue/will-ai-take-our-jobs?view=zzz")
n=$(printf '%s' "$html" | grep -o 'aria-pressed="true"' | wc -l)
if [ "$n" = "1" ]; then echo "ok  unknown ?view falls back"; pass=$((pass+1)); else echo "FAIL unknown ?view"; fail=$((fail+1)); fi

# BUG1: body sections rendered
for needle in "Both sides accept that" "What the evidence says" "close to zero so far" "What remains uncertain" "How fast firms actually adopt" "AI takes tasks" "This comparison asks whether"; do
  check "BUG1 section: $needle" sh -c "curl -s '$base/issue/will-ai-take-our-jobs' | grep -qF '$needle'"
done

# media
check "BUG1 media: image" sh -c "curl -s '$base/issue/will-ai-take-our-jobs' | grep -qF 'automation-factory.svg'"
check "BUG1 media: youtube-nocookie embed" sh -c "curl -s '$base/issue/will-ai-take-our-jobs' | grep -qF 'youtube-nocookie.com/embed/WSfu7jHiGtA'"

# BUG2/BUG3
check "BUG2: home #methodology anchor" sh -c "curl -s '$base/' | grep -qF 'id=\"methodology\"'"
check "BUG3: favicon link" sh -c "curl -s '$base/' | grep -qF 'rel=\"icon\"'"
check "favicon file serves" sh -c "curl -s -o /dev/null -w '%{http_code}' '$base/assets/img/favicon.svg' | grep -q 200"

# canonical: Link header bare URL on every view
for v in "" "?view=compact"; do
  hdr=$(curl -sI "$base/issue/will-ai-take-our-jobs$v" | grep -i 'link.*canonical' || true)
  if printf '%s' "$hdr" | grep -q 'view='; then echo "FAIL canonical on '$v' has ?view="; fail=$((fail+1));
  elif printf '%s' "$hdr" | grep -q 'canonical'; then echo "ok  canonical bare on '$v'"; pass=$((pass+1));
  else echo "FAIL canonical missing on '$v'"; fail=$((fail+1)); fi
done

# views.js served
check "views.js serves 200" sh -c "curl -s -o /dev/null -w '%{http_code}' '$base/assets/js/views.js' | grep -q 200"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]

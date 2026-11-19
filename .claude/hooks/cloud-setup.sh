#!/bin/bash
# Claude Code cloud sessions only (Life OS's cloud worker, docs/office/30 in life-os): install what
# this Laravel project needs when a cloud session starts. Does nothing on your own computer.
# Kept by Life OS (docs/office/cloud-worker/cloud-setup.sh); edit it there.
#
# Flux Pro comes through the cloud environment's network secret for composer.fluxui.dev (Basic):
# no auth.json is needed or made here.
[ "${CLAUDE_CODE_REMOTE:-}" = "true" ] || exit 0
cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

LOG=/tmp/cloud-setup.log
: > "$LOG"
said=()
step() {
  # step "what" command...: runs it, logs it, and remembers how it went.
  local what="$1"; shift
  echo "== $what" >> "$LOG"
  if "$@" >> "$LOG" 2>&1; then said+=("ok: $what"); else said+=("FAILED: $what (see $LOG)"); fi
}

said+=("$(php -r 'echo "PHP ", PHP_VERSION;' 2>/dev/null || echo 'PHP missing')")

if [ -f composer.json ]; then
  if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    step "composer install" composer install --no-interaction --prefer-dist --no-progress
  else
    said+=("ok: composer packages already installed")
  fi
fi

if [ -f package-lock.json ]; then
  if [ ! -d node_modules ] || [ package-lock.json -nt node_modules ]; then
    step "npm ci" npm ci --no-audit --no-fund
  else
    said+=("ok: npm packages already installed")
  fi
fi

if [ -f artisan ]; then
  [ -f .env ] || cp .env.example .env 2>/dev/null
  if [ -f .env ] && ! grep -q '^APP_KEY=base64' .env; then step "artisan key:generate" php artisan key:generate --no-interaction; fi
  # Feature tests render pages that need Vite's manifest.
  if [ -f package.json ] && [ ! -f public/build/manifest.json ]; then step "npm run build" npm run build; fi
fi

# What Claude sees at the start of the session.
echo "Cloud setup (Life OS): ${said[*]}"
exit 0

#!/usr/bin/env bash
# .devcontainer/post-create.sh
# Runs automatically after the Codespace is created.
# All commands execute inside the `php` container as the remoteUser (www-data).

set -euo pipefail

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║   🚀  P2P Commerce – Codespace Setup                ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# ── 1. Composer dependencies ────────────────────────────────────────────────
echo "📦  Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader
echo "    ✓ done"

# ── 2. Wait for PostgreSQL ───────────────────────────────────────────────────
echo "⏳  Waiting for PostgreSQL to accept connections..."
for i in $(seq 1 30); do
  php bin/console doctrine:query:sql "SELECT 1" --quiet 2>/dev/null && break
  echo "    attempt $i/30…"
  sleep 2
done
echo "    ✓ PostgreSQL ready"

# ── 3. Run migrations ────────────────────────────────────────────────────────
echo "🗄️   Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo "    ✓ done"

# ── 4. Load fixtures ─────────────────────────────────────────────────────────
echo "🌱  Loading demo fixtures (alice / bob / charlie / admin)..."
php bin/console doctrine:fixtures:load --no-interaction
echo "    ✓ done"

# ── 5. Warm cache ────────────────────────────────────────────────────────────
echo "🔥  Warming Symfony cache..."
php bin/console cache:warmup --env=dev
echo "    ✓ done"

# ── 6. Ensure var/ directories are writable ──────────────────────────────────
echo "🔒  Fixing var/ permissions..."
mkdir -p var/cache var/log var/receipts
chmod -R 777 var/
echo "    ✓ done"

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║   ✅  Setup complete!                               ║"
echo "║                                                      ║"
echo "║   Web app  → port 8080 (Ports tab)                 ║"
echo "║   Postgres → port 5432                              ║"
echo "║   Redis    → port 6379                              ║"
echo "║                                                      ║"
echo "║   Demo logins (password: password123)               ║"
echo "║     alice@example.com   ($1,500 balance)            ║"
echo "║     bob@example.com     ($500 balance)              ║"
echo "║     admin@example.com   ($10,000 balance)           ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

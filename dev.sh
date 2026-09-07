#!/bin/bash
# dev.sh — Start all StudyCaseEISD dev servers
# Kills existing processes on required ports, then starts:
#   1. Vite dev server  (port 5173)  — Tailwind/Alpine hot reload
#   2. Laravel backend  (port 8000)  — API + Blade web
#   3. Expo web         (port 19006) — Mobile web frontend
#
# Usage:  ./dev.sh
# Stop:   Ctrl+C (gracefully stops all three)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
BACKEND="$ROOT/backend"
MOBILE="$ROOT/mobile"

PORTS=(5173 8000 19006)
PIDS=()

# ── helpers ──────────────────────────────────────────────

kill_port() {
    local port=$1
    local pids
    pids=$(lsof -ti tcp:"$port" 2>/dev/null || true)
    if [ -n "$pids" ]; then
        echo "  Killing PID $(echo "$pids" | tr '\n' ' ')on port $port"
        kill -9 $pids 2>/dev/null || true
    fi
}

cleanup() {
    echo ""
    echo "Stopping all servers…"
    for pid in "${PIDS[@]:-}"; do
        kill "$pid" 2>/dev/null || true
    done
    for port in "${PORTS[@]}"; do
        kill_port "$port"
    done
    echo "Done."
}

# ── pre-flight ────────────────────────────────────────────

trap cleanup EXIT INT TERM

echo "Killing existing processes…"
for port in "${PORTS[@]}"; do
    kill_port "$port"
done
sleep 1   # let OS free the sockets

# ── start servers ─────────────────────────────────────────

echo "Starting Vite dev server  → :5173"
( cd "$BACKEND" && npm run dev ) &
PIDS+=($!)

sleep 2   # let Vite claim :5173 before Laravel probes it

echo "Starting Laravel backend  → :8000"
( cd "$BACKEND" && php artisan serve ) &
PIDS+=($!)

echo "Starting Expo web         → :19006"
( cd "$MOBILE" && npx expo start --web ) &
PIDS+=($!)

# ── status ────────────────────────────────────────────────

cat <<'BANNER'

  All servers started:
    Vite dev   http://localhost:5173   (Tailwind/Alpine HMR)
    Laravel    http://localhost:8000   (API + Blade web)
    Expo web   http://localhost:19006   (mobile web)

  Desktop in browser → :8000 (auto-redirects mobile to :19006)
  Press Ctrl+C to stop all.

BANNER

wait

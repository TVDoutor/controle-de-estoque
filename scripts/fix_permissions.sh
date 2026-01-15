#!/usr/bin/env bash
set -euo pipefail

# Run from project root. Adjust USER:GROUP if needed.
ROOT=$(cd "$(dirname "$0")/.." && pwd)
echo "Fixing permissions in $ROOT"

find "$ROOT" -type d -exec chmod 755 {} +
find "$ROOT" -type f -exec chmod 644 {} +

echo "Done. If you need to change owner, run: chown -R youruser:yourgroup $ROOT"

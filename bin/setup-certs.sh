#!/usr/bin/env bash
set -euo pipefail

# Generates a locally-trusted TLS certificate for blog.me (+ subdomains) and
# localhost, for Caddy to serve. Requires mkcert on the host.

CERT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/docker/caddy/certs"
DOMAINS=(blog.me "*.blog.me" localhost 127.0.0.1 ::1)
HOSTS_LINE="127.0.0.1 blog.me www.blog.me mail.blog.me s3.blog.me minio.blog.me"

if ! command -v mkcert >/dev/null 2>&1; then
  echo "mkcert is not installed." >&2
  echo "  macOS:  brew install mkcert nss" >&2
  echo "  Linux:  https://github.com/FiloSottile/mkcert#installation" >&2
  exit 1
fi

mkdir -p "$CERT_DIR"

echo "==> Installing mkcert local CA (idempotent)"
mkcert -install

echo "==> Issuing certificate for: ${DOMAINS[*]}"
mkcert \
  -cert-file "$CERT_DIR/blog.me.pem" \
  -key-file  "$CERT_DIR/blog.me-key.pem" \
  "${DOMAINS[@]}"

echo
echo "Wrote:"
echo "  docker/caddy/certs/blog.me.pem"
echo "  docker/caddy/certs/blog.me-key.pem"
echo
echo "Make sure your /etc/hosts contains (needs sudo):"
echo "  $HOSTS_LINE"

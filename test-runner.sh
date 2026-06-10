#!/usr/bin/env bash
set -euo pipefail

exec nix run .#test-runner -- "$@"

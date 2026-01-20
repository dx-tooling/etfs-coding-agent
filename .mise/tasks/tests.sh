#!/usr/bin/env bash
#MISE description="Run software tests"

set -e

echo
echo "Running unit tests..."
mise run in-app-container php vendor/bin/pest

echo "All tests completed successfully! ✨"

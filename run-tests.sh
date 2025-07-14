#!/bin/bash

# ==============================================================================
# RoleManager Test Runner
# ==============================================================================
#
# This script simplifies running the PHPUnit test suite.
#
# Usage:
#   ./run-tests.sh            - Run all tests.
#   ./run-tests.sh --coverage - Run all tests and generate an HTML coverage report.
#
# ==============================================================================

set -e # Exit immediately if a command exits with a non-zero status.

# --- Configuration ---
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PHPUNIT_BIN="$ROOT_DIR/vendor/bin/phpunit"
COVERAGE_DIR="$ROOT_DIR/coverage-report"

# --- Pre-flight Checks ---
if [ ! -f "$PHPUNIT_BIN" ]; then
    echo "   Error: vendor/bin/phpunit not found."
    echo "   Please run 'composer install' first."
    exit 1
fi

# --- Argument Parsing ---
PHPUNIT_ARGS=""
COVERAGE_ENABLED=false

for arg in "$@"
do
    case $arg in
        --coverage)
        COVERAGE_ENABLED=true
        PHPUNIT_ARGS="$PHPUNIT_ARGS --coverage-html $COVERAGE_DIR"
        shift
        ;;
    esac
done

# --- Execution ---
echo "🚀 Running RoleManager Test Suite..."

if [ "$COVERAGE_ENABLED" = true ] ; then
    echo "   (Code coverage enabled)"
fi

eval "$PHPUNIT_BIN $PHPUNIT_ARGS"

echo ""
echo "Tests completed successfully."
if [ "$COVERAGE_ENABLED" = true ] ; then
    echo "Coverage report generated in: file://$COVERAGE_DIR/index.html"
    # Try to open the report automatically in the default browser
    if command -v open &> /dev/null; then # macOS
        open "$COVERAGE_DIR/index.html"
    elif command -v xdg-open &> /dev/null; then # Linux
        xdg-open "$COVERAGE_DIR/index.html"
    fi
fi
echo ""

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
# Determine the project root directory, assuming this script is in project_root/bin/
# This allows running the script from any location.
ROOT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." &> /dev/null && pwd )"
PHPUNIT_BIN="$ROOT_DIR/vendor/bin/phpunit"
COVERAGE_DIR="$ROOT_DIR/coverage-report"
TESTS_DIR="$ROOT_DIR/tests"

# --- Pre-flight Checks ---
if [ ! -f "$PHPUNIT_BIN" ]; then
    echo "   Error: vendor/bin/phpunit not found."
    echo "   Please run 'composer install' first."
    exit 1
fi

if [ ! -d "$TESTS_DIR" ]; then
    echo "   Error: Test directory not found at $TESTS_DIR."
    echo "   Please make sure your tests are in a 'tests/' directory in the project root."
    exit 1
fi

# --- Argument Parsing ---
COVERAGE_ENABLED=false
PHPUNIT_PASSTHRU_ARGS=()

while (( "$#" )); do
    case "$1" in
        --coverage)
            COVERAGE_ENABLED=true
            shift
            ;;
        *) # Collect any other arguments to pass to PHPUnit
            PHPUNIT_PASSTHRU_ARGS+=("$1")
            shift
            ;;
    esac
done

# --- Execution ---
echo "🚀 Running RoleManager Test Suite..."

# If specific files/filters were passed, use them. Otherwise, default to the main tests directory.
if [ ${#PHPUNIT_PASSTHRU_ARGS[@]} -eq 0 ]; then
    PHPUNIT_PASSTHRU_ARGS=("$TESTS_DIR")
fi

if [ "$COVERAGE_ENABLED" = true ] ; then
    echo "   (Code coverage enabled, will be generated in $COVERAGE_DIR)"
    eval "$PHPUNIT_BIN --coverage-html $COVERAGE_DIR \"${PHPUNIT_PASSTHRU_ARGS[@]}\""
else
    eval "$PHPUNIT_BIN \"${PHPUNIT_PASSTHRU_ARGS[@]}\""
fi

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

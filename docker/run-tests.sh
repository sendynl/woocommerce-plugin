#!/usr/bin/env bash
# Runs inside the test container (see docker-compose.yml): installs the
# WordPress test suite into the cached volume when needed, then runs PHPUnit.
set -euo pipefail

marker="$WP_TESTS_DIR/.installed-wp-version"

if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ] \
    || [ ! -f "$WP_CORE_DIR/wp-settings.php" ] \
    || [ "$(cat "$marker" 2>/dev/null)" != "$WP_VERSION" ]; then
    echo "==> Installing WordPress test suite into $WP_TESTS_DIR"
    rm -rf "$WP_TESTS_DIR" "$WP_CORE_DIR"
    echo y | bash bin/install-wp-tests.sh wordpress_test root wordpress mysql "$WP_VERSION"
    printf '%s' "$WP_VERSION" > "$marker"
fi

vendor/bin/phpunit

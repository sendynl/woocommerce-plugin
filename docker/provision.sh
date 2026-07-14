#!/usr/bin/env bash
# Provisions the WordPress installation. Runs inside the wp-cli container in a
# single `docker compose run` (one container start for all steps); ./develop up
# passes the inputs as environment variables.
set -euo pipefail

: "${SITE_URL:?}" "${ADMIN_PASSWORD:?}"

locale="${WP_LOCALE:-nl_NL}"

install_plugin_language() {
    wp language plugin install "$1" "$locale" \
        || echo "Warning: no $1 language pack for $locale" >&2
}

echo "==> Installing WordPress at $SITE_URL"
wp core install --url="$SITE_URL" --title="Sendy Dev" \
    --admin_user=admin --admin_password="$ADMIN_PASSWORD" \
    --admin_email=dev@sendy.example --skip-email

# The wordpress:latest Docker image can lag behind the real latest WordPress
# release, which breaks installing current WooCommerce (it may require a newer
# core). Update core unless the user pinned WP_VERSION.
if [ "${WP_VERSION:-latest}" = "latest" ]; then
    echo "==> Updating WordPress core to latest"
    wp core update
    wp core update-db
fi

echo "==> Installing locale $locale"
wp language core install "$locale" --activate \
    || echo "Warning: could not install core locale $locale" >&2

echo "==> Installing WooCommerce"
# shellcheck disable=SC2086
wp plugin install woocommerce ${WC_VERSION:+--version="$WC_VERSION"} --activate
install_plugin_language woocommerce

echo "==> Configuring the store"
wp option update woocommerce_store_address "Wiltonstraat 41"
wp option update woocommerce_store_city "Veenendaal"
wp option update woocommerce_store_postcode "3905KW"
wp option update woocommerce_default_country "NL"
wp option update woocommerce_coming_soon "no"
wp option update woocommerce_cod_settings '{"enabled":"yes"}' --format=json
if [ "${WC_HPOS:-on}" = "on" ]; then
    wp option update woocommerce_custom_orders_table_enabled "yes"
else
    wp option update woocommerce_custom_orders_table_enabled "no"
fi

echo "==> Seeding products and orders"
product1="$(wp wc product create --name="Sample product" --type=simple --regular_price=10 --porcelain --user=admin)"
product2="$(wp wc product create --name="Another sample product" --type=simple --regular_price=25 --porcelain --user=admin)"

address='{"first_name":"Sendy","last_name":"Dev","address_1":"Wiltonstraat 41","city":"Veenendaal","postcode":"3905KW","country":"NL"}'
wp wc shop_order create --status=processing --billing="$address" --shipping="$address" \
    --line_items="[{\"product_id\":$product1,\"quantity\":1}]" --porcelain --user=admin
wp wc shop_order create --status=processing --billing="$address" --shipping="$address" \
    --line_items="[{\"product_id\":$product2,\"quantity\":2}]" --porcelain --user=admin

echo "==> Activating the Sendy plugin"
wp plugin activate sendy
install_plugin_language sendy

echo "==> Installing magic-login command"
# Prefer the release zip (served from codeload.github.com) — installing by
# package name hits the unauthenticated GitHub API, which rate-limits.
if { wp package install https://github.com/aaemnnosttv/wp-cli-login-command/archive/refs/tags/v1.5.0.zip \
    || wp package install aaemnnosttv/wp-cli-login-command; } \
    && wp login install --activate --yes; then
    :
else
    echo "Warning: could not install wp-cli-login-command; log in with the printed password" >&2
fi

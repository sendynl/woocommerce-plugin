# Contributing

At Sendy, we highly value and encourage contributions from developers who are passionate about improving our plugin. Whether it’s fixing bugs, enhancing features, or introducing new ideas, your input can help drive innovation and make our products better.

While we welcome all contributions, it’s important to note that the final decision on accepting any changes lies with Sendy. Our team will carefully review each submission to ensure it aligns with our project goals, coding standards, and overall vision.

We believe collaboration and open dialogue are key, so we encourage you to discuss your ideas with us before diving into development. 

## Procedure

Before filing an issue:

- Attempt to replicate the problem, to ensure that it wasn't a coincidental incident.
- Check to make sure your feature suggestion isn't already present within the project.
- Check the pull requests tab to ensure that the bug doesn't have a fix in progress.
- Check the pull requests tab to ensure that the feature isn't already in progress.

Before submitting a pull request:

- Check the codebase to ensure that your feature doesn't already exist.
- Check the pull requests to ensure that another person hasn't already submitted the feature or fix.

## Requirements

All code must pass the plugin check action. This is mandatory check before merging a pull request.

## Local development environment

The repository ships a disposable Docker environment for reproducing issues: a WordPress + WooCommerce installation with this plugin activated.

```sh
./develop up      # start + provision; prints the URL, credentials and a magic login URL
./develop down    # deactivate the plugin (cleans up the Sendy webhook!) and destroy everything
```

Always use `./develop down`, not plain `docker compose down` — it deactivates the plugin first, which deletes the webhook registered in Sendy, so Sendy doesn't keep retrying deliveries against a dead URL. The one manual step is connecting to your Sendy account via the plugin's settings page. Copy `.env.example` to `.env` to change any of the knobs it documents (versions, ports, locale, HPOS, ...).

To receive real webhook deliveries from Sendy, use tunnel mode: `./develop up --tunnel`, which exposes the site via an account-less Cloudflare quick tunnel.

Ad-hoc commands run through passthroughs, no local PHP required: `./develop wp option get siteurl` and `./develop composer install`.

## Translations

After adding or changing translatable strings in the code, regenerate the translation files:

```sh
bin/update_translations.sh
```

This runs `wp i18n make-pot`, `update-po` and `make-mo` in a one-off Docker container (no local wp-cli needed) and reports any strings that still need a translation. Translate those in `languages/sendy-nl_NL.po` with your IDE or Poedit, then run the script again to recompile the MO file (Poedit compiles it itself when saving, making the second run optional).

## Running the tests

The plugin has PHPUnit integration tests that run against a real WordPress test instance, following the [WP-CLI plugin unit test guide](https://make.wordpress.org/cli/handbook/how-to/plugin-unit-tests/).

Install the dependencies and the WordPress test suite once:

```sh
composer install
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
# e.g. against a local MySQL: bin/install-wp-tests.sh sendy_wp_tests root '' 127.0.0.1 latest
```

The `<db-name>` database is dropped and recreated by the test suite on every run, so use a dedicated throwaway database.

Then run the tests:

```sh
composer test
```

Alternatively, run the suite in Docker without a local MySQL or PHP:

```sh
./develop test
```

This provisions the WordPress test suite in a cached volume on first run (and again whenever `WP_VERSION` changes) and then runs PHPUnit.

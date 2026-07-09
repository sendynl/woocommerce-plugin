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

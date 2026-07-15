#!/usr/bin/env bash
# Regenerate the translation files (POT/PO/MO) with WP-CLI in a one-off
# Docker container, so no local wp-cli installation is needed.
#
# Workflow: run this script, translate the strings it reports as missing in
# languages/sendy-nl_NL.po (with your IDE or Poedit), then run it again to
# compile the MO file. Poedit compiles the MO file itself when saving, which
# makes the second run optional.

set -euo pipefail

cd "$(dirname "$0")/.."

docker run --rm -v "$PWD":/plugin -w /plugin wordpress:cli bash -c '
    wp i18n make-pot . --slug=sendy &&
    wp i18n update-po languages/sendy.pot &&
    wp i18n make-mo languages/
'

for po in languages/*.po; do
    # Collect the msgids of all untranslated entries, excluding the file header.
    untranslated="$(grep -B 1 '^msgstr ""$' "$po" | grep '^msgid ' | grep -v '^msgid ""$' || true)"
    if [ -n "$untranslated" ]; then
        echo
        echo "Untranslated strings in $po:"
        echo "${untranslated//msgid \"/  \"}"
        echo "Translate them and run this script again to recompile the MO file."
    fi
done

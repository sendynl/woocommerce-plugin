#!/usr/bin/env bash
# Print the current version of the project and bump it to the given version.

current_version="$(grep '^Version: ' readme.txt | cut -d ' ' -f 2)"
echo "Current version: $current_version"

if [[ -z "$1" ]]
then
  echo "To bump the version, provide the new version number as an argument."
  exit 1
fi

# Remove the 'v' prefix if it exists
new_version="${1#v}"

if ! [[ "$new_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
then
  echo "Invalid version format. Please use semantic versioning (https://semver.org/)."
  exit 1
fi

echo "Bumping version to: $new_version"

perl -pi -e "s/^Version: .*/Version: $new_version/" readme.txt
perl -pi -e "s/^Stable tag: .*/Stable tag: $new_version/" readme.txt
perl -pi -e "s/^ \* Version: .*/ \* Version: $new_version/" sendy.php
perl -pi -e "s/^    public const VERSION = .*/    public const VERSION = '$new_version';/" src/Plugin.php

if ! grep -q "^= $new_version =$" readme.txt
then
  echo "Remember to add a new changelog entry in the readme.txt for version $new_version."
fi

echo
echo "You can now commit the changes and merge them into the main branch. Then, create a new release on GitHub:"
echo "https://github.com/sendynl/woocommerce-plugin/releases/new?tag=v$new_version"

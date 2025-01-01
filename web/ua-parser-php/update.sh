#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

composer_command="composer"

command -v "$composer_command" >/dev/null 2>&1 || {
    composer_command="composer.phar"
}

$composer_command update $*

echo "Updating ua-parser data..."
php vendor/ua-parser/uap-php/bin/uaparser ua-parser:update

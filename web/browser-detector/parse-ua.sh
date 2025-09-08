#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

php -d memory_limit=3048M $parent_path/scripts/parse-ua.php "$@"

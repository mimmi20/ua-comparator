#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

cd ../web

echo "Updating the parsers"

for f in *; do
    if [ -d ${f} ]; then
        echo -e "\033[0;35mRunning update script for the \033[4;31m$f\033[0;35m parser\033[0m"
        cd $f
        sh ./update.sh $*
        cd ..
    fi
done

echo "Done updating the parsers"

cd ..

echo "Updating browscap-php data..."
./vendor/bin/browscap-php browscap:update --cache ./data/cache/browscap --remote-file Full_PHP_BrowscapINI -vv
./vendor/bin/browscap-php browscap:fetch --remote-file Full_PHP_BrowscapINI -vv data/cache/browscap/browscap.ini

echo "Updating ua-parser data..."
php vendor/ua-parser/uap-php/bin/uaparser ua-parser:update

echo "Done updating the data"

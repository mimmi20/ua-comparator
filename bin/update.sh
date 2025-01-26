#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

cd ../web

for f in *; do
    if [ -d ${f} ]; then
        echo -e "\033[0;35mRunning update script for the \033[4;31m$f\033[0;35m parser\033[0m"
        cd $f
        sh ./update.sh $*
        cd ..
    fi
done


cd ..
./vendor/bin/browscap-php browscap:update --cache ./data/cache/browscap --remote-file Full_PHP_BrowscapINI -vv
./vendor/bin/browscap-php browscap:fetch --remote-file Full_PHP_BrowscapINI -vv data/cache/browscap/browscap.ini

echo "Done updating the parsers"

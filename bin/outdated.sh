#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

cd ../web

for f in *; do
    if [ -d ${f} ]; then
        echo -e "\033[0;35mRunning outdated script for the \033[4;31m$f\033[0;35m parser\033[0m"
        cd $f
        sh ./outdated.sh
        cd ..
    fi
done

echo "Done updating the parsers"

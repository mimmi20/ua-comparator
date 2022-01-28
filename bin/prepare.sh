#!/bin/sh

cd `dirname $0`

echo "clearing results directory ..."
rm -rf ../data/results/

mkdir ../data/results/

echo "clearing browser directory ..."
rm -rf ../data/browser/

mkdir ../data/browser/

echo "clearing cache directory ..."
rm -rf ../data/cache/

mkdir ../data/cache/
mkdir ../data/cache/browscap/
mkdir ../data/cache/browser/
mkdir ../data/cache/matomo/
mkdir ../data/cache/uaparser/
mkdir ../data/cache/uasparser/
mkdir ../data/cache/general/

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

cd ../web

for f in *; do
    if [ -d ${f} ]; then
        echo -e "\033[0;35mRunning update script for the \033[4;31m$f\033[0;35m parser\033[0m"
        cd $f
        sh ./update.sh
        cd ..
    fi
done

echo "Done updating the parsers"


#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

echo "clearing log directory ..."
rm -rf ../log/

echo "recreate log directory ..."
mkdir ../log/

echo "clearing results directory ..."
rm -rf ../data/results/

mkdir ../data/results/

echo "clearing browser directory ..."
rm -rf ../data/browser/

mkdir ../data/browser/

echo "clearing cache directory ..."
rm -rf ../data/cache/

echo "recreate cache directories ..."
mkdir ../data/cache/
mkdir ../data/cache/browscap/
mkdir ../data/cache/browser/
mkdir ../data/cache/matomo/
mkdir ../data/cache/endorphin/
mkdir ../data/cache/mobiledetect/
mkdir ../data/cache/whichbrowser/

cd ../web

echo "Prepare the parsers"

for f in *; do
    if [ -d ${f} ]; then
        echo -e "\033[0;35mRunning update script for the \033[4;31m$f\033[0;35m parser\033[0m"
        cd $f
        sh ./prepare.sh $*
        cd ..
    fi
done

echo "Done preparing the parsers"

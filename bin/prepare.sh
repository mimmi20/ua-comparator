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
mkdir ../data/cache/browscap-php/
mkdir ../data/cache/browscap/
mkdir ../data/cache/browser/
mkdir ../data/cache/matomo/
mkdir ../data/cache/uaparser/
mkdir ../data/cache/general/

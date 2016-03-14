#!/bin/sh

cd `dirname $0`

php -S localhost:8000 -t ../web/ -c ../data/configs/server.ini

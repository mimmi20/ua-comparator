#!/bin/sh

php -S localhost:8000 -d browscap=data/browser/full_php_browscap.ini -d error_log=log/php_error.log -d log_errors=On -d display_errors=Off -c data/configs/server.ini -t public/ public/index.php > log/server.log 2>&1

#!/bin/sh

cd `dirname $0`

echo "Updating ua-parser data..."
php ../vendor/bin/uaparser.php ua-parser:update

echo "Creating browscap.ini file..."
php ../vendor/browscap/browscap/bin/browscap build dev-master --output=../../../data/browser/

echo "Updating php-browscap (2.x) data..."
php update-php-browscap.php

echo "Updating browscap-php (3.x) data..."
php update-browscap-php.php

echo "Updating crossjoin-browscap data..."
php update-crossjoin-browscap.php

echo "Preparing Wurfl data..."
php prepare-wurfl.php

echo "Preparing Wurfl (old) data..."
php prepare-wurfl-old.php

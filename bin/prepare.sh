#!/bin

cd `dirname $0`

echo "Updating ua-parser data..."
php ../vendor/bin/uaparser.php ua-parser:update

echo "Updating browscap-php data..."
php update-browscap-php.php

echo "Updating crossjoin-browscap data..."
php update-crossjoin-browscap.php

echo "Preparing Wurfl data..."
php prepare-wurfl.php

echo "Preparing Wurfl (old) data..."
php prepare-wurfl-old.php

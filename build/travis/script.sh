#! /bin/bash

set -x

cd ../wiki/tests/phpunit
php phpunit.php -c ../../extensions/WikibaseQualityConstraints/phpunit.xml.dist
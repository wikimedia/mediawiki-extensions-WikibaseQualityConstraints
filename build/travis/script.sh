#! /bin/bash

set -x

cd ../wiki/tests/phpunit
php phpunit.php -c ../../extensions/WikidataQuality/extensions/WikidataQualityConstraints/phpunit.xml.dist

# cd ../wiki/extensions/WikidataQuality
# php vendor/bin/phpunit -c phpunit.xml.dist
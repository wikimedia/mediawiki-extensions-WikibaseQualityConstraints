#! /bin/bash

cd ../wiki/extensions/WikibaseQualityConstraints
composer remove "wikibase/quality"
composer remove "wikibase/wikibase"
php vendor/bin/coveralls -v
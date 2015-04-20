#! /bin/bash

cd ../wiki/extensions/WikidataQuality/extensions/WikidataQualityConstraints

ls build/logs

php vendor/bin/coveralls -v

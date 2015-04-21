# WikidataQualityConstraints
[![Build Status](https://travis-ci.org/wikimedia/mediawiki-extensions-WikidataQualityConstraints.svg?branch=master)](https://travis-ci.org/wikimedia/mediawiki-extensions-WikidataQualityConstraints)  [![Coverage Status](https://coveralls.io/repos/wikimedia/mediawiki-extensions-WikidataQualityConstraints/badge.svg)](https://coveralls.io/r/wikimedia/mediawiki-extensions-WikidataQualityConstraints)  [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-WikidataQualityConstraints/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-WikidataQualityConstraints/?branch=master)

This is a complementary extension for the [Wikidata Quality extension](https://github.com/wikimedia/mediawiki-extensions-WikidataQuality.git).
It performs constraint checks in Wikidata.

## Installation

* Clone this repo into WikidataQuality/extensions

`git clone https://github.com/wikimedia/mediawiki-extensions-WikidataQualityConstraints.git`  

* Add `require_once __DIR__ . "/extensions/Wikidata/extensions/WikidataQuality/extensions/WikidataQualityConstraints/WikidataQualityConstraints.php";` to your LocalSettings.php
* Run `php maintenance/update.php --quick` in your Mediawiki directory
* Run `composer install` in the WikidataQualityConstraints directory
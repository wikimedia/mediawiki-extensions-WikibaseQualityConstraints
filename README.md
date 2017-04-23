# Wikibase Quality Constraints
[![Build Status](https://travis-ci.org/wikimedia/mediawiki-extensions-WikibaseQualityConstraints.svg?branch=master)]
(https://travis-ci.org/wikimedia/mediawiki-extensions-WikibaseQualityConstraints)
[![Coverage Status](https://coveralls.io/repos/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/badge.svg)]
(https://coveralls.io/r/wikimedia/mediawiki-extensions-WikibaseQualityConstraints)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/badges/quality-score.png?b=master)]
(https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/?branch=master)

This is a complementary extension for the [Wikibase Quality base extension]
(https://github.com/wikimedia/mediawiki-extensions-WikibaseQuality.git).
It performs constraint checks in Wikibase.

## Installation

_If you have already installed a complementary Wikibase Quality extension you can skip the first two steps and just
add the repository (second entry in "repositories" and the required version (last entry in "require") to the
composer.local.json._  

* Create the file `composer.local.json` in the directory of your mediawiki installation.

* Add the following lines:
```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://gerrit.wikimedia.org/r/mediawiki/extensions/WikibaseQuality"
        },
        {
            "type": "git",
            "url": "https://gerrit.wikimedia.org/r/mediawiki/extensions/WikibaseQualityConstraints"
        }
    ],
    "require": {
        "wikibase/quality": "@dev",
        "wikibase/wikibase": "@dev",
        "wikibase/constraints": "1.x-dev"
    }
}
```

* Run `composer install`.

* If not already done, add the following lines to your `LocalSettings.php` to enable Wikibase:
```php
$wgEnableWikibaseRepo = true;
$wgEnableWikibaseClient = false;
require_once "$IP/extensions/Wikibase/repo/ExampleSettings.php";
```

* Run `php maintenance/update.php --quick`.

* Last but not least, you need to fill the constraints table - for that you need the
[constraints from templates script](https://github.com/WikidataQuality/ConstraintsFromTemplates).  
Follow the instruction in the README to create a csv file.  
Run `php maintenance/runScript.php extensions/WikibaseQualityConstraints/maintenance/UpdateConstraintsTable.php --csv-file <path_to_csv_file>`.

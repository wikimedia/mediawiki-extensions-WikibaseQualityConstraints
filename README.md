# Wikibase Quality Constraints
[![Build Status][travis-badge]][travis]
[![Coverage Status][coveralls-badge]][coveralls]
[![Scrutinizer Code Quality][scrutinizer-badge]][scrutinizer]

This is a complementary extension for the [Wikibase Quality base extension][wbq].
It performs constraint checks in Wikibase.

[travis-badge]: https://travis-ci.org/wikimedia/mediawiki-extensions-WikibaseQualityConstraints.svg?branch=master
[travis]: https://travis-ci.org/wikimedia/mediawiki-extensions-WikibaseQualityConstraints
[coveralls-badge]: https://coveralls.io/repos/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/badge.svg
[coveralls]: https://coveralls.io/r/wikimedia/mediawiki-extensions-WikibaseQualityConstraints
[scrutinizer-badge]: https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/badges/quality-score.png?b=master
[scrutinizer]: https://scrutinizer-ci.com/g/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/?branch=master
[wbq]: https://github.com/wikimedia/mediawiki-extensions-WikibaseQuality.git

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
Run `php maintenance/runScript.php extensions/Constraints/maintenance/UpdateConstraintsTable.php --csv-file <path_to_csv_file>`.

* Optional: to enable importing constraints from statements on properties,
  add the following line to your `LocalSettings`.php:
  ```php
  $wgWBQualityConstraintsEnableConstraintsImportFromStatements = true;
  ```

### Gadget

The extension includes a gadget that checks constraints on entity pages and displays violations on statements.
The gadget is loaded via ResourceLoader and can be used with the following definition in MediaWiki:Gadgets-definition:

```mediawiki
* checkConstraints[ResourceLoader|dependencies=wikibase.quality.constraints.gadget]|checkConstraints.js
```

`checkConstraints.js` (page title: MediaWiki:Gadget-checkConstraints.js) is only required because the the Gadgets extension does not support gadgets without any JS files.
It can be empty (or even nonexistent), but some explanatory comment is recommended, for example:

```js
// this gadget resides in the wikibase.quality.constraints.gadget ResourceLoader module
// its source code is available in the gadget* files at https://github.com/wikimedia/mediawiki-extensions-WikibaseQualityConstraints/tree/master/modules
```

To make the gadget display nicely in the preferences and on Special:Gadget,
MediaWiki:Gadget-checkConstraints should be created, e. g. with the following content:

```mediawiki
''checkConstraints'': Shows property constraint reports for statements on an item or property page ([[Help:Property constraints portal|help]])
```

The gadget can also be loaded directly as a standalone script, e. g. as a user script,
by simply including `modules/gadget.js` in `common.js` and `modules/gadget.css` in `common.css`.

### Data import

For local development, it’s easiest to import some data from Wikidata.
You can use the [WikibaseImport] extension to do this;
once you have installed it, you can use the following command
to import the essential entities that this extension needs
(stuff like the “instance of” property):

```sh
# working directory should be the MediaWiki installation folder, i.e. where LocalSettings.php is
jq -r '.config | with_entries(select(.key | endswith("Id"))) | .[] | .value' extensions/WikibaseQualityConstraints/extension.json | php extensions/WikibaseImport/maintenance/importEntities.php --stdin
```

Afterwards, export the resulting entity IDs to your local config (since they’ll be different than on Wikidata):

```sh
# from the MediaWiki installation folder
extensions/WikibaseQualityConstraints/maintenance/exportEntityMapping
# or directly from the extension folder
maintenance/exportEntityMapping
```

[WikibaseImport]: https://github.com/filbertkm/WikibaseImport

### Running the tests

There are two ways to run the tests of this extension:

- Using the included configuration file:

  ```sh
  # from the MediaWiki installation folder
  php tests/phpunit/phpunit.php -c extensions/WikibaseQualityConstraints/phpunit.xml.dist
  ```

  This creates test coverage reports
  (in `tests/coverage/` and `build/logs/clover.xml`)
  and is therefore fairly slow.

- Without the configuration file:

  ```sh
  # from the MediaWiki installation folder
  php tests/phpunit/phpunit.php extensions/WikibaseQualityConstraints/tests/phpunit/
  ```

  This runs the tests without coverage report
  and is therefore much faster.

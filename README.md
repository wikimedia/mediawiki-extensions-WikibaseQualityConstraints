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

* Configure the extension.
  You can find the configuration settings with documentation in `extension.json`.
  (Note that the variable name for `LocalSettings.php` always begins with `wg`,
  e. g. `$wgWBQualityConstraintsClassId` for the `WBQualityConstraintsClassId` setting.)

  * Specify the entity IDs of entities that are used to define constraints.
    See the “Data import” section for an automatic way to do this.

  * If you have a SPARQL endpoint, configure it in `WBQualityConstraintsSparqlEndpoint`.

  * Alternatively, to check “format” constraints without running a full SPARQL server,
    you can use the [minisparql] server.

* Run `php maintenance/runScript.php extensions/WikibaseQualityConstraints/maintenance/ImportConstraintStatements.php`.

[minisparql]: https://github.com/lucaswerkmeister/minisparql

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

### Adding a new constraint type

To add a new constraint type, the following steps are necessary:

* Define the constraint checker class.
  * It should be defined in a new file in `src/ConstraintCheck/Checker/`,
    named after the class name.
    It should be in the `WikibaseQuality\ConstraintReport\ConstraintCheck\Checker` namespace.
  * The class name should follow the constraint type name (in English), ending in “Checker”.
  * The class must implement the `ConstraintChecker` interface.
  * It should have at least the following class-level documentation comment:
    ```php
    /**
     * @author YOUR NAME HERE
     * @license GNU GPL v2+
     */
    ```
  * Any services you need (`Config`, `EntityLookup`, …) should be injected as constructor parameters.
  * If the constraint has parameters,
    add support for parsing them to `ConstraintParameterParser`
    (add a config setting for the associated property in `extension.json`
    and a method to parse the parameter in `ConstraintParameterParser`),
    and then add tests for them in `ConstraintParameterParserTest`.
    This should be done in a separate commit.
* Define new messages (at least a violation message for the constraint type).
  * Define the message in `i18n/en.json`.
    A violation message should have a key like `wbqc-violation-message-constraintType`.
  * Document the message in `i18n/qqq.json`.
    Use the same message key,
    and insert the documentation in the same location where you also added the message in `en.json`
    (that is, `en.json` and `qqq.json` should contain message keys in the same order).
* Add a configuration setting for the constraint type item ID.
  * Configuration settings are defined in `extension.json`,
    as members of the `config` object.
  * It should be added right after the current last `…ConstraintId` entry.
  * It should be named after the constraint type item’s English label,
    following the pattern `WBQualityConstraints…ConstraintId`.
  * The default value should be the item ID on Wikidata,
    so that no extra configuration is required for Wikidata
    and importing the constraint type item (see “Data import” section) works.
  * The first part of the description can be copied from similar settings,
    the rest should contain a short description of the constraint type.
  * The ID can always be public (`"public": true`).
* Configure the constraint type checker in `ConstraintReportFactory`.
  * Add an array entry like
    ```php
    $this->config->get( 'WBQualityConstraints…ConstraintId' )
    	=> new …Checker(
    		// injected services
    	),
    ```
    at the end of the `getConstraintCheckerMap()` function.
* Add tests for the new constraint checker.
  * The test class name should be the same as the checker class name,
    with an additional suffix `Test` (i. e., `…CheckerTest`).
  * The test class should be placed somewhere in `tests/phpunit/Checker/`,
    either in the most suitable subdirectory
    or directly in that directory if none of the subdirectories are suitable.
    (The division into subdirectories there is dubious anyways,
    and we may get rid of it in the future.)
  * It should have at least the following class-level documentation comment:
    ```php
    /**
     * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\…Checker
     *
     * @group WikibaseQualityConstraints
     *
     * @author YOUR NAME HERE
     * @license GNU GPL v2+
     */
    ```
  * It should have at least one test for compliance with a constraint,
    one test for a constraint violation,
    one test for behavior on a deprecated statement,
    and one test for the `checkConstraintParameters` method.
  * Use the `ResultAssertions` trait’s methods to check constraint check results.
  * Use the `NewItem` and `NewStatement` builders to construct test data.
    (You might see `JsonFileEntityLookup` and separate JSON files used in some existing tests,
    but that’s a lot less readable.)
  * If the checker uses a `Config`, use the `DefaultConfig` trait.
  * If the constraint has parameters,
    add methods for them to the `ConstraintParameters` trait and use it in the tests.
  * You can copy+paste a `getConstraintMock` function from one of the existing tests,
    adjusting the `getConstraintTypeItemId` mocked return value.
    (Hopefully we’ll improve this in the future.)
* Update the tests for `DelegatingConstraintChecker`.
    * In `DelegatingConstraintCheckerTest`,
      add an entry for your constraint type to the `$constraints` array in `addDBData()`.
    * The `constraint_guid` should be `P1$`,
      followed by a new UUID (e. g. `cat /proc/sys/kernel/random/uuid` or `journalctl --new-id128`).
    * The `pid` should be `1`. (Not `'1'`!)
    * The `constraint_type_qid` should be `$this->getConstraintTypeItemId( '…' )`,
      where `…` is just the `…` part of the `WBQualityConstraints…ConstraintId` `extension.json` config key.
    * The `constraint_parameters` should be a valid JSON serialization of constraint parameters.
      If the constraint type doesn’t have any parameters, you can pass `{}`,
      otherwise there should ideally be methods to create the parameters in the `ConstraintParameters` trait
      so that you can use `json_encode( $this->…Parameter( … ) )`
      (perhaps with `array_merge` if there are multiple parameters).

An example commit that performs all of these steps is [Change Ica05406e14](https://gerrit.wikimedia.org/r/382715).

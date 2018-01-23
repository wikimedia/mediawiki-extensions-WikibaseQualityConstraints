#! /bin/bash

cd ../wiki/extensions/WikibaseQualityConstraints

export CI_BUILD_NUMBER="$TRAVIS_BUILD_NUMBER"
export CI_PULL_REQUEST="$TRAVIS_PULL_REQUEST"
export CI_BRANCH="$TRAVIS_BRANCH"

php vendor/bin/php-coveralls -v

#! /bin/bash

set -ex

cd ..
echo $PWD
php mediawiki/tests/phpunit/phpunit.php mediawiki/extensions/WikibaseReconcileEdit/tests

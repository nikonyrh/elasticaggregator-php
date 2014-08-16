#!/bin/bash
B=`dirname $0`
cd "$B/.."
phpunit -c tests/phpunit.xml.dist --coverage-html coverage

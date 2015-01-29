#!/bin/bash
B=`dirname $0`
cd "$B/.."
phpunit --configuration phpunit.xml --coverage-html coverage

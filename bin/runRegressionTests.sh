#!/bin/bash
B=`dirname $0`
cd "$B/.."
php src/NikoNyrh/ElasticAggregator/RegressionTests/AggregationQueryRegressionTest.php localhost:9200

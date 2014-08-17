ElasticAggregator
=================

An utility PHP wrapper for [elasticsearch-php](https://github.com/elasticsearch/elasticsearch-php), for an easier usage of [ElasticSearch](http://www.elasticsearch.org/overview/elasticsearch)'s [Aggregation](http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/search-aggregations.html) framework. Its DSL is fairly straight-forward, but nested arrays are tedious and error-prone to be manually constructed. Also resulting responses have a quite verbose structure which is not typically necessary.


Features and goals
--------
 - Simple aggregation query construction ([AggregationQuery.php](https://github.com/NikoNyrh/ElasticAggregator/blob/master/src/NikoNyrh/ElasticAggregator/AggregationQuery.php))
 - Simple aggregation result parsing and "condensing" ((AggregationResponse.php)[https://github.com/NikoNyrh/ElasticAggregator/blob/master/src/NikoNyrh/ElasticAggregator/AggregationResponse.php])
 - Clean, minimalistic interfaces (combined at (Aggregator.php)[https://github.com/NikoNyrh/ElasticAggregator/blob/master/src/NikoNyrh/ElasticAggregator/Aggregator.php])
 - Well tested codebase
 - Configurable and customizable for differend needs


TODO
--------
 - Decide a better naming convention of public functions (to reflect better the underlying bucketing and metric aggregations), **this will break code compatibility with this initial version**
 - Support for more than one aggregation at any query level (currenly all bucketing aggregations are nested within the previous aggregation)
 - Improve the overall the flexibility of the library, now this only works on trivial use cases
 - Write tests for all existing aggregation queries
 - Use 4 spaces instead of tabs? That PSR-2 stuff... :(
 - Start using this in my own procjets instead of its earlier un-tested version
 - Confirm that including this project via Composer works as intended
 - Is it good idea to configure index name at Aggregator constructor but configure object's type in exec() method?
 - Actual documentation on how stuff works, especially if the codebase grows significantly bigger


Example usage
--------
```php
// Create the standard ElasticSearch client object
$client = new \Elasticsearch\Client(array(
    'hosts' => 'localhost:9200',
));

// Create the aggregator, configure the index name
$aggregator = new \NikoNyrh\ElasticAggregator\Aggregator(
    $client,
    array('index' => 'index_name')
);

// For each "user" get stats on post_length field
$result = $aggregator
    ->aggregate('terms', 'user')
    ->stats('post_length')
    ->exec('type_name');

echo json_encode($result, JSON_PRETTY_PRINT);
```

Result:
```json
{
    "User 4": {
        "post_length": {
            "count": 25,
            "min": 34,
            "max": 193,
            "avg": 109.44,
            "sum": 2736
        }
    },
    "User 2": {
        "post_length": {
            "count": 22,
            "min": 31,
            "max": 199,
            "avg": 121.5,
            "sum": 2673
        }
    }
}
```

The corresponding raw query and response of this simple aggregation look like this:
```json
{
    "index": "index_name",
    "type": "type_name",
    "body": {
        "size": 0,
        "aggs": {
            "user_agg": {
                "terms": {
                    "field": "user"
                },
                "aggs": {
                    "post_length_stats": {
                        "stats": {
                            "field": "post_length"
                        }
                    }
                }
            }
        }
    }
}
```
and
```json
{
    "took": 1,
    "timed_out": false,
    "_shards": {
        "total": 1,
        "successful": 1,
        "failed": 0
    },
    "hits": {
        "total": 100,
        "max_score": 0,
        "hits": []
    },
    "aggregations": {
        "user_agg": {
            "buckets": [
                {
                    "key": "User 4",
                    "doc_count": 25,
                    "post_length_stats": {
                        "count": 25,
                        "min": 34,
                        "max": 193,
                        "avg": 109.44,
                        "sum": 2736
                    }
                },
                {
                    "key": "User 2",
                    "doc_count": 22,
                    "post_length_stats": {
                        "count": 22,
                        "min": 31,
                        "max": 199,
                        "avg": 121.5,
                        "sum": 2673
                    }
                }
            ]
        }
    }
}
```

I guess you see my point on why this is usually overly verbose.


License
-------
Copyright 2014 Niko Nyrhilä

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

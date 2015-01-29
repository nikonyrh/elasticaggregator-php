<?php
namespace NikoNyrh\ElasticAggregator;

/*
	This is the main intended usage of this library:
		$client = new \Elasticsearch\Client(array(
			'hosts' => array('localhost:9200'),
		));
		
		$aggregator = new \NikoNyrh\ElasticAggregator\Aggregator(
			$client,
			array('index' => 'index_name')
		);
		
		$result = $aggregator
			->aggregate('terms', 'user')
			->stats('post_length')
			->exec('type_name');
	
	
	$result is something like:
	Array
	(
		[User 4] => Array
			(
				[post_length] => Array
					(
						[count] => 25
						[min] => 34
						[max] => 193
						[avg] => 109.44
						[sum] => 2736
					)

			)

		[User 2] => Array
			(
				[post_length] => Array
					(
						[count] => 22
						[min] => 31
						[max] => 199
						[avg] => 121.5
						[sum] => 2673
					)

			)
	)
*/

class Aggregator extends AggregationQuery
{
	protected $response;
	
	public function __construct(
		$esClient,
		array $config = array()
	) {
		$this->response = new AggregationResponse(
			$esClient,
			$config
		);
	}
	
	public function exec($type, $config = array())
	{
		return $this->response->exec(
			$this,
			$type,
			$config
		);
	}
}

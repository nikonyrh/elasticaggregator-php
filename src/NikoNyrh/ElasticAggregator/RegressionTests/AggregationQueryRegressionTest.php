<?php
namespace NikoNyrh\ElasticAggregator\RegressionTests;

require __DIR__ . '/../../../../vendor/autoload.php';

class AggregationQueryRegressionTest
{
	protected $client;
	protected $index;
	
	public function __construct(
		$esClient,
		$index = '.elastic_aggregator_test'
	) {
		$this->client = $esClient;
		$this->index  = $index;
	}
	
	public function getIndex()
	{
		return $this->index;
	}
	
	public function run()
	{
		$this->deleteIndex();               // Delete previous index,
		$this->createIndex($this->index);   // re-create it (in case mapping has changed)
		$this->populateIndex($this->index); // and generate pseudorandom data
		
		// Get example plain-text queries from unit tests reference output
		$queries = $this->loadQueries(
			__DIR__ . '/../Tests/AggregationQueryTest.php'
		);
		
		// Store queries and their responses here:
		$outFolder = __DIR__ . '/results';
		if (!is_dir($outFolder)) {
			mkdir($outFolder);
		}
		
		// Execute each query and store the result
		foreach ($queries as $i => $query) {
			$response = $this->client->search(array(
				'index' => $this->index,
				'type'  => 'post',
				'body'  => $query
			));
			
			file_put_contents(
				$outFolder . sprintf('/result_%02d.json', $i+1),
				json_encode(array(
					'query'    => $query,
					'response' => $response
				), JSON_PRETTY_PRINT)
			);
		}
	}
	
	protected function loadQueries($fname)
	{
		// I hope I won't need to tweak this pattern in future...
		preg_match_all(
			'/->decode\(.[ \t\n\r]*(\{.*?\})[ \t\n\r]*.\)/s',
			file_get_contents($fname),
			$match
		);
		
		$queries = array();
		foreach ($match[1] as $query) {
			$queries[] = json_decode($query, true);
		}
		
		return $queries;
	}
	
	public function deleteIndex()
	{
		// Return the success indicator, not that we really care.
		try {
			$this->client->indices()->delete(array(
				'index' => $this->index
			));
			return true;
		}
		catch (\Exception $e) {
			return false;
		}
	}
	
	protected function createIndex($index)
	{
		$integer  = array('type' => 'integer');
		$float    = array('type' => 'float');
		$date     = array('type' => 'date',   'format' => 'basic_date');
		$string   = array('type' => 'string', 'index'  => 'not_analyzed');
		$aString  = array('type' => 'string');
		
		// This will need to evolve as new features
		// are added, such as geospatial queries.
		$body = array(
			'settings' => array(
				'number_of_shards'   => 1,
				'number_of_replicas' => 0
			),
			'mappings' => array(
				'post' => array(
					'properties' => array(
						'id'            => $integer,
						'post_date'     => $date,
						'post_length'   => $integer,
						'tag'           => $string,
						'user'          => $string,
						'num_of_tags'   => $integer,
						'link'        => array(
							'type' => 'nested',
							'properties' => array(
								'url'   => $string,
								'title' => $string
							)
						)
					)
				)
			)
		);
		
		$this->client->indices()->create(array(
			'index' => $index,
			'body' =>  $body
		));
	}
	
	protected function populateIndex($index)
	{
		$tags  = explode(' ', 'MySQL ES MSSQL MongoDB RedShift');
		$links = array(
			array('url' => 'www.google.com/path1', 'title' => 'G Path 1'),
			array('url' => 'www.google.com/path2', 'title' => 'G Path 2'),
			array('url' => 'www.google.com/path3', 'title' => 'G Path 3'),
			array('url' => 'www.bing.com/path1',   'title' => 'B Path 1'),
			array('url' => 'www.bing.com/path2',   'title' => 'B Path 2'),
			array('url' => 'www.bing.com/path3',   'title' => 'B Path 3'),
			array('url' => 'www.bing.com/path4',   'title' => 'B Path 4')
		);
		
		$numOfTags = function($i) use ($tags) {
			return ($i * ($i + 2)) % sizeof($tags);
		};
		
		$genTags = function($i) use ($tags, $numOfTags) {
			$n = $numOfTags($i);
			return $n > 0 ? array_slice($tags, 0, $n) : null;
		};
		
		$genLinks = function($i) use ($links) {
			$nLinks = sizeof($links);
			$n      = round(pow((($i * $i * ($i + 1)) % 100) * 0.01, 2) * $nLinks);
			
			return $n > 0 ? array_slice($links, 0, $n) : null;
		};
		
		$refDate = mktime(0, 0, 0, 1, 1, 2014);
		
		// Generate and store 100 "post" type documents
		foreach (range(1, 100) as $i) {
			$this->client->index(array(
				'index' => $index,
				'type'  => 'post',
				'id'    => $i,
				'body'  => array(
					'id'          => $i,
					'user'        => sprintf('User %d', 1 + (round(pow($i, 1.5)) % 5)),
					'post_date'   => date('Ymd', $refDate + $i * 3600*11),
					'post_length' => 30 + (($i * $i) % 171),
					'num_of_tags' => $numOfTags($i),
					'tag'         => $genTags($i),
					'link'        => $genLinks($i)
				)
			));
		}
		
		// Flush to make sure that documents show up in our queries
		$this->client->indices()->flush(array('index' => $index));
	}
}

if (!isset($argv[1])) {
	die(sprintf("Usage: php %s localhost:9200\n", basename(__FILE__)));
}

// Populate the data
$client = new \Elasticsearch\Client(array(
	'hosts' => array($argv[1]),
));

$test = new AggregationQueryRegressionTest($client);
$test->run();

// Test aggregation
$aggregator = new \NikoNyrh\ElasticAggregator\Aggregator(
	$client,
	array('index' => $test->getIndex())
);

$result = $aggregator
	->aggregate('terms', 'user')
	->stats('post_length')
	->exec('post');

/*
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

// Clean up
$test->deleteIndex();

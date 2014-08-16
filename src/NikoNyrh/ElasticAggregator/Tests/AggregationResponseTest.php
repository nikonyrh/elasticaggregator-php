<?php
namespace NikoNyrh\ElasticAggregator\Tests;

class AggregationResponseTest extends \PHPUnit_Framework_TestCase
{
	protected static $testData;
	
	public static function setUpBeforeClass()
	{
		self::$testData = array();
		$glob = __DIR__ . '/Fixtures/Queries/*.json';
		
		foreach (glob($glob) as $fname) {
			self::$testData[$fname] = json_decode(
				file_get_contents($fname),
				true
			);
			
			if (empty(self::$testData[$fname])) {
				die("Failed to load $fname!\n");
			}
		}
		
		if (empty(self::$testData)) {
			die("Failed to find any queries from '$glob'!\n");
		}
	}
	
	protected function getQuery($response) {
		return new Fixtures\MockQuery(
			$response
		);
	}
	
	protected function getResponse()
	{
		return new \NikoNyrh\ElasticAggregator\AggregationResponse(
			new Fixtures\MockClient(),
			array(
				'index' => 'test_index'
			)
		);
	}
	
	public function testResponseParser()
	{
		foreach (self::$testData as $fname => $testCase) {
			$result = $this->getResponse()->exec(
				$this->getQuery($testCase['response']),
				'test_type'
			);
			
			if (!isset($testCase['expected'])) {
				// This enables easy copy-pasting of JSON when writing new test cases,
				// after having checked that the output is indeed correct.
				die("\nNo expected result for:\n\n" .
					json_encode($result, JSON_PRETTY_PRINT) . "\n");
			}
			else {
				$this->assertEquals(
					$testCase['expected'],
					$result,
					$testCase['name'] . ' (file ' . basename($fname) . ')'
				);
			}
		}
	}
}

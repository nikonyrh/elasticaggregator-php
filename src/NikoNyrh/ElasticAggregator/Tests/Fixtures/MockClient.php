<?php
namespace NikoNyrh\ElasticAggregator\Tests\Fixtures;

class MockClient
{
	public function __construct()
	{
	}
	
	public function search($query)
	{
		$body = $query['body'];
		return is_array($body) ? $body : json_decode($body, true);
	}
}

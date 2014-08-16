<?php
namespace NikoNyrh\ElasticAggregator\Tests\Fixtures;

class MockQuery
{
	protected $body;
	
	public function __construct($body)
	{
		$this->body = $body;
	}
	
	public function buildBody()
	{
		return $this->body;
	}
}

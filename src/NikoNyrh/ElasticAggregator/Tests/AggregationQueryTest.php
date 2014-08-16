<?php
namespace NikoNyrh\ElasticAggregator\Tests;

class AggregationQueryTest extends \PHPUnit_Framework_TestCase
{
	protected function query()
	{
		return new \NikoNyrh\ElasticAggregator\AggregationQuery();
	}
	
	protected function decode($json)
	{
		// Return an array, not an object
		return json_decode($json, true);
	}
	
	public function testBasicAggregation_1()
	{
		// - Find users with most posts,
		// - group posts by date and
		// - calculate stats on post lengths
		$result = $this->query()
			->aggregate('terms', 'user')
			->aggregate('date_histogram', array(
				'field'         => 'post_date',
				'interval'      => '60d',
				'min_doc_count' => 1
			))
			->stats('post_length')
			->buildBody();
		
		$this->assertEquals($this->decode('
			{
				"size": 0,
				"aggs": {
					"user_agg": {
						"terms": {
							"field": "user"
						},
						"aggs": {
							"post_date_agg": {
								"date_histogram": {
									"field": "post_date",
									"interval": "60d",
									"min_doc_count": 1
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
			}
		'), $result);
	}
	
	public function testBasicAggregation_2()
	{
		// - Group posts by length and
		// - filter on of posts tagged as ES and
		// - get maximum post length and the average number of tags
		$result = $this->query()
			->aggregate('histogram', array(
				'field'           => 'post_length',
				'interval'        => 25,
				'extended_bounds' => array(
					'min' => 0,
					'max' => 250
				)
			))
			->aggregate('filter', array(
				'field'     => 'tag',
				'subtype'   => 'term',
				'condition' => 'ES'
			))
			->stats(array(
				array('type' => 'avg', 'field' => 'num_of_tags'),
				array('type' => 'max', 'field' => 'num_of_tags')
			))
			->buildBody();
		
		$this->assertEquals($this->decode('
			{
				"size": 0,
				"aggs": {
					"post_length_agg": {
						"histogram": {
							"field": "post_length",
							"interval": 25,
							"min_doc_count": 0,
							"extended_bounds": {
								"min": 0,
								"max": 250
							}
						},
						"aggs": {
							"tag_filter": {
								"filter": {
									"term": {
										"tag": "ES"
									}
								},
								"aggs": {
									"num_of_tags_avg": {
										"avg": {
											"field": "num_of_tags"
										}
									},
									"num_of_tags_max": {
										"max": {
											"field": "num_of_tags"
										}
									}
								}
							}
						}
					}
				}
			}
		'), $result);
	}
	
	public function testBasicFiltering_1()
	{
		// - Keep posts not tagged as ES or MySQL and
		// - keep posts longer than 50 characters and
		// - get users with most matchings posts
		$result = $this->query()
			->filter('not', array(
				'type'      => 'terms',
				'condition' => array('tag' => array('ES', 'MySQL'))
			))
			->filter('range', array(
				'field'     => 'post_length',
				'condition' => array('lt' => 50)
			))
			->aggregate('terms', 'user')
			->buildBody();
		
		$this->assertEquals($this->decode('
			{
				"size": 0,
				"aggs": {
					"user_agg": {
						"terms": {
							"field": "user"
						}
					}
				},
				"query": {
					"filtered": {
						"filter": {
							"and": [
								{
									"not": {
										"terms": {
											"tag": [
												"ES",
												"MySQL"
											]
										}
									}
								},
								{
									"range": {
										"post_length": {
											"lt": 50
										}
									}
								}
							]
						}
					}
				}
			}
		'), $result);
	}
	
	public function testBasicFiltering_2()
	{
		// - Keep posts not tagged as ES, or having ids 1, 2 or 3
		// - get users with most matchings posts
		$result = $this->query()
			->filter('or', array(
				array(
					'type'      => 'term',
					'condition' => array('tag' => 'ES')
				),
				array(
					// This is an example usage if the desired filter is not "natively"
					// implemented. Just put the whole thing to the 'type' element.
					'type' => array(
						'ids' => array(
							'type'   => 'post',
							'values' => array(1, 2, 3)
						)
					)
				)
			))
			->aggregate('terms', 'user')
			->stats(array('post_length', 'num_of_tags'))
			->buildBody();
		
		$this->assertEquals($this->decode('
			{
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
							},
							"num_of_tags_stats": {
								"stats": {
									"field": "num_of_tags"
								}
							}
						}
					}
				},
				"query": {
					"filtered": {
						"filter": {
							"or": [
								{
									"term": {
										"tag": "ES"
									}
								},
								{
									"ids": {
										"type": "post",
										"values": [
											1,
											2,
											3
										]
									}
								}
							]
						}
					}
				}
			}
		'), $result);
	}
	
	public function testBasicFiltering_3()
	{
		$result = $this->query()
			->filter('nested', array(
				'path'      => 'link',
				'type'      => 'prefix',
				'condition' => array('url' => 'www.google.com/')
			))
			// An example custom aggregation: the first parameter
			// is an array and no 2nd parameter is needed.
			->aggregate(array(
				'name' => 'no_tag',
				'aggs'  => array(
					'missing' => array(
						'field' => 'tag'
					)
				)
			))
			->aggregate('terms', 'user')
			->buildBody();
		
		$this->assertEquals($this->decode('
			{
				"size": 0,
				"aggs": {
					"no_tag": {
						"missing": {
							"field": "tag"
						},
						"aggs": {
							"user_agg": {
								"terms": {
									"field": "user"
								}
							}
						}
					}
				},
				"query": {
					"filtered": {
						"filter": {
							"nested": {
								"path": "link",
								"filter": {
									"prefix": {
										"link.url": "www.google.com/"
									}
								}
							}
						}
					}
				}
			}
		'), $result);
	}
	
	/**
	* @expectedException InvalidArgumentException
	*/
	public function testInvalidFilterType()
	{
		$result = $this->query()->filter('no_such_filter');
	}
	
	/**
	* @expectedException InvalidArgumentException
	*/
	public function testInvalidAggregationType()
	{
		$result = $this->query()->aggregate('no_such_aggregate');
	}
}

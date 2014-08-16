<?php
namespace NikoNyrh\ElasticAggregator;

class AggregationResponse
{
	protected $esClient;
	protected $config;
	
	public function __construct($esClient, array $config = array())
	{
		$this->esClient = $esClient;
		$this->config   = $config;
	}
	
	public function exec($query, $type, $config = array())
	{
		$body = $query->buildBody();
		
		$search = array(
			'index' => $this->config['index'],
			'type'  => $type,
			'body'  => $body
		);
		
		if (isset($config['getSearch']) && $config['getSearch']) {
			return $search;
		}
		
		$response = $this->esClient->search($search);
		
		if (isset($config['getResponse']) && $config['getResponse']) {
			return $response;
		}
		
		$parse = function ($response) use (&$parse) {
			if (sizeof($response) == 1) {
				$key = array_keys($response);
				$key = $key[0];
				
				if (isset($response[$key]['buckets'])) {
					$result = array();
					foreach ($response[$key]['buckets'] as $bucket) {
						$resultKey = $bucket['key'];
						
						if (
							isset($bucket['key_as_string']) &&
							is_int($resultKey) &&
							($resultKey % 1000) == 0
						) {
							$resultKey = (new \DateTime(
								'@' . ($resultKey/1000)
							))->format('Y-m-d H:i:s');
						}
						
						unset($bucket['key']);
						
						if (isset($bucket['key_as_string'])) {
							unset($bucket['key_as_string']);
						}
						
						if (sizeof($bucket) > 1) {
							unset($bucket['doc_count']);
						}
						
						$result[$resultKey] = $parse($bucket);
					}
					
					return $result;
				}
				elseif (
					sizeof($response[$key]) > 1 &&
					isset($response[$key]['doc_count'])
				) {
					unset($response[$key]['doc_count']);
					return $parse($response[$key]);
				}
			}
			
			if (
				sizeof($response) == 1 &&
				isset($response['doc_count']) &&
				!is_array($response['doc_count'])
			) {
				return $response['doc_count'];
			}
			
			$result = array();
			foreach ($response as $key => $value) {
				$result[preg_replace('/_stats$/', '', $key)] = $value;
			}
			
			return $result;
		};
		
		return $parse($response['aggregations']);
	}
}

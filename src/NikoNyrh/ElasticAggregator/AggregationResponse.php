<?php
namespace NikoNyrh\ElasticAggregator;

class AggregationResponse
{
	protected $esClient;
	protected $config;
	
	// A lazy way to toggle debug printing :P
	protected static function debug($msg) {
		// echo $msg;
	}
	
	public function __construct($esClient, array $config = array())
	{
		$this->esClient = $esClient;
		$this->config   = $config;
	}
	
	public function exec($query, $type, $config = array())
	{
		$search = array(
			'index' => $this->config['index'],
			'type'  => $type,
			'body'  => $query->buildBody()
		);
		
		if (isset($config['getSearch']) && $config['getSearch']) {
			return $search;
		}
		
		$response = $this->esClient->search($search);
		
		if (isset($config['getResponse']) && $config['getResponse']) {
			return $response;
		}
		
		$keysToSkip = array(
			'doc_count_error_upper_bound',
			'sum_other_doc_count'
		);
		
		$removeKeysToSkip = function (&$arr) use (&$removeKeysToSkip, $keysToSkip) {
			if (!is_array($arr)) {
				return;
			}
			
			foreach ($keysToSkip as $key) {
				if (isset($arr[$key])) {
					unset($arr[$key]);
				}
			}
			
			foreach (array_keys($arr) as $key) {
				$removeKeysToSkip($arr[$key]);
			}
		};
		
		$removeKeysToSkip($response);
		
		$parse = function ($response) use (&$parse) {
			if (sizeof($response) == 1) {
				$key = array_keys($response);
				$key = $key[0];
				
				if (isset($response[$key]['buckets'])) {
					self::debug(sprintf(
						"Case 1a (%s): %s\n",
						$key, print_r($response[$key], true)
					));
					
					$result = array();
					foreach ($response[$key]['buckets'] as $bucket) {
						$resultKey = $bucket['key'];
						
						if (
							isset($bucket['key_as_string']) &&
							is_int($resultKey) && ($resultKey % 1000) == 0
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
					self::debug(sprintf(
						"Case 1b (%s): %s\n",
						$key, print_r($response[$key], true)
					));
					
					unset($response[$key]['doc_count']);
					return $parse($response[$key]);
				}
				else {
					self::debug(sprintf(
						"Case 1c (%s): %s\n",
						$key, print_r($response[$key], true)
					));
				}
			}
			elseif (
				isset($response['parent'])
			) {
				self::debug(sprintf(
					"Case 2a: %s\n", print_r($response, true)
				));
				
				$parent = $response['parent'];
				unset($response['parent']);
				
				$tmp = $parse($response);
				$tmp['parent'] = $parse(array('parent' => $parent));
				return $tmp;
			}
			else {
				self::debug(sprintf(
					"Case 2b %s\n", print_r($response, true)
				));
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
		
		$result = $parse($response['aggregations']);
		self::debug(sprintf("Result: %s\n", print_r($result, true)));
		return $result;
	}
}

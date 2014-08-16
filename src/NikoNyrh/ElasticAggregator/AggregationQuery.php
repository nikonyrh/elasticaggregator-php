<?php
namespace NikoNyrh\ElasticAggregator;

class AggregationQuery
{
	protected $filters;
	protected $aggregates;
	protected $nestedPath;
	
	public function __construct()
	{
		$this->init();
	}
	
	// This is also called from buildBody, to clear previous statements
	public function init()
	{
		$this->filters    = array();
		$this->aggregates = array();
		$this->nestedPath = array();
	}
	
	public function genFilter($type, $config = null)
	{
		$passThroughTypes = array_flip(array(
			'prefix',
			'range',
			'terms',
			'term'
		));
		
		$nestedPath = empty($this->nestedPath) ?
			'' : (implode('.', $this->nestedPath) . '.');
		
		if (is_array($type)) {
			return $type;
		}
		elseif (isset($passThroughTypes[$type])) {
			if (is_array($config)) {
				if (isset($config['field'])) {
					$config = array($nestedPath . $config['field'] => $config['condition']);
				}
				elseif (sizeof($config) == 1 && !empty($nestedPath)) {
					$field = key($config);
					$config = array($nestedPath . $field => $config[$field]);
				}
			}
			
			return array(
				$type => $config
			);
		}
		elseif ($type == 'not') {
			return array(
				$type => $this->genFilter($config['type'], $config['condition'])
			);
		}
		elseif ($type == 'or') {
			foreach ($config as $key => $c) {
				$config[$key] = $this->genFilter(
					$c['type'],
					isset($c['condition']) ? $c['condition']  : null
				);
			}
			
			return array(
				$type => $config
			);
		}
		elseif ($type == 'nested') {
			// Push the path and get the filter
			array_push($this->nestedPath, $config['path']);
			$result = array(
				$type => array(
					'path' => $config['path'],
					'filter' => $this->genFilter(
						$config['type'],
						$config['condition']
					)
				)
			);
			
			// Pop the path and return the result
			array_pop($this->nestedPath);
			return $result;
		}
		else {
			throw new \InvalidArgumentException("Unknown type '$type'!");
		}
	}
	
	public function filter($type, $config = null)
	{
		$this->filters[] = $this->genFilter($type, $config);
		return $this;
	}
	
	public function aggregate($type, $config = null)
	{
		$i = sizeof($this->aggregates) + 1;
		
		if (is_array($type)) {
			$this->aggregates[$type['name'] . "_$i"] = $type['aggs'];
		}
		elseif ($type == 'terms') {
			$this->aggregates[$config . "_agg_$i"] = array(
				$type => array('field' => $config)
			);
		}
		elseif ($type == 'date_histogram') {
			$this->aggregates[$config['field'] . "_agg_$i"] = array(
				$type => array(
					'field'         => $config['field'],
					'interval'      => $config['interval'],
					'min_doc_count' => isset($config['min_doc_count']) ?
						$config['min_doc_count'] : 0
				)
			);
		}
		elseif ($type == 'histogram') {
			$this->aggregates[$config['field'] . "_agg_$i"] = array(
				$type => array(
					'field'           => $config['field'],
					'interval'        => $config['interval'],
					'min_doc_count'   => isset($config['min_doc_count']) ?
						$config['min_doc_count'] : 0,
					'extended_bounds' => isset($config['extended_bounds']) ?
						$config['extended_bounds'] : null
				)
			);
		}
		elseif ($type == 'filter') {
			$this->aggregates[$config['field'] . "_filter_$i"] = array(
				$type => array(
					$config['subtype'] => array(
						$config['field'] => $config['condition']
					)
				)
			);
		}
		else {
			throw new \InvalidArgumentException("Unknown type '$type'!");
		}
		
		return $this;
	}
	
	public function stats($fields)
	{
		if (!is_array($fields)) {
			$fields = array($fields);
		}
		
		$stats = array();
		
		foreach ($fields as $field) {
			$i = sizeof($this->aggregates) + 1;
			if (is_array($field)) {
				$stats[$field['field'] . '_' . $field['type']] = array(
					$field['type'] => array('field' => $field['field'])
				);
			}
			else {
				$stats[$field . '_stats'] = array(
					'stats' => array('field' => $field)
				);
			}
		}
		
		$this->aggregates["_merged_$i"] = $stats;
		
		return $this;
	}
	
	public function buildBody()
	{
		$aggs = array();
		
		// The most recently added aggregations go to the deepest nesting level
		foreach (array_reverse(array_keys($this->aggregates)) as $key) {
			$aggKey = preg_replace('/_[0-9]+$/', '', $key);
			
			if (empty($aggs)) {
				$aggs = array($aggKey => $this->aggregates[$key]);
			}
			else {
				$agg = $this->aggregates[$key];
				$agg['aggs'] = $aggs;
				$aggs = array($aggKey => $agg);
			}
			
			if ($aggKey == '_merged') {
				$aggs = $aggs[$aggKey];
			}
		}
		
		$body = array(
			'size' => 0,
			'aggs' => $aggs
		);
		
		if (!empty($this->filters)) {
			if (sizeof($this->filters) == 1) {
				// Single filter works on its own
				$filter = $this->filters[0];
			}
			else {
				// Multiple filters are combined by "and" filter
				$filter = array(
					'and' => $this->filters
				);
			}
			
			$body['query'] = array(
				'filtered' => array(
					'filter' => $filter
				)
			);
		}
		
		$this->init();
		return $body;
	}
}

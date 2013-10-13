<?php
namespace DB;

class Mongo {
	private static $db = false;
	private $config;

	public function __construct ($config) {
		$this->config = $config;
	}

	private function connect () {
		if (self::$db === false) {
			$config = $this->config;
			$config = $config::db();
			$client = new \MongoClient($config['conn']);
			self::$db = new \MongoDB($client, $config['name']);
		}
	}

	public function collection ($collection) {
		$this->connect();
		return new \MongoCollection(self::$db, $collection);
	}

	public function id ($id) {
		return new \MongoId((string)$id);
	}

	public function mapReduce ($map, $reduce, Array $command, &$response=[], $fetch=true) {
		$this->connect();
		$command['map'] = new \MongoCode($map);
		$command['reduce'] = new \MongoCode($reduce);
		$response = self::$db->command($command);
		if ($response['ok'] != 1) {
			throw new \Exception(print_r($response, true));
		}
		if (!$fetch) {
			return true;	
		}
		$collection = false;
		if (isset($command['out'])) {
			$collection = $command['out'];
		}
		if ($collection === false) {
			return true;
		}
		return $this->collection($collection);
	}
}
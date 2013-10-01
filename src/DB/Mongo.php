<?php
namespace DB;
use Config\Config;

class Mongo {
	private static $config = false;
	private static $client = false;
	private static $db = false;

	private static function connect () {
		if (self::$config === false) {
			self::$config = Config::db();
		}
		if (self::$client === false) {
			self::$client = new \MongoClient(self::$config['conn']);
		}
		if (self::$db === false) {
			self::$db = new \MongoDB(self::$client, self::$config['name']);
		}
	}

	public static function collection ($collection) {
		self::connect();
		return new \MongoCollection(self::$db, $collection);
	}

	public static function id ($id) {
		return new \MongoId((string)$id);
	}

	public static function mapReduce ($map, $reduce, Array $command, &$response=[], $fetch=true) {
		self::connect();
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
		return self::collection($collection);
	}
}
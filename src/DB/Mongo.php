<?php
/**
 * virtuecenter\db
 *
 * Copyright (c)2013 Ryan Mahoney, https://github.com/virtuecenter <ryan@virtuecenter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace DB;

class Mongo {
	private static $db = false;
	private $config;
	private $topic;

	public function __construct ($config, $topic) {
		$this->config = $config;
		$this->topic = $topic;
	}

	private function connect () {
		if (self::$db === false) {
			$client = new \MongoClient($this->config->db['conn']);
			self::$db = new \MongoDB($client, $this->config->db['name']);
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

	public function documentStage ($dbURI, $document) {
		return new Document($this, $dbURI, $document, $this->topic);
	}
}
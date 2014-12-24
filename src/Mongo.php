<?php
/**
 * Opine\DB\Mongo
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
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
namespace Opine\DB;

use Exception;
use MongoCollection;
use MongoId;
use MongoClient;
use MongoDB;
use MongoCode;
use MongoDate;
use MongoCursor;
use Closure;
use Opine\Interfaces\DB as DbInterface;
use Opine\Interfaces\Topic as TopicInterface;

class Mongo implements DbInterface
{
    private $client;
    private static $db = false;
    private $topic;
    private $userId = false;
    private $dbName;
    private $dbConn;

    public function __construct(Array $config, TopicInterface $topic)
    {
        $this->dbName = $config['name'];
        $this->dbConn = $config['conn'];
        $this->topic = $topic;
    }

    public function userIdSet($userId)
    {
        $this->userId = $userId;

        return true;
    }

    private function connect()
    {
        if (self::$db === false) {
            $this->client = new MongoClient($this->dbConn);
            self::$db = new MongoDB($this->client, $this->dbName);
        }
    }

    public function collectionList($system = false)
    {
        $this->connect();

        return self::$db->listCollections($system);
    }

    public function collection($collection)
    {
        $this->connect();

        return new MongoCollection(self::$db, $collection);
    }

    public function each(MongoCursor $cursor, Closure $callback)
    {
        while ($cursor->hasNext()) {
            $callback($cursor->getNext());
        }

        return true;
    }

    public function id($id = false)
    {
        if ($id === false) {
            return new MongoId();
        }

        return new MongoId((string) $id);
    }

    public function mapReduce($map, $reduce, Array $command, &$response = [], $fetch = true)
    {
        $this->connect();
        $command['map'] = new MongoCode($map);
        $command['reduce'] = new MongoCode($reduce);
        $response = self::$db->command($command);
        if ($response['ok'] != 1) {
            throw new Exception(print_r($response, true));
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

    public function document($dbURI, $document = [])
    {
        return new Document($this, $dbURI, $document, $this->topic, $this->userId);
    }

    public function distinct($collection, $key, array $query = [])
    {
        if (empty($query)) {
            $query = [];
        }
        $this->connect();
        $result = self::$db->command(['distinct' => $collection, 'key' => $key, 'query' => $query]);

        return $result['values'];
    }

    public function fetchAllGrouped(MongoCursor $cursor, $key, $value, $assoc = false)
    {
        $rows = [];
        while ($cursor->hasNext()) {
            $tmp = $cursor->getNext();
            if (is_array($value)) {
                if ($assoc === true) {
                    $rows[(string) $tmp[$key]] = [];
                    foreach ($value as $val) {
                        if (!isset($tmp[$val])) {
                            $rows[trim((string) $tmp[$key])][$val] = null;
                            continue;
                        }
                        $rows[trim((string) $tmp[$key])][trim($val)] = $tmp[$val];
                    }
                } else {
                    foreach ($value as $val) {
                        $rows[trim((string) $tmp[$key])] .= (string) $tmp[$val].' ';
                    }
                    $rows[trim((string) $tmp[$key])] = trim($rows[(string) $tmp[$key]]);
                }
            } elseif (is_callable($value)) {
                $rows[trim((string) $tmp[$key])] = $value($tmp);
            } else {
                if (!isset($tmp[$key]) || !isset($tmp[$value])) {
                    continue;
                }
                $rows[trim((string) $tmp[$key])] = (string) $tmp[$value];
            }
        }

        return $rows;
    }

    public function date($dateString = false)
    {
        if ($dateString === false) {
            $dateString = strtotime('now');
        } else {
            if (!is_numeric($dateString)) {
                $dateString = strtotime($dateString);
            }
        }

        return new MongoDate($dateString);
    }
}

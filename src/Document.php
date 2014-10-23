<?php
/**
 * Opine\Document
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
namespace Opine;
use MongoDate;
use Exception;
use Opine\Framework;

class Document {
    private $collection;
    private $document;
    private $db;
    private $id;
    private $dbURI;
    private $topic;
    private $embeddedPath = false;
    private $embeddedId = false;
    private $embeddedDocument = [];
    private $embeddedMode = 'update';

    public function __construct ($db, $dbURI, $document, $topic) {
        $this->db = $db;
        $this->document = $document;
        $this->topic = $topic;
        $this->dbURI = $dbURI;
        list($this->collection, $this->id) = explode(':', $dbURI, 2);
        if (substr_count($this->id, ':') > 0) {
            $this->embeddedPath = $this->idToOffset();
        }
    }

    public function upsert ($document=false) {
        if ($document !== false) {
            $this->document = $document;
        }
        $documentIdRetained = false;
        if (isset($this->document['_id'])) {
            $documentIdRetained = $this->document['_id'];
            unset ($this->document['_id']);
        }

        //user id
        $userId = Framework::keyGet('user_id');

        //handle modification / version history
        if ($this->embeddedPath === false) {
            $check = $this->db->collection($this->collection)->findOne(['_id' => $this->db->id($this->id)]);
        } else {
            $check = $this->embeddedDocument;
        }
        $this->document['dbURI'] = $this->dbURI;
        if (isset($check['_id'])) {
            $this->document['modified_date'] = new MongoDate(strtotime('now'));
            if (!isset($this->document['created_date'])) {
                $dateId = $this->db->id($this->id);
                $this->document['created_date'] = new MongoDate($dateId->getTimestamp());
            }
            if ($userId !== false) {
                $this->document['modified_user'] = $this->db->id($userId);
            }
            $this->document['revision'] = (isset($check['revision']) ? ($check['revision'] + 1) : 1);
        } else {
            $this->document['created_date'] = new MongoDate(strtotime('now'));
            $this->document['modified_date'] = $this->document['created_date'];
            $this->document['revision'] = 1;
            if ($userId !== false) {
                $this->document['created_user'] = $this->db->id($userId);
                $this->document['modified_user'] = $this->document['created_user'];
            }
        }
        if (!isset($this->document['acl'])) {
            $this->document['acl'] = ['public'];
        }
        if (!isset($this->document['status'])) {
            $this->document['status'] = 'published';
        }
        if (!isset($this->document['featured'])) {
            $this->document['featured'] = 'f';
        }
        if ($this->embeddedPath === false) {
            $result = $this->db->collection($this->collection)->update(
                ['_id' => $this->db->id($this->id)], 
                ['$set' => (array)$this->document], 
                ['w' => true, 'fsync' => true, 'upsert' => true]
            );
        } else {
            $this->document['_id'] = $this->db->id($this->embeddedId);
            if ($this->embeddedMode == 'update') {
                $this->document = array_merge((array)$check, (array)$this->document);
                $result = $this->db->collection($this->collection)->update(
                    ['_id' => $this->db->id($this->id)], 
                    ['$set' => [$this->embeddedPath => (array)$this->document]], 
                    ['w' => true, 'fsync' => true, 'upsert' => true]
                );
            } elseif ($this->embeddedMode == 'insert') {
                $embeddedPath = $this->embeddedPath;
                $embeddedPath = explode('.', $embeddedPath);
                array_pop($embeddedPath);
                $embeddedPath = implode('.', $embeddedPath);
                $result = $this->db->collection($this->collection)->update(
                    ['_id' => $this->db->id($this->id)], 
                    ['$push' => [$embeddedPath => (array)$this->document]], 
                    ['w' => true, 'fsync' => true, 'upsert' => true]
                );
            }
        }

        //versions
        if (isset($check['_id']) && isset($result['ok']) && $result['ok'] == true) {
            $this->db->collection('versions')->save([
                'collection' => $this->collection,
                'document' => $check
            ]);

            //attempt indexing
            $searchIndexContext = [
                'collection' => $this->collection,
                'id' => (string)$this->id
            ];
            $this->topic->publish('searchIndexUpsert', $searchIndexContext);
        }

        if ($documentIdRetained !== false && !isset($this->document['_id'])) {
            $this->document['_id'] = $documentIdRetained;
        }
        return $result;
    }

    public function remove () {
        $parts = [];
        if ($this->embeddedPath !== false) {
            $parts = explode('.', $this->embeddedPath);
        }
        $partCount = count($parts);
        if ($partCount == 0) {
            $result = $this->db->collection($this->collection)->remove(['_id' => $this->db->id($this->id)], ['justOne' => true]);
        } else {
            array_pop($parts);
            $field = implode('.', $parts);
            $result = $this->db->collection($this->collection)->update([
                    '_id' => $this->db->id($this->id)
                ], [
                    '$pull' => [
                        $field => ['_id' => $this->db->id($this->embeddedId)]
                    ]
                ]
            );
        }
        $searchIndexContext = [
            'collection' => $this->collection,
            'id' => (string)$this->id
        ];
        $this->topic->publish('searchIndexDelete', $searchIndexContext);
        return $result;
    }

    public function current () {
        $filter = [];
        $parts = [];
        if (substr_count($this->dbURI, ':') > 0) {
            $parts = explode(':', $this->dbURI);
            $collection = array_shift($parts);
            $id = array_shift($parts);
            if (count($parts) > 0) {
                $filter = [$parts[0]];
            }
        }
        $document = $this->db->collection($this->collection)->findOne(['_id' => $this->db->id($id)], $filter);
        $partCount = count($parts);
        if ($partCount == 0) {
            return $document;
        }
        for ($i=0; $i < $partCount; $i++) {
            $key = $parts[$i];
            $i++;
            $value = $parts[$i];
            if (!isset($document[$key]) || !is_array($document[$key])) {
                return [];
            }
            $hit = false;
            for ($j=0; $j < count($document[$key]); $j++) {
                if (!isset($document[$key][$j]) || !isset($document[$key][$j]['_id'])) {
                    continue;
                }
                if ($value == (string)$document[$key][$j]['_id']) {
                    $hit = true;
                    $document = $document[$key][$j];
                    break;
                }
            }
            if ($hit == false) {
                return [];
            }
        }
        return $document;
    }

    public function collection () {
        return $this->collection;
    }

    public function id () {
        return $this->id;
    }

    public function __get ($field) {
        if (!isset($this->document[$field])) {
            return false;
        }
        return $this->document[$field];
    }

    private function idToOffset () {
        $parts = explode(':', $this->id);
        $this->id = array_shift($parts);
        $count = count($parts);
        $out = '';
        $document = $this->db->collection($this->collection)->findOne(['_id' => $this->db->id($this->id)], [$parts[0]]);
        if (!isset($document['_id'])) {
            throw new Exception('Can not find root document: ' . $this->collection . ':' . $this->id . ':' . $parts[0]);
        }
        $partCount = count($parts);
        for ($i=0; $i < $partCount; $i++) {
            $key = $parts[$i];
            $i++;
            $value = $parts[$i];
            $out .= $key . '.';
            if (!isset($document[$key]) || !is_array($document[$key])) {
                $out .= '0.';
                $this->embeddedMode = 'insert';
                $document = [];
                continue;
            }
            $hit = false;
            for ($j=0; $j < count($document[$key]); $j++) {
                if (!isset($document[$key][$j]) || !isset($document[$key][$j]['_id'])) {
                    $this->embeddedMode = 'insert';
                    continue;
                }
                if ($value == (string)$document[$key][$j]['_id']) {
                    $this->embeddedMode = 'update';
                    $hit = true;
                    $out .= $j . '.';
                    $document = $document[$key][$j];
                    break;
                }
            }
            if ($hit == false) {
                $this->embeddedMode = 'insert';
                $document = [];
                $out .= $j . '.';
                break;
            }
        }
        if ($partCount != count(explode('.', trim($out, '.')))) {
            throw new Exception('Mis-matched embedded document query');
        }
        $this->embeddedDocument = $document;
        $this->embeddedId = array_pop($parts);
        return substr($out, 0, -1);
    }
}
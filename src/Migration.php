<?php
/**
 * Opine\DB\Migration
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

class Migration {
    private $db;

    public function __construct ($db)  {
        $this->db = $db;
    }

    public function addURI () {
        $collections = $this->db->collectionList();
        foreach ($collections as $collection) {
            $collectionName = $collection->getName();
            $sample = $collection->findOne();
            $size = count($sample);
            if ($size == 2) {
                $keys = array_keys($sample);
                if (in_array('value', $keys)) {
                    //skip map reduces
                    continue;
                }
            }
            if (substr($collectionName, 0, '7') == 'system.') {
                continue;
            }
            if (in_array($collectionName, ['versions'])) {
                continue;
            }
            echo $collectionName, '...', "\n";
            flush();
            $this->db->each($this->db->collection($collectionName)->find()->snapshot(), function ($doc) use ($collectionName) {
                if (isset($doc['dbURI'])) {
                    return;
                }
                $dbURI = $collectionName . ':' . (string)$doc['_id'];
                $doc['dbURI'] = $dbURI;
                foreach ($doc as $fieldName => &$field) {
                    if (!is_array($field) || is_object($field)) {
                        continue;
                    }
                    if (!isset($field[0]) || is_object($field[0]) || !isset($field[0]['_id'])) {
                        continue;
                    }
                    foreach ($field as &$embeddedDoc) {
                        $embeddedDoc['dbURI'] = $doc['dbURI'] . ':' . $fieldName . ':' . (string)$embeddedDoc['_id'];
                        foreach ($embeddedDoc as $fieldName2 => &$field2) {
                            if (!is_array($field2)) {
                                continue;
                            }
                            if (!isset($field2[0]) || !isset($field2[0]['_id'])) {
                                continue;
                            }
                            foreach ($field2 as &$embeddedDoc2) {
                                $embeddedDoc2['dbURI'] = $embeddedDoc['dbURI'] . ':' . $embeddedFieldName . ':' . (string)$embeddedDoc2['_id'];
                            }
                        }
                    }
                }
                $id = $doc['_id'];
                unset($doc['_id']);
                $this->db->collection($collectionName)->update(['_id' => $id], $doc);
            });
        }
    }
}
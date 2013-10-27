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

class Document {
	private $document;
	private $db;
	private $id;
	private $dbURI;

	public function __construct ($db, $dbURI, $document) {
		$this->db = $db;
		$this->document = $document;
		list($this->collection, $this->id) = explode(':', $dbURI, 2);
	}

	public function upsert ($authContext='manager') {
		if (substr_count((string)$this->id, '/') > 0) {
			$this->mongoIdToOffset();
		}
		if (isset($this->document['_id'])) { unset ($this->document['_id']); }
		if (isset($this->document['id'])) { unset ($this->document['id']); }
		
		//user id
		if (isset($_SESSION['auth']) && isset($_SESSION['auth'][$authContext]) && isset($_SESSION['auth'][$authContext]['_id'])) {
			$user = $_SESSION['auth'][$authContext]['_id'];
		}

		//handle modification / version history
		$check = $this->db->collection($this->collection)->findOne(['_id' => $this->db->id($this->id)]);
		if (isset($check['_id'])) {
			$this->document['modified_date'] = new \MongoDate(strtotime('now'));
			if ($user !== false) {
				$this->document['modified_user'] = $this->db->id($user);
			}
		} else {
			$this->document['created_date'] = new \MongoDate(strtotime('now'));
			$this->document['modified_date'] = $this->document['created_date'];
			if (!isset($this->document['acl'])) {
				$this->document['acl'] = ['public'];
			}
			if ($user !== false) {
				$this->document['created_user'] = $this->db->id($user);
			}
		}

		$result = $this->db->collection($this->collection)->update(
			['_id' => $this->db->id($this->id)], 
			['$set' => (array)$this->document], 
			['safe' => true, 'fsync' => true, 'upsert' => true]
		);

		var_dump($result);
		exit;

		return $result;
	}

	public function delete ($collection, $id) {
		$this->db->collection($colletion)->remove(['_id' => $this->db->id($id)], ['justOne' => true]);
	}

	public function __get ($field) {
		if (!isset($this->document[$field])) {
			return false;
		}
		return $this->document[$field];
	}

	private function mongoIdToOffset () {
        //takes input like collection:ID/field/ID/field/ID/field
        $parts = explode('/', $this->id);
        $id = array_shift($parts);
        $count = count($parts);
        $out = '';
        $document = $this->db($this->collection)->findOne(['_id' => new \MongoId((string)$id)], [$parts[0]]);
        $i=0;
        foreach ($document[$parts[0]] as &$sub1) {
            if ((string)$sub1['_id'] == (string)$parts[1]) {
                $out .= $parts[0] . '.' . $i;
                if ($count > 2) {
                    $out .= '.' . $parts[2];
                    $j=0;
                    if (!isset($parts[3]) || !isset($sub1[$parts[2]]) || !is_array($sub1[$parts[2]]) || empty($sub1[$parts[2]])) {
                        break;
                    }
                    foreach ($sub1[$parts[2]] as $sub2) {
                        if ((string)$sub2['_id'] == (string)$parts[3]) {
                            $out .= '.' . $j;
                            break;
                        }
                        $j++;
                    }
                }
                break;
            }
            $i++;
        }
        $this->id = $out;
    }
}
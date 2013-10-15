<?php
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

	public function upsert () {
		if (substr_count((string)$this->id, '/') > 0) {
			$this->mongoIdToOffset();
		}
		if (isset($this->document['_id'])) { unset ($this->document['_id']); }
		if (isset($this->document['id'])) { unset ($this->document['id']); }
		$this->db->collection($this->collection)->update(
			['_id' => $this->db->id($this->id)], 
			['$set' => (array)$this->document], 
			['safe' => true, 'fsync' => true, 'upsert' => true]
		);
	}

	public function delete () {

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
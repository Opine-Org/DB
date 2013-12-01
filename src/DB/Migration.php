<?php
namespace DB;

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
			$this->db->each($this->db->collection($collectionName)->find()->snapshot(), function ($doc) use ($collectionName) {
				if (isset($doc['dbURI'])) {
					return;
				}
				$dbURI = $collectionName . ':' . (string)$doc['_id'];
				$doc['dbURI'] = $dbURI;
				foreach ($doc as $fieldName => &$field) {
					if (!is_array($field)) {
						continue;
					}
					if (!isset($field[0]) || !isset($field[0]['_id'])) {
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
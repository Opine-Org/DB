<?php
namespace Opine\DB;

use Opine\Container\Service as Container;
use PHPUnit_Framework_TestCase;
use Opine\Config\Service as Config;

class DBTest extends PHPUnit_Framework_TestCase {
    private $db;

    public function setup () {
        $root = __DIR__ . '/../public';
        $config = new Config($root);
        $config->cacheSet();
        $container = Container::instance($root, $config, $root . '/../config/containers/test-container.yml');
        $this->db = $container->get('db');
    }

    public static function setUpBeforeClass() {
        shell_exec('mongo phpunit < ' . __DIR__ . '/db.js');
        $root = __DIR__ . '/../public';
        $config = new Config($root);
        $config->cacheSet();
        $container = Container::instance($root, $config, $root . '/../config/containers/test-container.yml');
        $db = $container->get('db');
        $dbURI = 'comments:546d255127987119148b4567';
        $db->document($dbURI, [
            'body' => 'abc',
            'email' => 'test@email.com'
        ])->upsert();

        $dbURI = 'comments:546d255127987119148b4567:upvotes:546d60da2798710d338b4569';
        $db->document($dbURI, [
            'email' => 'ryan@email.com'
        ])->upsert();

        $dbURI = 'comments:546d255127987119148b4567:upvotes:546d60da2798710d338b456a';
        $db->document($dbURI, [
            'email' => 'bob@email.com'
        ])->upsert();

        $dbURI = 'comments:546d255127987119148b4567:replies:546d60da2798710d338b4568';
        $db->document($dbURI, [
            'email' => 'test@email.com',
            'body' => 'abc'
        ])->upsert();

        $dbURI = 'comments:546d255127987119148b4567:replies:546d60da2798710d338b4568:upvotes:546d60da2798710d338b456b';
        $db->document($dbURI, [
            'email' => 'mary@email.com'
        ])->upsert();

        $dbURI = 'comments:546d255127987119148b4567:replies:546d60da2798710d338b4568:upvotes:546d60da2798710d338b456c';
        $db->document($dbURI, [
            'email' => 'larry@email.com'
        ])->upsert();
    }

    public function testCheckByCriteriaOneLevelOneCriteria () {
        $this->assertTrue(is_array($this->db->document('comments:546d255127987119148b4567:upvotes')->
            checkByCriteria([
                'email' => 'ryan@email.com'
            ])));
    }

    public function testCheckByCriteriaOneLevelTwoCriteria () {
        $this->assertTrue(is_array($this->db->document('comments:546d255127987119148b4567:upvotes')->
            checkByCriteria([
                'email' => 'ryan@email.com',
                'acl' => ['public']
            ])));
    }

    public function testCheckByCriteriaOneLevelNoMatch () {
        $this->assertFalse($this->db->document('comments:546d255127987119148b4567:upvotes')->
            checkByCriteria([
                'email' => 'nope@email.com'
            ]));
    }

    public function testCheckByCriteriaOneLevelTwoCriteriaNoMatch () {
        $this->assertFalse($this->db->document('comments:546d255127987119148b4567:upvotes')->
            checkByCriteria([
                'email' => 'ryan@email.com',
                'acl' => ['private']
            ]));
    }

    public function testCheckByCriteriaTwoLevelsOneCriteria () {
        $this->assertTrue(is_array($this->db->document('comments:546d255127987119148b4567:replies:546d60da2798710d338b4568:upvotes')->
            checkByCriteria([
                'email' => 'mary@email.com'
            ])));
    }

    public function testCheckByCriteriaTwoLevelsTwoCriteria () {
        $this->assertTrue(is_array($this->db->document('comments:546d255127987119148b4567:replies:546d60da2798710d338b4568:upvotes')->
            checkByCriteria([
                'email' => 'mary@email.com',
                'acl' => ['public']
            ])));
    }

    public function testCheckByCriteriaTwoLevelsNoMatch () {
        $this->assertFalse($this->db->document('comments:546d255127987119148b4567:replies:546d60da2798710d338b4568:upvotes')->
            checkByCriteria([
                'email' => 'nope@email.com'
            ]));
    }

    public function testCheckByCriteriaTwoLevelsTwoCriteriaNoMatch () {
        $this->assertFalse($this->db->document('comments:546d255127987119148b4567:replies:546d60da2798710d338b4568:upvotes')->
            checkByCriteria([
                'email' => 'mary@email.com',
                'acl' => ['private']
            ]));
    }

    public function testIncrementOneLevel () {
        $document = $this->db->document('comments:546d60da2798710d338b456d');
        $document->upsert(['up' => 3]);
        $document->increment('up');
        $this->assertTrue($document->current()['up'] == 4);
    }

    public function testIncrementTwoLevels () {
        $document = $this->db->document('comments:546d60da2798710d338b456d:replies:546d60da2798710d338b456e');
        $document->upsert(['up' => 2]);
        $document->increment('up');
        $this->assertTrue($document->current()['up'] == 3);
    }

    public function testDecrementOneLevel () {
        $document = $this->db->document('comments:546d60da2798710d338b456d');
        $document->upsert(['up' => 3]);
        $document->decrement('up');
        $this->assertTrue($document->current()['up'] == 2);
    }

    public function testDecrementTwoLevels () {
        $document = $this->db->document('comments:546d60da2798710d338b456d:replies:546d60da2798710d338b456e');
        $document->upsert(['up' => 2]);
        $document->decrement('up');
        $this->assertTrue($document->current()['up'] == 1);
    }

    public function testUserIdSet () {
        $this->assertTrue($this->db->userIdSet('546d60da2798710d338b456d'));
    }

    public function testCollectionList () {
        $collections = $this->db->collectionList(true);
        $this->assertTrue(is_array($collections));
        $this->assertTrue(get_class($collections[0]) == 'MongoCollection');
    }

    public function testCollection () {
        $comments = $this->db->collection('comments');
        $this->assertTrue(get_class($comments) == 'MongoCollection');
        $this->assertTrue($this->db->each($comments->find(), function () {}));
    }

    public function testIdNew () {
        $this->assertTrue(get_class($this->db->id()) == 'MongoId');
    }

    public function testIdFromString () {
        $id = $this->db->id('546d60da2798710d338b456d');
        $this->assertTrue(get_class($id) === 'MongoId' && (string)$id === '546d60da2798710d338b456d');
    }

    public function testMapReduce () {
        $map = <<<MAP
        function() {
            if (!this.email) {
                return;
            }
            emit(this.email, 1);
        }
MAP;

        $reduce = <<<REDUCE
        function(key, values) {
            var count = 0;
            for (var i = 0; i < values.length; i++) {
                count += values[i];
            }
            return count;
        }
REDUCE;

        $this->assertTrue('MongoCollection' == get_class($this->db->mapReduce($map, $reduce, [
            'mapreduce' => 'comments',
            'out' => 'comments_mr_test'
        ])));
    }

    public function testDistinct () {
        $this->assertTrue(is_array($this->db->distinct('comments', '_id', [])));
    }

    public function testFetchGrouped () {
        $this->assertTrue(is_array($this->db->fetchAllGrouped($this->db->collection('comments')->find(), 'email', 'email', false)));
    }

    public function testDate () {
        $this->assertTrue(get_class($this->db->date(strtotime('now'))) == 'MongoDate');
    }

    public function testDocumentStage () {
        $this->assertTrue('Opine\DB\Document' == get_class($this->db->document('comments:546d60da2798710d338b456d')));
    }
}
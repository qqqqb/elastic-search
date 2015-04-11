<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test;

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Type;
use Cake\ElasticSearch\Document;
use Cake\TestSuite\TestCase;

/**
 * Tests the Type class
 *
 */
class TypeTest extends TestCase
{
    public $fixtures = ['plugin.cake/elastic_search.articles'];

    public function setUp()
    {
        parent::setUp();
        $this->type = new Type([
            'name' => 'articles',
            'connection' => ConnectionManager::get('test')
        ]);
    }

    /**
     * Tests that calling find will return a query object
     *
     * @return void
     */
    public function testFindAll()
    {
        $query = $this->type->find('all');
        $this->assertInstanceOf('Cake\ElasticSearch\Query', $query);
        $this->assertSame($this->type, $query->repository());
    }

    /**
     * Test the default entityClass.
     *
     * @return void
     */
    public function testEntityClassDefault()
    {
        $this->assertEquals('\Cake\ElasticSearch\Document', $this->type->entityClass());
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace
     *
     * @return void
     */
    public function testTableClassInApp()
    {
        $class = $this->getMockClass('Cake\ElasticSearch\Document');
        class_alias($class, 'App\Model\Document\TestUser');

        $type = new Type();
        $this->assertEquals(
            'App\Model\Document\TestUser',
            $type->entityClass('TestUser')
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the Plugin namespace when using plugin notation
     *
     * @return void
     */
    public function testTableClassInPlugin()
    {
        $class = $this->getMockClass('\Cake\ElasticSearch\Document');
        class_alias($class, 'MyPlugin\Model\Document\SuperUser');

        $type = new Type();
        $this->assertEquals(
            'MyPlugin\Model\Document\SuperUser',
            $type->entityClass('MyPlugin.SuperUser')
        );
    }

    /**
     * Tests the get method
     *
     * @return void
     */
    public function testGet()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'foo',
            'connection' => $connection
        ]);

        $index = $this->getMockBuilder('Elastica\Index')
            ->disableOriginalConstructor()
            ->getMock();

        $internalType = $this->getMockBuilder('Elastica\Type')
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->once())
            ->method('getIndex')
            ->will($this->returnValue($index));

        $index->expects($this->once())
            ->method('getType')
            ->will($this->returnValue($internalType));

        $document = $this->getMock('Elastica\Document', ['getId', 'getData']);
        $internalType->expects($this->once())
            ->method('getDocument')
            ->with('foo', ['bar' => 'baz'])
            ->will($this->returnValue($document));

        $document->expects($this->once())
            ->method('getData')
            ->will($this->returnValue(['a' => 'b']));
        $document->expects($this->once())
            ->method('getId')
            ->will($this->returnValue('foo'));

        $result = $type->get('foo', ['bar' => 'baz']);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertEquals(['a' => 'b', 'id' => 'foo'], $result->toArray());
        $this->assertFalse($result->dirty());
        $this->assertFalse($result->isNew());
    }

    /**
     * Test that newEntity is wired up.
     *
     * @return void
     */
    public function testNewEntity()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'articles',
            'connection' => $connection
        ]);
        $data = [
            'title' => 'A newer title'
        ];
        $result = $type->newEntity($data);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame($data, $result->toArray());
    }

    /**
     * Test that newEntities is wired up.
     *
     * @return void
     */
    public function testNewEntities()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'articles',
            'connection' => $connection
        ]);
        $data = [
            [
                'title' => 'A newer title'
            ],
            [
                'title' => 'A second title'
            ],
        ];
        $result = $type->newEntities($data);
        $this->assertCount(2, $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[1]);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Test saving a new document.
     *
     * @return void
     */
    public function testSaveNew()
    {
        $doc = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content'
        ], ['markNew' => true]);
        $this->assertSame($doc, $this->type->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');
        $this->assertFalse($doc->isNew(), 'Not new anymore.');
        $this->assertFalse($doc->dirty(), 'Not dirty anymore.');

        $result = $this->type->get($doc->id);
        $this->assertEquals($doc->title, $result->title);
        $this->assertEquals($doc->body, $result->body);
    }

    /**
     * Test saving a new document.
     *
     * @return void
     */
    public function testSaveUpdate()
    {
        $doc = new Document([
            'id' => '123',
            'title' => 'A brand new article',
            'body' => 'Some new content'
        ], ['markNew' => false]);
        $this->assertSame($doc, $this->type->save($doc));
        $this->assertFalse($doc->isNew(), 'Not new.');
        $this->assertFalse($doc->dirty(), 'Not dirty anymore.');
    }
}

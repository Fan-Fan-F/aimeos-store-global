<?php

use Aimeos\Shop\Base\Cache\LaravelCacheAdapter;
use Illuminate\Support\Facades\Cache;

class LaravelCacheAdapterTest extends AimeosTestAbstract
{
	protected function getEnvironmentSetUp( $app )
	{
		parent::getEnvironmentSetUp( $app );
		$app['config']->set( 'cache.default', 'array' );
	}

	public function testBasicGetSetDelete()
	{
		$adapter = new LaravelCacheAdapter( null, 'test:', 300 );

		$this->assertFalse( $adapter->has( 'foo' ) );
		$this->assertNull( $adapter->get( 'foo' ) );
		$this->assertEquals( 'default_val', $adapter->get( 'foo', 'default_val' ) );

		$this->assertTrue( $adapter->set( 'foo', 'bar', 300 ) );
		$this->assertTrue( $adapter->has( 'foo' ) );
		$this->assertEquals( 'bar', $adapter->get( 'foo' ) );

		$this->assertTrue( $adapter->delete( 'foo' ) );
		$this->assertFalse( $adapter->has( 'foo' ) );
	}

	public function testMultipleOperations()
	{
		$adapter = new LaravelCacheAdapter( null, 'test:', 300 );

		$pairs = [
			'key1' => 'val1',
			'key2' => 'val2',
		];

		$this->assertTrue( $adapter->setMultiple( $pairs, 300 ) );
		$results = $adapter->getMultiple( ['key1', 'key2', 'key3'], 'missing' );

		$this->assertEquals( 'val1', $results['key1'] );
		$this->assertEquals( 'val2', $results['key2'] );
		$this->assertEquals( 'missing', $results['key3'] );

		$this->assertTrue( $adapter->deleteMultiple( ['key1', 'key2'] ) );
		$this->assertFalse( $adapter->has( 'key1' ) );
		$this->assertFalse( $adapter->has( 'key2' ) );
	}

	public function testDeleteByTags()
	{
		$adapter = new LaravelCacheAdapter( null, 'test:', 300 );

		$adapter->set( 'item1', 'content1', 300, ['tag-product', 'tag-category-1'] );
		$adapter->set( 'item2', 'content2', 300, ['tag-product', 'tag-category-2'] );
		$adapter->set( 'item3', 'content3', 300, ['tag-other'] );

		$this->assertTrue( $adapter->has( 'item1' ) );
		$this->assertTrue( $adapter->has( 'item2' ) );
		$this->assertTrue( $adapter->has( 'item3' ) );

		// Delete by specific category tag
		$adapter->deleteByTags( ['tag-category-1'] );
		$this->assertFalse( $adapter->has( 'item1' ) );
		$this->assertTrue( $adapter->has( 'item2' ) );
		$this->assertTrue( $adapter->has( 'item3' ) );

		// Delete by common tag
		$adapter->deleteByTags( ['tag-product'] );
		$this->assertFalse( $adapter->has( 'item2' ) );
		$this->assertTrue( $adapter->has( 'item3' ) );
	}

	public function testClearAndCleanup()
	{
		$adapter = new LaravelCacheAdapter( null, 'test:', 300 );
		$adapter->set( 'alpha', '123', 300 );

		$this->assertTrue( $adapter->cleanup() );
		$this->assertTrue( $adapter->clear() );
		$this->assertFalse( $adapter->has( 'alpha' ) );
	}
}

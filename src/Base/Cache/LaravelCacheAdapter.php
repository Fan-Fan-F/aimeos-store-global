<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 * @copyright Aimeos (aimeos.org), 2015-2026
 */

namespace Aimeos\Shop\Base\Cache;

use Aimeos\Base\Cache\Iface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;


/**
 * High-performance cache adapter bridging Aimeos cache interface to Laravel Cache.
 * Provides key-based storage with tag-based invalidation index compatible with all cache drivers.
 */
class LaravelCacheAdapter implements Iface
{
	/**
	 * @var CacheRepository
	 */
	protected CacheRepository $cache;

	/**
	 * @var string
	 */
	protected string $prefix;

	/**
	 * @var int
	 */
	protected int $defaultTtl;


	/**
	 * Initializes the cache adapter.
	 *
	 * @param CacheRepository|null $cache Laravel cache repository
	 * @param string $prefix Key prefix for isolation
	 * @param int $defaultTtl Default lifetime in seconds (3600 = 1 hour)
	 */
	public function __construct( ?CacheRepository $cache = null, string $prefix = 'aimeos:', int $defaultTtl = 3600 )
	{
		$storeName = config( 'shop.cache.store' );
		$this->cache = $cache ?: ( $storeName ? Cache::store( $storeName ) : Cache::store() );
		$this->prefix = $prefix;
		$this->defaultTtl = $defaultTtl;
	}


	/**
	 * Removes all expired cache entries.
	 *
	 * @return bool
	 */
	public function cleanup() : bool
	{
		return true;
	}


	/**
	 * Clears all cached entries under this adapter prefix.
	 *
	 * @return bool
	 */
	public function clear() : bool
	{
		// Delete all tracked keys under prefix
		$keyListKey = $this->prefix . '__keys__';
		$keys = (array) $this->cache->get( $keyListKey, [] );

		foreach( $keys as $fullKey ) {
			$this->cache->forget( $fullKey );
		}

		$this->cache->forget( $keyListKey );

		// Also invoke clear/flush on store if supported
		if( method_exists( $this->cache, 'flush' ) ) {
			$this->cache->flush();
		} elseif( method_exists( $this->cache, 'clear' ) ) {
			$this->cache->clear();
		}

		return true;
	}


	/**
	 * Removes the cache entry identified by the given key.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function delete( string $key ) : bool
	{
		$fullKey = $this->prefix . $key;
		$this->untrackKey( $fullKey );
		return (bool) $this->cache->forget( $fullKey );
	}


	/**
	 * Removes multiple cache entries.
	 *
	 * @param iterable $keys
	 * @return bool
	 */
	public function deleteMultiple( iterable $keys ) : bool
	{
		$success = true;

		foreach( $keys as $key )
		{
			if( !$this->delete( (string) $key ) ) {
				$success = false;
			}
		}

		return $success;
	}


	/**
	 * Removes all cache entries associated with the given tags.
	 *
	 * @param iterable $tags
	 * @return bool
	 */
	public function deleteByTags( iterable $tags ) : bool
	{
		foreach( $tags as $tag )
		{
			$tagKey = (string) $tag;
			$indexKey = $this->prefix . '_tag:' . $tagKey;
			$taggedKeys = (array) $this->cache->get( $indexKey, [] );

			foreach( $taggedKeys as $k )
			{
				$this->cache->forget( $k );
				$this->untrackKey( $k );
			}

			$this->cache->forget( $indexKey );
		}

		return true;
	}


	/**
	 * Returns the cached value for the given key.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( string $key, $default = null )
	{
		return $this->cache->get( $this->prefix . $key, $default );
	}


	/**
	 * Returns multiple cached values.
	 *
	 * @param iterable $keys
	 * @param mixed $default
	 * @return iterable
	 */
	public function getMultiple( iterable $keys, $default = null ) : iterable
	{
		$result = [];

		foreach( $keys as $key )
		{
			$k = (string) $key;
			$result[$k] = $this->get( $k, $default );
		}

		return $result;
	}


	/**
	 * Checks if an item is present in cache.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function has( string $key ) : bool
	{
		return (bool) $this->cache->has( $this->prefix . $key );
	}


	/**
	 * Sets a value in the cache with optional tags and expiration.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param \DateInterval|int|string|null $expires
	 * @param iterable $tags
	 * @return bool
	 */
	public function set( string $key, $value, $expires = null, iterable $tags = [] ) : bool
	{
		$ttl = $this->resolveTtl( $expires );
		$fullKey = $this->prefix . $key;

		$this->trackKey( $fullKey );

		// Maintain reverse tag index
		foreach( $tags as $tag )
		{
			$indexKey = $this->prefix . '_tag:' . (string) $tag;
			$taggedKeys = (array) $this->cache->get( $indexKey, [] );
			if( !in_array( $fullKey, $taggedKeys, true ) ) {
				$taggedKeys[] = $fullKey;
				$this->cache->put( $indexKey, $taggedKeys, 86400 * 7 );
				$this->trackKey( $indexKey );
			}
		}

		return (bool) $this->cache->put( $fullKey, $value, $ttl );
	}


	/**
	 * Sets multiple key-value pairs in the cache.
	 *
	 * @param iterable $pairs
	 * @param \DateInterval|int|string|null $expires
	 * @param iterable $tags
	 * @return bool
	 */
	public function setMultiple( iterable $pairs, $expires = null, iterable $tags = [] ) : bool
	{
		$success = true;

		foreach( $pairs as $key => $value )
		{
			if( !$this->set( (string) $key, $value, $expires, $tags ) ) {
				$success = false;
			}
		}

		return $success;
	}


	/**
	 * Resolves TTL in seconds from various expiry formats.
	 *
	 * @param \DateInterval|int|string|null $expires
	 * @return int
	 */
	protected function resolveTtl( $expires ) : int
	{
		if( $expires === null ) {
			return $this->defaultTtl;
		}

		if( is_int( $expires ) ) {
			return $expires > 0 ? $expires : $this->defaultTtl;
		}

		if( $expires instanceof \DateInterval ) {
			$reference = new \DateTimeImmutable();
			$endTime = $reference->add( $expires );
			return max( 1, $endTime->getTimestamp() - $reference->getTimestamp() );
		}

		if( is_string( $expires ) ) {
			$timestamp = strtotime( $expires );
			if( $timestamp !== false ) {
				return max( 1, $timestamp - time() );
			}
		}

		return $this->defaultTtl;
	}


	/**
	 * Tracks a cache key for whole-adapter clearing.
	 *
	 * @param string $fullKey
	 */
	protected function trackKey( string $fullKey ) : void
	{
		$keyListKey = $this->prefix . '__keys__';
		if( $fullKey === $keyListKey ) {
			return;
		}

		$keys = (array) $this->cache->get( $keyListKey, [] );
		if( !in_array( $fullKey, $keys, true ) ) {
			$keys[] = $fullKey;
			$this->cache->put( $keyListKey, $keys, 86400 * 7 );
		}
	}


	/**
	 * Untracks a key.
	 *
	 * @param string $fullKey
	 */
	protected function untrackKey( string $fullKey ) : void
	{
		$keyListKey = $this->prefix . '__keys__';
		$keys = (array) $this->cache->get( $keyListKey, [] );
		$filtered = array_values( array_filter( $keys, fn( $k ) => $k !== $fullKey ) );
		$this->cache->put( $keyListKey, $filtered, 86400 * 7 );
	}
}

<?php
/**
 * Created by PhpStorm.
 * User: diego
 * Date: 5/11/15
 * Time: 12:15
 */

namespace TFC\Cache;

use TFC\Cache\Exception\StorageException;

class DoctrineCacheFactory
{
    /**
     * Cache store storage options
     * @var array
     */
    private static $options = [];

    /**
     * @var array Cache of established connections (to eliminate overhead).
     */
    private static $connectionMap = [];

    /**
     * Registered storage
     * @var array
     */
    private static $storage = [
        'apc'       => 'Doctrine\Common\Cache\ApcCache',
        'memcached' => 'Doctrine\Common\Cache\MemcachedCache',
        'memcache'  => 'Doctrine\Common\Cache\MemcacheCache',
        'redis'     => 'Doctrine\Common\Cache\RedisCache',
    ];

    /**
     * Set cache store storage options
     * @param array $options cache store storage options
     */
    public static function setOptions($options = [])
    {
        self::$options = $options;

        // clear cache storage instances
        self::$connectionMap = [];
    }

    /**
     * Get cache store storage options
     * @return array cache store storage options
     */
    public static function getOptions()
    {
        return self::$options;
    }

    /**
     * Set cache store storage option
     * @param array $option cache store storage option
     */
    public static function setOption($option = [])
    {
        self::$options[] = $option;
    }

    /**
     * Get cache store storage option
     * @param  string $storage_type cache store storage type (eg. 'apc', 'memcached')
     * @return array                       cache store storage option
     */
    public static function getOption($storage_type)
    {
        foreach (self::$options as $option) {
            if ($option['storage'] == $storage_type) {
                return $option;
            }
        }

        return false;
    }

    /**
     * Clear cache store storage options
     */
    public static function clearOptions()
    {
        self::$options = [];
    }

    /**
     * Clear connection cache
     */
    public static function clearConnectionCache()
    {
        self::$connectionMap = [];
    }

    /**
     * Instantiate a cache storage
     * @param  string $storage_type cache store storage type (eg. 'apc', 'memcached')
     * @return \Doctrine\Common\Cache\CacheProvider cache store storage instance
     * @throws StorageException when $storage_type is not registered
     */
    public static function factory($storage_type)
    {
        if (!array_key_exists($storage_type, self::$storage)) {
            throw new StorageException(sprintf('Storage class not set for type %s', $storage_type));
        }
        if (!isset(self::$connectionMap[$storage_type])) {
            self::$connectionMap[$storage_type] = self::initializeCacheDriver($storage_type);
        }

        return self::$connectionMap[$storage_type];
    }

    /**
     * Register a cache storage
     *
     * @param $storage_type string      store storage type (eg. 'apc', 'memcached', 'my_apc')
     * @param $storage_class string     name which must implement Domino\CacheStore\Storage\StorageInterface
     * @throws StorageException when $storage_class not implements Domino\CacheStore\Storage\StorageInterface
     */
    public static function registerStorage($storage_type, $storage_class)
    {
        $interface = 'Doctrine\Common\Cache\Cache';
        if (!in_array($interface, class_implements($storage_class, true))) {
            throw new StorageException(sprintf('Class %s must implements %s ', $storage_class, $interface));
        }
        self::$storage[$storage_type] = $storage_class;
    }

    /**
     * Initialize a Doctrine Cache driver
     * @param  string $storage_type cache store storage type (eg. 'apc', 'memcached')
     * @return \Doctrine\Common\Cache\CacheProvider cache store storage instance
     * @throws StorageException when $storage_type is not registered
     */
    public static function initializeCacheDriver($storage_type)
    {
        $driverClass = "initialize" . ucfirst($storage_type) . "CacheDriver";
        return self::$driverClass();
    }

    /**
     * Initialize a Doctrine ApcCache driver
     * @return \Doctrine\Common\Cache\ApcCache instance
     */
    private static function initializeApcCacheDriver()
    {
        return $driver = new self::$storage["apc"];
    }

    /**
     * Initialize a Doctrine MemcachedCache driver
     * @return \Doctrine\Common\Cache\MemcachedCache instance
     */
    private static function initializeMemcachedCacheDriver()
    {

        $options = self::getOption("memcached");

        $memcached = new \Memcached();
        $memcached->setOption(\Memcached::OPT_PREFIX_KEY, $options["prefix"]);
        $memcached->addServers($options['servers']);

        $driver = new self::$storage["memcached"];
        $driver->setMemcached($memcached);

        return $driver;
    }

    /**
     * Initialize a Doctrine MemcacheCache driver
     * @return \Doctrine\Common\Cache\MemcacheCache instance
     */
    private static function initializeMemcacheCacheDriver()
    {

        $options = self::getOption("memcache");

        $memcache = new \Memcache();
        foreach ($options['servers'] as $server) {
            $memcache->addserver($server[0], $server[1]);
        }

        $driver = new self::$storage["memcache"];
        $driver->setMemcache($memcache);

        return $driver;
    }

    /**
     * Initialize a Doctrine RedisCache driver
     * @return \Doctrine\Common\Cache\RedisCache instance
     */
    private static function initializeRedisCacheDriver()
    {

        $options = self::getOption("redis");

        $redis = new \Redis;
        $redis->connect($options["host"], $options["port"]);
        $redis->setOption(\Redis::OPT_PREFIX, $options["prefix"]);

        $driver = new self::$storage["redis"];
        $driver->setRedis($redis);

        return $driver;
    }


}
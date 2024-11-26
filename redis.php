<?php
namespace App\Models;
class RedisCache
{
    public $keyCache;

    /**
     * @param mixed $keyCache
     */
    public function setKeyCache($keyCache)
    {
        $this->keyCache = $keyCache;
    }

    private $redis;

    function __construct($keyCache, $sv = '15.235.216.178')
    {
        //49.12.120.177 : mate003
        $this->keyCache = $keyCache;
        $this->redis = new Redis();

        try {
            $this->redis->connect($sv, 2209);
            $this->redis->auth('ngocquang14@AA');
        } catch (Exception $e) {
            $MaxRetries = 3;
            for ($Counts = 1; $Counts <= $MaxRetries; $Counts++) {
                try {
                    $this->redis->connect($sv, 2209);
                    $this->redis->auth('ngocquang14@AA');
                } catch (Exception $e) {
                    continue;
                }
            }
        }

    }

    function getCache()
    {
        return $this->redis->get($this->keyCache);
    }

    function delCache()
    {
        return $this->redis->del($this->keyCache);
    }

    function setCache($cacheValue, $timeout)
    {
        if (is_array($cacheValue))
            $cacheValue = json_encode($cacheValue);
        return $this->redis->set($this->keyCache, $cacheValue, $timeout);
    }

    function setServer($sv)
    {
        $this->redis = new Redis();
        $this->redis->connect($sv, 2209);
        $this->redis->auth('ngocquang14@AA');
    }
}

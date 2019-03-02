<?php

namespace Smf\ConnectionPool;

trait ConnectionPoolTrait
{
    /**
     * @var ConnectionPool[] $pools
     */
    protected $pools = [];

    public function addConnectionPool(string $key, ConnectionPool $pool)
    {
        $this->pools[$key] = $pool;
    }

    public function getConnectionPool(string $key): ConnectionPool
    {
        return $this->pools[$key];
    }

    public function closeConnectionPool(string $key)
    {
        return $this->pools[$key]->close();
    }

    public function closeConnectionPools()
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
    }

}
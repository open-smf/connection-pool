<?php

namespace Smf\ConnectionPool;

trait ConnectionPoolTrait
{
    /**
     * @var ConnectionPool[] $pools
     */
    protected $pools = [];

    public function addConnectionPool($key, ConnectionPool $pool)
    {
        $this->pools[$key] = $pool;
    }

    public function getConnectionPool($key): ConnectionPool
    {
        return $this->pools[$key];
    }

    public function closeConnectionPool($key)
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
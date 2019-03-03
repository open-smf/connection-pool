<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    const CHANNEL_TIMEOUT = 0.001;

    protected $pool;
    protected $config;
    protected $currentCount = 0;

    protected $minActive   = 1;
    protected $maxActive   = 1;
    protected $maxWaitTime = 5;
    protected $maxIdleTime = 5;

    protected $balancerTimerId;

    /**
     * ConnectionPool constructor.
     * @param int $minActive The minimum number of connections
     * @param int $maxActive The maximum number of connections
     * @param float $maxWaitTime The maximum waiting time for connection, when reached, an exception will be thrown.
     * @param float $maxIdleTime The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least minActive connections in the pool.
     */
    public function __construct(int $minActive, int $maxActive, float $maxWaitTime, float $maxIdleTime)
    {
        $this->minActive = $minActive;
        $this->maxActive = $maxActive;
        $this->maxWaitTime = $maxWaitTime;
        $this->maxIdleTime = $maxIdleTime;
        $this->pool = new Channel($this->maxActive);
        $this->balancerTimerId = swoole_timer_tick($maxIdleTime * 1000, [$this, 'clearInactiveConnections']);
    }

    public function init(array $config): bool
    {
        if ($this->currentCount > 0) {
            return false;
        }
        $this->config = $config;
        for ($i = 0; $i < $this->minActive; $i++) {
            $this->currentCount++;
            $connection = $this->createConnection($this->config);
            $connection->updateLastActiveTime();
            $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
            if ($ret === false) {
                $this->currentCount--;
            }
        }
        return true;
    }

    /**
     * Create the connector to create the connection
     * @return ConnectorInterface
     */
    abstract protected function createConnector(): ConnectorInterface;

    /**
     * Create the connection
     * @param array $config
     * @return mixed Return the connection resource
     */
    abstract protected function createConnection(array $config): Connection;

    protected function clearInactiveConnections()
    {
        $now = time();
        $validConnections = [];
        while (true) {
            /**@var Connection $connection */
            $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
            if ($connection === false) {
                var_dump('exit clear');
                break;
            }
            $lastActiveTime = $connection->getLastActiveTime();
            if ($lastActiveTime === 0) {
                continue;
            }
            $this->currentCount--;
            if ($now - $lastActiveTime < $this->maxIdleTime) {
                $validConnections[] = $connection;
            } else {
                var_dump(__METHOD__);
            }
        }

        foreach ($validConnections as $validConnection) {
            $ret = $this->pool->push($validConnection, static::CHANNEL_TIMEOUT);
            if ($ret === false) {
                $this->currentCount--;
            }
        }
    }

    public function borrow(): Connection
    {
        if ($this->pool->isEmpty()) {
            // Create more connections
            if ($this->currentCount < $this->maxActive) {
                $this->currentCount++;
                return $this->createConnection($this->config);
            }
        }

        $connection = $this->pool->pop($this->maxWaitTime);
        if ($connection === false) {
            $exception = new BorrowConnectionTimeoutException(sprintf('Borrow the connection timeout in %.2f(s)', $this->maxWaitTime));
            $exception->setTimeout($this->maxWaitTime);
            throw $exception;
        }
        return $connection;
    }

    public function return(Connection $connection): bool
    {
        if ($this->pool->isFull()) {
            // Discard the connection
            return false;
        }
        $connection->updateLastActiveTime();
        $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
        if ($ret === false) {
            $this->currentCount--;
        }
        return $ret;
    }

    public function getCurrentCount(): int
    {
        return $this->currentCount;
    }

    public function close(): bool
    {
        if ($this->pool === null) {
            return false;
        }
        swoole_timer_clear($this->balancerTimerId);
        $this->pool->close();
        $this->pool = null;
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }
}

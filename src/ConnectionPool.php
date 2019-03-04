<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    const CHANNEL_TIMEOUT      = 0.001;
    const LAST_ACTIVE_TIME_KEY = '__lat';

    protected $pool;
    protected $config;
    protected $connector;
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
            $connection->{static::LAST_ACTIVE_TIME_KEY} = time();
            $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
            if ($ret === false) {
                $this->currentCount--;
            }
        }
        return true;
    }

    protected function getConnector(): ConnectorInterface
    {
        static $connector = null;
        if ($connector === null) {
            $connector = $this->getConnectorClass();
            $connector = new $connector();
        }
        return $connector;
    }

    /**
     * Specify the class name of the connector.
     * @return ConnectorInterface
     */
    abstract protected function getConnectorClass(): string;

    protected function createConnection(array $config)
    {
        return $this->getConnector()->connect($config);
    }

    protected function clearInactiveConnections()
    {
        $now = time();
        $validConnections = [];
        while (true) {
            if ($this->currentCount <= $this->minActive) {
                break;
            }

            $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
            if ($connection === false) {
                break;
            }
            $lastActiveTime = $connection->{static::LAST_ACTIVE_TIME_KEY} ?? 0;
            if ($now - $lastActiveTime < $this->maxIdleTime) {
                $validConnections[] = $connection;
            } else {
                $this->currentCount--;
            }
        }

        foreach ($validConnections as $validConnection) {
            $ret = $this->pool->push($validConnection, static::CHANNEL_TIMEOUT);
            if ($ret === false) {
                $this->currentCount--;
            }
        }
    }

    public function borrow()
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

    public function return($connection): bool
    {
        if ($this->pool->isFull()) {
            // Discard the connection
            return false;
        }
        $connection->{static::LAST_ACTIVE_TIME_KEY} = time();
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

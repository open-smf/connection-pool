<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    /**@var float The timeout of the operation channel */
    const CHANNEL_TIMEOUT = 0.001;

    /**@var float The minimum interval to check the idle connections */
    const MIN_CHECK_IDLE_INTERVAL = 10;

    /**@var string The key about the last active time of connection */
    const KEY_LAST_ACTIVE_TIME = '__lat';

    /**@var Channel The connection pool */
    protected $pool;

    /**@var array The config of connection */
    protected $connectionConfig;

    /**@var int Current all connection count */
    protected $connectionCount = 0;

    /**@var int The minimum number of active connections */
    protected $minActive = 1;

    /**@var int The maximum number of active connections */
    protected $maxActive = 1;

    /**@var float The maximum waiting time for connection, when reached, an exception will be thrown */
    protected $maxWaitTime = 5;

    /**@var float The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least $minActive connections in the pool */
    protected $maxIdleTime = 5;

    /**@var float The interval to check idle connection */
    protected $idleCheckInterval = 5;

    /**@var int The timer id of balancer */
    protected $balancerTimerId;

    /**
     * ConnectionPool constructor.
     * @param array $poolConfig The minimum number of active connections, the detail keys:
     * int minActive The minimum number of active connections
     * int maxActive The maximum number of active connections
     * float maxWaitTime The maximum waiting time for connection, when reached, an exception will be thrown
     * float maxIdleTime The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least $minActive connections in the pool
     * float idleCheckInterval The interval to check idle connection
     * @param array $connectionConfig The config of connection
     */
    public function __construct(array $poolConfig, array $connectionConfig)
    {
        $this->minActive = $poolConfig['minActive'] ?? 20;
        $this->maxActive = $poolConfig['maxActive'] ?? 100;
        $this->maxWaitTime = $poolConfig['maxWaitTime'] ?? 5;
        $this->maxIdleTime = $poolConfig['maxIdleTime'] ?? 30;
        $poolConfig['idleCheckInterval'] = $poolConfig['idleCheckInterval'] ?? 15;
        $this->idleCheckInterval = $poolConfig['idleCheckInterval'] >= static::MIN_CHECK_IDLE_INTERVAL ? $poolConfig['idleCheckInterval'] : static::MIN_CHECK_IDLE_INTERVAL;
        $this->connectionConfig = $connectionConfig;
    }

    /**
     * Initialize the connection pool
     * @return bool
     */
    public function init(): bool
    {
        if ($this->connectionCount > 0) {
            return false;
        }
        $this->pool = new Channel($this->maxActive);
        $this->balancerTimerId = $this->startBalanceTimer($this->idleCheckInterval);
        for ($i = 0; $i < $this->minActive; $i++) {
            $connection = $this->createConnection();
            $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
            if ($ret === false) {
                $this->removeConnection($connection);
            }
        }
        return true;
    }

    /**
     * Borrow a connection from the connection pool, throw an exception if timeout
     * @return mixed The connection resource
     * @throws BorrowConnectionTimeoutException
     */
    public function borrow()
    {
        if ($this->pool->isEmpty()) {
            // Create more connections
            if ($this->connectionCount < $this->maxActive) {
                return $this->createConnection();
            }
        }

        $connection = $this->pool->pop($this->maxWaitTime);
        if ($connection === false) {
            $exception = new BorrowConnectionTimeoutException(sprintf(
                'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                $this->maxWaitTime,
                $this->pool->length(),
                $this->connectionCount
            ));
            $exception->setTimeout($this->maxWaitTime);
            throw $exception;
        }
        $connector = $this->getConnector();
        if ($connector->isConnected($connection)) {
            // Reset the connection for the connected connection
            $connector->reset($connection, $this->connectionConfig);
        } else {
            // Remove the disconnected connection, then create a new connection
            $this->removeConnection($connection);
            $connection = $this->createConnection();
        }
        return $connection;
    }

    /**
     * Return a connection to the connection pool
     * @param mixed $connection The connection resource
     * @return bool
     */
    public function return($connection): bool
    {
        if ($this->pool->isFull()) {
            // Discard the connection
            return false;
        }
        $connection->{static::KEY_LAST_ACTIVE_TIME} = time();
        $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
        if ($ret === false) {
            $this->removeConnection($connection);
        }
        return $ret;
    }

    /**
     * Get the number of connections created
     * @return int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * Close the connection pool and disconnect all connections
     * @return bool
     */
    public function close(): bool
    {
        if ($this->pool === null) {
            return false;
        }
        swoole_timer_clear($this->balancerTimerId);
        while (true) {
            if ($this->pool->isEmpty()) {
                break;
            }
            $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
            if ($connection !== false) {
                $this->getConnector()->disconnect($connection);
            }
        }
        $this->pool->close();
        $this->pool = null;
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function startBalanceTimer(float $interval)
    {
        swoole_timer_tick(round($interval) * 1000, function () {
            $now = time();
            $validConnections = [];
            while (true) {
                if ($this->connectionCount <= $this->minActive) {
                    break;
                }
                if ($this->pool->isEmpty()) {
                    break;
                }
                $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
                if ($connection === false) {
                    continue;
                }
                $lastActiveTime = $connection->{static::KEY_LAST_ACTIVE_TIME} ?? 0;
                if ($now - $lastActiveTime < $this->maxIdleTime) {
                    $validConnections[] = $connection;
                } else {
                    $this->removeConnection($connection);
                }
            }

            foreach ($validConnections as $validConnection) {
                $ret = $this->pool->push($validConnection, static::CHANNEL_TIMEOUT);
                if ($ret === false) {
                    $this->removeConnection($validConnection);
                }
            }
        });
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
     * @return string
     */
    abstract protected function getConnectorClass(): string;

    protected function createConnection()
    {
        $this->connectionCount++;
        $connection = $this->getConnector()->connect($this->connectionConfig);
        $connection->{static::KEY_LAST_ACTIVE_TIME} = time();
        return $connection;
    }

    protected function removeConnection($connection)
    {
        $this->connectionCount--;
        go(function () use ($connection) {
            try {
                $this->getConnector()->disconnect($connection);
            } catch (\Throwable $e) {
                // Ignore this exception.
            }
        });
    }
}

<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Atomic;
use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    const CHANNEL_TIMEOUT = 0.001;

    protected $pool;
    protected $config;

    protected $currentSize;
    protected $minSize = 1;
    protected $maxSize = 1;
    protected $timeout = 5;

    /**
     * ConnectionPool constructor.
     * @param int $minSize The minimum number of connections
     * @param int $maxSize The maximum number of connections
     * @param float $timeout The maximum waiting time for connection, when arrived, an exception will be thrown.
     */
    public function __construct(int $minSize, int $maxSize, float $timeout)
    {
        $this->minSize = $minSize;
        $this->maxSize = $maxSize;
        $this->timeout = $timeout;
        $this->pool = new Channel($this->maxSize);
        $this->currentSize = new Atomic(0);
    }

    public function init(array $config)
    {
        $this->config = $config;
        $this->addConnections($this->minSize);
    }

    protected function addConnections(int $count): bool
    {
        for ($i = 0; $i < $count; $i++) {
            if ($this->currentSize->get() >= $this->maxSize) {
                return false;
            }
            $this->currentSize->add(1);
            $connection = $this->createConnection($this->config);
            $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
            if ($ret === false) {
                $this->currentSize->sub(1);
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
    abstract protected function createConnection(array $config);

    public function return($connection): bool
    {
        if ($this->pool->isFull()) {
            // Discard the connection
            return false;
        }
        $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
        if ($ret === false) {
            $this->currentSize->sub(1);
        }
        return $ret;
    }

    public function borrow()
    {
        if ($this->pool->isEmpty()) {
            // Create more connections then add them to pool
            $this->addConnections(1);
        }
        $connection = $this->pool->pop($this->timeout);
        if ($connection === false) {
            $exception = new BorrowConnectionTimeoutException(sprintf('Borrow the connection timeout in %.2f(s)', $this->timeout));
            $exception->setTimeout($this->timeout);
            throw $exception;
        }
        return $connection;
    }

    public function getCurrentSize(): int
    {
        return $this->currentSize->get();
    }

    public function close(): bool
    {
        if ($this->pool === null) {
            return false;
        }
        $this->pool->close();
        $this->pool = null;
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }
}

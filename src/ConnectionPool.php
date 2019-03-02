<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    protected $pool;
    protected $config;

    protected $currentSize = 0;
    protected $minSize     = 1;
    protected $maxSize     = 1;
    protected $timeout     = 5;

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
    }

    public function init(array $config)
    {
        $this->config = $config;
        $this->addConnections($this->minSize);
    }

    protected function addConnections(int $count)
    {
        $this->currentSize += $count;
        for ($i = 0; $i < $count; $i++) {
            $conn = $this->createConnection($this->config);
            $this->return($conn);
        }
    }

    protected function removeConnections(int $count)
    {
        for ($i = 0; $i < $count; $i++) {
            if ($this->pool->isEmpty()) {
                break;
            }
            $conn = $this->pool->pop(0.001);
            if ($conn !== false) {
                $this->currentSize--;
            }
        }
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
        $sub = $this->currentSize - $this->maxSize;
        if ($sub === 0) {
            return false;
        } elseif ($sub > 0) {
            $this->removeConnections($sub);
            return false;
        } else {
            $ret = $this->pool->push($connection);
            if ($ret === false) {
                throw new \RuntimeException(sprintf('Failed to push connection into channel: %s', $this->pool->errCode));
            }
            return true;
        }
    }

    public function borrow()
    {
        if ($this->pool->isEmpty()) {
            $add = $this->maxSize - $this->currentSize;
            if ($add > 0) {
                $this->addConnections($add);
            }
        }
        $conn = $this->pool->pop($this->timeout);
        if ($conn === false) {
            $exception = new BorrowConnectionTimeoutException(sprintf('Borrow the connection timeout in %.2f(s)', $this->timeout));
            $exception->setTimeout($this->timeout);
            throw $exception;
        }
        return $conn;
    }

    public function getCurrentSize(): int
    {
        return $this->currentSize;
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

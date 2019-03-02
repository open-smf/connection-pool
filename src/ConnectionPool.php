<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connections\Connection;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    protected        $pool;
    protected        $config;

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
            $ret = $this->return($conn);
            if ($ret === false) {
                throw new \RuntimeException(sprintf('Failed to push connection into channel: %s', $this->pool->errCode));
            }
        }
    }

    protected function removeConnections(int $count)
    {
        for ($i = 0; $i < $count; $i++) {
            if ($this->pool->isEmpty()) {
                return;
            }
            $conn = $this->pool->pop(0.001);
            if ($conn !== false) {
                $this->currentSize--;
            }
        }
    }

    abstract protected function createConnector(): ConnectorInterface;

    abstract protected function createConnection(array $config): Connection;

    public function return(Connection $connection): bool
    {
        $sub = $this->pool->length() - $this->minSize;
        if ($sub === 0) {
            // todo close connection
            return false;
        } elseif ($sub > 0) {
            $this->removeConnections($sub);
            return false;
        } else {
            return $this->pool->push($connection);
        }
    }

    public function borrow(): Connection
    {
        if ($this->pool->isEmpty()) {
            $add = $this->maxSize - $this->currentSize;
            if ($add > 0) {
                $this->addConnections($add);
            }
        }
        $conn = $this->pool->pop($this->timeout);
        if ($conn === false) {
            $exception = new BorrowConnectionTimeoutException(sprintf('Get the connection timeout in %.2f(s)', $this->timeout));
            $exception->setTimeout($this->timeout);
            throw $exception;
        }
        return $conn;
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

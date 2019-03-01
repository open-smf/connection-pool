<?php

namespace Smf\ConnectionPool;

use Swoole\Coroutine\Channel;

abstract class ConnectionPool implements ConnectionPoolInterface
{
    protected $pool;

    public function __construct(array $config, int $size)
    {
        $this->pool = new Channel($size);
        for ($i = 0; $i < $size; $i++) {
            $conn = $this->createConnection($config);
            $ret = $this->put($conn);
            if ($ret === false) {
                throw new \RuntimeException(sprintf('Failed to push connection into channel: %s', $this->pool->errCode));
            }
        }
    }

    abstract protected function createConnection(array $config): Connection;

    public function put(Connection $connection): bool
    {
        return $this->pool->push($connection);
    }

    public function get(float $timeout = 0): Connection
    {
        return $this->pool->pop($timeout);
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

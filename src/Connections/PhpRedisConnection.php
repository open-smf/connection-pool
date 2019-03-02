<?php

namespace Smf\ConnectionPool\Connections;

class PhpRedisConnection extends Connection
{
    /**
     * @var \Redis
     */
    protected $rawConnection;

    public function close(): bool
    {
        return $this->rawConnection->close();
    }
}
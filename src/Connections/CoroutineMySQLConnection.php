<?php

namespace Smf\ConnectionPool\Connections;

use Swoole\Coroutine\MySQL;

class CoroutineMySQLConnection extends Connection
{
    /**
     * @var MySQL
     */
    protected $rawConnection;

    public function close(): bool
    {
        return $this->rawConnection->close();
    }
}
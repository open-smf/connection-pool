<?php

namespace Smf\ConnectionPool\Connectors;

use Swoole\Coroutine\MySQL;

class CoroutineMySQLConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $connection = new MySQL();
        if ($connection->connect($config) === false) {
            throw new \RuntimeException(sprintf('Failed to connect MySQL server [%d]%s', $connection->connect_errno, $connection->connect_error));
        }
        return $connection;
    }
}
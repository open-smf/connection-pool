<?php

namespace Smf\ConnectionPool\Connectors;

use Smf\ConnectionPool\Connection;
use Swoole\Coroutine\MySQL;

class CoroutineMySQLConnector implements ConnectorInterface
{
    public function connect(array $config): Connection
    {
        $raw = new MySQL();
        if ($raw->connect($config) === false) {
            throw new \RuntimeException(sprintf('Failed to connect MySQL server [%d]%s', $raw->connect_errno, $raw->connect_error));
        }
        return new Connection($raw);
    }
}
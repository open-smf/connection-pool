<?php

namespace Smf\ConnectionPool;

use Swoole\Coroutine\MySQL;

class MySQLPool extends ConnectionPool
{
    protected function createConnection(array $config): Connection
    {
        $mysql = new MySQL();
        $ret = $mysql->connect($config);
        if ($ret === false) {
            throw new \RuntimeException(sprintf('Failed to connect MySQL server [%d]%s', $mysql->connect_errno, $mysql->connect_error));
        }
        return new Connection($mysql);
    }
}
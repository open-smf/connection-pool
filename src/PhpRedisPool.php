<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connections\Connection;
use Smf\ConnectionPool\Connections\PhpRedisConnection;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;

class PhpRedisPool extends ConnectionPool
{
    protected function createConnector(): ConnectorInterface
    {
        static $connector = null;
        if ($connector === null) {
            $connector = new PhpRedisConnector();
        }
        return $connector;
    }

    protected function createConnection(array $config): Connection
    {
        $rawConnection = $this->createConnector()->connect($config);
        return new PhpRedisConnection($rawConnection);
    }
}
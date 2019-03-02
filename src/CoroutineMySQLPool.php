<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connections\Connection;
use Smf\ConnectionPool\Connections\CoroutineMySQLConnection;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Smf\ConnectionPool\Connectors\CoroutineMySQLConnector;

class CoroutineMySQLPool extends ConnectionPool
{
    protected function createConnector(): ConnectorInterface
    {
        static $connector = null;
        if ($connector === null) {
            $connector = new CoroutineMySQLConnector();
        }
        return $connector;
    }

    protected function createConnection(array $config): Connection
    {
        $rawConnection = $this->createConnector()->connect($config);
        return new CoroutineMySQLConnection($rawConnection);
    }
}
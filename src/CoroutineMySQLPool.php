<?php

namespace Smf\ConnectionPool;

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
        return $this->createConnector()->connect($config);
    }
}
<?php

namespace Smf\ConnectionPool;

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
        return $this->createConnector()->connect($config);
    }
}
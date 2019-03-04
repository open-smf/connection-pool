<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\CoroutineMySQLConnector;

class CoroutineMySQLPool extends ConnectionPool
{
    protected function getConnectorClass(): string
    {
        return CoroutineMySQLConnector::class;
    }
}
<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connectors\PhpRedisConnector;

class PhpRedisPool extends ConnectionPool
{
    protected function getConnectorClass(): string
    {
        return PhpRedisConnector::class;
    }
}
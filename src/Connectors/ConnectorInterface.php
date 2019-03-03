<?php

namespace Smf\ConnectionPool\Connectors;

use Smf\ConnectionPool\Connection;

interface ConnectorInterface
{
    public function connect(array $config): Connection;
}
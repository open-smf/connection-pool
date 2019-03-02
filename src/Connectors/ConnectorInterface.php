<?php

namespace Smf\ConnectionPool\Connectors;

interface ConnectorInterface
{
    public function connect(array $config);
}
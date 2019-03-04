<?php

namespace Smf\ConnectionPool\Connectors;

interface ConnectorInterface
{
    /**
     * Connect to the specified Server and returns the connection resource
     * @param array $config
     * @return mixed
     */
    public function connect(array $config);

    /**
     * Disconnect and free resources
     * @param mixed $connection
     * @return mixed
     */
    public function disconnect($connection);
}
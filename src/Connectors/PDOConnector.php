<?php

namespace Smf\ConnectionPool\Connectors;

class PDOConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        try {
            $connection = new \PDO($config['dsn'], $config['username'] ?? '', $config['password'] ?? '', $config['options'] ?? []);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to connect the requested database: [%d] %s', $e->getCode(), $e->getMessage()));
        }
        return $connection;
    }

    public function disconnect($connection)
    {
        /**@var \PDO $connection */
        $connection = null;
    }

    public function isConnected($connection): bool
    {
        return true;
    }

    public function reset($connection, array $config)
    {

    }

    public function validate($connection): bool
    {
        return $connection instanceof \PDO;
    }
}
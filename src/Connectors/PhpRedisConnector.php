<?php

namespace Smf\ConnectionPool\Connectors;

class PhpRedisConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $connection = new \Redis();
        $ret = $connection->connect($config['host'], $config['port'], $config['timeout'] ?? 10);
        if ($ret === false) {
            throw new \RuntimeException(sprintf(
                'Failed to connect Redis server %s:%d, %s',
                $config['host'],
                $config['port'],
                $connection->getLastError()
            ));
        }
        if (isset($config['database'])) {
            $connection->select($config['database']);
        }
        if (isset($config['password'])) {
            $config['password'] = (string)$config['password'];
            if ($config['password'] !== '') {
                $connection->auth($config['password']);
            }
        }
        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $connection->setOption($key, $value);
            }
        }
        return $connection;
    }

    public function disconnect($connection)
    {
        $connection->close();
    }
}
<?php

namespace Smf\ConnectionPool\Connectors;

use Smf\ConnectionPool\Connection;

class PhpRedisConnector implements ConnectorInterface
{
    public function connect(array $config): Connection
    {
        $raw = new \Redis();
        $ret = $raw->connect($config['host'], $config['port'], $config['timeout'] ?? 10);
        if ($ret === false) {
            throw new \RuntimeException(sprintf(
                'Failed to connect Redis server %s:%d, %s',
                $config['host'],
                $config['port'],
                $raw->getLastError()
            ));
        }
        if (isset($config['database'])) {
            $raw->select($config['database']);
        }
        if (isset($config['password'])) {
            $config['password'] = (string)$config['password'];
            if ($config['password'] !== '') {
                $raw->auth($config['password']);
            }
        }
        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $raw->setOption($key, $value);
            }
        }
        return new Connection($raw);
    }
}
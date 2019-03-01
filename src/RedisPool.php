<?php

namespace Smf\ConnectionPool;

class RedisPool extends ConnectionPool
{
    protected function createConnection(array $config): Connection
    {
        $redis = new \Redis();
        $ret = $redis->connect($config['host'], $config['port'], $config['timeout'] ?? 10);
        if ($ret === false) {
            throw new \RuntimeException(sprintf(
                'Failed to connect Redis server %s:%d, %s',
                $config['host'],
                $config['port'],
                $redis->getLastError()
            ));
        }
        if (isset($config['database'])) {
            $redis->select($config['database']);
        }
        if (isset($config['password']) && $config['password'] !== '') {
            $redis->auth($config['password']);
        }
        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $redis->setOption($key, $value);
            }
        }
        return new Connection($redis);
    }
}
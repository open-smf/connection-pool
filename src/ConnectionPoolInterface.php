<?php

namespace Smf\ConnectionPool;

interface ConnectionPoolInterface
{
    public function put(Connection $connection): bool;

    public function get(float $timeout = 0): Connection;

    public function close(): bool;
}
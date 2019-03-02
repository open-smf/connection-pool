<?php

namespace Smf\ConnectionPool;

interface ConnectionPoolInterface
{

    /**
     * Initialize the connection pool
     * @param array $config The configurations of connection
     */
    public function init(array $config);

    /**
     * Return a connection to the connection pool
     * @param mixed $connection
     * @return bool
     */
    public function return($connection): bool;

    /**
     * Borrow a connection to the connection pool
     * @return mixed
     * @throws BorrowConnectionTimeoutException
     */
    public function borrow();

    /**
     * Close the connection pool, release the resource of all connections
     * @return bool
     */
    public function close(): bool;
}
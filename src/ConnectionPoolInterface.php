<?php

namespace Smf\ConnectionPool;

use Smf\ConnectionPool\Connections\Connection;

interface ConnectionPoolInterface
{

    /**
     * Initialize the connection pool
     * @param array $config The configurations of connection
     */
    public function init(array $config);

    /**
     * Return a connection to the connection pool
     * @param Connection $connection
     * @return bool
     */
    public function return(Connection $connection): bool;

    /**
     * Borrow a connection to the connection pool
     * @return Connection
     * @throws BorrowConnectionTimeoutException
     */
    public function borrow(): Connection;

    /**
     * Close the connection pool
     * @return bool
     */
    public function close(): bool;
}
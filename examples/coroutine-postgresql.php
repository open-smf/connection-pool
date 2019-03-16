<?php
include '../vendor/autoload.php';

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\CoroutinePostgreSQLConnector;
use Swoole\Coroutine\PostgreSQL;

go(function () {
    // All PostgreSQL connections: [10, 30]
    $pool = new ConnectionPool(
        [
            'minActive'         => 10,
            'maxActive'         => 30,
            'maxWaitTime'       => 5,
            'maxIdleTime'       => 20,
            'idleCheckInterval' => 10,
        ],
        new CoroutinePostgreSQLConnector,
        [
            'connection_strings' => 'host=127.0.0.1 port=5432 dbname=postgres user=postgres password=xy123456',
        ]
    );
    echo "Initialize connection pool\n";
    $pool->init();
    defer(function () use ($pool) {
        echo "Close connection pool\n";
        $pool->close();
    });

    /**@var PostgreSQL $connection */
    $connection = $pool->borrow();
    defer(function () use ($pool, $connection) {
        echo "Return the connection to pool\n";
        $pool->return($connection);
    });
    $result = $connection->query("SELECT * FROM pg_stat_database where datname='postgres';");
    $stat = $connection->fetchAssoc($result);
    var_dump($stat);
});

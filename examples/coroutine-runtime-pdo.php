<?php
include '../vendor/autoload.php';

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\PDOConnector;

// Enable coroutine for PDO
Swoole\Runtime::enableCoroutine();

go(function () {
    // All Redis connections: [10, 30]
    $pool = new ConnectionPool(
        [
            'minActive'         => 10,
            'maxActive'         => 30,
            'maxWaitTime'       => 5,
            'maxIdleTime'       => 20,
            'idleCheckInterval' => 10,
        ],
        new PDOConnector,
        [
            'dsn'      => 'mysql:host=127.0.0.1;port=3306;dbname=mysql',
            'username' => 'root',
            'password' => 'xy123456',
            'options'  => [],
        ]
    );
    echo "Initializing connection pool\n";
    $pool->init();
    defer(function () use ($pool) {
        echo "Close connection pool\n";
        $pool->close();
    });

    echo "Borrowing the connection from pool\n";
    /**@var \PDO $connection */
    $connection = $pool->borrow();

    $statement = $connection->query('SHOW STATUS LIKE "Threads_connected"');

    echo "Return the connection to pool as soon as possible\n";
    $pool->return($connection);

    var_dump($statement->fetch(\PDO::FETCH_ASSOC));
});

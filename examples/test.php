<?php

include '../vendor/autoload.php';

use Smf\ConnectionPool\CoroutineMySQLPool;
use Swoole\Coroutine\MySQL;

go(function () {
    // All MySQL connections: [10, 20]
    $pool = new CoroutineMySQLPool(10, 20, 5);
    $pool->init([
        'host'        => '127.0.0.1',
        'port'        => '3306',
        'user'        => 'root',
        'password'    => 'xy123456',
        'database'    => 'test',
        'timeout'     => 10,
        'charset'     => 'utf8mb4',
        'strict_type' => true,
        'fetch_mode'  => true,
    ]);

    for ($i = 0; $i < 10; $i++) {
        go(function () use ($pool) {
            /**@var MySQL $mysql */
            $mysql = $pool->borrow();
            defer(function () use ($pool, $mysql) {
                $pool->return($mysql);
            });
            $ret = $mysql->query('select sleep(1)');
            var_dump($ret);
        });
    }
});
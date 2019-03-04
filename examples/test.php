<?php

include '../vendor/autoload.php';

use Smf\ConnectionPool\CoroutineMySQLPool;
use Swoole\Coroutine;
use Swoole\Coroutine\MySQL;

go(function () {
    // All MySQL connections: [10, 30]
    $pool = new CoroutineMySQLPool(10, 30, 5, 10);
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

    swoole_timer_tick(1000, function () use ($pool) {
        var_dump('Current connection count: ' . $pool->getCurrentCount());
    });

    while (true) {
        $count = mt_rand(10, 32);
        var_dump('Query count: ' . $count);
        for ($i = 0; $i < $count; $i++) {
            go(function () use ($pool) {
                /**@var MySQL $mysql */
                $mysql = $pool->borrow();
                defer(function () use ($pool, $mysql) {
                    $pool->return($mysql);
                });
                $ret = $mysql->query('select sleep(1),now() as now');
                if (!isset($ret[0]['now'])) {
                    var_dump("Invalid query result: \n" . print_r($ret, true));
                }
            });
        }
        Coroutine::sleep(1);
    }
});

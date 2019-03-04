<?php
include '../vendor/autoload.php';

use Smf\ConnectionPool\CoroutineMySQLPool;
use Swoole\Coroutine;
use Swoole\Coroutine\MySQL;

go(function () {
    // All MySQL connections: [10, 30]
    $pool = new CoroutineMySQLPool(10, 30, 5, 20, 10);
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
        var_dump('Pool connection count: ' . $pool->getConnectionCount());
    });

    while (true) {
        $count = mt_rand(1, 32);
        var_dump('Query count: ' . $count);
        for ($i = 0; $i < $count; $i++) {
            go(function () use ($pool) {
                /**@var MySQL $mysql */
                $mysql = $pool->borrow();
                defer(function () use ($pool, $mysql) {
                    $pool->return($mysql);
                });
                $ret = $mysql->query('show status like \'Threads_connected\'');
                if (!isset($ret[0]['Variable_name'])) {
                    var_dump("Invalid query result: \n" . print_r($ret, true));
                }
                var_dump($ret[0]['Variable_name'] . ': ' . $ret[0]['Value']);
            });
        }
        Coroutine::sleep(mt_rand(1, 15));
    }
});

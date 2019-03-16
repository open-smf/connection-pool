<?php
include '../vendor/autoload.php';

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\CoroutineMySQLConnector;
use Swoole\Coroutine;
use Swoole\Coroutine\MySQL;

go(function () {
    // All MySQL connections: [10, 30]
    $pool = new ConnectionPool(
        [
            'minActive'         => 10,
            'maxActive'         => 30,
            'maxWaitTime'       => 5,
            'maxIdleTime'       => 20,
            'idleCheckInterval' => 10,
        ],
        new CoroutineMySQLConnector,
        [
            'host'        => '127.0.0.1',
            'port'        => '3306',
            'user'        => 'root',
            'password'    => 'xy123456',
            'database'    => 'mysql',
            'timeout'     => 10,
            'charset'     => 'utf8mb4',
            'strict_type' => true,
            'fetch_mode'  => true,
        ]
    );
    $pool->init();

    // For debug
    $peakCount = 0;
    swoole_timer_tick(1000, function () use ($pool, &$peakCount) {
        $count = $pool->getConnectionCount();
        $idleCount = $pool->getIdleCount();
        if ($peakCount < $count) {
            $peakCount = $count;
        }
        echo "Pool connection count: $count, peak count: $peakCount, idle count: $idleCount\n";
    });

    while (true) {
        $count = mt_rand(1, 32);
        echo "Query count: $count\n";
        for ($i = 0; $i < $count; $i++) {
            go(function () use ($pool) {
                /**@var MySQL $mysql */
                $mysql = $pool->borrow();
                defer(function () use ($pool, $mysql) {
                    $pool->return($mysql);
                });
                $ret = $mysql->query('show status like \'Threads_connected\'');
                if (!isset($ret[0]['Variable_name'])) {
                    echo "Invalid query result: \n", print_r($ret, true);
                }
                echo $ret[0]['Variable_name'] . ': ' . $ret[0]['Value'] . "\n";
            });
        }
        Coroutine::sleep(mt_rand(1, 15));
    }
});

# Connection pool
A common connection pool based on Swoole is usually used as the database connection pool.


## Requirements

| Dependency | Requirement |
| -------- | -------- |
| [PHP](https://secure.php.net/manual/en/install.php) | `>=7.0.0` |
| [Swoole](https://github.com/swoole/swoole-src) | `>=4.2.9` `Recommend 4.2.13+` |

## Install
> Install package via [Composer](https://getcomposer.org/).

```shell
composer require "open-smf/connection-pool:~1.0"
```

## Usage

- Basic usage

```php
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
            'database'    => 'test',
            'timeout'     => 10,
            'charset'     => 'utf8mb4',
            'strict_type' => true,
            'fetch_mode'  => true,
        ]
    );
    $pool->init();

    $peakCount = 0;
    swoole_timer_tick(1000, function () use ($pool, &$peakCount) {
        $count = $pool->getConnectionCount();
        if ($peakCount < $count) {
            $peakCount = $count;
        }
        echo "Pool connection count: $count, peak count: $peakCount\n";
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
```

- Usage in Swoole Server

```php
include '../vendor/autoload.php';

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\Connectors\CoroutineMySQLConnector;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use Swoole\Coroutine\MySQL;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class HttpServer
{
    use ConnectionPoolTrait;

    protected $swoole;

    public function __construct(string $host, int $port)
    {
        $this->swoole = new Server($host, $port);

        $this->setDefault();
        $this->bindWorkerEvents();
        $this->bindHttpEvent();
    }

    protected function setDefault()
    {
        $this->swoole->set([
            'daemonize'             => false,
            'dispatch_mode'         => 1,
            'max_request'           => 8000,
            'open_tcp_nodelay'      => true,
            'reload_async'          => true,
            'max_wait_time'         => 60,
            'enable_reuse_port'     => true,
            'enable_coroutine'      => true,
            'http_compression'      => false,
            'enable_static_handler' => false,
            'buffer_output_size'    => 4 * 1024 * 1024,
            'worker_num'            => 4, // Each worker holds a connection pool
        ]);
    }

    protected function bindHttpEvent()
    {
        $this->swoole->on('Request', function (Request $request, Response $response) {
            $pool1 = $this->getConnectionPool('mysql');
            /**@var MySQL $mysql */
            $mysql = $pool1->borrow();
            defer(function () use ($pool1, $mysql) {
                $pool1->return($mysql);
            });
            $status = $mysql->query('SHOW STATUS LIKE \'Threads_connected\'');


            $pool2 = $this->getConnectionPool('redis');
            /**@var Redis $redis */
            $redis = $pool2->borrow();
            defer(function () use ($pool2, $redis) {
                $this->pools['redis']->return($redis);
            });
            $clients = $redis->info('Clients');

            $json = [
                'status'  => $status,
                'clients' => $clients,
            ];
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode($json));
        });
    }

    protected function bindWorkerEvents()
    {
        $createPools = function () {
            // All MySQL connections: [4 workers * 2 = 8, 4 workers * 10 = 40]
            $pool1 = new ConnectionPool(
                [
                    'minActive' => 2,
                    'maxActive' => 10,
                ],
                new CoroutineMySQLConnector,
                [
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
            $pool1->init();
            $this->addConnectionPool('mysql', $pool1);

            // All Redis connections: [4 workers * 5 = 20, 4 workers * 20 = 80]
            $pool2 = new ConnectionPool(
                [
                    'minActive' => 5,
                    'maxActive' => 20,
                ],
                new PhpRedisConnector,
                [
                    'host'     => '127.0.0.1',
                    'port'     => '6379',
                    'database' => 0,
                    'password' => null,
                ]);
            $pool2->init();
            $this->addConnectionPool('redis', $pool2);
        };
        $closePools = function () {
            $this->closeConnectionPools();
        };
        $this->swoole->on('WorkerStart', $createPools);
        $this->swoole->on('WorkerStop', $closePools);
        $this->swoole->on('WorkerExit', $closePools);
        $this->swoole->on('WorkerError', $closePools);
    }

    public function start()
    {
        $this->swoole->start();
    }
}

// Enable runtime coroutine for PhpRedis
Swoole\Runtime::enableCoroutine(true);
$server = new HttpServer('0.0.0.0', 5200);
$server->start();
```

## License

[MIT](LICENSE)
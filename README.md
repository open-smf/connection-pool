# Connection pool
A common connection pool based on Swoole is usually used as the database connection pool.


## Requirements

| Dependency | Requirement |
| -------- | -------- |
| [PHP](https://secure.php.net/manual/en/install.php) | `>=7.0.0` |
| [Swoole](https://www.swoole.com/) | `>=4.2.9` `Recommend 4.2.13+` |

## Install
> Install package via [Composer](https://getcomposer.org/).

```shell
composer require "open-smf/connection-pool"
```

## Usage

```php
include '../vendor/autoload.php';

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\MySQLPool;
use Smf\ConnectionPool\RedisPool;
use Swoole\Coroutine\MySQL;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class HttpServer
{
    protected $swoole;

    /**
     * @var ConnectionPool[]
     */
    protected $pools = [];

    public function __construct($host, $port)
    {
        $this->swoole = new Server($host, $port);

        $this->set();
        $this->bindWorkerEvents();
        $this->bindHttpEvent();
    }

    protected function set()
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
            'worker_num'            => 4,
        ]);
    }

    protected function bindHttpEvent()
    {
        $this->swoole->on('Request', function (Request $request, Response $response) {
            /**
             * @var MySQL $mysql
             */
            $mysql = $this->pools['mysql']->get();
            defer(function () use ($mysql) {
                $this->pools['mysql']->put($mysql);
            });
            $now = $mysql->query('select now()');


            /**
             * @var Redis $redis
             */
            $redis = $this->pools['redis']->get();
            defer(function () use ($redis) {
                $this->pools['redis']->put($redis);
            });
            $info = $redis->info('Memory');

            $json = [
                'now'  => $now,
                'info' => $info,
            ];
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode($json));
        });
    }

    protected function bindWorkerEvents()
    {
        $createPool = function () {
            $this->pools['mysql'] = new MySQLPool([
                'host'        => '127.0.0.1',
                'port'        => '3306',
                'user'        => 'root',
                'password'    => 'xy123456',
                'database'    => 'test',
                'timeout'     => 10,
                'charset'     => 'utf8mb4',
                'strict_type' => true,
                'fetch_mode'  => true,
            ], 10);

            $this->pools['redis'] = new RedisPool([
                'host'     => '127.0.0.1',
                'port'     => '6379',
                'database' => 0,
                'password' => null,
            ], 10);
        };
        $closePool = function () {
            foreach ($this->pools as $pool) {
                $pool->close();
            }
        };
        $this->swoole->on('WorkerStart', $createPool);
        $this->swoole->on('WorkerStop', $closePool);
        $this->swoole->on('WorkerExit', $closePool);
        $this->swoole->on('WorkerError', $closePool);
    }

    public function start()
    {
        $this->swoole->start();
    }
}

$server = new HttpServer('0.0.0.0', 5200);
$server->start();
```

## License

[MIT](LICENSE)
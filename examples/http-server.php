<?php
include '../vendor/autoload.php';

use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\MySQLPool;
use Smf\ConnectionPool\RedisPool;
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
            'worker_num'            => 4,
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
            // All MySQL connections: [4*2, 4*10]
            $pool1 = new MySQLPool(2, 10, 5);
            $pool1->init([
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
            $this->addConnectionPool('mysql', $pool1);

            // All Redis connections: [4*2, 4*10]
            $pool2 = new RedisPool(2, 10, 5);
            $pool2->init([
                'host'     => '127.0.0.1',
                'port'     => '6379',
                'database' => 0,
                'password' => null,
            ]);
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

$server = new HttpServer('0.0.0.0', 5200);
$server->start();
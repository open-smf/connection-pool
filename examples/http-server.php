<?php
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
            /**@var MySQL $mysql */
            $mysql = $this->pools['mysql']->borrow();
            defer(function () use ($mysql) {
                $this->pools['mysql']->return($mysql);
            });
            $status = $mysql->query('SHOW STATUS LIKE \'Threads_connected\'');


            /**@var Redis $redis */
            $redis = $this->pools['redis']->borrow();
            defer(function () use ($redis) {
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
            $this->pools['mysql'] = $pool1;

            // All Redis connections: [4*2, 4*10]
            $pool2 = new RedisPool(2, 10, 5);
            $pool2->init([
                'host'     => '127.0.0.1',
                'port'     => '6379',
                'database' => 0,
                'password' => null,
            ]);
            $this->pools['redis'] = $pool2;
        };
        $closePools = function () {
            foreach ($this->pools as $pool) {
                $pool->close();
            }
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
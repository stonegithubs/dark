<?php

namespace Dark;

use Closure;
use swoole_server as SwooleServer;

class Server
{

    /**
     * 连接池服务器IP
     *
     * @var string
     */
    private $_host = '127.0.0.1';

    /**
     * 监听端口
     *
     * @var int
     */
    private $_port = 9501;

    /**
     * 连接池配置
     *
     * @var array
     */
    private $_config = array(
        'worker_num'        => 2,   //工作进程数量
        'daemonize'         => false,   //是否以守护进程方式启动
        'log_file'          => '/tmp/dbpool.log',   //日志输出文件
        'worker_conn_num'   => 3,   //每个工作进程启动几个数据库连接
    );

    /**
     * 提供数据库驱动器的匿名函数
     *
     * @var Closure
     */
    private $_driver;

    /**
     * 构造器
     *
     * @param $host
     * @param $port
     * @param array $config
     */
    public function __construct($host, $port, array $config = array())
    {
        $this->_host = $host;
        $this->_port = $port;
        $this->_config = array_merge($this->_config, $config);
    }

    /**
     * 设置连接池配置
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->_config = array_merge($this->_config, $config);
        return $this;
    }

    /**
     * 设置数据库驱动器
     *
     * @param Closure $driver
     * @return $this
     */
    public function setDriver(Closure $driver)
    {
        $this->_driver = $driver;
        return $this;
    }

    /**
     * 启动连接池
     */
    public function startup()
    {

        if (!extension_loaded('swoole')) {
            throw new Exception('无法加载swoole扩展');
        }

        global $dbpool;
        $dbpool = array();

        $server = new SwooleServer($this->_host, $this->_port);
        $server->set($this->_config);

        //启动服务
        $server->on('Start', function(SwooleServer $server) {
            $this->_printLog('连接池服务已启动');
        });

        //worker进程启动
        $server->on('WorkerStart', function(SwooleServer $server, $worker_id) {
            global $dbpool;
            for ($i = 1; $i <= $this->_config['worker_conn_num']; $i++) {
                try {
                    $driver = $this->_driver;
                    $driver = $driver();
                    if (!$driver || !$driver instanceof IDriver) {
                        throw new Exception('无效的数据库连接驱动');
                    }
                    $conn_id = (($worker_id + 1) * 10). $i;
                    $dbpool[] = array(
                        'worker_id'=> $worker_id,
                        'id'=> $conn_id,
                        'driver' => $driver,
                        'fd'=> -1,
                        'hash'=> spl_object_hash($driver->getInstance()),
                    );
                    $this->_printLog(sprintf('数据库连接创建成功[conn:%s][pool:%s]', $conn_id, $worker_id));
                } catch (Exception $e) {
                    $this->_printLog(sprintf('数据库连接创建异常[pool:%s]: %s', $worker_id, $e->getMessage()));
                }
            }
        });

        //客户端连通事件
        $server->on('Connect', function (SwooleServer $server, $fd, $from_id) {
            global $dbpool;
            $assigned = array();
            foreach ($dbpool as $k=> $v) {
                if ($v['fd'] == $fd) {
                    $assigned = $v;
                    break;
                }
            }
            if (!$assigned) {
                foreach ($dbpool as $k=> $v) {
                    if ($v['fd'] == -1) {
                        $dbpool[$k]['fd'] = $fd;
                        $assigned = $dbpool[$k];
                        break;
                    }
                }
            }
            $server->send($fd, $assigned['id'] ? 1 : 0);
            if ($assigned['id']) {
                $this->_printLog(sprintf('客户端[fd:%s]已成功连接[pool:%s], 分配的数据库连接为[conn:%s]', $fd, $server->worker_id, $assigned['id']));
            }
        });

        //响应请求
        $server->on('Receive', function(SwooleServer $server, $fd, $from_id, $data) {
            global $dbpool;
            $data = json_decode($data, true);
            if (!$data || !$data['method']) {
                $server->send($fd, json_encode(array('status'=> 0, 'message'=> '数据格式异常')));
                $this->_printLog(sprintf('客户端数据异常[fd:%s][pool:%s]:%s', $fd, $server->worker_id, json_encode($data)));
            } else {
                $driver = null;
                $conn = null;
                foreach ($dbpool as $k=> $v) {
                    if ($v['fd'] == $fd) {
                        $driver = $v['driver'];
                        $conn = $v['id'];
                        break;
                    }
                }
                if ($driver) {
                    $method = $data['method'];
                    $params = $data['params'] ? $data['params'] : array();
                    try {
                        $data['status'] = 1;
                        if (!method_exists($driver, $method)) {
                            throw new Exception(sprintf('当前数据库驱动器不存在方法[method:%s]', $method));
                        }
                        $data['result'] = call_user_func_array(array($driver, $method), $params);
                        $server->send($fd, json_encode($data));
                        $this->_printLog(sprintf('数据库执行成功[fd:%s][pool:%s][conn:%s]: %s', $fd, $server->worker_id, $conn, json_encode(array('method'=> $method, 'params'=> $params))));
                    } catch (Exception $e) {
                        $server->send($fd, json_encode(array('status'=> 0, 'message'=> $e->getMessage())));
                        $this->_printLog(sprintf('数据库执行异常[fd:%s][pool:%s][conn:%s]: %s', $fd, $server->worker_id, $conn, $e->getMessage()));
                    }
                } else {
                    $server->send($fd, json_encode(array('status'=> 0, 'message'=> '无效的数据库驱动器')));
                    $this->_printLog(sprintf('无有效的数据库驱动器[fd:%s][pool:%s][conn:%s]', $fd, $server->worker_id, $conn));
                }
            }
        });

        //关闭链接
        $server->on('Close', function (SwooleServer $server, $fd) {
            global $dbpool;
            foreach ($dbpool as $k=> $v) {
                if ($v['fd'] == $fd) {
                    $dbpool[$k]['fd'] = -1;
                }
            }
            $this->_printLog(sprintf('客户端[fd:%s]已关闭连接[pool:%s]', $fd, $server->worker_id));
        });

        $server->start();

    }

    /**
     * 输出log
     *
     * @param $msg
     */
    private function _printLog($msg)
    {
        echo sprintf("[%s] %s", date('Y-m-d H:i:s'), $msg). PHP_EOL;
    }

}
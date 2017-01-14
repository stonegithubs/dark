<?php

namespace Dark;

use swoole_client as SwooleClient;

class Client implements IDriver
{

    /**
     * 连接对象实例
     *
     * @access private
     * @var SwooleClient
     */
    private $_instance;

    /**
     * 构造器
     *
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @throws Exception
     */
    public function __construct($host, $port, $timeout = 3)
    {
        if (!extension_loaded('swoole')) {
            throw new Exception('无法加载swoole扩展');
        }
        $i = 0;
        while ($i < $timeout) {
            $this->_instance = new SwooleClient(SWOOLE_TCP);
            $this->_instance->connect($host, $port);
            if ($this->_instance->recv()) {
                break;
            }
            $this->_instance->close();
            $this->_instance = null;
            sleep(1);
            $i++;
        }
        if (!$this->_instance) {
            throw new Exception('无法访问Oracle连接池');
        }
    }

    /**
     * 执行操作，返回影响行数
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return int
     * @throws Exception
     */
    public function query($sql, array $bind = array())
    {
        $data = array(
            'method'=> 'query',
            'params'=> array($sql, $bind),
        );
        return $this->_query($data);
    }

    /**
     * 执行带返回值的操作
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return array
     */
    public function fetchAll($sql, array $bind = array())
    {
        $data = array(
            'method'=> 'fetchAll',
            'params'=> array($sql, $bind),
        );
        return $this->_query($data);
    }

    /**
     * 获取第一行第一列的值，一般用在聚合函数
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     */
    public function fetchOne($sql, array $bind = array())
    {
        $data = array(
            'method'=> 'fetchOne',
            'params'=> array($sql, $bind),
        );
        return $this->_query($data);
    }

    /**
     * 获取单行数据
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     */
    public function fetch($sql, array $bind = array())
    {
        $data = array(
            'method'=> 'fetch',
            'params'=> array($sql, $bind),
        );
        return $this->_query($data);
    }

    /**
     * 该序列的当前值
     *
     * @access public
     * @param string $seq
     * @return integer
     */
    public function lastInsertId($seq = null)
    {
        $data = array(
            'method'=> 'lastInsertId',
            'params'=> array($seq),
        );
        return $this->_query($data);
    }

    /**
     * 返回最近执行的SQL语句所影响到的行数
     *
     * @access public
     * @return integer
     */
    public function lastRowCount()
    {
        $data = array(
            'method'=> 'lastRowCount',
            'params'=> array(),
        );
        return $this->_query($data);
    }

    /**
     * 开始事务处理
     *
     * @access public
     * @return bool
     */
    public function beginTransaction()
    {
        $data = array(
            'method'=> 'beginTransaction',
            'params'=> array(),
        );
        return $this->_query($data);
    }

    /**
     * 提交当前事务
     *
     * @access public
     * @return bool
     */
    public function commit()
    {
        $data = array(
            'method'=> 'commit',
            'params'=> array(),
        );
        return $this->_query($data);
    }

    /**
     * 回滚当前事务
     *
     * @access public
     * @return bool
     */
    public function rollback()
    {
        $data = array(
            'method'=> 'rollback',
            'params'=> array(),
        );
        return $this->_query($data);
    }

    /**
     * 获取驱动器名称
     *
     * @access public
     * @return string
     */
    public function getDriverName()
    {
        return 'Oci';
    }

    /**
     * 获取驱动器实例
     *
     * @return resource
     */
    public function getInstance()
    {
        return $this->_instance;
    }

    /**
     * 通用执行调用
     *
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    private function _query(array $data)
    {
        $this->_instance->send(json_encode($data));
        $result = json_decode($this->_instance->recv(), true);
        if (!$result['status']) {
            throw new Exception($result['message']);
        }
        return $result['result'];
    }

}
<?php

namespace Dark\Pool\Driver;

use Dark\Toolkit\Database;
use Dark\Contact\IDatabase;

class Oci implements IDatabase
{

    /**
     * 连接对象实例
     *
     * @access private
     * @var resource
     */
    private $_instance;

    /**
     * 数据库取值模式
     *
     * @access private
     * @var string
     */
    private $_fetch_mode = OCI_ASSOC;

    /**
     * 事务提交模式
     *
     * @access private
     * @var int
     */
    private $_commit_mode = OCI_COMMIT_ON_SUCCESS;

    /**
     * 最近一次执行SQL语句所影响的行数
     *
     * @access private
     * @var integer
     */
    private $_row_count = 0;

    /**
     * 构造器
     *
     * @param $dsn
     * @param array $option
     * @throws Exception
     */
    public function __construct($dsn, array $option = array())
    {
        if (!function_exists('oci_connect')) {
            throw new Exception('无法加载oci8扩展');
        }
        $database = Database::parseConnectUrl($dsn);
        $dbstring = $database['host'];
        if ($database['host'] && $database['name']) {
            $database['port'] || $database['port'] = 1521;
            $dbstring = '//'. $database['host']. ':'. $database['port']. '/'. $database['name'];
        }
        //选择连接函数
        $func = 'oci_new_connect';
        $list_func = array(
            'default'   => 'oci_connect',
            'new'       => 'oci_new_connect',
            'persisent' => 'oci_pconnect',
        );
        if ($option['type'] && isset($list_func[$option['type']])) {
            $func = $list_func[$option['type']];
        }
        $this->_instance = $func($database['user'], $database['pass'], $dbstring, $database['charset']);
        if (!$this->_instance) {
            throw new Exception('数据库连接失败');
        }
    }

    /**
     * 执行一条SQL语句，并返回结果记录源
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     * @throws Exception
     */
    public function _query($sql, array $bind = array())
    {
        is_string($sql) || $sql = (string)$sql;
        $resource = oci_parse($this->_instance, $sql);
        if (!$resource) {
            throw new Exception('解析SQL语句失败');
        }
        if ($bind) {
            foreach ($bind as $key=> &$val) {
                oci_bind_by_name($resource, $key, $val, empty($val) ? 255 : strlen($val));
            }
        }
        $result = oci_execute($resource, $this->_commit_mode);
        $this->_catch($resource, $sql);
        $this->_row_count = $result ? oci_num_rows($resource) : 0;
        return $resource;
    }

    /**
     * 执行操作，返回影响行数
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return int
     */
    public function query($sql, array $bind = array())
    {
        $this->_query($sql, $bind);
        return $this->lastRowCount();
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
        $resource = $this->_query($sql, $bind);
        $result = array();
        while ($row = oci_fetch_array($resource, $this->_fetch_mode)) {
            $result[] = array_change_key_case($row, CASE_LOWER);
        }
        return $result;
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
        $resource = $this->_query($sql, $bind);
        $result = oci_fetch_row($resource);
        return current($result);
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
        $resource = $this->_query($sql, $bind);
        $result = array_change_key_case(oci_fetch_array($resource, $this->_fetch_mode), CASE_LOWER);
        return $result;
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
        return $this->fetchOne("select $seq.currval from dual");
    }

    /**
     * 取该序列下一条数据的值
     *
     * @access public
     * @param string $seq
     * @return integer
     */
    public function nextInsertId($seq = null)
    {
        return $this->fetchOne("select $seq.nextval from dual");
    }

    /**
     * 返回最近执行的SQL语句所影响到的行数
     *
     * @access public
     * @return integer
     */
    public function lastRowCount()
    {
        return $this->_row_count;
    }

    /**
     * 开始事务处理
     *
     * @access public
     * @return bool
     */
    public function beginTransaction()
    {
        $this->_commit_mode = OCI_NO_AUTO_COMMIT;
        return true;
    }

    /**
     * 提交当前事务
     *
     * @access public
     * @return bool
     */
    public function commit()
    {
        oci_commit($this->_instance);
        $this->_commit_mode = OCI_COMMIT_ON_SUCCESS;
        return true;
    }

    /**
     * 回滚当前事务
     *
     * @access public
     * @return bool
     */
    public function rollback()
    {
        oci_rollback($this->_instance);
        $this->_commit_mode = OCI_COMMIT_ON_SUCCESS;
        return true;
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
     * 捕捉数据库运行时错误
     *
     * @access private
     * @param resource $resource
     * @param string $sql
     * @return null
     * @throws Exception
     */
    private function _catch($resource = null, $sql = null)
    {
        if ($error = oci_error($resource)) {
            throw new Exception($error['message']);
        }
    }

}
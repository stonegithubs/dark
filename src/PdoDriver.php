<?php

namespace Dark;

use Dark\IDriver;

class PdoDriver implements IDriver
{

    /**
     * PDO对象
     *
     * @var PDO
     */
    protected $_instance;

    /**
    * 数据库取值方式
    *
    * @access protected
    * @var string
    */
    protected $_fetch_mode = \PDO::FETCH_ASSOC;

    /**
     * SQL语句执行后影响到的行数
     *
     * @access protected
     * @var integer
     */
    protected $_row_count = 0;

    /**
     * 构造函数
     *
     * @access public
     * @param null $dsn
     * @param null $user
     * @param null $pass
     * @param array $options
     * @throws Exception
     */
	function __construct($dsn = null, $user = null, $pass = null, array $options = array())
	{
		try {
            $_options = array(
                \PDO::ATTR_ERRMODE              => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_ORACLE_NULLS         => \PDO::NULL_EMPTY_STRING,
                \PDO::ATTR_EMULATE_PREPARES     => false,
                \PDO::ATTR_PERSISTENT           => false,
            );
            $options && $_options = array_merge($_options, $options);
            $this->_instance = new \PDO($dsn, $user, $pass, $_options);
		} catch(\PDOException $e) {
			throw new Exception($e->getMessage());
		}
	}

    /**
     * 执行SQL语句并返回一个PDOStatement对象
     *
     * @param string $sql
     * @param array $bind
     * @return \PDOStatement
     * @throws Exception
     */
    protected function _query($sql, $bind = array())
    {
        try {
            is_string($sql) || $sql = (string)$sql;
            $smt = $this->_instance->prepare($sql);
            if ($bind) {
                foreach ($bind as $key=> $val) {
                    is_string($key) || $key += 1;
                    //binParam按引用传递第二个参数,如果此处写$val,则execute执行时,值会全变为最后一次的$val值
                    $smt->bindParam($key, $bind[$key]);
                }
            }
            $smt->execute();
            $smt->setFetchMode($this->_fetch_mode);
            $this->_row_count = $smt->rowCount();
            return $smt;
        } catch(\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 执行一个SQL语句
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return int
     * @throws Exception
     */
    function query($sql, array $bind = array())
    {
        return $this->_query($sql, $bind)->rowCount();
    }

    /**
     * 返回所有数据
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     * @throws Exception
     */
    function fetchAll($sql, array $bind = array())
    {
        return $this->_query($sql, $bind)->fetchAll($this->_fetch_mode);
    }

    /**
     * 返回第一行第一列数据，一般用在聚合函数中
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     * @throws Exception
     */
    function fetchOne($sql, array $bind = array())
    {
        return $this->_query($sql, $bind)->fetchColumn(0);
    }

    /**
     * 返回单行数据
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     * @throws Exception
     */
    function fetch($sql, array $bind = array())
    {
        return $this->_query($sql, $bind)->fetch($this->_fetch_mode);
    }

    /**
     * 开启事务处理
     *
     * @return mixed
     */
    function beginTransaction()
    {
        return $this->_instance->beginTransaction();
    }

    /**
     * 提交事务处理
     *
     * @return mixed
     */
    function commit()
    {
        return $this->_instance->commit();
    }

    /**
     * 回滚事务处理
     *
     * @return mixed
     */
    function rollback()
    {
        return $this->_instance->rollback();
    }

    /**
     * 返回上一次插入的数据ID
     *
     * @access public
     * @param string $seq
     * @return integer
     */
    function lastInsertId($seq = null)
    {
        return $this->_instance->lastInsertId($seq);
    }

	/**
	 * 返回受影响的行数
	 *
	 * @access public
	 * @return integer
	 */
	function lastRowCount()
	{
		return $this->_row_count;
	}

    /**
     * 设置取值模式
     *
     * @param $mode
     * @return $this
     */
    function setFetchMode($mode)
    {
        $this->_fetch_mode = $mode;
        return $this;
    }

    /**
     * 获取PDO对象实例
     *
     * @return Pdo|\PDO
     */
    function getInstance()
    {
        return $this->_instance;
    }

}
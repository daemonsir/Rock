<?php

namespace Rock\Db;
class MysqlPdo
{

    private $_dbhost; // 主机名

    private $_dbuser; // 用户名

    private $_dbpwd; // 用户密码

    private $_database; // 数据库名

    private $_link;

    private $_prepare; // PDOStatement 对象预处理

    private $_charset; // 数据库操作的编码方式

    private $_slave = 0; // 0:未连接数据库 1:固定主 2:事务主 3:普通主 4:从

    /*public function __construct($dbhost, $dbuser, $dbpwd, $dbname, $charset = "utf8") {
         $this->_dbhost = $dbhost;
         $this->_dbuser = $dbuser;
         $this->_dbpwd = $dbpwd;
         $this->_database = $dbname;
         $this->_charset = $charset;
         $this->connectDb();
     } */

    public function __construct ($node)
    {
        global $system_dbserver;
        if (!$node || !isset($system_dbserver[$node])) {
            throw new \Exception('db_error:node does not exist!');
        }
        $this->_node     = $node;
        $this->_database = $system_dbserver[$node]['database'];
    }

    /**
     * @param $dbhost
     * @param $dbuser
     * @param $dbpwd
     * @param string $charset
     * @throws \Exception
     * 连接数据库
     */
    public function connectDb ($dbhost ,$dbuser ,$dbpwd ,$charset = "utf8")
    {
        try {
            $this->_dbhost = $dbhost;
            $this->_dbuser = $dbuser;
            $this->_dbpwd  = $dbpwd;
            //$this->_database = $dbname;
            $this->_charset = $charset;
            $this->_link    = new \PDO('mysql:host=' . $this->_dbhost . ';port=3306;dbname=' . $this->_database ,$this->_dbuser ,$this->_dbpwd ,[
                \PDO::ATTR_PERSISTENT         => false ,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->_charset
            ]);
            $this->_link->setAttribute(\PDO::ATTR_ERRMODE ,\PDO::ERRMODE_EXCEPTION); // 设置错误级别为异常级别
        } catch (\Exception $ex) {
            throw new \Exception("db_error_code_1001:" . $ex->getMessage());
        }
    }

    /**
     * 返回到表集合
     * @param $query
     * @return mixed
     * @throws \Exception
     */
    public function dataTable ($query)
    {
        $this->queryMysql($query);
        return $this->_prepare->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 返回表的一行数据
     *
     * @param array $query
     */
    public function fetchRow ($query)
    {
        $this->queryMysql($query);
        return $this->_prepare->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 执行一条sql语句返回结果
     * 做读写分离处理 同时加入强制主服务器处理
     * @param boolean $mysqlstring
     */
    public function queryMysql ($mysqlstring)
    {
        global $system_dbserver;
        $dbServer    = $system_dbserver[$this->_node];
        $slaveLen    = count($dbServer['slave']); //从库个数
        $mysqlstring = trim($mysqlstring);
        $sql         = strtolower($mysqlstring);
        //$sql = $sql." /*master*/"; //强制主库
        if (preg_match('/^\/\*master\*\//' ,$sql) || preg_match('/^select\s+\/\*master\*\//' ,$sql)) {//强制主库
            $sqlType = 'master';
        } else if (preg_match('/^([a-z]+)\s*/' ,$sql ,$matches)) {
            $sqlType = $matches[1];
        } else {
            $sqlType = '';
        }

        if ($this->_slave == 0) {//未连接数据库
            if ($slaveLen == 0 || $sqlType != 'select') {//连接主库
                $db = $dbServer['master'];
                $this->connectDb($db['host'] ,$db['user'] ,$db['pwd'] ,$db['charset']);
                if ($slaveLen == 0) {//固定主库连接
                    $this->_slave = 1;
                } else if ($sqlType == 'begin' || $sqlType == 'start') {//事务主库连接
                    $this->_slave = 2;
                } else {//普通主库连接
                    $this->_slave = 3;
                }
            } else {//连接从库
                $key = rand(0 ,$slaveLen - 1);
                $db  = $dbServer['slave'][$key];
                $this->connectDb($db['host'] ,$db['user'] ,$db['pwd'] ,$db['charset']);
                $this->_slave = 4;
            }
        } else if ($this->_slave == 2) {//事务主库
            if ($sqlType == 'commit' || $sqlType == 'rollback') {
                $this->_slave = 3;
            }
        } else if ($this->_slave == 3) {//普通主库
            if ($sqlType == 'select') {//由主库切换为从库
                $key = rand(0 ,$slaveLen - 1);
                $db  = $dbServer['slave'][$key];
                $this->connectDb($db['host'] ,$db['user'] ,$db['pwd'] ,$db['charset']);
                $this->_slave = 4;
            } else if ($sqlType == 'begin' || $sqlType == 'start') {//事务主
                $this->_slave = 2;
            }
        } else if ($this->_slave == 4) {//从库
            if ($sqlType != 'select') {//连接主库
                $db = $dbServer['master'];
                $this->connectDb($db['host'] ,$db['user'] ,$db['pwd'] ,$db['charset']);
                if ($sqlType == 'begin' || $sqlType == 'start') {
                    $this->_slave = 2;
                } else {
                    $this->_slave = 3;
                }
            }
        }

        try {
            $this->_prepare = $this->_link->prepare($mysqlstring);
            $res            = $this->_prepare->execute(); // 添加条件数据
            return $res;
        } catch (\PDOException $ex) {
            throw new \Exception("db_error_code_1003:" . $ex->getMessage() . ", the sql is :" . $mysqlstring);
        }
    }

    /**
     * 返回影响行数 (select 除外)
     *
     * @return int
     */
    function getAffectedCount ()
    {
        return $this->_prepare->rowCount();
    }

    /**
     * 执行sql语句并返回影响行数 (select 除外)
     *
     * @param string $query
     * @return int
     */
    public function queryAffectedCount ($query)
    {
        try {
            return $this->_link->exec($query);
        } catch (\PDOException $ex) {
            throw new \Exception("db_error_code_1003:" . $ex->getMessage() . ", the sql is :" . $query);
        }
    }

    /**
     * 取得上一步 INSERT 操作产生的 ID
     *
     * @return int
     */
    public function insertId ()
    {
        return $this->_link->lastInsertId();
    }

    /**
     * 返回错误代码
     *
     * @return int
     */
    public function errorNo ()
    {
        return $this->_link->errorCode();
    }

    /**
     * 关闭连接释放资源
     */
    public function mysqlClose ()
    {
        unset($this->_link);
    }
}
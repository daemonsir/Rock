<?php
namespace Rock\Db;
class DBMysql
{
    private $_dbhost; //主机名
    private $_dbuser;//用户名
    private $_dbpwd; //用户密码
    private $_database ; //数据库名
    private $_link;
    private $_charset; //数据库操作的编码方式

    /**
     * 构造函数，初始化数据，并创建连接
     *
     * @param string $dbhost
     * @param string $dbuser
     * @param string $dbpwd
     * @param string $dbname
     * @return DBMysql
     */
    function DBMysql($dbhost,$dbuser,$dbpwd,$dbname,$charset="utf8")
    {
        $this->_dbhost=$dbhost;
        $this->_dbuser=$dbuser;
        $this->_dbpwd=$dbpwd;
        $this->_database=$dbname;
        $this->_charset=$charset;
        $this->connectDb();
    }
    /**
     * 连接数据库
     *
     */
    function connectDb(){
        $this->_link = mysql_connect($this->_dbhost,$this->_dbuser,$this->_dbpwd);
        if(!$this->_link){
            throw new Exception("db_error_code_1001:".mysql_error($this->_link));
        }
        mysql_query("SET NAMES $this->_charset");
    }
    /**
     * 链接数据库
     *
     */
    function setLink(){
        $this->_link = mysql_connect($this->_dbhost,$this->_dbuser,$this->_dbpwd);
        if(!$this->_link){
            throw new Exception("db_error_code_1001:".mysql_error($this->_link));
        }
    }
    /**
     * 选择数据库
     *
     */
    function selectDb(){
        if(!mysql_select_db($this->_database,$this->_link)){
            throw new Exception("db_error_code_1002:".mysql_error($this->_link));
        }
    }
    /**
     * 返回到表集合
     *
     * @param query $query
     * @return array
     */
    function dataTable($query)
    {
        if($query){
            $ListTable=array();
            while($rows=mysql_fetch_array($query,MYSQL_ASSOC))
            {
                array_push($ListTable,$rows);
            }
            return $ListTable;
        }
        else{
            return 0;
        }
    }
    /**
     * 返回资源
     *
     * @param string $mysqlstring
     * @return resource
     */
    function queryMysql($mysqlstring)
    {
        $this->selectDb();
        $result=mysql_query($mysqlstring,$this->_link);
        if(!$result){
            throw new Exception("db_error_code_1003:".mysql_error($this->_link).", the sql is :".$mysqlstring);
        }
        return $result;
    }
    /**
     * 返回结果集行数
     *
     * @param resource  $query
     * @return int
     */
    function getCount($query)
    {
        return mysql_num_rows($query);
    }
    /**
     * 返回影响行数
     *
     * @return int
     */
    function getAffectedCount()
    {
        return mysql_affected_rows($this->_link);
    }
    /**
     * 从结果集中取得一行作为枚举数组
     *
     * @param resource $query
     * @return array
     */
    function fetchRow($query)
    {
        return mysql_fetch_array($query,MYSQL_BOTH);
    }
    /**
     * 取得结果数据
     *
     * @param resource $query
     * @param array $row
     * @return unknown
     */
    function result($query, $row)
    {
        return @mysql_result($query, $row);
    }
    /**
     * 取得结果集中字段的数目
     *
     * @param resource $query
     * @return int
     */
    function numFields($query)
    {
        return mysql_num_fields($query);
    }
    /**
     * 返回错误代码
     *
     * @return int
     */
    function errorNo(){
        return mysql_errno($this->_link);
    }
    /**
     * 从结果集中取得列信息并作为对象返回
     *
     * @param resource $query
     * @return object
     */
    function fetchFields($query)
    {
        return mysql_fetch_field($query);
    }
    /**
     * 取得上一步 INSERT 操作产生的 ID
     *
     * @return int
     */
    function insertId()
    {
        return @mysql_insert_id($this->_link);
    }
    /**
     * 获得版本号
     *
     * @return string
     */
    function version() {
        return mysql_get_server_info($this->_link);
    }
    /**
     * 关闭连接释放资源
     *
     */
    function mysqlClose()
    {
        mysql_close($this->_link);
        mysql_free_result($this->_link);
    }
}
?>
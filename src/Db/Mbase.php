<?php

namespace Rock\Db;
class Mbase
{
    private $_tablename;/*表明 */
    private $_dbhandler;/*链接对象 */
    private $_fields = "*";/*字段 */
    /**
     * 构造函数
     *
     * @param dbhandler $dbhandler
     * @param string $tablename
     * @return mbase
     */
    function mbase ($dbhandler ,$tablename)
    {
        $this->_tablename = $tablename;
        $this->_dbhandler = $dbhandler;
    }

    /**
     * 查询表的一行值
     *
     * @param array $options 格式为 array("0"=>"fields","1"=>"primarykey","2"=>"keyvalue")
     * @return array
     */
    public function tableRow (array $options)
    {
        $sql = $this->parseOptions($options ,"row");
        return $this->_dbhandler->selectSqlRow($sql);
    }

    /**
     * 查询表数据，返回数组
     *
     * @param array $options 格式 array("fields"=>"*","where"=>"1=1","groupby"=>"","orderby"=>"","limit"=>"")任意一列都可以不写
     * @return array
     */
    public function tableList (array $options = [])
    {
        $sql = $this->parseOptions($options ,"select");
        return $this->_dbhandler->selectSqlTable($sql);
    }

    /**
     * 多表链接查询，返回数组
     *
     * @param array $options 格式 array("fields"=>"*","join"=>"left|right|cross|natural","tables"=>"表名1 as 别名1,表名2 as 别名2 ...","conditions"=>"","where"=>" 1=1 ","groupby"=>"","orderby"=>"","limit"=>"") where,groupby,orderby,limit可以不写
     * @return array
     */

    public function joinInternal ($options = [])
    {
        $sql = $this->parseOptions($options ,"join");
        return $this->_dbhandler->selectSqlTable($sql);
    }

    /**
     * 插入一条数据
     * @param array $options 格式为 array(0=>array("fields"=>"value"),1=>array("field"=>0,"field"=>1....))
     * @return int
     */
    public function tableAdd (array $options)
    {
        $sql = $this->parseOptions($options ,"add");
        $this->beforeAdd($options);
        $flag = $this->_dbhandler->insertRow($sql);
        $this->afterAdd($flag);
        return $flag;
    }

    /**
     * 插入替换一条数据
     * @param array $options 格式为 array(0=>array("fields"=>"value"),1=>array("field"=>0,"field"=>1....))
     * @return bool
     */
    public function tableReplaceAdd (array $options)
    {
        $sql = $this->parseOptions($options ,"replace");
        $this->beforeUpdate($options);
        $flag = $this->_dbhandler->querySql($sql);
        $this->afterUpdate($flag);
        return $flag;
    }

    //添加之前回调函数
    public function beforeAdd ($options)
    {
    }

    //添加之后回调函数
    public function afterAdd ($flag)
    {
    }

    /**
     * 删除数据
     *
     * @param array $options 格式为 array("where");
     * @return boolean
     */
    public function tableDelete (array $options)
    {
        $sql = $this->parseOptions($options ,"delete");
        $this->beforeDelete($options);
        $flag = $this->_dbhandler->querySql($sql);
        $this->afterDelete($flag);
        return $flag;
    }

    //删除之前回调函数
    public function beforeDelete (array $options)
    {
    }

    //删除之后回调函数
    public function afterDelete ($flag)
    {
    }

    /**
     * 更新数据
     *
     * @param array $options array("0"=>array("where"=>"value"),"1"=>array("files"=>"value"),"2"=>array("field"=>0,"field"=>1....))
     * @return boolean
     */
    public function tableUpdate (array $options)
    {
        $sql = $this->parseOptions($options ,"update");
        $this->beforeUpdate($options);
        $flag = $this->_dbhandler->querySql($sql);
        $this->afterUpdate($flag);
        return $flag;
    }

    //更新之前回调函数
    public function beforeUpdate (array $options)
    {
    }

    //更新之后回调函数
    public function afterUpdate ($flag)
    {
    }

    /**
     * 把拼装数组转换成字符串
     *
     * @param array $options
     * @param string $type
     * @return string
     */
    private function parseOptions (array $options = [] ,$type)
    {
        $sqlStr = "";
        if ($type == "row") {
            $sqlStr = "select " . $options[0] . " from " . $this->_tablename . " where " . $options[1] . " ='" . $options[2] . "'";
        }
        if ($type == "select") {
            $keys    = array_keys($options);
            $values  = array_values($options);
            $keyslen = count($keys);
            $fields  = null;
            $join    = null;
            $where   = null;
            $groupby = null;
            $having  = null;
            $orderby = null;
            $limit   = null;
            for ($i = 0; $i < $keyslen; $i++) {
                if ($keys[$i] == "fields") {
                    $fields = $values[$i];
                } else if ($keys[$i] == "where") {
                    $where = " where " . $values[$i];
                } else if ($keys[$i] == "groupby") {
                    $groupby = " group by " . $values[$i];
                } else if ($keys[$i] == "having") {
                    $having = " having " . $values[$i];
                } else if ($keys[$i] == "orderby") {
                    $orderby = " order by " . $values[$i];
                } else if ($keys[$i] == "limit") {
                    $limit = " limit " . $values[$i];
                }
            }
            $fields == null ? "*" : $fields;
            $sqlStr = "select " . $fields . " from " . $this->_tablename . $where . $groupby . $having . $orderby . $limit;
        } else if ($type == "add") {
            $keys        = array_keys($options[0]);
            $values      = array_values($options[0]);
            $len         = count($options[0]);
            $i           = 0;
            $fields      = "";
            $filedsvalue = "";
            for ($i; $i < $len; $i++) {
                $fields .= "`" . $keys[$i] . "`";
                if ($options[1][$keys[$i]] == 1) {
                    $filedsvalue .= "'" . $values[$i] . "'";
                } else {
                    $filedsvalue .= $values[$i];
                }
                if ($i != $len - 1) {
                    $fields      .= ",";
                    $filedsvalue .= ",";
                }
            }
            $sqlStr = "insert into " . $this->_tablename . "(" . $fields . ") values(" . $filedsvalue . ")";
        } else if ($type == "replace") {
            $keys        = array_keys($options[0]);
            $values      = array_values($options[0]);
            $len         = count($options[0]);
            $i           = 0;
            $fields      = "";
            $filedsvalue = "";
            for ($i; $i < $len; $i++) {
                $fields .= "`" . $keys[$i] . "`";
                if ($options[1][$keys[$i]] == 1) {
                    $filedsvalue .= "'" . $values[$i] . "'";
                } else {
                    $filedsvalue .= $values[$i];
                }
                if ($i != $len - 1) {
                    $fields      .= ",";
                    $filedsvalue .= ",";
                }
            }
            $sqlStr = "replace into " . $this->_tablename . "(" . $fields . ") values(" . $filedsvalue . ")";
        } else if ($type == "update") {
            $keys   = array_keys($options[1]);
            $values = array_values($options[1]);
            $len    = count($options[1]);
            $i      = 0;
            $sqlStr .= "update " . $this->_tablename . " set ";
            for ($i; $i < $len; $i++) {
                if ($options[2][$keys[$i]] == 1) {
                    $sqlStr .= "`" . $keys[$i] . "`='" . $values[$i] . "'";
                } else {
                    $sqlStr .= "`" . $keys[$i] . "`=" . $values[$i];
                }

                if ($i != $len - 1)
                    $sqlStr .= ",";
            }
            $sqlStr .= " where " . $options[0]["where"];
        } else if ($type == "delete") {
            $wheresql = $options[0];
            $sqlStr   = "delete from " . $this->_tablename . " where " . $wheresql;
        } else if ($type == "join") {
            $keys    = array_keys($options);
            $values  = array_values($options);
            $keyslen = count($keys);
            $fields  = null;
            $join    = null;
            $where   = null;
            $groupby = null;
            $having  = null;
            $orderby = null;
            $limit   = null;
            for ($i = 0; $i < $keyslen; $i++) {
                if ($keys[$i] == "fields") {
                    $fields = $values[$i];
                } else if ($keys[$i] == "where") {
                    $where = " where " . $values[$i];
                } else if ($keys[$i] == "groupby") {
                    $groupby = " group by " . $values[$i];
                } else if ($keys[$i] == "having") {
                    $having = " having " . $values[$i];
                } else if ($keys[$i] == "orderby") {
                    $orderby = " order by " . $values[$i];
                } else if ($keys[$i] == "limit") {
                    $limit = " limit " . $values[$i];
                }
            }
            $fields == null ? "*" : $fields;
            $join       = " " . $options["join"] . " join ";
            $tables     = $options["tables"];
            $conditions = $options["conditions"];

            $sqlStr = "select " . $fields . " from " . $this->_tablename . $join . " (" . $tables . ") on " . $conditions . $where . $groupby . $having . $orderby . $limit;
        }
        return $sqlStr;
    }
}

?>
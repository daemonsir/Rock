<?php

namespace Rock\Db;

class Pdohandler extends MysqlPdo
{
    /**
     * 删除指定表中指定记录 condition参数条件中需要带and或or
     *
     * @param string $tableName
     * @param string $IDName
     * @param string $IDValue
     * @param string $condition
     * @return bool
     */
    function deleteRow ($tableName ,$IDName ,$IDValue ,$condition = null)
    {
        $sql = "delete from $tableName where $IDName='$IDValue'";
        if ($condition != null) {
            $sql .= " " . $condition;
        }
        return $this->queryMysql($sql);

    }

    /**
     * 执行sql语句，返回true与false
     *
     * @param string $sql
     * @return boolean
     */
    function querySql ($sql)
    {
        $this->queryMysql($sql);
        if ($this->getAffectedCount() > 0 || $this->errorNo() == 0)
            return true;
        else
            return false;
    }

    /**
     * 查找指定表中指定的记录 带条件
     *
     * @param string $tableName
     * @param string $IDName
     * @param string $IDValue
     * @param string $fileds
     * @param string $condition
     * @return array
     */
    function selectRow ($tableName ,$IDName ,$IDValue ,$fileds = '*' ,$condition = null)
    {
        $sql = "select $fileds from $tableName where $IDName= '$IDValue'";
        if ($condition != null) {
            $sql .= " " . $condition;
        }
        return $this->fetchRow($sql);
    }

    /**
     * 直接传递数据库查询语句返回一行数据
     *
     * @param string $sql
     * @return array
     */
    function selectSqlRow ($sql)
    {
        return $this->fetchRow($sql);
    }

    /**
     * 查找指定表的中指定的记录结果集
     *
     * @param string $tableName
     * @param string $startrow
     * @param string $fetchrow
     * @param string $fileds
     * @param string $condition
     * @return array
     */
    function selectTable ($tableName ,$fileds = '*' ,$startrow = 0 ,$fetchrow = 0 ,$condition = null)
    {
        $table = [];
        $sql   = "select $fileds from $tableName";
        if ($condition != null) {
            $sql .= " " . $condition;
        }
        if ($startrow > 0 && $fetchrow == 0) {
            $sql .= " limit $startrow";
        } else if ($startrow >= 0 && $fetchrow > 0) {
            $sql .= " limit $startrow ,$fetchrow ";
        }
        return $this->dataTable($sql);
    }

    /**
     *  查找指定查询语句返回的记录结果集
     *
     * @param string $sql
     * @return array
     */
    function selectSqlTable ($sql)
    {
        return $this->dataTable($sql);
    }

    /**
     * 获得指定表的数据总行数。condition为条件。需要带where
     *
     * @param string $tableName
     * @param string $condition
     * @return int
     */
    function selectRowsNum ($tableName ,$condition = null)
    {
        $sql = "select count(1) from {$tableName} ";
        if ($condition != null) {
            $sql .= " " . $condition;
        }
        return $this->selectScan($sql);
    }

    /**
     * 获得查询的第一行第一列的值
     *
     * @param string $sql
     * @return string
     */
    function selectScan ($sql)
    {
        $row = $this->fetchRow($sql);
        if ($row != null && $row != "")
            return current($row);
        else
            return 0;
    }

    /**
     * 插入一条记录并获得插入的ID值
     *
     * @param string $sql
     * @return int
     */
    function insertRow ($sql)
    {
        $this->queryMysql($sql);
        return $this->insertId();
    }
}

?>
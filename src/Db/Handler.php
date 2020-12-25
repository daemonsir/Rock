<?php

namespace Rock\Db;

class Handler extends Mysql
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
        $this->queryMysql($sql);
        if ($this->getAffectedCount() > 0)
            return true;
        else
            return false;
    }

    /**
     * 执行sql语句，返回表中是否有记录受到影响
     *
     * @param string $sql
     * @return bool
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
        $rel = $this->queryMysql($sql);
        return $this->fetchRow($rel);
    }

    /**
     * 直接传递数据库查询语句返回一行数据
     *
     * @param string $sql
     * @return array
     */
    function selectSqlRow ($sql)
    {
        $rel = $this->queryMysql($sql);
        return $this->fetchRow($rel);
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
        $rel = $this->queryMysql($sql);
        return $this->dataTable($rel);
    }

    /**
     *  查找指定查询语句返回的记录结果集
     *
     * @param string $sql
     * @return array
     */
    function selectSqlTable ($sql)
    {
        $rel = $this->queryMysql($sql);
        return $this->dataTable($rel);
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
        $sql = "select 1 from {$tableName} ";
        if ($condition != null) {
            $sql .= " " . $condition;
        }
        $rel = $this->queryMysql($sql);
        return $this->getCount($rel);
    }

    /**
     * 获得指定表的数据总行数 sql语句
     *
     * @param string $sql
     * @return int
     */
    function selectSqlRowsNum ($sql)
    {
        $rel = $this->queryMysql($sql);
        return $this->getCount($rel);
    }

    /**
     * 获得查询的第一行第一列的值
     *
     * @param string $sql
     * @return string
     */
    function selectScan ($sql)
    {
        $rel = $this->queryMysql($sql);
        echo $sql;
        $row = $this->fetchRow($rel);
        if ($row != null && $row != "")
            return $row[0];
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
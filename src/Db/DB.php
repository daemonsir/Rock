<?php
namespace Rock\Db;
!defined('_DEFAULT_DB_NODE_') && define('_DEFAULT_DB_NODE_', 'jygp');//默认数据库连接节点
!defined('_DEFAULT_DB_SEPARATOR_') && define('_DEFAULT_DB_SEPARATOR_', '_');//默认表分隔符 _
class DB {
    public static $_langdbs = [];
    private static $_tableHandlers = [];//对象集合
    private $_langdb = null;
    private $_fullTableName;//表全名
    private $_tableColumnsInfo;//表字段信息
    public $_columnsValues;//动态设置字段=》字段值集合
    public $_joins = '';
    public $_bindings = [
        'where' => '',
        'group by' => '',
        'having' => '',
        'order by' => '',
        'limit' => '',
    ];
    public $_bindingsTemp = [
        'where' => '',
        'group by' => '',
        'having' => '',
        'order by' => '',
        'limit' => '',
    ];

    /**
     * 构造函数
     */
    private function __construct($tableName, $dbNode){
        $this->_langdb = self::dbLangConnect($dbNode);
        $this->_fullTableName = $this->_langdb->_prefix._DEFAULT_DB_SEPARATOR_.$tableName;
        $columnsSql = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA="'.$this->_langdb->_database.'" AND TABLE_NAME="'.$this->_fullTableName.'"';
        $columnsList = $this->_langdb->selectSqlTable($columnsSql);
        $this->_tableColumnsInfo = array_column($columnsList, null, 'COLUMN_NAME');//以字段名为key的字段信息集合
    }

    /**
     * 创建数据连接 去除重复创建
     */
    public static function dbLangConnect($dbNode = _DEFAULT_DB_NODE_){
        if (!isset(self::$_langdbs[$dbNode])) {
            try {
                self::$_langdbs[$dbNode] = new \Rock\Db\Pdohandler($dbNode);
            } catch (Exception $e) {
                throw $e;
            }
        }
        return self::$_langdbs[$dbNode];
    }

    /**
     * 实例化表操作对象
     * @param $tableName string 表名
     * @param $dbNode string 表前缀
     * @return DB
     * @使用 DB::table('tableName')
     */
    public static function table($tableName, $dbNode = _DEFAULT_DB_NODE_){
        if (!isset(self::$_tableHandlers[$dbNode])) {
            self::$_tableHandlers[$dbNode] = [];
        }
        if (!isset(self::$_tableHandlers[$dbNode][$tableName])) {//如果表对象没有实例化，则实例化表对象
            self::$_tableHandlers[$dbNode][$tableName] = new DB($tableName, $dbNode);
        }
        self::$_tableHandlers[$dbNode][$tableName]->_columnsValues = [];//每次调用DB::table('tableName')清空字段=》字段值集合
        return self::$_tableHandlers[$dbNode][$tableName];
    }

    /**
     * 执行sql语句
     * @param $sql string sql脚本
     * @return boolean
     * @使用 DB::query($sql)
     */
    public static function query($sql, $dbNode = _DEFAULT_DB_NODE_){
        return self::dbLangConnect($dbNode)->querySql($sql);
    }

    /**
     * 根据sql查询所有数据 （结果以二维数据返回）
     * @param $sql string sql脚本
     * @return array
     * @使用 DB::select($sql)
     */
    public static function select($sql, $dbNode = _DEFAULT_DB_NODE_){
        return self::dbLangConnect($dbNode)->selectSqlTable($sql);
    }

    /**
     * 根据sql查询记录数
     * @param $sql string sql脚本
     * @return int
     * @使用 DB::selectRowNum($sql)
     */
    public static function selectRowNum($sql, $dbNode = _DEFAULT_DB_NODE_){
        return self::dbLangConnect($dbNode)->selectSqlRowsNum($sql);
    }

    /**
     * 获取一个数据库链接最近一次执行的sql
     * @return int
     * @使用 DB::getLatelySql()
     */
    public static function getLatelySql($dbNode = _DEFAULT_DB_NODE_){
        return self::dbLangConnect($dbNode)->_sql;
    }

    /**
     * 设置where条件 可以多次调用
     * @param 最少1个参数，最多4个参数
     * @return DB
     * @使用：
     * DB::table('tableName')->where('Id<10 AND Isvalid=1 ORDER BY Id DESC')//直接设置where条件
     * DB::table('tableName')->where('Id', 1)->where('Isvalid', 1)//拼接where条件 AND Id=1 默认'=' 'and'
     * DB::table('tableName')->where('Id', 1, 'or')//拼接where条件 or Id=1
     * DB::table('tableName')->where('Id', '>', 1)//拼接where条件 AND Id>1
     * DB::table('tableName')->where('Id', '>', 1, 'or')//拼接where条件 or Id>1
     * DB::table('tableName')->where('Status', 'in', [1,2,3])//拼接where条件 AND Status in(1,2,3)
     * DB::table('tableName')->where('Status', 'in', '1,2,3')//拼接where条件 AND Status in(1,2,3)
     */
    public function where(){
        $argsNum = func_num_args();
        $argsList = func_get_args();
        if ($argsNum > 0) {//最后一个参数是or/and where条件连接已改参数决定，否则默认and
            $argsLastItem = $argsList[$argsNum-1];
            if (is_array($argsLastItem)) {
                $argsLastItem = '';
            } else {
                $argsLastItem = trim(strtolower((string) $argsLastItem));
            }
            if ($argsLastItem == 'or') {
                $logic = ' OR ';
                $argsNum -= 1;
            } else if ($argsLastItem == 'and') {
                $logic = ' AND ';
                $argsNum -= 1;
            } else {
                $logic = ' AND ';
            }
        }
        if ($argsNum >= 3) {//有3个参数或4个参数（最后一个是or/and） 条件为 args1.args2.args3
            list($key, $operate, $value) = $argsList;
        } else if ($argsNum == 2) {//有2个参数或3个参数（最后一个是or/and）条件为 args1=args3
            list($key, $value) = $argsList;
            $operate = '=';
        } else if (isset($argsList[0])) {
            $this->_bindings['where'] = $argsList[0];
        }

        if ($argsNum >= 2) {
            $operateLower = strtolower(trim($operate));
            if ($operateLower == 'in' || $operateLower == 'not in') {
                $quoteRequired = $this->checkColumnQuoteRequired($key);
                if (!is_array($value)) {
                    $value = [$value];
                }
                if ($quoteRequired !== false) {
                    $queryValue = '("'.implode('", "', $value).'")';
                } else {
                    $queryValue = '('.implode(', ', $value).')';
                }
            } else {
                $quoteRequired = $this->checkColumnQuoteRequired($key, $value);
                if ($quoteRequired !== false) {
                    $queryValue = '"'.$this->fieldEscape($value).'"';
                } else {
                    $queryValue = $value.'';
                }
            }
            $operate = ' '.$operate.' ';
            if ($this->_bindings['where'] == '') {
                $this->_bindings['where'] .= $key.$operate.$queryValue;
            } else {
                $this->_bindings['where'] .= $logic.$key.$operate.$queryValue;
            }
        }
        return $this;
    }

    /**
     * 拼接where字符串
     * @param $str string 拼接的字符串
     * @return DB
     * @使用：
     * DB::table('tableName')->where('Id', '>', 1)->joinWhere(' and (Isvalid=1))')//结果"Id>1 and (Isvalid=1)"
     */
    public function joinWhere($str){
        $this->_bindings['where'] .= ' '.$str;
        return $this;
    }

    /**
     * 设置 "group by"
     * @param $value string 分组字段名
     * @return DB
     */
    public function groupBy($value){
        if ($value != '') {
            $this->_bindings['group by'] = $value;
        }
        return $this;
    }

    /**
     * 设置 "having"
     * @param $value string
     * @return DB
     */
    public function having($value){
        if ($value != '') {
            $this->_bindings['having'] = $value;
        }
        return $this;
    }

    /**
     * 设置 "order by"
     * @param $column string 字段名
     * @param $direction string 排序规则 asc/desc
     * @return DB
     * @使用：
     * DB::table('tableName')->orderBy('Id')//order by Id asc
     * DB::table('tableName')->orderBy('Id')->orderBy('AddTime', 'desc')//order by Id asc,AddTime desc
     */
    public function orderBy($column, $direction = 'asc'){
        $direction = strtoupper($direction);
        if ($direction != 'DESC') $direction = 'ASC';
        if ($this->_bindings['order by'] == '') {
            $this->_bindings['order by'] = $column.' '.$direction;
        } else {
            $this->_bindings['order by'] .= ','.$column.' '.$direction;
        }
        return $this;
    }

    /**
     * 设置 "limit"
     * @param 最少1个参数，最多2个参数
     * @return DB
     * @使用：
     * DB::table('tableName')->limit(10)//limit 10
     * DB::table('tableName')->limit(0, 10)//limit 0,10
     */
    public function limit(){
        $argsNum = func_num_args();
        $argsList = func_get_args();
        if ($argsNum >= 2) {
            list($start, $end) = $argsList;
            $start = intval($start) < 0 || $start > 10000000 ? 0 : intval($start);
            $end = intval($end) <= 0 || $end > 10000000 ? 1 : intval($end);
            $this->_bindings['limit'] = "$start,$end";
        } else {
            if (isset($argsList[0])) {
                $this->_bindings['limit'] = intval($argsList[0]) <= 0 || $argsList[0] >10000000 ? 1 : intval($argsList[0]);
            }
        }
        return $this;
    }

    /**
     * 魔术方法，调用不能调用的方法时自动调用该方法
     * @param $method string 方法名
     * @param $arguments array 参数数组
     * @return mixed
     * @使用：
     * DB::table('tableName')->setId(1) return obj
     * DB::table('tableName')->getId() return mixed
     * DB::table('tableName as t')->leftJoin($joinTable, $conditions) return obj
     * DB::table('tableName as t')->rightJoin($joinTable, $conditions) return obj
     * DB::table('tableName as t')->innerJoin($joinTable, $conditions) return obj
     * DB::table('usersface as uf')->where('uf.Username', 'in', 'tl')->leftJoin('users as u', 'uf.UserId=u.UserId')->lists('uf.Username,u.Mobile')
     */
    public function __call($method, $arguments){
        if (preg_match('/^(set|get)(.*)$/i', $method, $matches)) {
            $type = $matches[1];
            $name = $matches[2];
            if ($type === 'set') {//设置字段
                $value = isset($arguments[0])?$arguments[0]:'';
                $this->_columnsValues[$name] = $value;
                return $this;
            } else {//获取字段
                if (isset($this->_columnsValues[$name])) {
                    return $this->_columnsValues[$name];
                } else {
                    return null;
                }
            }
        } else if (preg_match('/^(.*)join$/i', $method, $matches)) {
            $joinSql = '';
            $joinType = strtolower($matches[1]);
            $joinTable = isset($arguments[0])?$arguments[0]:'';
            $conditions = isset($arguments[1])?$arguments[1]:'';
            $joinTable = $this->_langdb->_prefix._DEFAULT_DB_SEPARATOR_.$joinTable;
            if ($joinType == '' || $joinType == 'inner') {
                $joinSql = ' INNER JOIN '.$joinTable.' ON '.$conditions;
            } else if ($joinType == 'left') {
                $joinSql = ' LEFT JOIN '.$joinTable.' ON '.$conditions;
            } else if ($joinType == 'right') {
                $joinSql = ' RIGHT JOIN '.$joinTable.' ON '.$conditions;
            }
            $this->_joins .= $joinSql;
            return $this;
        } else {
            throw new Exception('method not found');
        }
    }

    /**
     * 添加数据
     * @param $columnsValues array 插入字段 名=》值
     * @param $insertId int 插入数据Id
     * @return boolean
     * @说明：
     * 可以通过set和传参$columnsValues设置字段值，如果$columnsValues设置的字段和set相同会覆盖之前设置的值
     * 执行之后where、group by、order by、limit会清空
     * 执行之后设置的字段不会清除，使用DB::table('tableName')重新实例化，字段会清除
     * @使用：
     * DB::table('tableName')->setId(1)->insert()
     * DB::table('tableName')->insert(['Id' => 1])
     * DB::table('tableName')->setId(1)->insert(['Name' => '名称'])
     */
    public function insert($columnsValues = [], &$insertId = 0){
        $this->_columnsValues = array_merge($this->_columnsValues, $columnsValues);
        $columnsNames = $columnsValues = '';
        foreach ($this->_columnsValues as $name=>$value) {
            $quoteRequired = $this->checkColumnQuoteRequired($name, $value);
            if ($quoteRequired === true) {//需要加引号
                $columnsNames .= $name.',';
                $columnsValues .= '"'.$this->fieldEscape($value).'",';
            } else if ($quoteRequired === false) {//不需要加引号
                $columnsNames .= $name.',';
                $columnsValues .= $value.',';
            }
        }
        $columnsNames = rtrim($columnsNames, ',');
        $columnsValues = rtrim($columnsValues, ',');
        $this->cleanBindings();
        if ($columnsNames == '') {
            return false;
        } else {
            $sql = 'INSERT INTO '.$this->_fullTableName.' ('.$columnsNames.') VALUES ('.$columnsValues.')';
            $result = $this->_langdb->querySql($sql);
            $insertId = $this->_langdb->insertId();
            return $result;
        }
    }

    /**
     * 删除数据
     * @return boolean
     * @说明：
     * 可以通过where设置删除条件
     * 执行之后where、group by、order by、limit会清空
     * @使用：
     * DB::table('tableName')->where('Id', 1)->delete()
     */
    public function delete(){
        $bindings = $this->getBindings();
        $this->cleanBindings();
        if (stripos($bindings, 'WHERE') === false) {//不包含where条件不能删除数据，防止误删
            return false;
        } else {
            $sql = 'DELETE FROM '.$this->_fullTableName.$bindings;
            return $this->_langdb->querySql($sql);
        }
    }

    /**
     * 修改数据
     * @return boolean
     * @说明：
     * 可以通过set和传参$columnsValues设置字段值，如果$columnsValues设置的字段和set相同会覆盖之前设置的值
     * 可以通过where设置修改条件
     * 执行之后where、group by、order by、limit会清空
     * 执行之后设置的字段不会清除，使用DB::table('tableName')重新实例化，字段会清除
     * @使用：
     * DB::table('tableName')->setName('名称')->where('Id', 1)->update(['Desc' => '描述'])
     */
    public function update($columnsValues = []){
        $this->_columnsValues = array_merge($this->_columnsValues, $columnsValues);
        $setColumnsValues = '';
        foreach ($this->_columnsValues as $name=>$value) {
            $quoteRequired = $this->checkColumnQuoteRequired($name, $value);
            if ($quoteRequired === true) {//需要加引号
                $setColumnsValues .= $name.'="'.$this->fieldEscape($value).'",';
            } else if ($quoteRequired === false) {//不需要加引号
                $setColumnsValues .= $name.'='.$value.',';
            }
        }
        $setColumnsValues = rtrim($setColumnsValues, ',');
        $bindings = $this->getBindings();
        $this->cleanBindings();
        if ($setColumnsValues == '' || stripos($bindings, 'WHERE') === false) {
            return false;
        } else {
            $sql = 'UPDATE '.$this->_fullTableName.' SET '.$setColumnsValues.$bindings;
            return $this->_langdb->querySql($sql);
        }
    }

    /**
     * 查询记录数
     * @return int
     * @说明：
     * 可以通过where设置查询条件
     * 执行之后where、group by、order by、limit会清空
     * @使用：
     * DB::table('tableName')->where('Id', '<', 10)->count()
     */
    public function count(){
        $bindings = $this->_joins.' '.$this->getBindings();
        $this->cleanBindings();
        return $this->_langdb->selectRowsNum($this->_fullTableName, $bindings);
    }

    /**
     * 获取所有的数据
     * @param $columns 查询字段
     * @return array 二维数组
     * @说明：
     * 可以通过where、group by、order by、limit设置查询条件
     * 执行之后where、group by、order by、limit会清空
     * @使用：
     * DB::table('tableName')->where('Id', '<', 10)->orderBy('Id', 'desc')->lists('Id,Name')
     */
    public function lists($columns = '*'){
        $bindings = $this->_joins.' '.$this->getBindings();
        $this->cleanBindings();
        $sql = 'SELECT '.$columns.' FROM '.$this->_fullTableName.$bindings;
        return $this->_langdb->selectSqlTable($sql);
    }

    /**
     * 获取第一条数据
     * @param $columns 查询字段
     * @return array 一维数组
     * @说明：
     * 可以通过where、group by、order by设置查询条件
     * 执行之后where、group by、order by、limit会清空
     * @使用：
     * DB::table('tableName')->where('Id', '<', 10)->orderBy('Id', 'desc')->lists('Id,Name')
     */
    public function first($columns = '*'){
        $this->_bindings['limit'] = '1';
        $list = $this->lists($columns);
        return isset($list[0])?$list[0]:[];
    }

    /**
     * 获取拼接后条件
     * @return string
     * @使用：DB::table('tableName')->where('Id', '<', 10)->getBindings()
     */
    public function getBindings(){
        $bindings = '';
        foreach ($this->_bindings as $k=>$v) {
            if ($v != '') {
                $bindings .= ' '.strtoupper($k).' '.$v;
            }
        }
        return $bindings;
    }

    /**
     * 克隆cleanBindings之前的bindings
     * @return DB
     * @说明：
     * 使用该方法需要在where、group by、having、order by、limit、设置前，否则预先设置的bindings无效
     * @使用：DB::table('tableName')->cloneBindings()->where('Id', '<', 10)
     */
    public function cloneBindings(){
        $this->_bindings = $this->_bindingsTemp;
        return $this;
    }

    /**
     * 检测字段是否需要加引号
     */
    private function checkColumnQuoteRequired($name, $value = null){
        $numberTypes = ['FLOAT', 'DOUBLE', 'DECIMAL', 'BIT'];
        if (!isset($this->_tableColumnsInfo[$name])) {//表没有该字段
            return null;
        } else {
            $dataType = strtoupper($this->_tableColumnsInfo[$name]['DATA_TYPE']);
            if (strpos($dataType, 'INT') !== false || in_array($dataType, $numberTypes)) {
                if ($value !== null && !is_numeric($value) && strpos($value, $name) === false) {
                    throw new Exception('Illegal number');
                }
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * 字段转义
     * 说明：需要加引号的字段，根据是否包含以\开始的'或"来决定是否addslashes
     * 注意：本身没有转义的值仅包含\'或\"也不再addslashes
     */
    private function fieldEscape($fieldVal){
        $fieldValStr = strval($fieldVal);
        if (preg_match_all('/(.{0,1})[\'\"]/', $fieldValStr, $matches)) {
            foreach ($matches[1] as $v) {
                if ($v != '\\') {
                    return addslashes($fieldValStr);
                }
            }
        }
        return $fieldVal;
    }

    /**
     * Remove query value bindings
     * @param $key 清除指定key
     */
    private function cleanBindings($key = ''){
        $this->_joins = '';
        $this->_bindingsTemp = $this->_bindings;
        if ($key != '') {
            isset($this->_bindings[$key]) && $this->_bindings[$key] = '';
        } else {
            foreach ($this->_bindings as &$v) {
                $v = '';
            }
        }
    }
}
<?php

namespace Rock\Router;

class  Router
{
    private $_baseNamespeac;

    private static $_module;
    private static $_action;
    private static $_method;

    public function __construct ($baseNamespeac = "Project")
    {
        $this->_baseNamespeac = $baseNamespeac;
    }

    function dispatch ()
    {
        $module = ucfirst(strtolower(isset($_GET['_p']) ? $_GET['_p'] : 'back'));
        $action = ucfirst(strtolower(isset($_GET['_a']) ? $_GET['_a'] : 'index'));
        $method = ucfirst(strtolower(isset($_GET['_m']) ? $_GET['_m'] : 'index'));
        $method === '' && $method = 'index';
        if ('users' === $action || 'action' === $action) {
            $this->error('Forbidden');
        }
        self::$_module  = $module;
        self::$_action  = $action;
        self::$_method  = $method;
        $actionFileName = _SITE_ROOT_ . '/' . $module . '/Action/' . $action . '.php';
        if (true === file_exists($actionFileName)) {
            $className = "$this->_baseNamespeac\\$module\\Action\\$action";
            if (!class_exists($className))
                $this->error('404 Not Found');
            $controllerObject = new $className;
            $reflectionClass  = new \ReflectionClass($controllerObject);
            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                preg_match("/@Route\[\"(.+)\"\]/" ,$reflectionMethod->getDocComment() ,$ret);
                $route = isset($ret[1]) ? $ret[1] : '';
                if ($route == trim(strtolower("/$module/$action/$method"))) {
                    $functioName = $reflectionMethod->name;
                    $controllerObject->$functioName();
                    exit;
                }
            }
        }
        $this->error('404 Not Found');
    }

    function error ($msg)
    {
        header('HTTP/1.1 404 Not Found');
        header('Status:404 Not Found');// 确保FastCGI模式下正常
        //TODO 输出404 页面 暂时只是输出字符串处理
        echo $msg;
        exit;
    }
}
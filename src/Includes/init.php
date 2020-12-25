<?php

header("Content-type:text/html;charset=utf-8");
date_default_timezone_set("Asia/Shanghai");
$nowDate = date("Y-m-d H:i:s");

if (!defined("_NOSESSION_")) {
    define("_USE_WPWWEBDB_" ,true);
}

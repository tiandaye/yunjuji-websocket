<?php

/**
 * @Author: admin
 * @Date:   2017-10-26 14:03:54
 * @Last Modified by:   admin
 * @Last Modified time: 2017-10-26 14:04:35
 */

$db['master'] = array(
    'type'       => Swoole\Database::TYPE_MYSQLi,
    'host'       => "127.0.0.1",
    'port'       => 3306,
    'dbms'       => 'mysql',
    // 'engine'     => 'MyISAM',
    'user'       => "root",
    'passwd'     => "12345678",
    'name'       => "",
    'charset'    => "utf8",
    'setname'    => true,
    'persistent' => false, //MySQL长连接
);
return $db;
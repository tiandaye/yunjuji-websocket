<?php

/**
 * @Author: admin
 * @Date:   2017-10-26 14:36:22
 * @Last Modified by:   admin
 * @Last Modified time: 2017-10-26 14:48:24
 */
use Yunjuji\WebSocket\WebSocket;

$config = require_once './configs/yunjuji.php';

new WebSocket($config);
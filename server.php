<?php

/**
 * @Author: admin
 * @Date:   2017-10-26 14:36:22
 * @Last Modified by:   admin
 * @Last Modified time: 2017-10-26 15:12:53
 */
require_once __DIR__.'/vendor/autoload.php';

$config = require_once './configs/yunjuji.php';

new Yunjuji\WebSocket\WebSocket($config);
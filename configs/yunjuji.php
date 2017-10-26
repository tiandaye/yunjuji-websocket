<?php

/**
 * @Author: admin
 * @Date:   2017-10-26 14:38:00
 * @Last Modified by:   admin
 * @Last Modified time: 2017-10-26 15:05:40
 */
/**
 * websocket-服务端配置
 */
$config['server'] = array(
	'master' => array(
	    //监听的HOST
	    'host'   => '0.0.0.0',
	    //监听的端口
	    'port'   => '9503',
	),
    //WebSocket的URL地址，供浏览器使用的
    'url'    => 'ws://im.swoole.com:9503',
    //用于Comet跨域，必须设置为html所在的URL
    'origin' => 'http://im.swoole.com:8888',
);

/**
 * swoole配置
 */
$config['swoole'] = array(
    // 'log_file'        => ROOT_PATH . '/log/swoole.log',
    'worker_num'      => 1,
    //不要修改这里
    'max_request'     => 0,
    'task_worker_num' => 1,
    //是否要作为守护进程
    'daemonize'       => 0,
);

/**
 * redis配置
 */
$config['storage'] = array(
    'history_num' => 100,
    'master' => array(
	    'host' => '127.0.0.1',
	    'port' => '6379'
	)
);

/**
 * 基本配置
 */
$config['yunjuji'] = array(
    //聊天记录存储的目录
    // 'log_file' => ROOT_PATH . '/log/webim.log',
    'send_interval_limit' => 2, //只允许1秒发送一次
);
return $config;
<?php

/**
 * @Author: admin
 * @Date:   2017-10-26 11:38:13
 * @Last Modified by:   admin
 * @Last Modified time: 2017-10-26 14:29:38
 */
namespace Yunjuji\WebSocket;

class Storage
{
    /**
     * [$redis description]
     * @var [type]
     */
    protected $redis;

    /**
     * [$config description]
     * @var [type]
     */
    private $config;

    const PREFIX = 'yunjuji-websocket';

    /**
     * [__construct description]
     * @param [type] $config [description]
     */
    public function __construct($config = [])
    {
        // $this->redis = \Swoole::getInstance()->redis;
        // $this->redis->delete(self::PREFIX . ':online');
        // $this->config = $config;
        
        $this->config = $config;
        $this->redis = new Redis();
        $this->redis->connect($config['master']['host'], $config['master']['port']);
        $this->redis->delete(self::PREFIX . ':online');
    }

    /**
     * [login description]
     * @param  [type] $client_id [description]
     * @param  [type] $info      [description]
     * @return [type]            [description]
     */
    public function login($client_id, $info)
    {
        $this->redis->set(self::PREFIX . ':client:' . $client_id, json_encode($info));
        $this->redis->sAdd(self::PREFIX . ':online', $client_id);
    }

    /**
     * [logout description]
     * @param  [type] $client_id [description]
     * @return [type]            [description]
     */
    public function logout($client_id)
    {
        $this->redis->del(self::PREFIX . ':client:' . $client_id);
        $this->redis->sRemove(self::PREFIX . ':online', $client_id);
    }

    /**
     * 用户在线用户列表
     * @return array
     */
    public function getOnlineUsers()
    {
        return $this->redis->sMembers(self::PREFIX . ':online');
    }

    /**
     * 批量获取用户信息
     * @param $users
     * @return array
     */
    public function getUsers($users)
    {
        $keys = array();
        $ret  = array();
        foreach ($users as $v) {
            $keys[] = self::PREFIX . ':client:' . $v;
        }
        $info = $this->redis->mget($keys);
        foreach ($info as $v) {
            $ret[] = json_decode($v, true);
        }
        return $ret;
    }

    /**
     * 获取单个用户信息
     * @param $userid
     * @return bool|mixed
     */
    public function getUser($userid)
    {
        $ret  = $this->redis->get(self::PREFIX . ':client:' . $userid);
        $info = json_decode($ret, true);
        return $info;
    }

    /**
     * [exists 判断是否存在]
     * @param  [type] $userid [description]
     * @return [type]         [description]
     */
    public function exists($userid)
    {
        return $this->redis->exists(self::PREFIX . ':client:' . $userid);
    }

    /**
     * [addHistory description]
     * @param [type] $userid [description]
     * @param [type] $msg    [description]
     */
    public function addHistory($userid, $msg)
    {
        $info        = $this->getUser($userid);
        $log['user'] = $info;
        $log['msg']  = $msg;
        $log['time'] = time();
        $log['type'] = empty($msg['type']) ? '' : $msg['type'];
        table(self::PREFIX . '_history')->put(array(
            'name'   => $info['name'],
            'avatar' => $info['avatar'],
            'msg'    => json_encode($msg),
            'type'   => empty($msg['type']) ? '' : $msg['type'],
        ));
    }

    /**
     * [getHistory description]
     * @param  integer $offset [description]
     * @param  integer $num    [description]
     * @return [type]          [description]
     */
    public function getHistory($offset = 0, $num = 100)
    {
        $data = array();
        $list = table(self::PREFIX . '_history')->gets(array('limit' => $num));
        foreach ($list as $li) {
            $result['type'] = $li['type'];
            $result['user'] = array('name' => $li['name'], 'avatar' => $li['avatar']);
            $result['time'] = strtotime($li['addtime']);
            $result['msg']  = json_decode($li['msg'], true);
            $data[]         = $result;
        }
        return array_reverse($data);
    }
}

/**
 * 方式1
 */
// $client = new swoole_redis;
// $client->connect('127.0.0.1', 6379, function (swoole_redis $client, $result) {
//     if ($result === false) {
//         echo "connect to redis server failed.\n"
//         return;
//     }
//     $client->set('key', 'swoole', function (swoole_redis $client, $result) {
//         var_dump($result);
//     });
// });


/**
 * 方式2
 */
// $redis = new Swoole\Redis;
// $redis->connect('127.0.0.1', 6379, function ($redis, $result) {
//     $redis->set('test_key', 'value', function ($redis, $result) {
//         $redis->get('test_key', function ($redis, $result) {
//             var_dump($result);
//         });
//     });
// });

// $cli = new Swoole\Http\Client('127.0.0.1', 80);
// $cli->setHeaders(array('User-Agent' => 'swoole-http-client'));
// $cli->setCookies(array('test' => 'value'));

// $cli->post('/dump.php', array("test" => 'abc'), function ($cli) {
//     var_dump($cli->body);
//     $cli->get('/index.php', function ($cli) {
//         var_dump($cli->cookies);
//         var_dump($cli->headers);
//     });
// });

/**
 * 方式3
 */
// \Swoole::getInstance()->redis;

// <?php
// namespace WebIM;
// class Storage
// {
//     /**
//      * @var \redis
//      */
//     protected $redis;
//     const PREFIX = 'webim';
//     function __construct($config)
//     {
//         $this->redis = \Swoole::getInstance()->redis;
//         $this->redis->delete(self::PREFIX.':online');
//         $this->config = $config;
//     }
//     function login($client_id, $info)
//     {
//         $this->redis->set(self::PREFIX . ':client:' . $client_id, json_encode($info));
//         $this->redis->sAdd(self::PREFIX . ':online', $client_id);
//     }
//     function logout($client_id)
//     {
//         $this->redis->del(self::PREFIX.':client:'.$client_id);
//         $this->redis->sRemove(self::PREFIX.':online', $client_id);
//     }
//     /**
//      * 用户在线用户列表
//      * @return array
//      */
//     function getOnlineUsers()
//     {
//         return $this->redis->sMembers(self::PREFIX . ':online');
//     }
//     /**
//      * 批量获取用户信息
//      * @param $users
//      * @return array
//      */
//     function getUsers($users)
//     {
//         $keys = array();
//         $ret = array();
//         foreach ($users as $v)
//         {
//             $keys[] = self::PREFIX . ':client:' . $v;
//         }
//         $info = $this->redis->mget($keys);
//         foreach ($info as $v)
//         {
//             $ret[] = json_decode($v, true);
//         }
//         return $ret;
//     }
//     /**
//      * 获取单个用户信息
//      * @param $userid
//      * @return bool|mixed
//      */
//     function getUser($userid)
//     {
//         $ret = $this->redis->get(self::PREFIX . ':client:' . $userid);
//         $info = json_decode($ret, true);
//         return $info;
//     }
//     function exists($userid)
//     {
//         return $this->redis->exists(self::PREFIX . ':client:' . $userid);
//     }
//     function addHistory($userid, $msg)
//     {
//         $info = $this->getUser($userid);
//         $log['user'] = $info;
//         $log['msg'] = $msg;
//         $log['time'] = time();
//         $log['type'] = empty($msg['type']) ? '' : $msg['type'];
//         table(self::PREFIX.'_history')->put(array(
//             'name' => $info['name'],
//             'avatar' => $info['avatar'],
//             'msg' => json_encode($msg),
//             'type' => empty($msg['type']) ? '' : $msg['type'],
//         ));
//     }
//     function getHistory($offset = 0, $num = 100)
//     {
//         $data = array();
//         $list = table(self::PREFIX.'_history')->gets(array('limit' => $num,));
//         foreach ($list as $li)
//         {
//             $result['type'] = $li['type'];
//             $result['user'] = array('name' => $li['name'], 'avatar' => $li['avatar']);
//             $result['time'] = strtotime($li['addtime']);
//             $result['msg'] = json_decode($li['msg'], true);
//             $data[] = $result;
//         }
//         return array_reverse($data);
//     }
// }
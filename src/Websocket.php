<?php
/**
 *   the format of json
 *
 *   CONNECT
 *   {
status : 200,
type : 'connect',
data : {
id : 0,
avatar : '',
nickname : ''
}
}
DISCONNECT
{
status : 200,
type : 'disconnect',
data : {
id : 0
}
}
MESSAGE
{
status : 200,
type : 'message',
data : {
from : 0,
to : 0,
msg : ''
}
}
INIT
{
status : 200,
type : 'init',
data : {

}
}
 *
 */
namespace Yunjuji\WebSocket;

class WebSocket
{
    const CONNECT_TYPE    = 'connect';
    const DISCONNECT_TYPE = 'disconnect';
    const MESSAGE_TYPE    = 'message';
    const INIT_SELF_TYPE  = 'self_init';
    const INIT_OTHER_TYPE = 'other_init';
    const COUNT_TYPE      = 'count';
    private $avatars      = [
        'http://e.hiphotos.baidu.com/image/h%3D200/sign=08f4485d56df8db1a32e7b643922dddb/1ad5ad6eddc451dad55f452ebefd5266d116324d.jpg',
        'http://tva3.sinaimg.cn/crop.0.0.746.746.50/a157f83bjw8f5rr5twb5aj20kq0kqmy4.jpg',
        'http://www.ld12.com/upimg358/allimg/c150627/14353W345a130-Q2B.jpg',
        'http://www.qq1234.org/uploads/allimg/150121/3_150121144650_12.jpg',
        'http://tva1.sinaimg.cn/crop.4.4.201.201.50/9cae7fd3jw8f73p4sxfnnj205q05qweq.jpg',
        'http://tva1.sinaimg.cn/crop.0.0.749.749.50/ac593e95jw8f90ixlhjdtj20ku0kt0te.jpg',
        'http://tva4.sinaimg.cn/crop.0.0.674.674.50/66f802f9jw8ehttivp5uwj20iq0iqdh3.jpg',
        'http://tva4.sinaimg.cn/crop.0.0.1242.1242.50/6687272ejw8f90yx5n1wxj20yi0yigqp.jpg',
        'http://tva2.sinaimg.cn/crop.0.0.996.996.50/6c351711jw8f75bqc32hsj20ro0roac4.jpg',
        'http://tva2.sinaimg.cn/crop.0.0.180.180.50/6aba55c9jw1e8qgp5bmzyj2050050aa8.jpg',
    ];
    private $nicknames = [
        '沉淀', '暖寄归人', '厌世症i', '难免心酸°', '過客。', '昔日餘光。', '独特', '有爱就有恨', '共度余生', '忆七年', '单人旅行', '何日许我红装', '醉落夕风',
    ];
    private $server;
    private $host;
    private $port;
    private $config;
    private $storage;

    /**
     * [__construct description]
     * @param string  $ip   [description]
     * @param integer $port [description]
     */
    public function __construct($config = [])
    {
        $this->config = $config;
        $this->host   = $config['server']['master']['host'];
        $this->port = $config['server']['master']['port'];
        $this->init();
        $this->storage = new Storage($config['storage']);
        var_dump($storage);
    }

    /**
     * [init 初始化]
     * @return [type] [description]
     */
    public function init()
    {
        // $this->table = new \swoole_table(1024);
        // $this->table->column('id', \swoole_table::TYPE_INT, 4); //1,2,4,8
        // $this->table->column('avatar', \swoole_table::TYPE_STRING, 1024);
        // $this->table->column('nickname', \swoole_table::TYPE_STRING, 64);
        // $this->table->create();

        // 通过构造函数创建 `swoole_server` 对象
        $this->server = $server = new \swoole_websocket_server($this->host, $this->port);
        // 调用set函数设置 `swoole_server` 的相关配置选项
        $server->set([
            'task_worker_num' => 4,
        ]);
        // 调用 `on` 函数设置相关回调函数
        $server->on('open', [$this, 'open']);
        $server->on('message', [$this, 'message']);
        $server->on('close', [$this, 'close']);
        // 开启 `task` , 必须要有这两个函数。这两个回调函数分别用于执行 `Task` 任务和处理 `Task` 任务的返回结果
        $server->on('task', [$this, 'task']);
        $server->on('finish', [$this, 'finish']);
        $server->start();
    }

    /**
     * [open description]
     * @param  swoole_websocket_server $server [description]
     * @param  swoole_http_request     $req    [description]
     * @return [type]                          [description]
     */
    public function open(\swoole_websocket_server $server, \swoole_http_request $req)
    {
        $avatar   = $this->avatars[array_rand($this->avatars)];
        $nickname = $this->nicknames[array_rand($this->nicknames)];
        // 映射存到redis
        $this->storage->login($req->fd, [
            'id'       => $req->fd,
            'avatar'   => $avatar,
            'nickname' => $nickname,
        ]);
        // init selfs data
        $userMsg = $this->buildMsg([
            'id'       => $req->fd,
            'avatar'   => $avatar,
            'nickname' => $nickname,
            'count'    => count($this->storage->getUsers($server->connections)),
        ], self::INIT_SELF_TYPE);
        $this->server->task([
            'to'     => [$req->fd],
            'except' => [],
            'data'   => $userMsg,
        ]);

        // init others data
        $others = [];
        foreach ($server->connections as $row) {
            $others[] = $row;
        }
        $otherMsg = $this->buildMsg($others, self::INIT_OTHER_TYPE);
        $this->server->task([
            'to'     => [$req->fd],
            'except' => [],
            'data'   => $otherMsg,
        ]);

        //broadcast a user is online
        $msg = $this->buildMsg([
            'id'       => $req->fd,
            'avatar'   => $avatar,
            'nickname' => $nickname,
            'count'    => count($this->storage->getUsers($server->connections)),
        ], self::CONNECT_TYPE);
        $this->server->task([
            'to'     => [],
            'except' => [$req->fd],
            'data'   => $msg,
        ]);

        // $avatar   = $this->avatars[array_rand($this->avatars)];
        // $nickname = $this->nicknames[array_rand($this->nicknames)];
        // $this->table->set($req->fd, [
        //     'id'       => $req->fd,
        //     'avatar'   => $avatar,
        //     'nickname' => $nickname,
        // ]);
        // // init selfs data
        // $userMsg = $this->buildMsg([
        //     'id'       => $req->fd,
        //     'avatar'   => $avatar,
        //     'nickname' => $nickname,
        //     'count'    => count($this->table),
        // ], self::INIT_SELF_TYPE);
        // $this->server->task([
        //     'to'     => [$req->fd],
        //     'except' => [],
        //     'data'   => $userMsg,
        // ]);

        // // init others data
        // $others = [];
        // foreach ($this->table as $row) {
        //     $others[] = $row;
        // }
        // $otherMsg = $this->buildMsg($others, self::INIT_OTHER_TYPE);
        // $this->server->task([
        //     'to'     => [$req->fd],
        //     'except' => [],
        //     'data'   => $otherMsg,
        // ]);

        // //broadcast a user is online
        // $msg = $this->buildMsg([
        //     'id'       => $req->fd,
        //     'avatar'   => $avatar,
        //     'nickname' => $nickname,
        //     'count'    => count($this->table),
        // ], self::CONNECT_TYPE);
        // $this->server->task([
        //     'to'     => [],
        //     'except' => [$req->fd],
        //     'data'   => $msg,
        // ]);
    }

    /**
     * [message description]
     * @param  swoole_websocket_server $server [description]
     * @param  swoole_websocket_frame  $frame  [description]
     * @return [type]                          [description]
     */
    public function message(\swoole_websocket_server $server, \swoole_websocket_frame $frame)
    {
        $receive = json_decode($frame->data, true);
        $msg     = $this->buildMsg($receive, self::MESSAGE_TYPE);
        $task    = [
            'to'     => [],
            'except' => [$frame->fd],
            'data'   => $msg,
        ];
        if ($receive['to'] != 0) {
            $task['to'] = [$receive['to']];
        }
        $server->task($task);
    }

    /**
     * [close description]
     * @param  swoole_websocket_server $server [description]
     * @param  [type]                  $fd     [description]
     * @return [type]                          [description]
     */
    public function close(\swoole_websocket_server $server, $fd)
    {
        $this->storage->logout($fd);
        $msg = $this->buildMsg([
            'id'    => $fd,
            'count' => count($server->connections),
        ], self::DISCONNECT_TYPE);
        $this->server->task([
            'to'     => [],
            'except' => [$fd],
            'data'   => $msg,
        ]);

        // $this->table->del($fd);
        // $msg = $this->buildMsg([
        //     'id'    => $fd,
        //     'count' => count($this->table),
        // ], self::DISCONNECT_TYPE);
        // $this->server->task([
        //     'to'     => [],
        //     'except' => [$fd],
        //     'data'   => $msg,
        // ]);
    }

    /**
     * [task description]
     * @param  [type] $server  [description]
     * @param  [type] $task_id [description]
     * @param  [type] $from_id [description]
     * @param  [type] $data    [description]
     * @return [type]          [description]
     */
    public function task($server, $task_id, $from_id, $data)
    {   
        // 广播
        $clients = $server->connections;
        // 组播或点播
        if (count($data['to']) > 0) {
            $clients = $data['to'];
        }
        foreach ($clients as $fd) {
            if (!in_array($fd, $data['except'])) {
                // 服务端往客户端发送消息
                $this->server->push($fd, $data['data']);
            }
        }
    }

    /**
     * [finish description]
     * @return [type] [description]
     */
    public function finish()
    {
    }

    /**
     * [buildMsg description]
     * @param  [type]  $data   [description]
     * @param  [type]  $type   [description]
     * @param  integer $status [description]
     * @return [type]          [description]
     */
    private function buildMsg($data, $type, $status = 200)
    {
        return json_encode([
            'status' => $status,
            'type'   => $type,
            'data'   => $data,
        ]);
    }
}

// <?php
// namespace WebIM;
// use Swoole;
// use Swoole\Filter;
// class Server extends Swoole\Protocol\CometServer
// {
//     /**
//      * @var Store\File;
//      */
//     protected $storage;
//     protected $users;
//     /**
//      * 上一次发送消息的时间
//      * @var array
//      */
//     protected $lastSentTime = array();
//     const MESSAGE_MAX_LEN     = 1024; //单条消息不得超过1K
//     const WORKER_HISTORY_ID   = 0;
//     function __construct($config = array())
//     {
//         //将配置写入config.js
//         $config_js = <<<HTML
// var webim = {
//     'server' : '{$config['server']['url']}'
// }
// HTML;
//         file_put_contents(WEBPATH . '/config.js', $config_js);
//         //检测日志目录是否存在
//         $log_dir = dirname($config['webim']['log_file']);
//         if (!is_dir($log_dir))
//         {
//             mkdir($log_dir, 0777, true);
//         }
//         if (!empty($config['webim']['log_file']))
//         {
//             $logger = new Swoole\Log\FileLog($config['webim']['log_file']);
//         }
//         else
//         {
//             $logger = new Swoole\Log\EchoLog(true);
//         }
//         $this->setLogger($logger);   //Logger
//         /**
//          * 使用文件或redis存储聊天信息
//          */
//         $this->storage = new Storage($config['webim']['storage']);
//         $this->origin = $config['server']['origin'];
//         parent::__construct($config);
//     }
//     /**
//      * 下线时，通知所有人
//      */
//     function onExit($client_id)
//     {
//         $userInfo = $this->storage->getUser($client_id);
//         if ($userInfo)
//         {
//             $resMsg = array(
//                 'cmd' => 'offline',
//                 'fd' => $client_id,
//                 'from' => 0,
//                 'channal' => 0,
//                 'data' => $userInfo['name'] . "下线了",
//             );
//             $this->storage->logout($client_id);
//             unset($this->users[$client_id]);
//             //将下线消息发送给所有人
//             $this->broadcastJson($client_id, $resMsg);
//         }
//         $this->log("onOffline: " . $client_id);
//     }
//     function onTask($serv, $task_id, $from_id, $data)
//     {
//         $req = unserialize($data);
//         if ($req)
//         {
//             switch($req['cmd'])
//             {
//                 case 'getHistory':
//                     $history = array('cmd'=> 'getHistory', 'history' => $this->storage->getHistory());
//                     if ($this->isCometClient($req['fd']))
//                     {
//                         return $req['fd'].json_encode($history);
//                     }
//                     //WebSocket客户端可以task中直接发送
//                     else
//                     {
//                         $this->sendJson(intval($req['fd']), $history);
//                     }
//                     break;
//                 case 'addHistory':
//                     if (empty($req['msg']))
//                     {
//                         $req['msg'] = '';
//                     }
//                     $this->storage->addHistory($req['fd'], $req['msg']);
//                     break;
//                 default:
//                     break;
//             }
//         }
//     }
//     function onFinish($serv, $task_id, $data)
//     {
//         $this->send(substr($data, 0, 32), substr($data, 32));
//     }
//     /**
//      * 获取在线列表
//      */
//     function cmd_getOnline($client_id, $msg)
//     {
//         $resMsg = array(
//             'cmd' => 'getOnline',
//         );
//         $users = $this->storage->getOnlineUsers();
//         $info = $this->storage->getUsers(array_slice($users, 0, 100));
//         $resMsg['users'] = $users;
//         $resMsg['list'] = $info;
//         $this->sendJson($client_id, $resMsg);
//     }
//     /**
//      * 获取历史聊天记录
//      */
//     function cmd_getHistory($client_id, $msg)
//     {
//         $task['fd'] = $client_id;
//         $task['cmd'] = 'getHistory';
//         $task['offset'] = '0,100';
//         //在task worker中会直接发送给客户端
//         $this->getSwooleServer()->task(serialize($task), self::WORKER_HISTORY_ID);
//     }
//     *
//      * 登录
//      * @param $client_id
//      * @param $msg

//     function cmd_login($client_id, $msg)
//     {
//         $info['name'] = Filter::escape(strip_tags($msg['name']));
//         $info['avatar'] = Filter::escape($msg['avatar']);
//         //回复给登录用户
//         $resMsg = array(
//             'cmd' => 'login',
//             'fd' => $client_id,
//             'name' => $info['name'],
//             'avatar' => $info['avatar'],
//         );
//         //把会话存起来
//         $this->users[$client_id] = $resMsg;
//         $this->storage->login($client_id, $resMsg);
//         $this->sendJson($client_id, $resMsg);
//         //广播给其它在线用户
//         $resMsg['cmd'] = 'newUser';
//         //将上线消息发送给所有人
//         $this->broadcastJson($client_id, $resMsg);
//         //用户登录消息
//         $loginMsg = array(
//             'cmd' => 'fromMsg',
//             'from' => 0,
//             'channal' => 0,
//             'data' => $info['name'] . "上线了",
//         );
//         $this->broadcastJson($client_id, $loginMsg);
//     }
//     /**
//      * 发送信息请求
//      */
//     function cmd_message($client_id, $msg)
//     {
//         $resMsg = $msg;
//         $resMsg['cmd'] = 'fromMsg';
//         if (strlen($msg['data']) > self::MESSAGE_MAX_LEN)
//         {
//             $this->sendErrorMessage($client_id, 102, 'message max length is '.self::MESSAGE_MAX_LEN);
//             return;
//         }
//         $now = time();
//         //上一次发送的时间超过了允许的值，每N秒可以发送一次
//         if ($this->lastSentTime[$client_id] > $now - $this->config['webim']['send_interval_limit'])
//         {
//             $this->sendErrorMessage($client_id, 104, 'over frequency limit');
//             return;
//         }
//         //记录本次消息发送的时间
//         $this->lastSentTime[$client_id] = $now;
//         //表示群发
//         if ($msg['channal'] == 0)
//         {
//             $this->broadcastJson($client_id, $resMsg);
//             $this->getSwooleServer()->task(serialize(array(
//                 'cmd' => 'addHistory',
//                 'msg' => $msg,
//                 'fd'  => $client_id,
//             )), self::WORKER_HISTORY_ID);
//         }
//         //表示私聊
//         elseif ($msg['channal'] == 1)
//         {
//             $this->sendJson($msg['to'], $resMsg);
//             //$this->store->addHistory($client_id, $msg['data']);
//         }
//     }
//     /**
//      * 接收到消息时
//      * @see WSProtocol::onMessage()
//      */
//     function onMessage($client_id, $ws)
//     {
//         $this->log("onMessage #$client_id: " . $ws['message']);
//         $msg = json_decode($ws['message'], true);
//         if (empty($msg['cmd']))
//         {
//             $this->sendErrorMessage($client_id, 101, "invalid command");
//             return;
//         }
//         $func = 'cmd_'.$msg['cmd'];
//         if (method_exists($this, $func))
//         {
//             $this->$func($client_id, $msg);
//         }
//         else
//         {
//             $this->sendErrorMessage($client_id, 102, "command $func no support.");
//             return;
//         }
//     }
//     /**
//      * 发送错误信息
//     * @param $client_id
//     * @param $code
//     * @param $msg
//      */
//     function sendErrorMessage($client_id, $code, $msg)
//     {
//         $this->sendJson($client_id, array('cmd' => 'error', 'code' => $code, 'msg' => $msg));
//     }
//     /**
//      * 发送JSON数据
//      * @param $client_id
//      * @param $array
//      */
//     function sendJson($client_id, $array)
//     {
//         $msg = json_encode($array);
//         if ($this->send($client_id, $msg) === false)
//         {
//             $this->close($client_id);
//         }
//     }
//     /**
//      * 广播JSON数据
//      * @param $client_id
//      * @param $array
//      */
//     function broadcastJson($sesion_id, $array)
//     {
//         $msg = json_encode($array);
//         $this->broadcast($sesion_id, $msg);
//     }
//     function broadcast($current_session_id, $msg)
//     {
//         foreach ($this->users as $client_id => $name)
//         {
//             if ($current_session_id != $client_id)
//             {
//                 $this->send($client_id, $msg);
//             }
//         }
//     }
// }

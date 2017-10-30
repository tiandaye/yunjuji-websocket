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
    private $adminServer;
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
        $this->config  = $config;
        $this->host    = $config['server']['master']['host'];
        $this->port    = $config['server']['master']['port'];
        $this->storage = new Storage($config['storage']);
        $this->init();
    }

    /**
     * [init 初始化]
     * @return [type] [description]
     */
    public function init()
    {
        // 通过构造函数创建 `swoole_server` 对象
        $this->server = $server = new \swoole_websocket_server($this->host, $this->port);
        // 调用set函数设置 `swoole_server` 的相关配置选项
        $server->set([
            'task_worker_num' => 4,
        ]);
        // 调用 `on` 函数设置相关回调函数
        $server->on('handshake', [$this, 'handshake']);
        $server->on('open', [$this, 'open']);
        $server->on('message', [$this, 'message']);
        $server->on('close', [$this, 'close']);
        // 开启 `task` , 必须要有这两个函数。这两个回调函数分别用于执行 `Task` 任务和处理 `Task` 任务的返回结果
        $server->on('task', [$this, 'task']);
        $server->on('finish', [$this, 'finish']);

        // 多开启一个websocket服务, 端口不一样
        $this->adminServer = $server->listen('0.0.0.0', 9502, SWOOLE_TCP);
        $this->adminServer->on('handshake', [$this, 'adminHandshake']);
        $this->adminServer->on('open', [$this, 'open']);
        $this->adminServer->on('message', [$this, 'message']);
        $this->adminServer->on('close', [$this, 'close']);
        // 开启 `task` , 必须要有这两个函数。这两个回调函数分别用于执行 `Task` 任务和处理 `Task` 任务的返回结果
        // $this->adminServer->on('task', [$this, 'task']);
        // $this->adminServer->on('finish', [$this, 'finish']);

        $server->start();
    }

    /**
     * [adminHandshake `WebSocket` 建立连接后进行握手, 设置onHandShake回调函数后不会再触发 `onOpen` 事件，需要应用代码自行处理]
     * @param  \swoole_http_request  $request  [description]
     * @param  \swoole_http_response $response [description]
     * @return [type]                          [`onHandShake` 函数必须返回 `true` 表示握手成功，返回其他值表示握手失败]
     */
    public function adminHandshake(\swoole_http_request $request, \swoole_http_response $response)
    {
        // 打印日志
        echo "adminHandshake start\n";
        echo "server: ***admin*** handshake success with fd{$request->fd}\n";

        print_r($request);
        print_r($request->cookie);
        print_r($request->header);

        // 自定义鉴权
        $postUrl = "http://127.0.0.1:8006/admin/authorization";
        $header  = [];
        // $header[] = "Content-type: text/xml";

        // 注入调用的地址
        $url = $postUrl;
        //首先检测是否支持curl
        if (!extension_loaded("curl")) {
            trigger_error("对不起，请开启curl功能模块！", E_USER_ERROR);
        }
        // 初始一个curl会话
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request->header);
        // 设置url
        curl_setopt($ch, CURLOPT_URL, $url);
        // TRUE, 将curl_exec()获取的信息以字符串返回, 而不是直接输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 设置发送方式:post
        // curl_setopt($ch, CURLOPT_POST, 1);
        // 设置发送数据
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        // 超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        // 设置cookie
        curl_setopt($ch, CURLOPT_COOKIE, $request->cookie);
        // 用户
        // curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
        // 执行cURL会话
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            print curl_error($ch);
        }
        curl_close($ch);
        // post的数据为xml字符串，通过 $xml = simplexml_load_string($post_data);转换成xml对象
        // $xml = simplexml_load_string($response);

        //先把xml转换为simplexml对象，再把simplexml对象转换成 json，再将 json 转换成数组。
        // $value_array = json_decode(json_encode(simplexml_load_string($return_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        echo "接收到的值 start:\n";
        print_r($responseData);
        echo "\n";
        $responseData = json_decode($responseData, true);
        print_r($responseData);
        echo "\n";
        echo "接收到的值 end:\n";
        if (isset($responseData['data']['id'])) {
            $userId = $responseData['data']['id'];
            // // 打印日志
            // echo "server: handshake success with fd{$req->fd}\n";

            // $avatar   = 'thc';
            // $nickname = 'lwj';
            // // 映射存到redis
            // $this->storage->login($req->fd, [
            //     'id'       => $userId,// $req->fd,
            //     'user_id' => $userId,
            //     'avatar'   => $avatar,
            //     'nickname' => $nickname,
            // ]);
            // // $resMsg = array(
            // //     'cmd' => 'login',
            // //     'fd' => $client_id,
            // //     'name' => $info['name'],
            // //     'avatar' => $info['avatar'],
            // // );

            // // init selfs data
            // $userMsg = $this->buildMsg([
            //     'id'       => $userId,// $req->fd,
            //     'avatar'   => $avatar,
            //     'nickname' => $nickname,
            //     'count'    => count($this->storage->getUsers($server->connections)),
            // ], self::INIT_SELF_TYPE);
            // $this->server->task([
            //     'to'     => [$req->fd],
            //     'except' => [],
            //     'data'   => $userMsg,
            // ]);

            // // init others data
            // $others = [];
            // foreach ($server->connections as $row) {
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
            //     'id'       => $userId,// $req->fd,
            //     'avatar'   => $avatar,
            //     'nickname' => $nickname,
            //     'count'    => count($this->storage->getUsers($server->connections)),
            // ], self::CONNECT_TYPE);
            // $this->server->task([
            //     'to'     => [],
            //     'except' => [$req->fd],
            //     'data'   => $msg,
            // ]);
        } else {
            $response->end();
            return false;
        }

        // if (如果不满足我某些自定义的需求条件，那么返回end输出，返回false，握手失败) {
        //    $response->end();
        //     return false;
        // }

        // 自定定握手规则，没有设置则用系统内置的（只支持version:13的）
        if (!isset($request->header['sec-websocket-key'])) {
            //'Bad protocol implementation: it is not RFC6455.'
            $response->end();
            return false;
        }

        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten          = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        echo "sec-websocket-key:" . $request->header['sec-websocket-key'];
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade'               => 'websocket',
            'Connection'            => 'Upgrade',
            'Sec-WebSocket-Accept'  => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        // WebSocket connection to 'ws://127.0.0.1:9502/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();
        echo "connected!" . PHP_EOL;
        return true;
    }

    /**
     * [handshake `WebSocket` 建立连接后进行握手, 设置onHandShake回调函数后不会再触发 `onOpen` 事件，需要应用代码自行处理]
     * @param  \swoole_http_request  $request  [description]
     * @param  \swoole_http_response $response [description]
     * @return [type]                          [`onHandShake` 函数必须返回 `true` 表示握手成功，返回其他值表示握手失败]
     */
    public function handshake(\swoole_http_request $request, \swoole_http_response $response)
    {
        echo "handshake start\n";
        // 打印日志
        echo "server: handshake start with fd{$request->fd}\n";

        // print_r( $request );
        // print_r( $request->cookie );
        // print_r( $request->header );
        // print_r( $request->server['query_string'] );

        // 自定义鉴权
        if (isset($request->server['query_string'])) {
            // echo "parse_str start\n";
            $queryString = urldecode($request->server['query_string']);
            // echo substr($queryString, strpos($queryString, "=") + 1);
            // echo "\n";

            $postUrl       = "http://127.0.0.1:8005/api/authorization";
            $authorization = "Authorization:" . substr($queryString, strpos($queryString, "=") + 1);
            $header        = [];
            $header        = [$authorization];
            // $header[] = "Content-type: text/xml";
            // $header = ["Authorization:Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImExM2UwNDY3NTJmMmQxNDc0M2M1YzRhZTg2ZTlhZDU2MjM3MjU3ZTU3YWI3MWFlZDM0OGJkNDk5NjQ0YTQ0MmE5YjEyMzc4Mjk0MDViNWYzIn0.eyJhdWQiOiIyIiwianRpIjoiYTEzZTA0Njc1MmYyZDE0NzQzYzVjNGFlODZlOWFkNTYyMzcyNTdlNTdhYjcxYWVkMzQ4YmQ0OTk2NDRhNDQyYTliMTIzNzgyOTQwNWI1ZjMiLCJpYXQiOjE1MDgyMDIwNTUsIm5iZiI6MTUwODIwMjA1NSwiZXhwIjoxNTA5NDk4MDU0LCJzdWIiOiI3MzI0Iiwic2NvcGVzIjpbIioiXX0.q34mPCdJAzSHXZ7Trkf7vSnln8xluxsPQf3-v1ZEVZGfjGKoGyxxrzzjprsR7-Ui2f2gyu6ldk98O5VP4IyZBaYopDa4AQjLa_anzvvcZvONm5CDwumevDvuDKkR_BesLuBivNWEAVn3tKgjwRTShXWsbKE9xNmIJVPgD8gq1suux2puyo7XNGBvq5B-BpyPKqat4JZOzUAQ6vZ_R3c7TDBPFaPwjS0j22EhPTemzrl0AQmD7uByAMcnFpqEXmsWRlfAJwv3100yxpA2HYpi-5qi1TYAcHnKbkrGe8tFzB8EZTw7NiWRAcJ0WBuzMK23IFxEdwX3sDK67dRum769IgFW3R2eCodAuRlXjMek8Rk_c20gk4VOniozhgsAZ-o-5X6xyJP84L5Qn_xhSFJ6jW2ZWbJWs_lPwrmiTFV7h_UsWOHXKLqKRaI1xm9u03GAZ4NLRs5uNqJEgwXCdVT4XUfHneZ_urJJbsKr1_cQNGCQ87H-dj_qIkXOkXzPxDJjyc3RAsTEpo6A6wW1v7GeFcsjpvM-mG2zehEohNeBHswzGze2Lyjj4dPAdTzNNtuvxRrA5vruqmyQeu6dSe4zKDq4-qWj3pgzbE0hAxpHZOISV5IMR8S2OaXlNetc_Uo8byRstWPA0vQcwDEViiU70n_pUmXslkzvr_PPRYrg7mI"];
            $post_data = [];
            // $post_data["price"] = 180.00;

            // 注入调用的地址
            $url = $postUrl;
            //首先检测是否支持curl
            if (!extension_loaded("curl")) {
                trigger_error("对不起，请开启curl功能模块！", E_USER_ERROR);
            }
            // 初始一个curl会话
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            // 设置url
            curl_setopt($ch, CURLOPT_URL, $url);
            // TRUE, 将curl_exec()获取的信息以字符串返回, 而不是直接输出
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // 设置发送方式:post
            // curl_setopt($ch, CURLOPT_POST, 1);
            // 设置发送数据
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            // 超时
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            // 用户
            // curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
            // 执行cURL会话
            $responseData = curl_exec($ch);
            if (curl_errno($ch)) {
                print curl_error($ch);
            }
            curl_close($ch);

            // echo "接收到的值 start:\n";
            // print_r($responseData);
            // echo "\n";
            $responseData = json_decode($responseData, true);
            // print_r($responseData);
            // echo "\n";
            // echo "接收到的值 end:\n";
            if (isset($responseData['data'])) {
                // echo "data start\n";
                // print_r($responseData['data']);
                $data = json_decode($responseData['data'], true);
                echo "用户信息:\n";
                print_r($data);
                if (!isset($data['id'])) {
                    $response->end();
                    return false;
                }
                echo $data['id'] . ":\n";
                // echo "data end\n";
                $userId = $data['id'];

                $avatar   = 'thc';
                $nickname = 'lwj';
                // 映射存到redis
                $this->storage->login($request->fd, [
                    'id'       => $userId, // $request->fd,
                    'user_id'  => $userId,
                    'avatar'   => $avatar,
                    'nickname' => $nickname,
                ]);
                // $resMsg = array(
                //     'cmd' => 'login',
                //     'fd' => $client_id,
                //     'name' => $info['name'],
                //     'avatar' => $info['avatar'],
                // );

                // init selfs data
                $userMsg = $this->buildMsg([
                    'id'       => $userId, // $request->fd,
                    'avatar'   => $avatar,
                    'nickname' => $nickname,
                    'count'    => 1,
                    // 'count'    => count($this->storage->getUsers($server->connections)),
                ], self::INIT_SELF_TYPE);
                $this->server->task([
                    'to'     => [$request->fd],
                    'except' => [],
                    'data'   => $userMsg,
                ]);

                // init others data
                $others = [];
                // foreach ($server->connections as $row) {
                //     $others[] = $row;
                // }
                $otherMsg = $this->buildMsg($others, self::INIT_OTHER_TYPE);
                $this->server->task([
                    'to'     => [$request->fd],
                    'except' => [],
                    'data'   => $otherMsg,
                ]);

                //broadcast a user is online
                $msg = $this->buildMsg([
                    'id'       => $userId, // $request->fd,
                    'avatar'   => $avatar,
                    'nickname' => $nickname,
                    'count'    => 1,
                    // 'count'    => count($this->storage->getUsers($server->connections)),
                ], self::CONNECT_TYPE);
                $this->server->task([
                    'to'     => [],
                    'except' => [$request->fd],
                    'data'   => $msg,
                ]);
            } else {
                $response->end();
                return false;
            }
        } else {
            $response->end();
            return false;
        }

        echo "*****************鉴权完毕*****************\n";
        // print_r( parse_str($request->server['query_string']) );

        // print_r( $request->header );
        // if (如果不满足我某些自定义的需求条件，那么返回end输出，返回false，握手失败) {
        //    $response->end();
        //     return false;
        // }

        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten          = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        echo "sec-websocket-key:" . $request->header['sec-websocket-key'];
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade'               => 'websocket',
            'Connection'            => 'Upgrade',
            'Sec-WebSocket-Accept'  => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        // WebSocket connection to 'ws://127.0.0.1:9502/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();
        echo "connected!" . PHP_EOL;
        return true;
    }

    /**
     * [open 当 `WebSocket` 客户端与服务器建立连接并完成握手后会回调此函数]
     * @param  swoole_websocket_server $server [description]
     * @param  swoole_http_request     $req    [$req 是一个Http请求对象，包含了客户端发来的握手请求信息]
     * @return [type]                          [description]
     */
    public function open(\swoole_websocket_server $server, \swoole_http_request $req)
    {
        // 打印日志
        echo "server: handshake success with fd{$req->fd}\n";

        $avatar   = $this->avatars[array_rand($this->avatars)];
        $nickname = $this->nicknames[array_rand($this->nicknames)];
        // 映射存到redis
        $this->storage->login($req->fd, [
            'id'       => $req->fd,
            'avatar'   => $avatar,
            'nickname' => $nickname,
        ]);
        // $resMsg = array(
        //     'cmd' => 'login',
        //     'fd' => $client_id,
        //     'name' => $info['name'],
        //     'avatar' => $info['avatar'],
        // );

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
    }

    /**
     * [message 当服务器收到来自客户端的数据帧时会回调此函数]
     * @param  swoole_websocket_server $server [description]
     * @param  swoole_websocket_frame  $frame  [$frame 是swoole_websocket_frame对象，包含了客户端发来的数据帧信息]
     * @return [type]                          [description]
     */
    public function message(\swoole_websocket_server $server, \swoole_websocket_frame $frame)
    {
        // 打印日志
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

        // $frame 是swoole_websocket_frame对象，包含了客户端发来的数据帧信息。共有4个属性，分别是:
        // $frame->fd，客户端的socket id，使用$server->push推送数据时需要用到
        // $frame->data，数据内容，可以是文本内容也可以是二进制数据，可以通过opcode的值来判断,$data 如果是文本类型，编码格式必然是UTF-8，这是WebSocket协议规定的
        // $frame->opcode，WebSocket的OpCode类型，可以参考WebSocket协议标准文档
        // $frame->finish， 表示数据帧是否完整，一个WebSocket请求可能会分成多个数据帧进行发送

        $receive = json_decode($frame->data, true);
        echo json_encode($receive);
        $msg  = $this->buildMsg($receive, self::MESSAGE_TYPE);
        $task = [
            'to'     => [],
            'except' => [$frame->fd],
            'data'   => $msg,
        ];
        if (isset($receive['to']) && $receive['to'] != 0) {
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
        // 打印日志
        echo "client {$fd} closed\n";

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

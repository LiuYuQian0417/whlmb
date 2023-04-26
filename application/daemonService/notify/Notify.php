<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-03-25
 * Time: 11:13
 */

namespace app\daemonService\notify;


use think\facade\Config;
use think\swoole\Server;

class Notify extends Server
{
    /**
     * @var Helper
     */
    private $helper;
    private $conf = [];
    private $storeMap = [];
    private $pcMemberMap = [];

    private $allowedMethodsFromHttp = [
        '/NOTIFY' // 店铺订单通知
    ];

    function __construct()
    {
        $this->conf = Config::pull('daemon')['notify'];

        // 设置监听IP
        $this->host = $this->conf['host'];
        // 设置端口
        $this->port = $this->conf['port'];
        // 服务类型
        $this->serverType = $this->conf['type'];

        $_option = [];

        // 心跳检测
//        if (isset($this->conf['heartbeat_check_interval'])) {
//            $_option['heartbeat_check_interval'] = $this->conf['heartbeat_check_interval'];
//        }

        // 启动进程数
        if (isset($this->conf['worker_num'])) {
            $_option['worker_num'] = $this->conf['worker_num'];
        }

        // 守护进程
        if (isset($this->conf['daemonize'])) {
            $_option['daemonize'] = $this->conf['daemonize'];
        }

        // pid文件位置
        if (isset($this->conf['pid_file'])) {
            $_lastDotPosition = strripos($this->conf['pid_file'], '.');

            // 避免pid混乱
            if ($_lastDotPosition) {
                $_option['pid_file'] = substr_replace(
                    $this->conf['pid_file'],
                    '_' . $this->port,
                    $_lastDotPosition,
                    0
                );
            } else {
                $_option['pid_file'] .= '_' . $this->port;
            }
        }

        // 日志文件位置
        if (isset($this->conf['log_file'])) {
            $_option['log_file'] = $this->conf['log_file'];
        }

        // 设置参数
        $this->option = $_option;

        $this->helper = new Helper();

        parent::__construct();

    }

    /**
     * HTTP 请求
     *
     * @param $request
     * @param $response
     */
    function onRequest($request, $response)
    {

        $_server = $request->server;

        // 验证请求方式
        if ($_server['request_method'] !== 'POST') {
            // method 规则不符合
            $response->end("FAIL");
            return;
        }

        // 验证请求接口
        if (!in_array(strtoupper($_server['request_uri']), $this->allowedMethodsFromHttp)) {
            // 链接地址不在符合规则的地址列表中
            $response->end("FAIL");
            return;
        }

        // post 数据是否为空
        if (empty($request->post)) {
            $response->end("FAIL");
            return;
        }

        // 获取发送过来的数据
        $_post = $request->post;

        // base 和 data 是否设置
        if (!isset($_post['base']) || !isset($_post['data'])) {
            $response->end("FAIL");
            return;
        }
        switch ($_post['type'] ?? 'no') {
            //pc会员消息推送
            case 'PC_MEMBER_INFO':
                foreach ($_post['data'] as $val) {
                    if (!empty($this->pcMemberMap[$val['dist']])) {
                        foreach ($this->pcMemberMap[$val['dist']] as $_fd_v) {
                            // 发送数据
                            go(
                                function () use ($_post, $val, $_fd_v) {
                                    $this->helper->send(
                                        $this->swoole,
                                        $_fd_v,
                                        [
                                            'type' => 'message_push',
                                            'base' => [
                                                // 推送标题
                                                'title' => $_post['base']['title'],
                                                // 推送声音
                                                'sound' => $_post['base']['sound'] ?? '',
                                                // id
                                                'id' => $val['message_id'],
                                            ],
                                            'data' => [
                                                // 消息内容
                                                'body' => $val['body'],
                                                // 消息标签
                                                'tag' => $_post['base']['tag'],
                                                // 消息图标
                                                'icon' => $val['icon'] ?? '',
                                                // 是否替换上一个标签
                                                'renotify' => TRUE,
                                            ],
                                        ]
                                    );
                                }
                            );
                        }
                    }
                }
                break;
            //默认走店铺推送
            default:
                foreach ($_post['data'] as $val) {
                    if (isset($this->storeMap[$val['dist']])) {
                        foreach ($this->storeMap[$val['dist']] as $_sessionKey => $_fdList) {
                            // 判断fd列表是否为空
                            if (empty($_fdList)){
                                return;
                            }

                            // 之后要使用的fd
                            $_fd = reset($_fdList);

                            // 发送数据
                            go(
                                function () use ($_post, $val,$_fd) {
                                    $this->helper->send(
                                        $this->swoole,
                                        $_fd,
                                        [
                                            'type' => 'message_push',
                                            'base' => [
                                                // 推送标题
                                                'title' => $_post['base']['title'],
                                                // 推送声音
                                                'sound' => $_post['base']['sound'],
                                                // id
                                                'id' => $val['data']['order_attach_id'],
                                            ],
                                            'data' => [
                                                // 消息内容
                                                'body' => $val['body'],
                                                // 消息标签
                                                'tag' => $_post['base']['tag'],
                                                // 消息图标
                                                'icon' => $val['icon'],
                                                // 是否替换上一个标签
                                                'renotify' => TRUE,
                                            ],
                                        ]
                                    );
                                }
                            );
                        }
                    }
                }
                break;
        }

        $response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1>");
    }

    /**
     * 此事件在Worker进程/Task进程启动时发生。这里创建的对象可以在进程生命周期内使用
     *
     * @param \app\daemonService\notify\Server $server
     * @param int $worker_id
     */
    function onWorkerStart($server, int $worker_id)
    {
    }

    /**
     * 当客户端打开连接时的处理
     *
     * @param \app\daemonService\notify\Server $server
     * @param                                           $req
     */
    function onOpen($server, $req)
    {
        $this->helper->sendSuccess(
            $server,
            $req->fd,
            [
                'TYPE' => 'CONNECTED',
            ]
        );
        print_r('新连接');
        print_r($req);
    }

    /**
     * 当服务启动时运行的方法
     */
    function onStart()
    {
        echo "已启动\n";
    }

    /**
     * 当客户端发送消息时的处理函数
     *
     * @param \app\daemonService\notify\Server $server
     * @param                                           $frame
     */
    function onMessage($server, $frame)
    {
        $_data = $this->helper->decode($frame->data);
        if (!isset($_data)) {
            return;
        }

        $_allowMethods = [
            'LOGIN',
        ];

        if (!in_array($_data['TYPE'], $_allowMethods)) {
            return;
        }

        if (!isset($_data['DATA'])) {
            return;
        }

        if (!isset($_data['DATA']['TYPE'])) {
            return;
        }

        if (!isset($_data['DATA']['ID'])) {
            return;
        }

        switch ($_data['DATA']['TYPE']) {
            case 'CLIENT':
                if (!isset($_data['DATA']['SESSION_ID'])) {
                    return;
                }
                // storeMap['ID']['SESSION_ID'] 中 存数组
                $this->storeMap[$_data['DATA']['ID']][$_data['DATA']['SESSION_ID']][] = $frame->fd;
                break;
            //pc端链接
            case 'PC_CLIENT':
                if (isset($this->pcMemberMap[$_data['DATA']['ID']][$_data['DATA']['SESSION_ID']]) && $this->pcMemberMap[$_data['DATA']['ID']][$_data['DATA']['SESSION_ID']] == $_data['DATA']['SESSION_ID']) {
                    $this->disconnectClient(
                        $server,
                        $this->pcMemberMap[$_data['DATA']['ID']][$_data['DATA']['SESSION_ID']]
                    );
                    unset($this->pcMemberMap[$_data['DATA']['ID']][$_data['DATA']['SESSION_ID']]);
                }
                $this->pcMemberMap[$_data['DATA']['ID']][$_data['DATA']['SESSION_ID']] = $frame->fd;
                break;
            default:
                return;
        }


    }

    function disconnectClient($server, $fd)
    {
        if ($server->isEstablished($fd)) {
            $server->disconnect($fd);
        }
    }

    function onClose($server, $fd)
    {
        foreach ($this->storeMap as $_storeId => $_sessionList) {
            foreach ($_sessionList as $_sessionKey => $_fdList) {
                foreach ($_fdList as $_fdKey => $_fd) {
                    if ($_fd == $fd) {
                        unset($this->storeMap[$_storeId][$_sessionKey][$_fdKey]);
                        if (empty($this->storeMap[$_storeId][$_sessionKey])) {
                            unset($this->storeMap[$_storeId][$_sessionKey]);
                        }
                        if (empty($this->storeMap[$_storeId])) {
                            unset($this->storeMap[$_storeId]);
                        }
                        break 3;
                    }
                }
            }
        }

        foreach ($this->pcMemberMap as $_mid) {
            foreach ($_mid as $_storeId => $_fd) {
                if ($_fd == $fd) {
                    unset($this->pcMemberMap[$_storeId]);
                }
            }
        }
    }
}
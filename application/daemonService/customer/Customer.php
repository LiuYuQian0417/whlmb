<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-01-22
 * Time: 14:06
 */

namespace app\daemonService\customer;

use Redis;
use think\facade\Config;
use think\swoole\Server;

class Customer extends Server
{
    /**
     * 全局文字
     */
    const GLOBAL_CHAR = [
        'BASE_METHOD_PREFIX' => 'HANDLE_',
    ];
    /**
     * @var CustomerHelper
     */
    private $helper;
    private $conf = [];

    function __construct()
    {
        $this->conf = Config::pull('daemon')['customer'];

        // 设置监听IP
        $this->host = $this->conf['host'];
        // 设置端口
        $this->port = $this->conf['port'];
        // 服务类型
        $this->serverType = $this->conf['type'];

        $_option = [];

        // 心跳检测
        if (isset($this->conf['heartbeat_check_interval'])) {
            $_option['heartbeat_check_interval'] = $this->conf['heartbeat_check_interval'];
        }

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

        $this->helper = new CustomerHelper();

        parent::__construct();
    }

    /**
     * 此事件在Worker进程/Task进程启动时发生。这里创建的对象可以在进程生命周期内使用
     *
     * @param \app\daemonService\customer\Server $server
     * @param int $worker_id
     */
    function onWorkerStart($server, int $worker_id)
    {
        $server->redis = new Redis();
        $_redisConf = Config::pull('cache')['default'];

        $server->redis->connect(
            $_redisConf['host'],
            $_redisConf['port']
        );

        // 设置 redis 验证
        $server->redis->auth($_redisConf['password']);

        if ($worker_id == 0) {
            // 删除旧的 redis键
            $_keys = $server->redis->keys("{$this->conf['redis']['prefix']}*");

            foreach ($_keys as $key) {
                $server->redis->del($key);
            }


            // 每10秒 检测 废弃的数据
            \Swoole\Timer::tick(10000, function ($time_id, $server) {
                // 找出所有的 FD_INFO 的 KEY 列表
                $_keys = $server->redis->keys("FD_INFO_*");

                // 循环 KEY 列表 获取每个具体的 KEY
                foreach ($_keys as $key) {
                    // 读取KEY
                    $_fdInfo = $server->redis->get(substr($key, mb_strlen($this->conf['redis']['prefix']) + 1));
                    // 判断 fd 是否存在
                    if (!$_fdInfo) {
                        continue;
                    }
                    // 解压 fdInfo
                    $_fdInfo = $this->helper->unpack($_fdInfo);

                    if (!$server->exist($_fdInfo['FD'])) {
                        switch ($_fdInfo['USER_TYPE']) {
                            case 'USER':
                                dump('清理用户');
                                $this->helper->userLogout($server, $_fdInfo);
                                break;
                            case 'CUSTOMER':
                                dump('清理客服');
                                $this->helper->customerLogout($server, $_fdInfo);
                                break;
                        }
                    }
                }

            }, $server);
        }

        // 设置 redis 前缀
        $server->redis->setOption(Redis::OPT_PREFIX, $this->conf['redis']['prefix'] . '_');

        $this->helper->writeStdOut(
            'Worker started',
            [
                'WorkerId' => $worker_id,
            ]
        );
    }

    /**
     * 当客户端打开连接时的处理
     *
     * @param \app\daemonService\customer\Server $server
     * @param                                    $req
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

        $this->helper->writeStdOut(
            'Connect open',
            [
                'Fd' => $req->fd,
            ]
        );
    }

    /**
     * 当客户端发送消息时的处理函数
     *
     * @param \app\daemonService\customer\Server $server
     * @param                                    $frame
     */
    function onMessage($server, $frame)
    {
        $_data = $this->helper->decode($frame->data);

        if (!isset($_data)) {
            return;
        }

        // 可以调用的方法列表
        $_allowMethods = [
            'LOGIN',
            'MESSAGE',
            'MATCH_CUSTOMER',
            'LOGOUT',
            'HEART',
            'MESSAGE_DELIVERD',
        ];

        // 判断方法是否在允许的列表中
        if (!in_array($_data['TYPE'], $_allowMethods)) {
            $this->helper->writeStdOut(
                'Message Unmatched',
                array_merge(['Fd' => $frame->fd, 'NAME' => $_data['TYPE']], $_data['DATA'] ?? [])
            );
            return;
        }

        // 不需要登录的列表
        $_notNeedLoginMethods = [
            'LOGIN',
            'HEART',
        ];

        // 判断方法是否需要登录
        if (!in_array($_data['TYPE'], $_notNeedLoginMethods)) {
            // 需要登录
            if (FALSE === $_fdInfo = $this->helper->getFdInfo($server, $frame->fd)) {
                $this->helper->sendError(
                    $server,
                    $frame->fd,
                    [
                        'TYPE'    => 'MESSAGE',
                        'MESSAGE' => 'NOT_LOGGED',
                    ]
                );

                $this->helper->writeStdOut(
                    'Not Login',
                    array_merge(['Fd' => $frame->fd, 'NAME' => 'Message'], $_data['DATA'] ?? [])
                );

                return;
            }
            $this->helper->writeStdOut(
                'NEW MESSAGE',
                array_merge($_fdInfo, $_data)
            );
        }

        $_fdInfo['FD'] = $frame->fd;


        // 执行方法
        call_user_func_array(
            [
                $this,
                self::GLOBAL_CHAR['BASE_METHOD_PREFIX'] . $_data['TYPE'],
            ],
            [
                $server,
                $_fdInfo,
                $_data['DATA'] ?? [],
            ]
        );
    }

    /**
     * 当链接关闭
     * @param \app\daemonService\customer\Server $server
     * @param $fd
     */
    function onClose($server, $fd)
    {
        // 尝试获取FD信息
        $_fdInfo = $server->redis->get("FD_INFO_{$fd}");

        // 如果 FD 信息不存在 则表示 FD 并未登录直接返回
        if (!$_fdInfo) {
            return;
        }

        // 解码FD数据
        $_fdInfo = $this->helper->unpack($_fdInfo);

        $this->helper->writeStdOut('ON_CLOSE',$_fdInfo);

        // 判断FD 的用户类型
        switch ($_fdInfo['USER_TYPE']) {
            case 'CUSTOMER':
                // 客服 则操作 客服登出
                $this->helper->customerLogout($server, $_fdInfo);
                break;
            case 'USER':
                // 用户 则操作 用户登出
                $this->helper->userLogout($server, $_fdInfo);
                break;
        }
    }

    /**
     * 客户端登录处理方法
     *
     * @param \app\daemonService\customer\Server $server
     * @param                      $fdInfo
     * @param                      $data
     */
    function HANDLE_LOGIN($server, $fdInfo, $data)
    {
        if ($this->helper->checkParamMiss(
            [
                'USER_TYPE',
                'MEMBER_ID',
                'PLATFORM_ID',
            ],
            $data
        )) {
            $this->helper->writeStdOut(
                'Miss Param',
                array_merge(['Fd' => $fdInfo['FD'], 'NAME' => 'LOGIN'], $data)
            );
            return;
        }

        // 标记基础的用户信息
        $_fdInfo = [
            'FD'          => $fdInfo['FD'],
            'USER_TYPE'   => $data['USER_TYPE'],
            'MEMBER_ID'   => $data['MEMBER_ID'],
            'PLATFORM_ID' => $data['PLATFORM_ID'],
        ];

        // 判断用户类型
        switch ($data['USER_TYPE']) {
            // 客服
            case 'CUSTOMER':
                if ($this->helper->checkParamMiss(
                    [
                        'STORE_ID',
                        'DIVERSION_ID',
                        'NICK_NAME',
                    ],
                    $data
                )) {
                    $this->helper->writeStdOut(
                        'Miss Param',
                        array_merge(['Fd' => $fdInfo['FD'], 'NAME' => 'LOGIN'], $data)
                    );
                    return;
                }

                // redis memberInfo 键
                $_memberInfoKey = "MEMBER_CUSTOMER_INFO_{$data['PLATFORM_ID']}_{$data['MEMBER_ID']}";

                // 判断之前是否有店铺的信息 如果有就删除
                $_oldMemberInfo = $server->redis->get($_memberInfoKey);
                // 判断客服是否在线
                if ($_oldMemberInfo) {
                    // 发送下线信息
                    $this->helper->sendWarning(
                        $server, $fdInfo['FD'], [
                            'TYPE' => 'WAS_ONLINE',
                        ]
                    );
                    return;
                }

                // 店铺ID
                $_fdInfo['STORE_ID'] = $data['STORE_ID'];
                // 昵称
                $_fdInfo['NICK_NAME'] = $data['NICK_NAME'];
                // 分流ID 是个列表
                $_fdInfo['DIVERSION_ID'] = explode('|', $data['DIVERSION_ID']);
                // 循环插入到店铺的分流组中
                foreach ($_fdInfo['DIVERSION_ID'] as $diversionId) {
                    // 记录登录的客服MEMBER_ID至客服组
                    // CUSTOMER_DEV_STORE_{PLATFORM_ID}_{STORE_ID}_{DIVERSION_ID}
                    $server->redis->zAdd(
                        "STORE_{$data['PLATFORM_ID']}_{$data['STORE_ID']}_{$diversionId}",
                        [],
                        0,
                        $data['MEMBER_ID']
                    );
                }

                break;
            // 用户
            case 'USER':
                $_oldFdInfo = $server->redis->get("FD_INFO_{$_fdInfo['FD']}");
                if ($_oldFdInfo) {
                    $this->helper->userLogout($server, $this->helper->unpack($_oldFdInfo));
                }
                // redis memberInfo 键
                $_memberInfoKey = "MEMBER_USER_INFO_{$data['PLATFORM_ID']}_{$data['MEMBER_ID']}";
                // 将登录的用户信息写入redis,此处考虑了多点登录
                // CUSTOMER_DEV_USER_{PLATFORM_ID}_{MEMBER_ID}
                $server->redis->sAdd(
                    "USER_{$data['PLATFORM_ID']}_{$data['MEMBER_ID']}",
                    $fdInfo['FD']
                );
                break;
            default:
                {
                    return;
                }
        }

        // 要保存的数据
        $_saveData = $this->helper->pack($_fdInfo);

        // 将信息添加到FD_INFO_{FD}
        $server->redis->set(
            "FD_INFO_{$fdInfo['FD']}",
            $_saveData
        );

        if ($_memberInfoKey) {
            // 将信息添加到
            // MEMBER_USER_INFO_{PLATFORM_ID}_{MEMBER_ID}
            // MEMBER_CUSTOMER_INFO_{PLATFORM_ID}_{MEMBER_ID}
            $server->redis->set(
                $_memberInfoKey,
                $_saveData
            );
        }

        // 向用户发送登录成功的消息
        $this->helper->sendSuccess(
            $server,
            $fdInfo['FD'],
            [
                'TYPE' => 'LOGIN',
            ]
        );

        $this->helper->writeStdOut(
            'Client Login',
            array_merge(['Fd' => $fdInfo['FD']], $data)
        );

    }

    /**
     * 登出
     *
     * @param \app\daemonService\customer\Server $server
     * @param                      $fdInfo
     * @param                      $data
     */
    function HANDLE_LOGOUT($server, $fdInfo, $data)
    {
        switch ($data['USER_TYPE']) {
            // 发送给客服
            case 'CUSTOMER':
                $this->helper->customerLogout($server, $fdInfo);
                break;
            // 发送给用户
            case 'USER':
                $this->helper->userLogout($server, $fdInfo);
                break;
        }

        $this->helper->writeStdOut(
            'Client Logout',
            $fdInfo
        );

    }

    /**
     * 消息已送达
     *
     * @param \app\daemonService\customer\Server $server
     * @param                      $fdInfo
     * @param                      $data
     *
     * @return bool
     */
    function HANDLE_MESSAGE_DELIVERD($server, $fdInfo, $data)
    {
        switch ($data['TARGET_TYPE']) {
            case 'CUSTOMER':
                $_diversionId = $server->redis->get(
                    "USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}"
                );

                if (FALSE === $_diversionId) {
                    return FALSE;
                }

                $_fd = $server->redis->get(
                    "USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}_{$_diversionId}"
                );

                if (FALSE === $_fd) {
                    return FALSE;
                }

                $this->helper->sendSuccess(
                    $server,
                    $_fd, [
                        'TYPE'       => 'MESSAGE_DELIVERD',
                        'MESSAGE_ID' => $data['MESSAGE_ID'],
                        'FROM_TYPE'  => $fdInfo['USER_TYPE'],
                        'FROM_ID'    => $fdInfo['MEMBER_ID'],
                    ]
                );
                break;
            case 'USER':
                $_fdList = $server->redis->sMembers("USER_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}");

                foreach ($_fdList as $fd) {
                    $this->helper->sendSuccess(
                        $server,
                        $fd,
                        [
                            'TYPE'       => 'MESSAGE_DELIVERD',
                            'MESSAGE_ID' => $data['MESSAGE_ID'],
                            'FROM_TYPE'  => $fdInfo['USER_TYPE'],
                            'FROM_ID'    => $fdInfo['MEMBER_ID'],
                        ]
                    );
                }
                break;
            default:
                return FALSE;
        }
        return TRUE;
    }

    /**
     * 心跳
     *
     * @param \app\daemonService\customer\Server $server
     * @param                      $fdInfo
     * @param                      $data
     */
    function HANDLE_HEART($server, $fdInfo, $data = [])
    {
        unset($data);

        $this->helper->sendSuccess(
            $server,
            $fdInfo['FD'],
            [
                'TYPE' => 'HEART',
            ]
        );
    }

    /**
     * 为当前用户匹配一个客服
     *
     * @param $server
     * @param $fdInfo
     * @param $data
     */
    function HANDLE_MATCH_CUSTOMER($server, $fdInfo, $data)
    {
        $_customer = $this->helper->matchCustomer($server, $fdInfo, $data);

        // 判断店铺是否有客服在线
        if ($_customer === FALSE) {
            // 店铺无客服在线发送 warning 信息
            $this->helper->sendWarning(
                $server,
                $fdInfo['FD'],
                [
                    'TYPE'     => 'STORE_NOT_ONLINE',
                    'MESSAGE'  => '坐席繁忙请稍后再试',
                    'STORE_ID' => $data['TARGET_ID'],
                ]
            );
        }


        // 是否是重新匹配的客服
        if (TRUE === $_customer['REMATCH']) {

            $_customerInfo = $this->helper->getMemberInfo(
                $server,
                'CUSTOMER',
                $fdInfo['PLATFORM_ID'],
                $_customer['MEMBER_ID']
            );

            if (FALSE === $_customerInfo) {
                return;
            }

            // 重新匹配的客服
            $this->helper->sendSuccess(
                $server,
                $fdInfo['FD'],
                [
                    'TYPE'     => 'MATCH_CUSTOMER',
                    'STORE_ID' => $data['TARGET_ID'],
                    'MESSAGE'  => "客服{$_customerInfo['NICK_NAME']}为您服务",
                ]
            );
        }
    }

    /**
     * 客户端消息处理方法
     *
     * @param \app\daemonService\customer\Server $server
     * @param                      $fdInfo
     * @param                      $data
     *
     */
    function HANDLE_MESSAGE($server, $fdInfo, $data)
    {
        $_allowMethods = [
            'TEXT',     // 文本
            'IMAGE',    // 图片
            'VOICE',    // 语音
            'GOODS',    // 商品
            'ORDER',    // 订单
        ];

        if (in_array($data['MESSAGE_TYPE'], $_allowMethods)) {
            switch ($data['TARGET_TYPE']) {
                // 发送给客服
                case 'CUSTOMER':
                    /** @noinspection PhpUndefinedFunctionInspection */
                    go(
                        function () use ($server, $fdInfo, $data) {
                            $this->helper->sendToCustomerMessage($server, $fdInfo, $data);
                        }
                    );

                    break;
                // 发送给用户
                case 'USER':
                    /** @noinspection PhpUndefinedFunctionInspection */
                    go(
                        function () use ($server, $fdInfo, $data) {
                            $this->helper->sendToUserMessage($server, $fdInfo, $data);
                        }
                    );

                    break;
            }
            // 发送消息服务器已收到
            /** @noinspection PhpUndefinedFunctionInspection */
            go(
                function () use ($server, $fdInfo, $data) {
                    $this->helper->sendSuccess(
                        $server,
                        $fdInfo['FD'],
                        [
                            'TYPE'        => 'MESSAGE_DELIVERD_SERVER',
                            'MESSAGE_ID'  => $data['MESSAGE_ID'],
                            'TARGET_TYPE' => $data['TARGET_TYPE'],
                            'TARGET_ID'   => $data['TARGET_ID'],
                        ]
                    );
                }
            );


        } else {
            $this->helper->writeStdOut(
                'MessageType Unmatched',
                array_merge(['Fd' => $fdInfo['FD'], 'NAME' => $data['MESSAGE_TYPE']], $data)
            );
        }
    }
}
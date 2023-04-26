<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-01-22
 * Time: 17:24
 */

namespace app\daemonService\customer;


use think\Exception;

class CustomerHelper
{

    private $mongo_db;

    public function __construct()
    {

        $this->mongo_db = new MongoDb();
        $this->mongo_db->is_member_customer();
        $this->mongo_db->is_member_store();

    }

    /**
     * 数据压缩
     *
     * @param $data
     *
     * @return false|string
     */
    function pack($data)
    {
//        return \Swoole\Serialize::pack($data);
        return $this->encode($data);
    }

    /**
     * 数据编码
     *
     * @param $data
     *
     * @return false|string
     */
    function encode($data)
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * 发送成功消息
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function sendSuccess($server, $fd, $data)
    {
        $this->send(
            $server
            ,
            $fd,
            [
                'TYPE' => 'SUCCESS',
                'DATA' => $data,
            ]
        );
    }

    /**
     * 普通的发送
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function send($server, $fd, $data)
    {
        if ($server->exist($fd)) {
            $server->push($fd, $this->encode($data));
        }
    }

    /**
     * 返回错误消息
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function sendError($server, $fd, $data)
    {
        $this->send(
            $server,
            $fd,
            [
                'TYPE' => 'ERROR',
                'DATA' => $data,
            ]
        );
    }

    /**
     * 获取FD的信息
     *
     * @param Server $server
     * @param        $fd
     *
     * @return bool|array
     */
    function getFdInfo($server, $fd)
    {
        $_fdInfo = $server->redis->get("FD_INFO_{$fd}");

        if (!$_fdInfo) {
            return FALSE;
        }

        return $this->unpack($_fdInfo);
    }

    /**
     * 数据解压
     *
     * @param $data
     *
     * @return mixed
     */
    function unpack($data)
    {
//        return \Swoole\Serialize::unpack($data);
        return $this->decode($data);
    }

    /**
     * 数据解码
     *
     * @param $data
     *
     * @return mixed
     */
    function decode($data)
    {
        return json_decode(
            $data,
            TRUE
        );
    }

    /**
     * 判断是否有miss的参数
     *
     * @param array $checkList
     * @param array $data
     *
     * @return bool
     */
    function checkParamMiss(array $checkList, array $data)
    {
        foreach ($checkList as $value) {
            if (!isset($data[$value])) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * 用户登出
     *
     * @param Server $server
     * @param        $fdInfo
     */
    function userLogout($server, $fdInfo)
    {
        // 清理用户信息
        $this->clearLinkedInfo($server, $fdInfo);
        // 删除FD的用户信息
        $server->redis->del("FD_INFO_{$fdInfo['FD']}");
        // 删除 用户FD列表
        $server->redis->sRem("USER_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}", $fdInfo['FD']);
        // 获取用户的 fd 列表
        $_memberList = $server->redis->sMembers("USER_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}");
        // 如果现在用户没有客户端登录了 则把用户的信息直接删除
        if (!$_memberList) {
            $server->redis->del("MEMBER_USER_INFO_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}");
        }
    }

    /**
     * 客服登出
     *
     * @param Server $server
     * @param        $fdInfo
     */
    function customerLogout($server, $fdInfo)
    {
        $this->clearLinkedInfo($server, $fdInfo);
        // 删除客服的分流ID
        foreach ($fdInfo['DIVERSION_ID'] as $_diversionId) {
            $server->redis->zRem("STORE_{$fdInfo['PLATFORM_ID']}_{$fdInfo['STORE_ID']}_{$_diversionId}", $fdInfo['MEMBER_ID']);
        }
        // 删除客服的 FD_INFO
        $server->redis->del("FD_INFO_{$fdInfo['FD']}");
        // 删客服的 CUSTOMER_INFO
        $server->redis->del("MEMBER_CUSTOMER_INFO_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}");
    }

    /**
     * 清理链接信息
     * @param Server $server
     * @param $fdInfo
     */
    function clearLinkedInfo($server, $fdInfo)
    {
        $this->writeStdOut("清理", $fdInfo);
        // 如果登出的是客服
        if ($fdInfo['USER_TYPE'] === 'CUSTOMER') {
            $_memberIdList = $server->redis->sMembers("CUSTOMER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['STORE_ID']}_{$fdInfo['MEMBER_ID']}");
            // 删除当前客服连接了那些用户的记录
            $server->redis->del("CUSTOMER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['STORE_ID']}_{$fdInfo['MEMBER_ID']}");
        } else {
            // 如果登出的是用户的话 则 下面处理的用户ID列表 则为当前用户的 member_id
            $_memberIdList = [$fdInfo['MEMBER_ID']];
        }
        // 循环出来用户的Id
        foreach ($_memberIdList as $_memberId) {
            // 查看现在有几个FD在线
            $_fdList = $server->redis->sMembers("USER_{$fdInfo['PLATFORM_ID']}_{$_memberId}");

            // 如果当前登出的是用户,同时 用户的fd数量 大于1 (现在有一个客户端下线,不应该影响其他的客户端) 不进行任何操作
            if ($fdInfo['USER_TYPE'] === 'USER' && count($_fdList) > 1) {
                continue;
            }

            // 获取 所有的 用户与客服聊天的分流 KEY
            $_userLinkedDiversionKeyList = $server->redis->keys("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$_memberId}_*");
            if (!$_userLinkedDiversionKeyList) continue;

            // 循环KEY
            foreach ($_userLinkedDiversionKeyList as $_userLinkedDiversionKey) {
                // 通过 正则匹配出 store_id
                preg_match("/USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$_memberId}_(.*)$/", $_userLinkedDiversionKey, $matches);

                // 赋值店铺ID
                $_storeId = $matches[1];
                // 分流ID
                $_diversionId = $server->redis->get("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$_memberId}_{$_storeId}");

                // 删除 分流信息
                $server->redis->del("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$_memberId}_{$_storeId}");
                // 用户连接到的 客服ID
                $_customerId = $server->redis->get("USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$_memberId}_{$_storeId}_{$_diversionId}");

                // 删除 用户连接信息
                $server->redis->del("USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$_memberId}_{$_storeId}_{$_diversionId}");
                // 店铺下登录的客服 连接用户数量 -1
                $server->redis->zincrby("STORE_{$fdInfo['PLATFORM_ID']}_{$_storeId}_{$_diversionId}", -1, $_customerId);
                // 去除 客服中连接了用户的信息
                $server->redis->sRem("CUSTOMER_LINKED_{$fdInfo['PLATFORM_ID']}_{$_storeId}_{$_customerId}", $_memberId);
            }
        }
    }

    /**
     * 发送信息给客服
     *
     * @param Server $server
     * @param        $fdInfo
     * @param        $data
     *
     * @throws Exception
     */
    function sendToCustomerMessage($server, $fdInfo, $data)
    {
        // 根据规则返回要聊的客服信息
        $_memberInfo = $this->matchCustomer($server, $fdInfo, $data);

        // 判断店铺是否有客服在线
        if ($_memberInfo === FALSE) {
            // 店铺无客服在线发送 warning 信息
            $this->sendWarning(
                $server,
                $fdInfo['FD'],
                [
                    'TYPE'       => 'STORE_NOT_ONLINE',
                    'STORE_ID'   => (string)$data['TARGET_ID'],
                    'MESSAGE'    => '坐席繁忙请稍后再试',
                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                ]
            );

            return;

        }

        $_customerInfo = $this->getMemberInfo($server, 'CUSTOMER', $fdInfo['PLATFORM_ID'], $_memberInfo['MEMBER_ID']);

        /*------------------------------将消息存储进入mongodb--------------------------------*/
        //插入消息记录
        $this->mongo_db
            ->setcollection('message_log')
            ->insert(
                [
                    [
                        'member_id'   => (string)$fdInfo['MEMBER_ID'],
                        'store_id'    => (string)$data['TARGET_ID'],
                        'customer_id' => (string)$_memberInfo['MEMBER_ID'],
                        'type'        => 'USER',
                        'time'        => date('Y-m-d'),
                        'message'     =>
                            array_merge(
                                $data,
                                [
                                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                                    'FROM_TYPE'  => (string)$fdInfo['USER_TYPE'],
                                    'FROM_ID'    => (string)$fdInfo['MEMBER_ID'],
                                ]
                            ),
                    ],
                ]
            )
            ->save();
        //插入用户和客服关联表
        $this->mongo_db
            ->setcollection('member_customer')
            ->where(
                [
                    'member_id'   => (string)$fdInfo['MEMBER_ID'],
                    'customer_id' => (string)$_memberInfo['MEMBER_ID'],
                ]
            )
            ->update(
                [
                    '$set' => [
                        'member_id'       => (string)$fdInfo['MEMBER_ID'],
                        'store_id'        => (string)$data['TARGET_ID'],
                        'customer_id'     => (string)$_memberInfo['MEMBER_ID'],
                        'type'            => 'USER',
                        'after_chat_time' => (string)time(),
                        'message'         =>
                            array_merge(
                                $data,
                                [
                                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                                    'FROM_TYPE'  => (string)$fdInfo['USER_TYPE'],
                                    'FROM_ID'    => (string)$fdInfo['MEMBER_ID'],
                                ]
                            ),
                    ],
                ],
                TRUE,
                TRUE
            )
            ->save();
        //插入用户和店铺关联表
        $this->mongo_db
            ->setcollection('member_store')
            ->where(
                [
                    'member_id' => (string)$fdInfo['MEMBER_ID'],
                    'store_id'  => (string)$data['TARGET_ID'],
                ]
            )
            ->update(
                [
                    '$set' => [
                        'member_id'       => (string)$fdInfo['MEMBER_ID'],
                        'store_id'        => (string)$data['TARGET_ID'],
                        'customer_id'     => (string)$_memberInfo['MEMBER_ID'],
                        'type'            => 'USER',
                        'after_chat_time' => (string)time(),
                        'message'         =>
                            array_merge(
                                $data,
                                [
                                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                                    'FROM_TYPE'  => (string)$fdInfo['USER_TYPE'],
                                    'FROM_ID'    => (string)$fdInfo['MEMBER_ID'],
                                ]
                            ),
                    ],
                ],
                TRUE,
                TRUE
            )
            ->save();
        /*------------------------------将消息存储进入mongodb--------------------------------*/
        // 店铺有客服在线发送消息

        $_data = [
            'MESSAGE_ID'   => (string)$data['MESSAGE_ID'],
            'MESSAGE_TYPE' => (string)$data['MESSAGE_TYPE'],
            'MESSAGE_DATA' => (string)$data['MESSAGE_DATA'],
            'FROM_TYPE'    => (string)$fdInfo['USER_TYPE'],
            'FROM_ID'      => (string)$fdInfo['MEMBER_ID'],
        ];

        if (isset($data['VOICE_TIME'])) {
            $_data['VOICE_TIME'] = (string)$data['VOICE_TIME'];
        } else {
            $_data['VOICE_TIME'] = 0;
        }

        // 发送回流消息
        /** @noinspection PhpUndefinedFunctionInspection */
        go(
            function () use ($server, $fdInfo, $data) {
                // 获取当前用户 的 登录列表
                $_fdList = $server->redis->sMembers("USER_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}");

                if (count($_fdList) > 1) {
                    foreach ($_fdList as $_fd) {
                        if ($_fd == $fdInfo['FD']) {
                            continue;
                        }
                        $this->send(
                            $server,
                            $_fd,
                            [
                                'TYPE' => 'MESSAGE_REFLUX',
                                'DATA' => $data,
                            ]
                        );
                    }
                }
            }
        );

        $this->send(
            $server,
            $_customerInfo['FD'],
            [
                'TYPE' => 'MESSAGE',
                'DATA' => $_data,
            ]
        );
    }

    /**
     * 找到当前的客服的Member_id或者重新分配一位客服
     *
     * @param Server $server
     * @param array $fdInfo
     * @param array $data
     *
     * @return array|bool
     *      REMATCH     是否是重新匹配的客服
     *      MEMBER_ID   用户ID
     */
    function matchCustomer($server, $fdInfo, $data)
    {

        // 如果 用户是在消息列表页进入 查看用户 之前是否有聊过的人
        if ($data['DIVERSION_ID'] == 1000 || $data['DIVERSION_ID'] == 5000) {
            $_diversionId = $server->redis->get("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}");

            // 之前的存在
            if ($_diversionId) {
                // 获取对应分流聊天的 客服 ID 如果存在的话 直接返回
                if ($_customerId = $server->redis->get("USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}")) {
                    return [
                        'REMATCH'   => FALSE,
                        'MEMBER_ID' => $_customerId,
                    ];
                }
            }
            // 不存在的话重新匹配
        }

        // 用户链接到店铺的分流信息
        $_diversionId = $server->redis->get("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}");

        // 分流ID 不存在 则表示 对应的客服已经下线 或者是头一次进入
        if (!$_diversionId) {
            // 分配客服
            if ($data['DIVERSION_ID'] == 1000) {
                // 店铺客服 消息通知页 引流到店铺主页
                $data['DIVERSION_ID'] = 1002;
            } else if ($data['DIVERSION_ID'] == 5000) {
                // 平台客服 消息通知也 引流到客户服务页
                $data['DIVERSION_ID'] = 5001;
            }

            // 看看 有没有客服在线
            $_customerIdList = $server->redis->zRange(
                "STORE_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$data['DIVERSION_ID']}",
                0,
                1
            );
            // 没有客服在线
            if (!$_customerIdList) {
                //TODO::保留消息到 mongoDB
                return FALSE;
            }
            // 赋值客服ID
            $_customerId = $_customerIdList[0];
            // 设置 分流ID
            $server->redis->set("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}", $data['DIVERSION_ID']);
            // 设置 客服ID
            $server->redis->set("USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}_{$data['DIVERSION_ID']}", $_customerId);
            // 设置 客服连接用户
            $server->redis->sAdd(
                "CUSTOMER_LINKED_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$_customerId}",
                $fdInfo['MEMBER_ID']
            );
            // 客服接待数量+1
            $server->redis->zIncrBy(
                "STORE_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$data['DIVERSION_ID']}",
                1,
                $_customerId
            );
            return [
                'REMATCH'   => TRUE,
                'MEMBER_ID' => $_customerId,
            ];
        }

        // 有分流ID存在
        // 获取客服ID
        $_customerId = $server->redis->get("USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['MEMBER_ID']}_{$data['TARGET_ID']}_{$_diversionId}");

        return [
            'REMATCH'   => FALSE,
            'MEMBER_ID' => $_customerId,
        ];
    }

    /**
     * 获取Member信息
     *
     * @param Server $server
     * @param        $userType
     * @param        $platformId
     * @param        $memberId
     *
     * @return bool|mixed
     */
    function getMemberInfo($server, $userType, $platformId, $memberId)
    {
        $_memberInfo = $server->redis->get("MEMBER_{$userType}_INFO_{$platformId}_{$memberId}");

        if (!$_memberInfo) {
            return FALSE;
        }
        return $this->unpack($_memberInfo);
    }

    /**
     * 发送警告消息
     *
     * @param Server $server
     * @param        $fd
     * @param        $data
     */
    function sendWarning($server, $fd, $data)
    {
        $this->send(
            $server
            ,
            $fd,
            [
                'TYPE' => 'WARNING',
                'DATA' => $data,
            ]
        );
    }

    /**
     * 发送信息给用户
     *
     * @param Server $server
     * @param array $fdInfo 当前发送者的用户的信息
     * @param array $data 接收到的数据
     *
     * @return bool
     * @throws Exception
     */
    function sendToUserMessage($server, $fdInfo, $data)
    {

        // 验证当前客服是否可以与当前用户聊天
        if (!$this->checkCustomer($server, $fdInfo, $data)) {
            // 这里说明用户离线 或者是正在被其他客服接待
            $this->sendWarning(
                $server,
                $fdInfo['FD'],
                [
                    'TYPE'       => 'USER_NOT_LINKED_TO_YOU',
                    'MESSAGE'    => '用户离线或正在被其他客服接待',
                    'MESSAGE_ID' => $data['MESSAGE_ID'],
                ]
            );
            return FALSE;

        }
        /*------------------------------将消息存储进入mongodb--------------------------------*/
        //插入消息记录
        $this->mongo_db
            ->setcollection('message_log')
            ->insert(
                [
                    [
                        'member_id'   => (string)$data['TARGET_ID'],
                        'store_id'    => (string)$fdInfo['STORE_ID'],
                        'customer_id' => $fdInfo['MEMBER_ID'],
                        'type'        => 'CUSTOMER',
                        'time'        => date('Y-m-d'),
                        'message'     =>
                            array_merge(
                                $data,
                                [
                                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                                    'FROM_TYPE'  => $fdInfo['USER_TYPE'],
                                    'FROM_ID'    => $fdInfo['STORE_ID'],
                                ]
                            ),
                    ],
                ]
            )
            ->save();
        //插入用户和客服关联表
        $this->mongo_db
            ->setcollection('member_customer')
            ->where(
                [
                    'member_id'   => (string)$data['TARGET_ID'],
                    'customer_id' => (string)$fdInfo['MEMBER_ID'],
                ]
            )
            ->update(
                [
                    '$set' => [
                        'member_id'       => (string)$data['TARGET_ID'],
                        'store_id'        => (string)$fdInfo['STORE_ID'],
                        'customer_id'     => $fdInfo['MEMBER_ID'],
                        'type'            => 'CUSTOMER',
                        'after_chat_time' => (string)time(),
                        'message'         =>
                            array_merge(
                                $data,
                                [
                                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                                    'FROM_TYPE'  => $fdInfo['USER_TYPE'],
                                    'FROM_ID'    => $fdInfo['STORE_ID'],
                                ]
                            ),
                    ],
                ],
                TRUE,
                TRUE
            )
            ->save();
        //插入用户和店铺关联表
        $this->mongo_db
            ->setcollection('member_store')
            ->where(
                [
                    'member_id' => (string)$data['TARGET_ID'],
                    'store_id'  => (string)$fdInfo['STORE_ID'],
                ]
            )
            ->update(
                [
                    '$set' => [
                        'member_id'       => (string)$data['TARGET_ID'],
                        'store_id'        => (string)$fdInfo['STORE_ID'],
                        'customer_id'     => (string)$fdInfo['MEMBER_ID'],
                        'type'            => 'CUSTOMER',
                        'after_chat_time' => (string)time(),
                        'message'         =>
                            array_merge(
                                $data,
                                [
                                    'MESSAGE_ID' => (string)$data['MESSAGE_ID'],
                                    'FROM_TYPE'  => $fdInfo['USER_TYPE'],
                                    'FROM_ID'    => $fdInfo['STORE_ID'],
                                ]
                            ),
                    ],
                ],
                TRUE,
                TRUE
            )
            ->save();
        /*------------------------------将消息存储进入mongodb--------------------------------*/

        // 获取 用户的fd列表
        $_fdList = $server->redis->sMembers("USER_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}");
        foreach ($_fdList as $_fd) {
            $this->send(
                $server,
                $_fd,
                [
                    'TYPE' => 'MESSAGE',
                    'DATA' => [
                        'MESSAGE_ID'   => $data['MESSAGE_ID'],
                        'MESSAGE_TYPE' => $data['MESSAGE_TYPE'],
                        'MESSAGE_DATA' => $data['MESSAGE_DATA'],
                        'FROM_TYPE'    => $fdInfo['USER_TYPE'],
                        'FROM_ID'      => $fdInfo['STORE_ID'],
                    ],
                ]
            );
        }
        return TRUE;
    }

    /**
     * 检查用户是否在和自己聊天
     *
     * @param Server $server
     * @param        $fdInfo
     * @param        $data
     *
     * @return bool
     */
    function checkCustomer($server, $fdInfo, $data)
    {
        // 用户链接到店铺的分流ID
        // USER_LINKED_DIVERSION_{PLATFORM_ID}_{USER_MEMBER_ID}_{STORE_ID}
        $_diversionId = $server->redis->get("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$fdInfo['STORE_ID']}");

        // 如果分流ID不存在 说明用户不在线或未与任何客服聊天
        if (!$_diversionId) {
            // 判断用户是否在线
            $_memberInfo = $server->redis->get("MEMBER_USER_INFO_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}");

            // 如果用户不在线的话
            if (!$_memberInfo) {
                dump('用户不在线');
                return FALSE;
            }

            // 这里表明用户在线

            // 客服的第一个分流ID
            $_customerFirstDiversionId = $fdInfo['DIVERSION_ID'][0];
            // 设置 分流ID
            $server->redis->set("USER_LINKED_DIVERSION_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$fdInfo['STORE_ID']}", $_customerFirstDiversionId);
            // 设置 客服ID
            $server->redis->set("USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$fdInfo['STORE_ID']}_{$_customerFirstDiversionId}", $fdInfo['MEMBER_ID']);
            // 设置 客服连接用户
            $server->redis->sAdd(
                "CUSTOMER_LINKED_{$fdInfo['PLATFORM_ID']}_{$fdInfo['STORE_ID']}_{$fdInfo['MEMBER_ID']}",
                $data['TARGET_ID']
            );
            // 客服接待数量+1
            $server->redis->zIncrBy(
                "STORE_{$fdInfo['PLATFORM_ID']}_{$fdInfo['STORE_ID']}_{$_customerFirstDiversionId}",
                1,
                $fdInfo['MEMBER_ID']
            );
            return TRUE;
        }

        // 用户连接到店铺的分流客服信息
        // USER_LINKED_{PLATFORM_ID}_{USER_MEMBER_ID}_{STORE_ID}_{DIVERSION_ID}
        $_customerId = $server->redis->get(
            "USER_LINKED_{$fdInfo['PLATFORM_ID']}_{$data['TARGET_ID']}_{$fdInfo['STORE_ID']}_{$_diversionId}"
        );

        // 返回 获取到的客服ID 是否等于 客服的ID
        return $_customerId == $fdInfo['MEMBER_ID'];
    }

    /**
     * 输入信息在命令行中
     *
     * @param string $name
     * @param array $data
     * @param int $hrLength
     */
    function writeStdOut(string $name, array $data, int $hrLength = 40)
    {
        $_name = strlen($name) % 2 === 0 ? $name : $this->str_replace_limit(' ', '  ', $name, 1);

        $_nameLength = strlen($_name);

        $_hrLength = ($hrLength - 2 - $_nameLength) / 2;

        $_hr = '';

        for ($i = 0; $i < $_hrLength; $i++) {
            $_hr .= '-';
        }

        $_echo = "\n$_hr $_name $_hr\n\n";

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = http_build_query($value);
            }
            $_echo .= "$key : \t $value\n\n";
        }

        for ($i = 0; $i < $hrLength; $i++) {
            $_echo .= '-';
        }

        echo $_echo . "\n";
    }

    private function str_replace_limit($search, $replace, $subject, $limit = -1)
    {
        if (is_array($search)) {
            foreach ($search as $k => $v) {
                $search[$k] = '`' . preg_quote($search[$k], '`') . '`';
            }
        } else {
            $search = '`' . preg_quote($search, '`') . '`';
        }
        return preg_replace($search, $replace, $subject, $limit);
    }


}
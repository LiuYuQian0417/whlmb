<?php


namespace app\common\service;

/**
 * 推送类
 *
 * Class Publish
 * @package app\common\service
 * @author Malson
 */
class Publish
{
    /**
     * 发送的目标
     *
     * @var array
     */
    private $_target = [
        'MEMBER_ID'          => 0,      // 用户ID     通用
        'PHONE_NUMBER'       => '',     // 手机号     短信
        'OPEN_ID'            => '',     // 微信ID     微信/小程序
        'IOS_DEVICES_ID'     => '',     // IOS设备ID  IOS
        'ANDROID_DEVICES_ID' => '',     // 安卓设备ID Android
    ];

}